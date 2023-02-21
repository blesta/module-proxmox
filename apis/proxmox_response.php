<?php
/**
 * Proxmox API response handler
 *
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @package proxmox
 */
class ProxmoxResponse
{
    /**
     * @var object The json_decode'ed object
     */
    private $json;
    /**
     * @var string The raw response from the API (JSON)
     */
    private $raw;
    private $errors = [];

    /**
     * Initializes the Proxmox Response
     *
     * @param array $api_response The API response
     */
    public function __construct($api_response)
    {
        $this->raw = $api_response['content'];
        $this->response = json_decode($api_response['content']);
        $this->headers = $api_response['headers'];

        // Set status
        if (isset($this->headers[0])) {
            $status_parts = explode(' ', $this->headers[0]);
            if (isset($status_parts[1]) && $status_parts[1] == '500') {
                array_shift($status_parts);
                $this->errors = ['server' => implode(' ', $status_parts)];
            }
        }

        try {
            $this->json = $this->response;
            $this->json->status = empty($this->json->data) ? 'error' : 'success';
        } catch (Exception $e) {
            // Invalid response
            echo $e->getMessage();
        }
    }

    /**
     * Returns the status of the API Response
     *
     * @return string The status (success, error, null if invalid response)
     */
    public function status()
    {
        if ($this->json) {
            return (string)$this->json->status;
        }
        return null;
    }

    /**
     * Returns the response
     *
     * @return stdClass A stdClass object representing the response, null if invalid response
     */
    public function response()
    {
        if ($this->json) {
            return $this->json;
        }
        return null;
    }

    /**
     * Returns all errors contained in the response
     *
     * @return stdClass A stdClass object representing the errors in the response, false if invalid response
     */
    public function errors()
    {
        return $this->errors;
    }

    /**
     * Returns the raw response
     *
     * @return string The raw response
     */
    public function raw()
    {
        return $this->raw;
    }
}
