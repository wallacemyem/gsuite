<?php

namespace BrickServers\GoogleWorkspace\Tests;

use BrickServers\GoogleWorkspace\DTOs\UserDTO;
use BrickServers\GoogleWorkspace\Repositories\GroupsRepository;
use BrickServers\GoogleWorkspace\Repositories\UsersRepository;
use BrickServers\GoogleWorkspace\Services\GoogleServicesFactory;
use BrickServers\GoogleWorkspace\Utilities\BatchOperations;
use Google\Client;
use Google\Service\Directory;
use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

class BatchOperationsTest extends TestCase
{
    private array $history = [];

    /**
     * @param  array<string, array{0: int, 1: array}>  $parts  Content-ID => [status, JSON body]
     */
    private function batchResponse(array $parts): Response
    {
        $body = '';
        foreach ($parts as $contentId => [$status, $json]) {
            $body .= "--batch_test\r\nContent-Type: application/http\r\nContent-ID: {$contentId}\r\n\r\n"
                ."HTTP/1.1 {$status} X\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n"
                .json_encode($json)."\r\n";
        }

        return new Response(200, ['Content-Type' => 'multipart/mixed; boundary=batch_test'], $body.'--batch_test--');
    }

    private function operations(Response ...$responses): BatchOperations
    {
        $stack = HandlerStack::create(new MockHandler($responses));
        $stack->push(Middleware::history($this->history));

        $client = new Client;
        $client->setHttpClient(new HttpClient(['handler' => $stack, 'http_errors' => false]));
        $directory = new Directory($client);

        $services = $this->createMock(GoogleServicesFactory::class);
        $services->method('directory')->willReturn($directory);

        return new BatchOperations(
            new UsersRepository($services, 'example.com'),
            new GroupsRepository($services, 'example.com'),
            $services,
        );
    }

    public function test_group_members_are_added_in_a_single_batch_request()
    {
        $results = $this->operations($this->batchResponse([
            'response-item-0' => [200, ['email' => 'a@example.com']],
            'response-item-1' => [409, ['error' => ['code' => 409, 'message' => 'Member already exists.']]],
            'response-item-2' => [200, ['email' => 'c@example.com']],
        ]))->addGroupMembers('team@example.com', ['a@example.com', 'b@example.com', 'c@example.com']);

        $this->assertCount(1, $this->history, 'All members should be sent in one HTTP request');
        $this->assertStringEndsWith('/batch', (string) $this->history[0]['request']->getUri());
        $this->assertSame(3, substr_count((string) $this->history[0]['request']->getBody(), '/groups/team%40example.com/members'));

        $this->assertSame(['a@example.com', 'c@example.com'], $results['success']);
        $this->assertSame('b@example.com', $results['failed'][0]['email']);
        $this->assertStringContainsString('Member already exists', $results['failed'][0]['error']);
    }

    public function test_create_users_validates_locally_and_batches_the_rest()
    {
        $results = $this->operations($this->batchResponse([
            'response-item-0' => [200, ['primaryEmail' => 'new@example.com', 'name' => ['givenName' => 'New', 'familyName' => 'User']]],
        ]))->createUsers([
            new UserDTO('new@example.com', 'New', 'User'),
            new UserDTO('outsider@other.com', 'Out', 'Sider'),
        ]);

        $this->assertCount(1, $this->history);
        $this->assertSame('new@example.com', $results['success'][0]->email);
        $this->assertSame('outsider@other.com', $results['failed'][0]['user']);
        $this->assertStringContainsString('domain', $results['failed'][0]['error']);
    }

    public function test_a_failed_batch_request_marks_every_item_failed()
    {
        $results = $this->operations(new Response(400, ['Content-Type' => 'application/json'], '{"error":{"code":400,"message":"Bad batch"}}'))
            ->removeGroupMembers('team@example.com', ['a@example.com', 'b@example.com']);

        $this->assertSame([], $results['success']);
        $this->assertCount(2, $results['failed']);
    }
}
