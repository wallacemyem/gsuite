<?php

namespace Wyattcast44\GSuite\Actions;

use Spatie\QueueableAction\QueueableAction;
use Wyattcast44\GSuite\Resources\Groups\GroupsRepository;

class DeleteGroupAction
{
    use QueueableAction;

    protected $repository;

    public function __construct(GroupsRepository $groups_repo)
    {
        $this->repository = $groups_repo;
    }

    public function execute(string $email)
    {
        return $this->repository->delete($email);
    }
}
