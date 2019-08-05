<?php

namespace Wyattcast44\GSuite;

class GSuiteDirectory
{
    /**
     * @link https://developers.google.com/admin-sdk/directory
     */
    protected $directory_client;

    public function __construct(GSuite $gsuite)
    {
        $this->setDirectoryClient($gsuite);

        return $this->directory_client;
    }

    /**
     * Set the GSuite Directory client
     * @return void
     */
    protected function setDirectoryClient(GSuite $gsuite)
    {
        $this->directory_client = new \Google_Service_Directory($gsuite);
    }
}
