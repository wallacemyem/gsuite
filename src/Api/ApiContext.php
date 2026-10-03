<?php

namespace BrickServers\GoogleWorkspace\Api;

use BrickServers\GoogleWorkspace\Support\ProtectedResources;
use Closure;
use Google\Service;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Everything a generated API wrapper needs: how to get the Google service (which
 * may be impersonating a user), where to audit-log changes, and the safety rules.
 */
final class ApiContext
{
    private ?Service $service = null;

    /**
     * @param  string  $name  service name used in logs and errors, e.g. "directory"
     * @param  Closure(): Service  $resolver
     */
    public function __construct(
        public readonly string $name,
        private readonly Closure $resolver,
        public readonly LoggerInterface $logger = new NullLogger,
        public readonly ?ProtectedResources $protection = null,
        public readonly ?string $actingAs = null,
    ) {}

    public function service(): Service
    {
        return $this->service ??= ($this->resolver)();
    }
}
