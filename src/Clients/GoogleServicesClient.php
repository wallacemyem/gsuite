<?php

namespace Wyattcast44\GSuite\Clients;

use Wyattcast44\GSuite\Contracts\ClientContract;

class GoogleServicesClient implements ClientContract
{
    /**
     * The \Google_Service_Directory instance
     */
    protected $client;

    protected static $services = [
        'asps',
        'channels',
        'chromeoasdevices',
        'customers',
        'domainAliases',
        'domains',
        'groups',
        'groups_aliases',
        'members',
        'mobile_devices',
        'notifications',
        'orgunits',
        'privileges',
        'resolvedAppAccessSettings',
        'resources_buildings',
        'resources_calendars',
        'resources_features',
        'roleAssignments',
        'roles',
        'schemas',
        'tokens',
        'users',
        'users_aliases',
        'users_photos',
        'verificationCodes'
    ];

    /**
     * Bootstrap the client
     *
     * @return self
     */
    public function __construct(GoogleClient $google_client)
    {
        return $this->setClient($google_client);
    }

    /**
     * Set the client
     *
     * @return self
     */
    public function setClient(GoogleClient $google)
    {
        $this->client = new \Google_Service_Directory($google->getClient());

        return $this;
    }
    
    /**
     * Get the configured client
     *
     * @return \Google_Service_Directory
     */
    public function getClient()
    {
        return $this->client;
    }

    /**
     * Get a service instance from the service directory client
     */
    public function getService(string $service_name)
    {
        if (!in_array($service_name, self::$services)) {
            throw new Exception("Google Services Directory, does not have a service named: {$service_name}", 1);
        }

        return $this->client->$service_name;
    }
}
