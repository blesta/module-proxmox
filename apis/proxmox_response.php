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
        if ($this->json) {
            if ($this->json->status == 'error') {
                return $this->json;
            }
        }
        return false;
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
