<?php
/**
 * Proxmox Client Management
 *
 * @package proxmox.commands
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://www.fullambit.net/
 */
class ProxmoxStorage
{
    /**
     * @var ProxmoxApi
     */
    private $api;

    /**
     * Sets the API to use for communication
     *
     * @param ProxmoxApi $api The API to use for communication
     */
    public function __construct(ProxmoxApi $api)
    {
        $this->api = $api;
    }

    /**
     * List storage
     *
     * @return ProxmoxResponse
     */
    public function getList()
    {
        return $this->api->submit('storage');
    }
}
