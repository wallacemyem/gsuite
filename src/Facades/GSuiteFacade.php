<?php

namespace Wyattcast44\GSuite\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Wyattcast44\Gsuite\Accounts\GSuiteAccount
 */
class GSuiteGroupFacade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'gsuite-group';
    }
}
