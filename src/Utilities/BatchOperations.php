<?php

namespace BrickServers\GoogleWorkspace\Utilities;

use BrickServers\GoogleWorkspace\Contracts\GroupsRepositoryContract;
use BrickServers\GoogleWorkspace\Contracts\UsersRepositoryContract;
use BrickServers\GoogleWorkspace\DTOs\UserDTO;
use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use Google\Service\Directory;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Batch Operations Utility
 *
 * Helper for performing batch operations on multiple users/groups. When a services
 * factory is provided, creates and membership changes are sent as Google batch
 * requests (up to 1000 calls per HTTP request) instead of one request per item.
 */
class BatchOperations
{
    /** Directory API limit on calls per batch request */
    public const MAX_BATCH_SIZE = 1000;

    private LoggerInterface $logger;

    public function __construct(
        private readonly UsersRepositoryContract $users,
        private readonly GroupsRepositoryContract $groups,
        private readonly ?GoogleServicesFactory $services = null,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger;
    }

    /**
     * Create multiple users
     */
    public function createUsers(array $userDTOs): array
    {
        if (! $this->services) {
            return $this->each($userDTOs, fn (UserDTO $user) => $this->users->create($user), fn (UserDTO $user) => ['user' => $user->email]);
        }

        $results = ['success' => [], 'failed' => []];
        $valid = [];

        foreach (array_values($userDTOs) as $userDTO) {
            try {
                $this->users->validate($userDTO);
                $valid[] = $userDTO;
            } catch (GoogleWorkspaceException $e) {
                $results['failed'][] = ['user' => $userDTO->email, 'error' => $e->getMessage()];
            }
        }

        $batched = $this->batch(
            $valid,
            function (UserDTO $user, Directory $directory) {
                $payload = $user->toArray();
                $payload['changePasswordAtNextLogin'] ??= true;

                return $directory->users->insert(new \Google_Service_Directory_User($payload));
            },
            fn (UserDTO $user, $response) => UserDTO::fromArray((array) $response),
            fn (UserDTO $user) => ['user' => $user->email],
            'User created',
        );

        return [
            'success' => $batched['success'],
            'failed' => array_merge($results['failed'], $batched['failed']),
        ];
    }

    /**
     * Suspend multiple users. Each suspension goes through the users repository so
     * protected accounts are always checked.
     */
    public function suspendUsers(array $emails): array
    {
        return $this->each($emails, function (string $email) {
            $this->users->suspend($email);

            return $email;
        }, fn (string $email) => ['email' => $email]);
    }

    /**
     * Add multiple members to a group
     */
    public function addGroupMembers(string $groupEmail, array $memberEmails): array
    {
        if (! $this->services) {
            return $this->each($memberEmails, function (string $email) use ($groupEmail) {
                $this->groups->addMember($groupEmail, $email);

                return $email;
            }, fn (string $email) => ['email' => $email]);
        }

        return $this->batch(
            array_values($memberEmails),
            fn (string $email, Directory $directory) => $directory->members->insert(
                $groupEmail,
                new \Google_Service_Directory_Member(['email' => $email]),
            ),
            fn (string $email) => $email,
            fn (string $email) => ['email' => $email],
            'Member added to group',
            ['groupKey' => $groupEmail],
        );
    }

    /**
     * Remove multiple members from a group
     */
    public function removeGroupMembers(string $groupEmail, array $memberEmails): array
    {
        if (! $this->services) {
            return $this->each($memberEmails, function (string $email) use ($groupEmail) {
                $this->groups->removeMember($groupEmail, $email);

                return $email;
            }, fn (string $email) => ['email' => $email]);
        }

        return $this->batch(
            array_values($memberEmails),
            fn (string $email, Directory $directory) => $directory->members->delete($groupEmail, $email),
            fn (string $email) => $email,
            fn (string $email) => ['email' => $email],
            'Member removed from group',
            ['groupKey' => $groupEmail],
        );
    }

    /**
     * Run an operation for each item, one API call at a time.
     */
    private function each(array $items, callable $operation, callable $describe): array
    {
        $results = ['success' => [], 'failed' => []];

        foreach ($items as $item) {
            try {
                $results['success'][] = $operation($item);
            } catch (GoogleWorkspaceException $e) {
                $results['failed'][] = $describe($item) + ['error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Send one Google batch request per chunk of items.
     *
     * @param  callable  $makeRequest  builds the (deferred) API request for an item and the Directory service
     * @param  callable  $onSuccess  maps an item and its response to a success entry
     * @param  callable  $describe  identifies an item in a failure entry
     */
    private function batch(
        array $items,
        callable $makeRequest,
        callable $onSuccess,
        callable $describe,
        string $logMessage,
        array $logContext = [],
    ): array {
        $results = ['success' => [], 'failed' => []];
        $directory = ($this->services ?? throw new \LogicException('Batch requests need a services factory'))->directory();
        $client = $directory->getClient();

        foreach (array_chunk($items, self::MAX_BATCH_SIZE) as $chunk) {
            $client->setUseBatch(true);
            try {
                $batch = $directory->createBatch();
                foreach ($chunk as $i => $item) {
                    $batch->add($makeRequest($item, $directory), "item-{$i}");
                }
            } finally {
                $client->setUseBatch(false);
            }

            try {
                $responses = $batch->execute() ?? [];
            } catch (\Exception $e) {
                $error = GoogleWorkspaceException::fromGoogle($e, 'execute batch', 'Batch')->getMessage();
                foreach ($chunk as $item) {
                    $results['failed'][] = $describe($item) + ['error' => $error];
                }

                continue;
            }

            foreach ($chunk as $i => $item) {
                $response = $responses["response-item-{$i}"] ?? null;

                if ($response instanceof \Exception) {
                    $results['failed'][] = $describe($item) + [
                        'error' => GoogleWorkspaceException::fromGoogle($response, 'run batched request', 'Batch')->getMessage(),
                    ];
                } elseif (! array_key_exists("response-item-{$i}", $responses)) {
                    $results['failed'][] = $describe($item) + ['error' => 'No response returned for this item'];
                } else {
                    $results['success'][] = $onSuccess($item, $response);
                    $this->logger->info($logMessage, $logContext + $describe($item));
                }
            }
        }

        return $results;
    }
}
