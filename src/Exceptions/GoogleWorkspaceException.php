<?php

namespace BrickServers\GoogleWorkspace\Exceptions;

use Exception;
use Throwable;

class GoogleWorkspaceException extends Exception
{
    public static function rateLimitExceeded(string $message = 'API rate limit exceeded', ?Throwable $previous = null): self
    {
        return new self($message, 429, $previous);
    }

    public static function invalidConfiguration(string $message): self
    {
        return new self("Invalid configuration: {$message}", 1);
    }

    public static function missingCredentials(): self
    {
        return new self('Google Workspace credentials not configured', 2);
    }

    public static function apiError(string $message, ?Throwable $previous = null): self
    {
        return new self("Google Workspace API error: {$message}", 3, $previous);
    }

    public static function validationError(string $field, string $message): self
    {
        return new self("Validation error on '{$field}': {$message}", 4);
    }

    public static function resourceNotFound(string $resource, string $identifier, ?Throwable $previous = null): self
    {
        return new self("Resource not found: {$resource} - {$identifier}", 5, $previous);
    }

    public static function accessDenied(string $message = 'Access denied', ?Throwable $previous = null): self
    {
        return new self($message, 403, $previous);
    }

    public static function undeletableResource(string $resource, string $identifier): self
    {
        return new self("Cannot delete protected resource: {$resource} - {$identifier}", 6);
    }

    public static function protectedResource(string $action, string $resource, string $identifier): self
    {
        return new self("Cannot {$action} protected resource: {$resource} - {$identifier}", 6);
    }

    public static function connectionError(string $message, ?Throwable $previous = null): self
    {
        return new self("Connection error: {$message}", 7, $previous);
    }

    public static function invalidArgument(string $param, string $message): self
    {
        return new self("Invalid argument '{$param}': {$message}", 8);
    }

    /**
     * Translate an error thrown by the Google client into the matching exception type,
     * using the HTTP status Google returned rather than assuming every failure is the same.
     */
    public static function fromGoogle(Throwable $e, string $action, string $resource, ?string $identifier = null): self
    {
        if ($e instanceof self) {
            return $e;
        }

        $message = "Failed to {$action}: {$e->getMessage()}";

        if ($e instanceof \Google\Service\Exception) {
            // Google reports some quota errors as 403 with a rate-limit reason
            $reasons = array_column($e->getErrors() ?? [], 'reason');
            if (array_intersect($reasons, ['rateLimitExceeded', 'userRateLimitExceeded', 'quotaExceeded'])) {
                return self::rateLimitExceeded($message, $e);
            }

            return match ($e->getCode()) {
                404 => self::resourceNotFound($resource, $identifier ?? 'unknown', $e),
                401, 403 => self::accessDenied($message, $e),
                429 => self::rateLimitExceeded($message, $e),
                default => self::apiError($message, $e),
            };
        }

        if ($e instanceof \GuzzleHttp\Exception\ConnectException) {
            return self::connectionError($message, $e);
        }

        return self::apiError($message, $e);
    }
}
