<?php

namespace Wyattcast44\GSuite\Events;

use Illuminate\Queue\SerializesModels;

class GSuiteAccountDeleted
{
    use SerializesModels;

    public $email;

    /**
     * Create a new event instance.
     *
     * @param  \Wyattcast44\GSuite $order
     * @return void
     */
    public function __construct($email)
    {
        $this->email = $email;
    }
}
