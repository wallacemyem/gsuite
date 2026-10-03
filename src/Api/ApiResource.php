<?php

namespace BrickServers\GoogleWorkspace\Api;

use BrickServers\GoogleWorkspace\Exceptions\GoogleWorkspaceException;
use Generator;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Base class for the generated resource wrappers. Every call goes through call(),
 * which applies the safety rules, maps Google errors to GoogleWorkspaceException
 * and audit-logs anything that changes data.
 */
abstract class ApiResource
{
    /**
     * Google PHP method name => HTTP verb, filled in by the generator.
     *
     * @var array<string, string>
     */
    protected const HTTP_METHODS = [];

    public function __construct(
        protected readonly ApiContext $context,
        protected readonly string $property,
    ) {}

    /**
     * Iterate over every item of a paginated list call, fetching pages lazily.
     *
     * Pass the wrapper method name and its arguments as you would call it, e.g.
     * paginate('listUsers', ['customer' => 'my_customer']).
     */
    public function paginate(string $method, mixed ...$args): Generator
    {
        if (! str_starts_with($method, 'list') || ! method_exists($this, $method)) {
            throw GoogleWorkspaceException::invalidArgument('method', "{$method} is not a list method of ".static::class);
        }

        $params = (new ReflectionMethod($this, $method))->getParameters();
        $optIndex = count($params) - 1;
        if ($optIndex < 0 || $params[$optIndex]->getName() !== 'optParams' || count($args) < $optIndex) {
            throw GoogleWorkspaceException::invalidArgument('args', "{$method} needs its required arguments before the options array");
        }

        $options = (array) ($args[$optIndex] ?? []);

        do {
            $args[$optIndex] = $options;
            $response = $this->{$method}(...$args);

            yield from $this->items($response);

            $token = is_object($response) && method_exists($response, 'getNextPageToken') ? $response->getNextPageToken() : null;
            $options['pageToken'] = $token;
        } while ($token);
    }

    /**
     * @param  array<int, mixed>  $args
     */
    protected function call(string $method, array $args): mixed
    {
        $api = "{$this->context->name}.{$this->property}.{$method}";

        try {
            $resource = $this->context->service()->{$this->property};
            if (! is_object($resource) || ! is_callable([$resource, $method])) {
                throw GoogleWorkspaceException::invalidConfiguration(
                    "{$api} is not available in the installed google/apiclient-services; update it"
                );
            }

            $this->context->protection?->guard($this->context->name, $this->property, $method, $args);

            $result = $resource->{$method}(...$args);
        } catch (\Exception $e) {
            throw GoogleWorkspaceException::fromGoogle($e, $api, $this->property, $this->identifier($args));
        }

        if ((static::HTTP_METHODS[$method] ?? 'GET') !== 'GET') {
            $this->context->logger->info('Google Workspace API change', array_filter([
                'api' => $api,
                'keys' => array_values(array_filter($args, 'is_scalar')),
                'actingAs' => $this->context->actingAs,
            ]));
        }

        return $result;
    }

    /**
     * @param  array<int, mixed>  $args
     */
    private function identifier(array $args): ?string
    {
        $first = $args[0] ?? null;

        return is_scalar($first) ? (string) $first : null;
    }

    /**
     * @return iterable<mixed>
     */
    private function items(mixed $response): iterable
    {
        if (! is_object($response) || ! property_exists($response, 'collection_key')) {
            return [];
        }

        $key = (new ReflectionProperty($response, 'collection_key'))->getValue($response);
        $getter = 'get'.ucfirst((string) $key);

        return method_exists($response, $getter) ? ($response->{$getter}() ?? []) : [];
    }
}
