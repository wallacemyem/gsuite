<?php

namespace Wyattcast44\GSuite\Resources\Accounts;

class GSuiteAccount
{
    /**
     * The account instance
     *
     * \Google_Service_Directory_User
     */
    protected $account;

    protected $accounts_repo;

    public function __construct(\Google_Service_Directory_User $account)
    {
        $this->account = $account;
    }

    public function getEmail()
    {
        return $this->account->primaryEmail;
    }

    public function getAliases()
    {
        return $this->account->aliases;
    }

    public function suspend()
    {
        //
    }
}
