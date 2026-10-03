<?php

namespace BrickServers\GoogleWorkspace\Api;

use Google\Service;

/**
 * Base class for the generated per-API entry points (DirectoryApi, GmailApi, ...).
 */
abstract class ApiService
{
    /** @var array<string, ApiResource> */
    private array $resources = [];

    public function __construct(protected readonly ApiContext $context) {}

    /**
     * The underlying Google service, for anything not covered by the wrappers.
     */
    public function google(): Service
    {
        return $this->context->service();
    }

    /**
     * @template T of ApiResource
     *
     * @param  class-string<T>  $class
     * @return T
     */
    protected function resource(string $class, string $property): ApiResource
    {
        /** @var T */
        return $this->resources[$property] ??= new $class($this->context, $property);
    }
}
