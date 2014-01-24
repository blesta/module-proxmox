<?php
/**
 * Proxmox VServer Management
 *
 * @package proxmox.commands
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://www.fullambit.net/
 */
class ProxmoxVserver {
	
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
	 * Create a Virtual Server
	 *
	 * @param array $vars An array of input params including:
	 *  - vmid VMID of  to be created VM
	 * 	- node Name of the node
	 * 	- type Type of VM
	 * 	- hostname Hostname of the virtual server
	 * 	- password Root password
	 * 	- username Client username
	 *  - sockets vCPUs
	 *  - memory RAM (MB)
	 *  - hdd Storage (GB)
	 *  - netspeed Netspeed (MByte/s)
	 *  - ip Primary IP
	 *  - storage Storage Name
	 *  - template Template (OpenVZ)
	 * @return ProxmoxResponse 
	 */
	public function create(array $vars) {
		switch($vars["type"]) {
			case "qemu":
				$response = $this->api->submit("nodes/" . $vars["node"] . "/qemu", array(
					"vmid" => $vars["vmid"],
					"sockets" => $vars["sockets"],
					"memory" => $vars["memory"],
					"storage" => $vars["storage"],
					"net0" => "virtio,bridge=vmbr0,rate=" . $vars["netspeed"],
					"onboot" => "1"
				), "POST");
				
				$this->api->submit("nodes/" . $vars["node"] . "/storage/" . $vars["storage"] . "/content", array(
					"vmid" => $vars["vmid"],
					"size" => $vars["hdd"] . 'G',
					"filename" => "root_" . $vars["vmid"] . ".qcow2"
				), "POST");
						
				$this->api->submit("nodes/" . $vars["node"] . "/qemu/" . $vars["vmid"] . "/config", array(
					"virtio0" => $vars["storage"] . ":" . $vars["vmid"] . "/root_" . $vars["vmid"] . ".qcow2"
				), "PUT");
			
				break;
			case "openvz":
				$response = $this->api->submit("nodes/" . $vars["node"] . "/openvz", array(
					"vmid" => $vars["vmid"],
					"ostemplate" => $vars["template"],
					"cpus" => $vars["sockets"],
					"memory" => $vars["memory"],
					"disk" => $vars["hdd"],
					"ip_address" => $vars["ip"],
					"hostname" => $vars["hostname"],
					"password" => $vars["password"],
					"onboot" => "1"
				), "POST");
				break;
		}
		
		$this->api->submit("access/acl", array(
			"users" => $vars["userid"] . "@pve",
			"path" => "/vms/" . $vars["vmid"],
			"roles" => "PVEVMUser"
		), "PUT");
		
		return $response;
	}
	
	/**
	 * Check if a Virtual Server exists
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the node
	 * 	- type Type of VM
	 * @return ProxmoxResponse 
	 */
	public function checkExists(array $vars) {
		return $this->status($vars);
	}
	
	/**
	 * Check if a Virtual Server status
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the node
	 * 	- type Type of VM
	 * @return ProxmoxResponse 
	 */
	public function status(array $vars) {
		return $this->api->submit("nodes/" . $vars["node"] . "/" . $vars["type"] . "/" . $vars["vmid"] . "/status/current");
	}

	/**
	 * Shutdown a Virtual Server
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the node
	 * 	- type Type of VM
	 * @return ProxmoxResponse 
	 */	
	public function shutdown(array $vars) {
		$params = array('forceStop' => true);
		return $this->api->submit("nodes/" . $vars["node"] . "/" . $vars["type"] . "/" . $vars["vmid"] . "/status/shutdown", $params, "POST");
	}

	/**
	 * Force stop a Virtual Server
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the node
	 * 	- type Type of VM
	 * @return ProxmoxResponse 
	 */	
	public function stop(array $vars) {
		return $this->api->submit("nodes/" . $vars["node"] . "/" . $vars["type"] . "/" . $vars["vmid"] . "/status/stop", array(), "POST");
	}
	
	/**
	 * Boot a Virtual Server
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the node
	 * 	- type Type of VM
	 * @return ProxmoxResponse 
	 */	
	public function boot(array $vars) {
		return $this->api->submit("nodes/" . $vars["node"] . "/" . $vars["type"] . "/" . $vars["vmid"] . "/status/start", array(), "POST");
	}
	
	/**
	 * Get VNC info
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the node
	 * 	- type Type of VM
	 * @return ProxmoxResponse 
	 */	
	public function vnc(array $vars) {
		return $this->api->submit("nodes/" . $vars["node"] . "/" . $vars["type"] . "/" . $vars["vmid"] . "/vncproxy", array(), "POST");
	}

	/**
	 * Terminate a Virtual Server
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the nodes
	 * 	- type Type of VM
	 * @return ProxmoxResponse 
	 */	
	public function terminate(array $vars) {
		$this->stop($vars);
		return $this->api->submit("nodes/" . $vars["node"] . "/" . $vars["type"] . "/" . $vars["vmid"], array(), "DELETE");
	}

	/**
	 * Set hostname
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the nodes
	 * 	- type Type of VM
	 * 	- hostname The hostname
	 * @return ProxmoxResponse 
	 */		
	public function hostname(array $vars) {
		return $this->api->submit("nodes/" . $vars["node"] . "/" . $vars["type"] . "/" . $vars["vmid"] . "/config", array(
			"hostname" => $vars["hostname"]
		), "PUT");
	}
	
	/**
	 * Set root password
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the nodes
	 * 	- type Type of VM
	 * 	- password The password
	 * @return ProxmoxResponse 
	 */		
	public function password(array $vars) {
		return $this->api->submit("nodes/" . $vars["node"] . "/" . $vars["type"] . "/" . $vars["vmid"] . "/config", array(
			"password" => $vars["password"]
		), "PUT");
	}
	
	/**
	 * Mount an ISO
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the node
	 * 	- iso The filname of the ISO
	 * 	- storage The name of the storage
	 * @return ProxmoxResponse 
	 */	
	public function mountIso(array $vars) {
		return $this->api->submit("nodes/" . $vars["node"] . "/qemu/" . $vars["vmid"] . "/config", array(
			'cdrom' => $vars["iso"]
		), "PUT");
	}
	
	/**
	 * Unmount an ISO
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- node Name of the node
	 * @return ProxmoxResponse 
	 */	
	public function unmountIso(array $vars) {
		return $this->api->submit("nodes/" . $vars["node"] . "/qemu/" . $vars["vmid"] . "/config", array(
			'cdrom' => 'none'
		), "PUT");
	}

	/**
	 * Get VServer stats graph
	 *
	 * @param array $vars An array of input params including:
	 * 	- vmid The virtual server ID
	 * 	- type Type of VM
	 * 	- node Name of the node
	 * 	- timeframe Timeframe
	 *  - ds DataSource
	 * @return ProxmoxResponse 
	 */
	public function graph(array $vars) {
		return $this->api->submit("nodes/" . $vars["node"] . "/" . $vars["type"] . "/" . $vars["vmid"] . "/rrd", array(
			"timeframe" => $vars["timeframe"],
			"ds" => $vars["ds"]
		), "GET");
	}
}
?>