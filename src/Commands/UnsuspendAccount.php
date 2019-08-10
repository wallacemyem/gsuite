<?php

namespace Wyattcast44\GSuite\Commands;

use Illuminate\Console\Command;
use Wyattcast44\GSuite\Actions\UnsuspendAccountAction;

class UnsuspendAccount extends Command
{
    protected $signature = 'gsuite:unsuspend-account';

    protected $description = 'Unsuspend a G-Suite account';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(UnsuspendAccountAction $unsuspendAccountAction)
    {
        $email = $this->ask('What is the primary email address of the account you would like to unsuspend?');
                
        if (!$this->confirm("Are you sure your would like to unsuspend: {$email}", false)) {
            return;
        }

        $this->info('Unsuspending account...');

        try {
            $unsuspendAccountAction->execute($email);
            
            $this->line('');

            $this->info('Account unsuspended!');
        } catch (\Exception $e) {
            logger($e);

            $this->error('An error has occured and was logged, please try again later, or talk to an admin.');
        }
    }
}
