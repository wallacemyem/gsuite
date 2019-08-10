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
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

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

        $resetPassword = $this->confirm('Would you like the user to reset their password at next login?', true);

        $createAccountAction->execute($name, $email, $password, $resetPassword);
    }
}
