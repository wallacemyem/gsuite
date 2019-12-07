<?php

namespace Wyattcast44\GSuite\Commands;

use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Wyattcast44\GSuite\Actions\CreateAccountAction;

class CreateAccount extends Command
{
    protected $signature = 'gsuite:create-account';

    protected $description = 'Create a new G-Suite account';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle(CreateAccountAction $createAccountAction)
    {
        $name = [
            'first_name' => $this->ask('What is the first name for the account?'),
            'last_name' => $this->ask('What is the last name for the account?')
        ];

        $email = $this->ask('What should the primary email address be?');

        if ($this->confirm('Would you like us to automatically generate a password?', true)) {
            $password = Str::random(16);
        } else {
            $password = $this->ask('What you like the password to be?');
        };

        $this->info('Creating new account...');

        try {
            $createAccountAction->execute($name, $email, $password, true);

            $this->line('');
            
            $this->info("Account created! Email address: {$email}, Temporary Password: {$password}");
        } catch (\Exception $e) {
            logger($e);

            $this->error('An error has occured and was logged, please try again later, or talk to an admin.');
        }
    }
}
