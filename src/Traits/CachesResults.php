<?php

namespace Wyattcast44\GSuite\Traits;

use Illuminate\Support\Facades\Cache;

trait CachesResults
{
    protected function checkCache(string $key)
    {
        return Cache::has($key);
    }

    public function flushCache()
    {
        return Cache::forget($this->getCacheKey());
    }

    protected function getCache(string $key, $default = [])
    {
        return Cache::get($key, $default);
    }

    protected function putCache(string $key, $data)
    {
        return Cache::put($key, $data, $this->getCacheTime());
    }
}
