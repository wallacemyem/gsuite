<?php

namespace Wyattcast44\GSuite\Commands;

use Illuminate\Console\Command;
use Wyattcast44\GSuite\Actions\DeleteAccountAction;

class DeleteAccount extends Command
{
    protected $signature = 'gsuite:suspend-account';

    protected $description = 'Suspend a G-Suite account';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(DeleteAccountAction $deleteAccountAction)
    {
        $email = $this->ask('What is the primary email address of the account you would like to delete?');
                
        if (!$this->confirm("Are you sure your would like to delete the following account: {$email}", false)) {
            return;
        }

        $this->info('Deleting account...');

        try {
            $deleteAccountAction->execute($email);

            $this->line('');
            
            $this->info('Account deleted!');
        } catch (\Exception $e) {
            logger($e);

            $this->error('An error has occured and was logged, please try again later, or talk to an admin.');
        }
    }
}
