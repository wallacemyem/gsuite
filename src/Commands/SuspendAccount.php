<?php

namespace Wyattcast44\GSuite\Commands;

use Illuminate\Console\Command;
use Wyattcast44\GSuite\Actions\SuspendAccountAction;

class SuspendAccount extends Command
{
    protected $signature = 'gsuite:suspend-account';

    protected $description = 'Suspend a G-Suite account';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(SuspendAccountAction $suspendAccountAction)
    {
        $email = $this->ask('What is the primary email address of the account you would like to suspend?');

        $this->info('Suspending account...');

        try {
            $suspendAccountAction->execute($email);
            
            $this->info('Account suspended!');
        } catch (\Exception $e) {
            logger($e);

            $this->error('An error has occured and was logged, please try again later, or talk to an admin.');
        }
    }
}
