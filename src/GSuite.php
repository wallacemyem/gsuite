<?php

namespace Wyattcast44\GSuite;

use Wyattcast44\GSuite\Resources\Groups\GroupsRepository;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepository;

class GSuite
{
    public function groups(GroupsRepository $repo)
    {
        return $repo;
    }

    public function accounts(AccountsRepository $repo)
    {
        return $repo;
    }
}
