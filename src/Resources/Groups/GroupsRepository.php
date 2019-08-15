<?php

namespace Wyattcast44\GSuite\Resources\Groups;

use Wyattcast44\GSuite\Traits\CachesResults;
use Wyattcast44\GSuite\Clients\GoogleServicesClient;
use Wyattcast44\GSuite\Contracts\GroupsRepository as GroupsRepositoryContract;

class GroupsRepository implements GroupsRepositoryContract
{
    use CachesResults;

    /**
     * Groups repo client
     *
     * @see Google_Service_Directory_Resource_Groups
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

            $this->flushCache();
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
    public function get(string $groupKey, bool $withMembers = true)
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
        $group = new \Google_Service_Directory_Group([
            'name' => $name,
            'email' => $email,
            'description' => $description
        ]);

        try {
            $group = $this->client->insert($group);

            $this->flushCache();
        } catch (\Exception $e) {
            throw $e;
        }

        return $group;
    }

    /**
     * Get a list of all groups in your domain
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/groups/list
     */
    public function list()
    {
        try {
            if ($this->shouldCache()) {
                if ($this->checkCache($this->getCacheKey())) {
                    $groups = $this->getCache($this->getCacheKey());
                } else {
                    $groups = $this->client->listGroups(['domain' => config('gsuite.domain')])->groups;

                    $this->putCache($this->getCacheKey(), $groups);
                }
            } else {
                $groups = $this->client->listGroups(['domain' => config('gsuite.domain')])->groups;
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $groups;
    }

    /**
     * Update a groups name or description
     *
     * @link https://developers.google.com/admin-sdk/directory/v1/reference/groups/update
     */
    public function update(string $groupKey, string $name = '', string $desciption = '')
    {
        $group = tap(new \Google_Service_Directory_Group, function ($group) use ($name, $desciption) {
            if ($name <> '') {
                $group->setName($name);
            }

            if ($desciption <> '') {
                $group->setDescription($desciption);
            }
        });

        return $this->client->update($groupKey, $group);
    }

    /**
     * Should the groups be cached
     *
     * @return bool
     */
    public function shouldCache()
    {
        return config('gsuite.cache.groups.cache');
    }

    /**
     * Get the proper key for caching results
     *
     * @return string
     */
    protected function getCacheKey(string $groupKey = null)
    {
        $key = config('gsuite.cache.groups.key');

        if ($groupKey) {
            $key = $key . ":{$groupKey}";
        }
        
        return $key;
    }

    /**
     * Get the time to cache
     *
     * @return int
     */
    protected function getCacheTime()
    {
        return config('gsuite.cache.groups.time');
    }
}
