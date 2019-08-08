<?php

namespace Wyattcast44\GSuite\Resources\Groups;

use Wyattcast44\GSuite\Clients\GoogleServicesClient;
use Wyattcast44\GSuite\Contracts\GroupsRepoContract;

class GroupsRepo implements GroupsRepoContract
{
    /**
     * Groups repo client
     */
    protected $client;

    /**
     * Bootstrap groups repo
     *
     * @return self
     */
    public function __construct(GoogleServicesClient $services)
    {
        $this->client = $services->getService('groups');

        return $this;
    }

    /**
     * Delete a G-Suite group
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/groups/delete
     *
     * @return bool
     */
    public function delete(string $groupKey)
    {
        try {
            $response = $this->client->delete($groupKey);

            $this->flushCache(config('gsuite.cache.groups.key'));
            $this->flushCache(config('gsuite.cache.groups.key' . ':' . $groupKey));
        } catch (\Exception $e) {
            throw new \Exception("Error deleting group with key: {$groupKey}.", 1);
        }

        return ($response->getStatusCode() == 204) ? true : false;
    }

    public function get(string $groupKey)
    {
        //
    }

    public function insert()
    {
        //
    }

    public function list()
    {
        //
    }

    public function update(string $groupKey)
    {
        //
    }
}
