<?php
require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "proxmox_response.php";

/**
 * Proxmox API processor
 *
 * Documentation on the Proxmox API: http://pve.proxmox.com/wiki/Proxmox_VE_API
 *
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @package proxmox
 */
class ProxmoxApi {

	/**
	 * @var string The user to connect as
	 */
	private $user;
	/**
	 * @var string The password to use when connecting
	 */
	private $password;
	/**
	 * @var string The host to use when connecting (IP address or hostname)
	 */
	private $host;
	/**
	 * @var string The port to use when connecting
	 */
	private $port;
	/**
	 * @var array An array representing the last request made
	 */
	private $last_request = array('url' => null, 'args' => null);
	
	/**
	 * @var string The login ticket to be used in Cookie for API access
	 */
	private $ticket;
	
	/**
	 * @var string The username received from the ticket request
	 */
	private $username_from_ticket;
	
	/**
	 * @var string The CSRF prevention token to be used for certain requests
	 */
	private $csrf_prevention_token;
	
	/**
	 * Sets the connection details
	 *
	 * @param string $user The user to connect as
	 * @param string $password The password to use when connecting
	 * @param string $host The host to use when connecting (IP address or hostname)
	 * @param string $port The port to use when connecting
	 */
	public function __construct($user, $password, $host, $port = 8006) {
		$this->user = $user;
		$this->password = $password;
		$this->host = $host;
		$this->port = $port;
		
		$this->login();
	}
	
	public function login() {
		$res = $this->submit("access/ticket", array(
			"username" => $this->user,
			"password" => $this->password
		), "POST", false)->response();
		
		if ($res && property_exists($res, "data") && !empty($res->data)) {
			$this->ticket = $res->data->ticket;
			$this->username_from_ticket = $res->data->username;
			$this->csrf_prevention_token = $res->data->CSRFPreventionToken;
		}
	}
	
	/**
	 * Submits a request to the API
	 *
	 * @param string $command The command to submit (e.g. nodes)
	 * @param array $args An array of key/value pair arguments to submit to the given API command
	 * @return ProxmoxResponse The response object
	 */
	public function submit($command, array $args = array(), $method = "GET", $include_login = true) {

		$url = "https://" . $this->host . ":" . $this->port . "/api2/json/" . $command;
		
		$this->last_request = array(
			"url" => $url,
			"args" => $args
		);
		
		$ch = curl_init();
		
		//We need to do this manually to avoid multipart/form-data which Proxmox does not like
		$postfields = array();
		foreach($args as $arg_name => $arg_value) {
			$postfields[] = urlencode($arg_name) . '=' . urlencode($arg_value);
		}
		$postfields = implode('&', $postfields);
			
		if($method == "GET") {
			curl_setopt($ch, CURLOPT_URL, $url . '?' . $postfields);
		} else {
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
		}
		
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		
		if($include_login)
			curl_setopt($ch, CURLOPT_COOKIE, "PVEAuthCookie=" . $this->ticket);
		
		$additional_header = array("Expect:");
		
		if($method != "GET" && $include_login)
			$additional_header[] = "CSRFPreventionToken: " . $this->csrf_prevention_token;
		
		curl_setopt($ch, CURLOPT_HTTPHEADER, $additional_header);

		$response = curl_exec($ch);
		curl_close($ch);
		
		return new ProxmoxResponse($response);
	}
	
	/**
	 * Returns the details of the last request made
	 *
	 * @return array An array containg:
	 * 	- url The URL of the last request
	 * 	- args The paramters passed to the URL
	 */
	public function lastRequest() {
		return $this->last_request;
	}
	
	/**
	 * Loads a command class
	 *
	 * @param string $command The command class filename to load
	 */
	public function loadCommand($command) {
		require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "commands" . DIRECTORY_SEPARATOR . $command . ".php";
	}
}
?>