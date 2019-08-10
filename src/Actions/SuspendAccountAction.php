<?php

namespace Wyattcast44\GSuite\Actions;

use Wyattcast44\GSuite\Resources\Accounts\GSuiteAccount;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepository;

class SuspendAccountAction
{
    protected $repository;

    public function __construct(AccountsRepository $accounts_repo)
    {
        $this->repository = $accounts_repo;
    }

    public function execute(GSuiteAccount $account)
    {
        return $this->repository->suspend($account->getId());
    }
}
