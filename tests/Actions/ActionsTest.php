<?php

namespace Wyattcast44\Gsuite\Tests\Actions;

use Wyattcast44\GSuite\GSuite;
use Orchestra\Testbench\TestCase;
use Wyattcast44\GSuite\GSuiteServiceProvider;
use Wyattcast44\GSuite\Resources\Groups\GroupsRepository;
use Wyattcast44\GSuite\Resources\Accounts\AccountsRepository;
use Wyattcast44\GSuite\Actions\CreateAccountAction;
use Illuminate\Support\Str;
use Wyattcast44\GSuite\Actions\DeleteAccountAction;

class ActionsTest extends TestCase
{
    protected function getPackageProviders($app)
    {
        return [
            GSuiteServiceProvider::class
        ];
    }

    public function test_accounts_can_be_created(CreateAccountAction $createAccountAction, DeleteAccountAction $deleteAccountAction)
    {
        // Given we have the parameters for a new account
        $name = ['first_name' => 'Test', 'last_name' => 'Tester'];

        $email = "test.tester@{config('gsuite.domain'}";

        $password = Str::random(16);

        // When you call the CreateAccountAction
        $account = $createAccountAction->execute($name, $email, $password);

        // A \Google_Service_User should be returned
        $this->assertInstanceOf(\Google_Service_User::class, $account);

        // Let's clean up and delete the account
        $deleteAccountAction->execute($email);
    }
}
