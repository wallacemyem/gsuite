<?php

namespace Wyattcast44\GSuite\Resources\Groups;

use Wyattcast44\GSuite\Traits\CachesResults;
use Wyattcast44\GSuite\Clients\GoogleServicesClient;
use Wyattcast44\GSuite\Contracts\GroupsRepoContract;

class GroupsRepo implements GroupsRepoContract
{
    use CachesResults;

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
     * Should the groups be cached
     *
     * @return bool
     */
    public function shouldCache()
    {
        return config('gsuite.cache.groups.should-cache');
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

    /**
     * Get a G-Suite group
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/groups/get
     */
    public function get(string $groupKey, bool $withMembers)
    {
        if ($this->shouldCache() && $this->checkCache(config('gsuite.cache.groups.key') . ':' . $groupKey)) {
            $group = $this->getCache(config('gsuite.cache.groups.key') . ':' . $groupKey);
        } else {
            try {
                $group = $this->client->get($groupKey);

                if ($this->shouldCache()) {
                    $this->putCache(config('gsuite.cache.groups.key') . ':' . $groupKey, $group, config('gsuite.cache.groups.cache-time'));
                }
            } catch (\Exception $e) {
                throw \Exception("Error retriving group with key: {$groupKey}.", 1);
            }
        }

        return $group;
    }

    /**
     * Create and insert a new G-Suite group
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/groups/get
     */
    public function insert(string $email, string $name = '', string $description = '')
    {
        /**
         * Create the new Group
         */
        $group = new \Google_Service_Directory_Group([
            'name' => $name,
            'email' => $email,
            'description' => $description
        ]);

        try {
            $group = $this->client->insert($group);

            $this->flushCache(config('gsuite.cache.groups.key'));
        } catch (\Exception $e) {
            throw $e;
        }

        return $group;
    }

    /**
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/groups/list
     */
    public function list()
    {
        if ($this->shouldCache() && $this->checkCache(config('gsuite.cache.groups.key'))) {
            $groups = $this->getCache(config('gsuite.cache.groups.key'));
        } else {
            try {
                $groups = $this->client->listGroups(['domain' => config('gsuite.domain')]);

                if ($this->shouldCache()) {
                    $this->putCache(config('gsuite.cache.groups.key'), $groups, config('gsuite.cache.groups.cache-time'));
                }
            } catch (\Exception $e) {
                throw \Exception("Error retriving groups.", 1);
            }
        }

        return $groups;
    }

    /**
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/groups/update
     */
    public function update(string $groupKey)
    {
        //
    }
}
