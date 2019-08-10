<?php

namespace Wyattcast44\GSuite\Commands;

use Illuminate\Console\Command;
use Wyattcast44\GSuite\Actions\CreateGroupAction;

class CreateGroup extends Command
{
    protected $signature = 'gsuite:create-group';

    protected $description = 'Create a new G-Suite group email';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(CreateGroupAction $createGroupAction)
    {
        $email = (string) $this->ask('What should the group email address be?');

        $name = (string) $this->ask('What should the name of the group be?');

        $description = (string) $this->ask('What should the description of the group be?');

        $this->info('Creating new group...');

        try {
            $createGroupAction->execute($email, $name, $description);

            $this->line('');
            
            $this->info("Group created! Email address: {$email}");
        } catch (\Exception $e) {
            logger($e);

            $this->error('An error has occured and was logged, please try again later, or talk to an admin.');
        }
    }
}
