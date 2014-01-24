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
class ProxmoxClient {
	
	/**
	 * @var ProxmoxApi
	 */
	private $api;
	
	/**
	 * Sets the API to use for communication
	 *
	 * @param ProxmoxApi $api The API to use for communication
	 */
	public function __construct(ProxmoxApi $api) {
		$this->api = $api;
	}
	
	/**
	 * Check if a client exists
	 *
	 * @param array $vars An array of input params including:
	 * 	- userid
	 * @return ProxmoxResponse
	 */
	public function checkExists(array $vars) {
		return $this->api->submit("access/users/" . $vars["userid"] . "@pve");
	}
	
	/**
	 * Delete a client
	 *
	 * @param array $vars An array of input params including:
	 * 	- userid
	 * @return ProxmoxResponse
	 */
	public function delete(array $vars) {
		return $this->api->submit("access/users/" . $vars["userid"] . "@pve", array(), "DELETE");
	}
	
	/**
	 * Create a client
	 *
	 * @param array $vars An array of input params including:
	 * 	- userid
	 * 	- password
	 * 	- email
	 * 	- firstname
	 * 	- lastname
	 * @return ProxmoxResponse
	 */
	public function create(array $vars) {
		$vars['userid'] .= "@pve";
		$ret = $this->api->submit("access/users", $vars, "POST");
		return $ret;
	}
	
	/**
	 * Update a client's password
	 *
	 * @param array $vars An array of input params including:
	 * 	- userid
	 * 	- password
	 * @return ProxmoxResponse
	 */
	public function updatePassword(array $vars) {
		return $this->api->submit("access/password", $vars);
	}
	
	/**
	 * List clients
	 *
	 * @return ProxmoxResponse
	 */
	public function getList() {
		return $this->api->submit("access/users");
	}
	
	/**
	 * Authenticate a client
	 *
	 * @param array $vars An array of input params including:
	 * 	- username
	 * 	- password
	 * @return ProxmoxResponse
	 */
	public function authenticate(array $vars) {
		return $this->api->submit("client-authenticate", $vars);
	}
}
?>