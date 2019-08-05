<?php

namespace Wyattcast44\GSuite;

class GSuiteDirectory
{
    /**
     * \Google_Service_Directory
     * @link https://developers.google.com/admin-sdk/directory
     */
    protected $directory_client;

    /**
     * Bootstrap the service
     * @return \Google_Service_Directory
     */
    public function __construct(GSuite $gsuite)
    {
        $this->setDirectoryClient($gsuite);

        return $this->directory_client;
    }

    /**
     * Set the GSuite Directory client
     * @link https://developers.google.com/admin-sdk/directory/
     * @return void
     */
    protected function setDirectoryClient(GSuite $gsuite)
    {
        $this->directory_client = new \Google_Service_Directory($gsuite);
    }
}
