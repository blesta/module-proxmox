<?php

use Blesta\Core\Util\Common\Traits\Container;

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'proxmox_response.php';

/**
 * Proxmox API processor
 *
 * Documentation on the Proxmox API: http://pve.proxmox.com/wiki/Proxmox_VE_API
 *
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @package proxmox
 */
class ProxmoxApi
{
    // Load traits
    use Container;

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
    private $last_request = ['url' => null, 'args' => null];

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
    public function __construct($user, $password, $host, $port = 8006)
    {
        $this->user = $user;
        $this->password = $password;
        $this->host = $host;
        $this->port = $port;

        // Initialize logger
        $logger = $this->getFromContainer('logger');
        $this->logger = $logger;

        $this->login();
    }

    public function login()
    {
        $res = $this->submit('access/ticket', [
            'username' => $this->user,
            'password' => $this->password
        ], 'POST', false)->response();

        if ($res && property_exists($res, 'data') && !empty($res->data)) {
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
    public function submit($command, array $args = [], $method = 'GET', $include_login = true)
    {
        $url = 'https://' . $this->host . ':' . $this->port . '/api2/json/' . $command;

        $this->last_request = [
            'url' => $url,
            'args' => $args
        ];

        $ch = curl_init();

        // We need to do this manually to avoid multipart/form-data which Proxmox does not like
        $postfields = [];
        foreach ($args as $arg_name => $arg_value) {
            $postfields[] = urlencode($arg_name) . '=' . urlencode($arg_value);
        }
        $postfields = implode('&', $postfields);

        if ($method == 'GET') {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . $postfields);
        } else {
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postfields);
        }

        curl_setopt($ch, CURLOPT_HEADER, 1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        if (Configure::get('Blesta.curl_verify_ssl')) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        } else {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        }

        if ($include_login) {
            curl_setopt($ch, CURLOPT_COOKIE, 'PVEAuthCookie=' . $this->ticket);
        }

        $additional_header = ['Expect:'];

        if ($method != 'GET' && $include_login) {
            $additional_header[] = 'CSRFPreventionToken: ' . $this->csrf_prevention_token;
        }

        curl_setopt($ch, CURLOPT_HTTPHEADER, $additional_header);

        $response = curl_exec($ch);

        if (curl_errno($ch) || $response == false) {
            $error = [
                'error' => 'Curl Error',
                'message' => 'An internal error occurred, or the server did not respond to the request.',
                'status' => 500
            ];
            $this->logger->error(curl_error($ch));

            return new ProxmoxResponse(['content' => json_encode($error), 'headers' => []]);
        }
        curl_close($ch);

        $data = explode("\n", $response);

        return new ProxmoxResponse([
            'content' => $data[count($data) - 1],
            'headers' => array_splice($data, 0, count($data) - 1)
        ]);
    }

    /**
     * Returns the details of the last request made
     *
     * @return array An array containing:
     *  - url The URL of the last request
     *  - args The parameters passed to the URL
     */
    public function lastRequest()
    {
        return $this->last_request;
    }

    /**
     * Loads a command class
     *
     * @param string $command The command class filename to load
     */
    public function loadCommand($command)
    {
        require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . 'commands' . DIRECTORY_SEPARATOR . $command . '.php';
    }
}
