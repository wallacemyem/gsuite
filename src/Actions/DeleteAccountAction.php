<?php

namespace Wyattcast44\GSuite\Actions;

use Spatie\QueueableAction\QueueableAction;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepository;

class DeleteAccountAction
{
    use QueueableAction;

    protected $repository;

    public function __construct(AccountsRepository $accounts_repo)
    {
        $this->repository = $accounts_repo;
    }

    public function execute(string $userKey)
    {
        return $this->repository->delete($userKey);
    }
}
