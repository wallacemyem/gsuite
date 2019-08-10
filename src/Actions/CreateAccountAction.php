<?php

namespace Wyattcast44\GSuite\Actions;

use Spatie\QueueableAction\QueueableAction;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepository;

class CreateAccountAction
{
    use QueueableAction;

    protected $repository;

    public function __construct(AccountsRepository $accounts_repo)
    {
        $this->repository = $accounts_repo;
    }

    public function execute(array $name, string $email, string $password, bool $changePasswordNextLogin = true)
    {
        return $this->repository->insert($name, $email, $password, $changePasswordNextLogin);
    }
}
