<?php
/**
 * Proxmox Node Management
 *
 * @package proxmox.commands
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://www.fullambit.net/
 */
class ProxmoxNodes {
	
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
	 * List nodes
	 *
	 * @param array $vars An array of input params including:
	 * 	- type (openvz, kvm)
	 * @return ProxmoxResponse
	 */
	public function getList(array $vars) {
		return $this->api->submit("nodes");
	}
	
	/**
	 * List nodes by name
	 *
	 * @param array $vars An array of input params including:
	 * 	- type (openvz, kvm)
	 * @return ProxmoxResponse
	 */
	public function idList(array $vars) {
		$nodes = $this->getList();
		$return = array();
		foreach($nodes as $node) {
			$return[$node->name] = $node;
		}
		return $return;
	}
	
	/**
	 * Get list of storage content
	 *
     * @param array $vars An array of input params including:
	 * 	- node Name of node
	 * 	- storage Name of storage
	 * @return ProxmoxResponse 
	 */
	public function storageContent(array $vars) {
		return $this->api->submit("nodes/" .  $vars["node"] . "/storage/" . $vars["storage"] . "/content");
	}
	
	/**
	 * List virtual servers
	 *
	 * @param array $vars An array of input params including:
	 * 	- nodeid The name of the node
	 * 	- type (openvz, kvm)
	 * @return ProxmoxResponse
	 */
	public function virtualServers(array $vars) {
		return $this->api->submit("nodes/" . $vars['nodeid'] . "/" . $vars['type']);
	}
	
	/**
	 * List node statistics
	 *
	 * @param array $vars An array of input params including:
	 * 	- nodeid The name of the node
	 * @return ProxmoxResponse
	 */
	public function statistics(array $vars) {
		return $this->api->submit("nodes/" . $vars['nodeid'] . "/status");
	}
}
?>