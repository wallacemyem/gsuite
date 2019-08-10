<?php

namespace Wyattcast44\GSuite\Commands;

use Illuminate\Console\Command;
use Wyattcast44\GSuite\Actions\DeleteGroupAction;

class DeleteGroup extends Command
{
    protected $signature = 'gsuite:delete-group';

    protected $description = 'Delete a G-Suite group';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(DeleteGroupAction $deleteGroupAction)
    {
        $email = $this->ask('What is the email address of the group you would like to delete?');
                
        if (!$this->confirm("Are you sure your would like to delete the following group: {$email}", false)) {
            return;
        }

        $this->info('Deleting group...');

        try {
            $deleteGroupAction->execute($email);

            $this->line('');
            
            $this->info('Group deleted!');
        } catch (\Exception $e) {
            logger($e);

            $this->error('An error has occured and was logged, please try again later, or talk to an admin.');
        }
    }
}
