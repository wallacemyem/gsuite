<?php

namespace Wyattcast44\GSuite\Commands;

use Illuminate\Console\Command;
use Wyattcast44\GSuite\Resources\Groups\GroupsRepository;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepository;

class FlushCache extends Command
{
    protected $signature = 'gsuite:flush-cache';

    protected $description = 'Flush the G-Suite accounts and groups cache';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(AccountsRepository $accounts_repo, GroupsRepository $groups_repo)
    {
        $this->info('Flushing cache...');

        try {
            $groups_repo->flushCache();
            $accounts_repo->flushCache();

            $this->line('');
            
            $this->info('Cache flushed!');
        } catch (\Exception $e) {
            logger($e);

            $this->error('An error has occured and was logged, please try again later, or talk to an admin.');
        }
    }
}
