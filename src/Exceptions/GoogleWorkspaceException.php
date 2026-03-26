<?php

namespace BrickServers\GoogleWorkspace\Exceptions;

use Exception;

class GoogleWorkspaceException extends Exception
{
    public static function rateLimitExceeded(string $message = 'API rate limit exceeded'): self
    {
        return new self($message, 429);
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self("Invalid configuration: {$message}", 1);
    }

    public static function missingCredentials(): self
    {
        return new self('Google Workspace credentials not configured', 2);
    }

    public static function apiError(string $message, ?Exception $previous = null): self
    {
        return new self("Google Workspace API error: {$message}", 3, $previous);
    }

    public static function validationError(string $field, string $message): self
    {
        return new self("Validation error on '{$field}': {$message}", 4);
    }

    public static function resourceNotFound(string $resource, string $identifier): self
    {
        return new self("Resource not found: {$resource} - {$identifier}", 5);
    }

    public static function accessDenied(string $message = 'Access denied'): self
    {
        return new self($message, 403);
    }

    public static function undeletableResource(string $resource, string $identifier): self
    {
        return new self("Cannot delete protected resource: {$resource} - {$identifier}", 6);
    }

    public static function connectionError(string $message, ?Exception $previous = null): self
    {
        return new self("Connection error: {$message}", 7, $previous);
    }

    public static function invalidArgument(string $param, string $message): self
    {
        return new self("Invalid argument '{$param}': {$message}", 8);
    }
}
