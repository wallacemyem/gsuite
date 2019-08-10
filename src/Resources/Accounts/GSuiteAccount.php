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

        $this->accounts_repo = app('gsuite-accounts-repo');
    }

    public function getPrimaryEmail()
    {
        return $this->account->getPrimaryEmail();
    }

    public function getAliases()
    {
        return collect($this->account->getAliases());
    }

    public function addAliases(string $email)
    {
        //
    }

    public function suspend()
    {
        //
    }

    public function updateName(array $name)
    {
        //
    }
}
