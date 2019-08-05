<?php

namespace Wyattcast44\GSuite;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Wyattcast44\Gsuite\Skeleton\SkeletonClass
 */
class GSuiteFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return GSuite::class;
    }
}
