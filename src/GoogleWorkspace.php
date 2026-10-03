<?php

namespace BrickServers\GoogleWorkspace;

use BrickServers\GoogleWorkspace\Api\ApiContext;
use BrickServers\GoogleWorkspace\Api\CalendarApi;
use BrickServers\GoogleWorkspace\Api\ClassroomApi;
use BrickServers\GoogleWorkspace\Api\DirectoryApi;
use BrickServers\GoogleWorkspace\Api\DriveApi;
use BrickServers\GoogleWorkspace\Api\GmailApi;
use BrickServers\GoogleWorkspace\Support\ProtectedResources;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class GoogleWorkspace
{
    /** @var array<string, object> */
    private array $apis = [];

    public function __construct(
        private readonly Services\GoogleServicesFactory $services,
        private readonly Contracts\UsersRepositoryContract $users,
        private readonly Contracts\GroupsRepositoryContract $groups,
        private readonly LoggerInterface $logger = new NullLogger,
        private readonly ?ProtectedResources $protection = null,
        private readonly ?string $actingAs = null,
    ) {}

    public function users(): Contracts\UsersRepositoryContract
    {
        return $this->users;
    }

    public function groups(): Contracts\GroupsRepositoryContract
    {
        return $this->groups;
    }

    public function batch(): Utilities\BatchOperations
    {
        return app(Utilities\BatchOperations::class);
    }

    public function services(): Services\GoogleServicesFactory
    {
        return $this->services;
    }

    /**
     * Act as another user (domain-wide delegation), e.g. to manage their Gmail
     * settings, calendars or Drive files. The API accessors (gmail(), calendar(),
     * drive(), classroom(), directory()) use the impersonated account; users(),
     * groups() and batch() keep acting as the configured admin.
     *
     * The scopes (the configured ones by default) must be authorized for the
     * service account in the Google Admin console.
     */
    public function asUser(string $email, ?array $scopes = null): self
    {
        return new self(
            $this->services->forSubject($email, $scopes),
            $this->users,
            $this->groups,
            $this->logger,
            $this->protection,
            $email,
        );
    }

    /**
     * The account API calls are made as: the impersonated user, or null for the configured admin.
     */
    public function actingAs(): ?string
    {
        return $this->actingAs;
    }

    /**
     * Admin SDK Directory API: users, groups, members, org units, roles, domains,
     * devices, schemas, tokens, buildings and resources, and more.
     */
    public function directory(): DirectoryApi
    {
        return $this->api(DirectoryApi::class, 'directory', fn () => $this->services->directory());
    }

    public function classroom(): ClassroomApi
    {
        return $this->api(ClassroomApi::class, 'classroom', fn () => $this->services->classroom());
    }

    public function calendar(): CalendarApi
    {
        return $this->api(CalendarApi::class, 'calendar', fn () => $this->services->calendar());
    }

    public function gmail(): GmailApi
    {
        return $this->api(GmailApi::class, 'gmail', fn () => $this->services->gmail());
    }

    public function drive(): DriveApi
    {
        return $this->api(DriveApi::class, 'drive', fn () => $this->services->drive());
    }

    /**
     * @template T of Api\ApiService
     *
     * @param  class-string<T>  $class
     * @return T
     */
    private function api(string $class, string $name, \Closure $resolver): object
    {
        /** @var T */
        return $this->apis[$name] ??= new $class(
            new ApiContext($name, $resolver, $this->logger, $this->protection, $this->actingAs)
        );
    }
}
