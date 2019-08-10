<?php

namespace Wyattcast44\GSuite;

use Wyattcast44\GSuite\Resources\Groups\GroupsRepository;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepository;
use Wyattcast44\GSuite\Resources\Accounts\GSuiteAccounts;

class GSuite
{
    protected $groups_repo;

    protected $accounts_repo;

    public function __construct(GroupsRepository $groups_repo, GSuiteAccounts $accounts_repo)
    {
        $this->groups_repo = $groups_repo;

        $this->accounts_repo = $accounts_repo;
    }

    public function accounts()
    {
        return $this->accounts_repo;
    }
    
    public function groups()
    {
        return $this->groups_repo;
    }
}
