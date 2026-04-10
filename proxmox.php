<?php

use Blesta\Core\Util\Validate\Server;

/**
 * Proxmox Module
 *
 * @package blesta
 * @subpackage blesta.components.modules.proxmox
 * @author Phillips Data, Inc.
 * @author Mark Dietzer
 * @copyright Copyright (c) 2013, Phillips Data, Inc.
 * @license http://www.blesta.com/license/ The Blesta License Agreement
 * @link http://www.blesta.com/ Blesta
 * @link http://www.fullambit.net/
 */
class Proxmox extends Module
{
    /**
     * Initializes the module
     */
    public function __construct()
    {
        // Load configuration required by this module
        $this->loadConfig(dirname(__FILE__) . DS . 'config.json');

        // Load components required by this module
        Loader::loadComponents($this, ['Input']);

        // Load the language required by this module
        Language::loadLang('proxmox', null, dirname(__FILE__) . DS . 'language' . DS);
    }

    /**
     * Performs migration of data from $current_version (the current installed version)
     * to the given file set version. Sets Input errors on failure, preventing
     * the module from being upgraded.
     *
     * @param string $current_version The current installed version of this module
     */
    public function upgrade($current_version)
    {
        if (version_compare($current_version, '3.0.0', '<')) {
            if (!isset($this->ModuleManager)) {
                Loader::loadModels($this, ['ModuleManager']);
            }
            if (!isset($this->Packages)) {
                Loader::loadModels($this, ['Packages']);
            }

            // Update all module rows
            $modules = $this->ModuleManager->getByClass('proxmox');
            foreach ($modules as $module) {
                // Get rows and packages for the module
                $rows = $this->ModuleManager->getRows($module->id);
                $packages = $this->Packages->getAll(
                    $module->company_id,
                    ['name' => 'ASC'],
                    null,
                    null,
                    ['module_id' => $module->id]
                );

                foreach ($rows as $row) {
                    // Get and set module row meta as an in between step for those who have used the community update
                    $meta = (array)$row->meta;
                    $meta['gateway'] = $meta['gateway'] ?? '';
                    $meta['template_storage'] = $meta['template_storage'] ?? 'local';
                    foreach ($packages as $package) {
                        if ($package->module_row !== $row->id) {
                            continue;
                        }

                        // Update package meta
                        $package = $this->Packages->get($package->id);
                        $package_meta = (array) $package->meta;
                        $meta_fields = ['gateway', 'storage', 'template_storage', 'default_template'];
                        foreach ($meta_fields as $meta_field) {
                            $package_meta[$meta_field] = $meta[$meta_field] ?? '';
                        }

                        $this->Packages->edit($package->id, ['meta' => $package_meta]);
                    }
                }
            }
        }
    }

    /**
     * Attempts to validate service info. This is the top-level error checking method. Sets Input errors on failure.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @return bool True if the service validates, false otherwise. Sets Input errors when false.
     */
    public function validateService($package, array $vars = null)
    {
        $this->Input->setRules($this->getServiceRules($vars));
        return $this->Input->validates($vars);
    }

    /**
     * Attempts to validate an existing service against a set of service info updates. Sets Input errors on failure.
     *
     * @param stdClass $service A stdClass object representing the service to validate for editing
     * @param array $vars An array of user-supplied info to satisfy the request
     * @return bool True if the service update validates or false otherwise. Sets Input errors when false.
     */
    public function validateServiceEdit($service, array $vars = null)
    {
        $this->Input->setRules($this->getServiceRules($vars, true));
        return $this->Input->validates($vars);
    }

    /**
     * Returns the rule set for adding/editing a service
     *
     * @param array $vars A list of input vars
     * @param bool $edit True to get the edit rules, false for the add rules
     * @return array Service rules
     */
    private function getServiceRules(array $vars = null, $edit = false)
    {
        // Set rules
        $rules = [
            'proxmox_hostname' => [
                'format' => [
                    'if_set' => $edit,
                    'rule' => [[$this, 'validateHostName']],
                    'message' => Language::_('Proxmox.!error.proxmox_hostname.format', true)
                ]
            ]
        ];

        return $rules;
    }

    /**
     * Adds the service to the remote server. Sets Input errors on failure,
     * preventing the service from being added.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being added (if the current service is an addon
     *  service and parent service has already been provisioned)
     * @param string $status The status of the service being added. These include:
     *  - active
     *  - canceled
     *  - pending
     *  - suspended
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addService(
        $package,
        array $vars = null,
        $parent_package = null,
        $parent_service = null,
        $status = 'pending'
    ) {
        // Load the API
        $row = $this->getModuleRow();

        if (!$row) {
            $this->Input->setErrors(
                ['module_row' => ['missing' => Language::_('Proxmox.!error.module_row.missing', true)]]
            );
            return;
        }

        $api = $this->getApi($row->meta->user, $row->meta->password, $row->meta->host, $row->meta->port);

        // Get the fields for the service
        $params = $this->getFieldsFromInput($vars, $package);

        $new_vmid = [];

        if (empty($row->meta->vmid) || $row->meta->vmid < 200) {
            $new_vmid = 200;
        } else {
            $new_vmid = $row->meta->vmid;
        }

        $params['vmid'] = '';
        $params['storage'] = $package->meta->storage;
        $params['ip'] = '';
        $params['gateway'] = $package->meta->gateway;

        // Validate the service-specific fields
        $this->validateService($package, $vars);

        if ($this->Input->errors()) {
            return;
        }

        // Only provision the service if 'use_module' is true
        if ($vars['use_module'] == 'true') {
            $params['vmid'] = $new_vmid;

            $available_ips = explode("\n", $row->meta->ips);
            $params['ip'] = trim(array_shift($available_ips));

            $client_id = (isset($vars['client_id']) ? $vars['client_id'] : '');

            // Create a new client (if one does not already exist)
            $client = $this->createClient($client_id, $params['userid'], $row);

            if ($this->Input->errors()) {
                return;
            }

            // Attempt to create the virtual server
            $api->loadCommand('proxmox_vserver');
            try {
                // Load up the Virtual Server API
                $vserver_api = new ProxmoxVserver($api);
                $masked_params = $params;
                $masked_params['password'] = '***';

                // Create the Virtual Server
                $this->log($row->meta->host . '|vserver-create', serialize($masked_params), 'input', true);
                $response = $this->parseResponse($vserver_api->create($params), $row);
            } catch (Exception $e) {
                // Internal Error
                $this->Input->setErrors(['api' => ['internal' => Language::_('Proxmox.!error.api.internal', true)]]);
            }

            if ($this->Input->errors()) {
                return;
            }

            $new_mod_row_params = [];
            foreach ($row->meta as $key => $value) {
                $new_mod_row_params[$key] = $value;
            }
            $new_mod_row_params['vmid'] = $new_vmid + 1;
            $new_mod_row_params['ips'] = implode("\n", $available_ips);
            $this->ModuleManager->editRow($row->id, $new_mod_row_params);

            sleep(5);

            // Attempt to start the VM
            $module_row = $this->getModuleRow($package->module_row);
            $this->performAction('boot', $params['vmid'], $params['type'], $params['node'], $module_row, [], true);
        }

        // Return service fields
        return [
            [
                'key' => 'proxmox_vserver_id',
                'value' => $params['vmid'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_ip',
                'value' => $params['ip'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_hostname',
                'value' => $params['hostname'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_node',
                'value' => $params['node'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_type',
                'value' => $params['type'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_username',
                'value' => $params['userid'],
                'encrypted' => 0
            ],
            [
                'key' => 'password',
                'value' => $params['password'],
                'encrypted' => 1
            ],
            [
                'key' => 'proxmox_cpu',
                'value' => $params['sockets'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_memory',
                'value' => $params['memory'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_hdd',
                'value' => $params['hdd'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_storage',
                'value' => $params['storage'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_template_storage',
                'value' => $params['template_storage'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_gateway',
                'value' => $params['gateway'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_netspeed',
                'value' => $params['netspeed'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_cpulimit',
                'value' => $params['cpulimit'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_cpuunits',
                'value' => $params['cpuunits'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_unprivileged',
                'value' => $params['unprivileged'],
                'encrypted' => 0
            ],
            [
                'key' => 'proxmox_swap',
                'value' => $params['swap'],
                'encrypted' => 0
            ]
        ];
    }

    /**
     * Edits the service on the remote server. Sets Input errors on failure,
     * preventing the service from being edited.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $vars An array of user supplied info to satisfy the request
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being edited (if the current service is an addon service)
     * @return array A numerically indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function editService($package, $service, array $vars = [], $parent_package = null, $parent_service = null)
    {
        // Load the API
        $row = $this->getModuleRow();
        $api = $this->getApi($row->meta->user, $row->meta->password, $row->meta->host, $row->meta->port);

        // Validate the service-specific fields
        $this->validateServiceEdit($service, $vars);

        if ($this->Input->errors()) {
            return;
        }

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);

        // Only provision the service if 'use_module' is true
        if ($vars['use_module'] == 'true') {
            // Nothing to do
        }

        // Return all the service fields
        $fields = [];
        $encrypted_fields = ['proxmox_password'];
        foreach ($service_fields as $key => $value) {
            $fields[] = ['key' => $key, 'value' => $value, 'encrypted' => (in_array($key, $encrypted_fields) ? 1 : 0)];
        }

        return $fields;
    }

    /**
     * Cancels the service on the remote server. Sets Input errors on failure,
     * preventing the service from being canceled.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being canceled (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically
     *  indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function cancelService($package, $service, $parent_package = null, $parent_service = null)
    {
        if (($row = $this->getModuleRow())) {
            $api = $this->getApi($row->meta->user, $row->meta->password, $row->meta->host, $row->meta->port);
            $api->loadCommand('proxmox_vserver');

            $service_fields = $this->serviceFieldsToObject($service->fields);

            $available_ips = explode("\n", $row->meta->ips);

            $new_mod_row_params = [];
            foreach ($row->meta as $key => $value) {
                $new_mod_row_params[$key] = $value;
            }

            $available_ips[] = $service_fields->proxmox_ip;
            $new_mod_row_params['ips'] = implode("\n", $available_ips);
            $this->ModuleManager->editRow($row->id, $new_mod_row_params);


            // Attempt to terminate the virtual server
            try {
                // Load up the Virtual Server API
                $vserver_api = new ProxmoxVserver($api);
                $params = [
                    'vmid' => $service_fields->proxmox_vserver_id,
                    'type' => $service_fields->proxmox_type,
                    'node' => $service_fields->proxmox_node
                ];

                // Terminate the Virtual Server
                $this->log($row->meta->host . '|vserver-terminate', serialize($params), 'input', true);
                if ($service_fields->proxmox_type != 'qemu') {
                    $this->parseResponse($vserver_api->shutdown($params), $row);
                }
                sleep(5);
                $this->parseResponse($vserver_api->terminate($params), $row);
            } catch (Exception $e) {
                // Internal Error
                $this->Input->setErrors(['api' => ['internal' => Language::_('Proxmox.!error.api.internal', true)]]);
                return;
            }
        }
        return null;
    }

    /**
     * Suspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being suspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being suspended (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically
     *  indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function suspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        // Suspend the service by shutting the server down
        $response = null;

        if (($row = $this->getModuleRow())) {
            $api = $this->getApi($row->meta->user, $row->meta->password, $row->meta->host, $row->meta->port);

            // Get the service fields
            $service_fields = $this->serviceFieldsToObject($service->fields);

            // Load the virtual server API
            $api->loadCommand('proxmox_vserver');

            try {
                $server_api = new ProxmoxVserver($api);
                $params = [
                    'vmid' => $service_fields->proxmox_vserver_id,
                    'type' => $service_fields->proxmox_type,
                    'node' => $service_fields->proxmox_node
                ];

                $this->log($row->meta->host . '|vserver-shutdown', serialize($params), 'input', true);
                $response = $this->parseResponse($server_api->shutdown($params), $row);
            } catch (Exception $e) {
                // Nothing to do
                return;
            }
        }

        return null;
    }

    /**
     * Unsuspends the service on the remote server. Sets Input errors on failure,
     * preventing the service from being unsuspended.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being unsuspended (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically
     *  indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function unsuspendService($package, $service, $parent_package = null, $parent_service = null)
    {
        // Unsuspend the service by booting it up and releasing the suspension lock
        $response = null;

        if (($row = $this->getModuleRow())) {
            $api = $this->getApi($row->meta->user, $row->meta->password, $row->meta->host, $row->meta->port);

            // Get the service fields
            $service_fields = $this->serviceFieldsToObject($service->fields);

            // Load the virtual server API
            $api->loadCommand('proxmox_vserver');

            try {
                $server_api = new ProxmoxVserver($api);
                $params = [
                    'vmid' => $service_fields->proxmox_vserver_id,
                    'type' => $service_fields->proxmox_type,
                    'node' => $service_fields->proxmox_node
                ];

                $this->log($row->meta->host . '|vserver-boot', serialize($params), 'input', true);
                $response = $this->parseResponse($server_api->boot($params), $row);
            } catch (Exception $e) {
                // Nothing to do
                return;
            }
        }

        return null;
    }

    /**
     * Allows the module to perform an action when the service is ready to renew.
     * Sets Input errors on failure, preventing the service from renewing.
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being renewed (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically
     *  indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function renewService($package, $service, $parent_package = null, $parent_service = null)
    {
        // Nothing to do
        return null;
    }

    /**
     * Updates the package for the service on the remote server. Sets Input
     * errors on failure, preventing the service's package from being changed.
     *
     * @param stdClass $package_from A stdClass object representing the current package
     * @param stdClass $package_to A stdClass object representing the new package
     * @param stdClass $service A stdClass object representing the current service
     * @param stdClass $parent_package A stdClass object representing the parent
     *  service's selected package (if the current service is an addon service)
     * @param stdClass $parent_service A stdClass object representing the parent
     *  service of the service being changed (if the current service is an addon service)
     * @return mixed null to maintain the existing meta fields or a numerically
     *  indexed array of meta fields to be stored for this service containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function changeServicePackage(
        $package_from,
        $package_to,
        $service,
        $parent_package = null,
        $parent_service = null
    ) {
        // Nothing to do
        return null;
    }

    /**
     * Validates input data when attempting to add a package, returns the meta
     * data to save when adding a package. Performs any action required to add
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being added.
     *
     * @param array An array of key/value pairs used to add the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function addPackage(array $vars = null)
    {
        $this->Input->setRules($this->getPackageRules($vars));

        $meta = [];
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Validates input data when attempting to edit a package, returns the meta
     * data to save when editing a package. Performs any action required to edit
     * the package on the remote server. Sets Input errors on failure,
     * preventing the package from being edited.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param array An array of key/value pairs used to edit the package
     * @return array A numerically indexed array of meta fields to be stored for this package containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function editPackage($package, array $vars = null)
    {
        $this->Input->setRules($this->getPackageRules($vars));

        $meta = [];
        if ($this->Input->validates($vars)) {
            // Return all package meta fields
            foreach ($vars['meta'] as $key => $value) {
                $meta[] = [
                    'key' => $key,
                    'value' => $value,
                    'encrypted' => 0
                ];
            }
        }

        return $meta;
    }

    /**
     * Deletes the package on the remote server. Sets Input errors on failure,
     * preventing the package from being deleted.
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @see Module::getModule()
     * @see Module::getModuleRow()
     */
    public function deletePackage($package)
    {
        // Nothing to do
        return null;
    }

    /**
     * Returns the rendered view of the manage module page
     *
     * @param mixed $module A stdClass object representing the module and its rows
     * @param array $vars An array of post data submitted to or on the
     *  manage module page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the manager module page
     */
    public function manageModule($module, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('manage', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        $this->view->set('module', $module);

        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the add module row page
     *
     * @param array $vars An array of post data submitted to or on the add module
     *  row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the add module row page
     */
    public function manageAddRow(array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('add_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        $this->view->set('vars', (object)$vars);
        return $this->view->fetch();
    }

    /**
     * Returns the rendered view of the edit module row page
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of post data submitted to or on the edit module
     *  row page (used to repopulate fields after an error)
     * @return string HTML content containing information to display when viewing the edit module row page
     */
    public function manageEditRow($module_row, array &$vars)
    {
        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('edit_row', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html', 'Widget']);

        if (empty($vars)) {
            $vars = $module_row->meta;
            
            // Mask password by replacing with ***
            $vars->password = '***';
        }
        
        $this->view->set('vars', (object)$vars);
        return $this->view->fetch();
    }

    /**
     * Adds the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being added.
     *
     * @param array $vars An array of module info to add
     * @return array A numerically indexed array of meta fields for the module row containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function addModuleRow(array &$vars)
    {
        $meta_fields = ['server_name', 'user', 'password', 'host', 'port',
            'vmid', 'ips'
        ];
        $encrypted_fields = ['user', 'password'];

        $this->Input->setRules($this->getRowRules($vars));

        // Validate module row
        if ($this->Input->validates($vars)) {
            // Build the meta data for this row
            $meta = [];
            foreach ($vars as $key => $value) {
                if (in_array($key, $meta_fields)) {
                    $meta[] = [
                        'key' => $key,
                        'value' => $value,
                        'encrypted' => in_array($key, $encrypted_fields) ? 1 : 0
                    ];
                }
            }

            return $meta;
        }
    }

    /**
     * Edits the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being updated.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     * @param array $vars An array of module info to update
     * @return array A numerically indexed array of meta fields for the module row containing:
     *  - key The key for this meta field
     *  - value The value for this key
     *  - encrypted Whether or not this field should be encrypted (default 0, not encrypted)
     */
    public function editModuleRow($module_row, array &$vars)
    {
        // If the replacement sting is submitted, replace with the stored password
        if (isset($vars['password']) && $vars['password'] == '***') {
            $vars['password'] = $module_row->meta->password;
        }
        
        // Same as adding
        return $this->addModuleRow($vars);
    }

    /**
     * Deletes the module row on the remote server. Sets Input errors on failure,
     * preventing the row from being deleted.
     *
     * @param stdClass $module_row The stdClass representation of the existing module row
     */
    public function deleteModuleRow($module_row)
    {
        return null; // Nothing to do
    }

    /**
     * Returns an array of available service delegation order methods. The module
     * will determine how each method is defined. For example, the method "first"
     * may be implemented such that it returns the module row with the least number
     * of services assigned to it.
     *
     * @return array An array of order methods in key/value pairs where the key is the
     *  type to be stored for the group and value is the name for that option
     * @see Module::selectModuleRow()
     */
    public function getGroupOrderOptions()
    {
        return ['first' => Language::_('Proxmox.order_options.first', true)];
    }

    /**
     * Determines which module row should be attempted when a service is provisioned
     * for the given group based upon the order method set for that group.
     *
     * @return int The module row ID to attempt to add the service with
     * @see Module::getGroupOrderOptions()
     */
    public function selectModuleRow($module_group_id)
    {
        if (!isset($this->ModuleManager)) {
            Loader::loadModels($this, ['ModuleManager']);
        }

        $group = $this->ModuleManager->getGroup($module_group_id);

        if ($group) {
            switch ($group->add_order) {
                default:
                case 'first':

                    foreach ($group->rows as $row) {
                        return $row->id;
                    }

                    break;
            }
        }
        return 0;
    }

    /**
     * Returns all fields used when adding/editing a package, including any
     * javascript to execute when the page is rendered with these fields.
     *
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render
     *  as well as any additional HTML markup to include
     */
    public function getPackageFields($vars = null)
    {
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Fetch all packages available for the given server or server group
        if ($vars->module_group == 'select') {
            $vars->module_group = null;
        }
        $module_row = $this->getModuleRowByServer($vars->module_row ?? 0, $vars->module_group ?? '');

        // Load more server info when the type is set
        $nodes = [];
        if ($module_row && !empty($vars->meta['type'])) {
            // Load nodes
            $nodes_res = $this->getNodes($vars->meta['type'], $module_row);
            foreach ($nodes_res as $node) {
                $nodes[$node->node] = $node->node;
            }
        }

        // Remove nodes from 'available' if they are currently 'assigned'
        if (isset($vars->meta['nodes'])) {
            $this->assignGroups($nodes, $vars->meta['nodes']);

            // Set the node value as the node key
            $temp = [];
            foreach ($vars->meta['nodes'] as $key => $value) {
                $temp[$value] = $value;
            }
            $vars->meta['nodes'] = $temp;
            unset($temp, $key, $value);
        }

        $fields = new ModuleFields();

        // Show nodes, and set javascript field toggles
        $assigned_nodes = $vars->meta['nodes'] ?? [];
        $this->Form->setOutput(true);
        $fields->setHtml('
			<table id="proxmox_node_selector" style="display: none;">
				<tr>
					<td>' . Language::_('Proxmox.package_fields.assigned_nodes', true) . '</td>
					<td></td>
					<td>' . Language::_('Proxmox.package_fields.available_nodes', true) . '</td>
				</tr>
				<tr>
					<td>
						'
                        . $this->Form->fieldMultiSelect(
                            'meta[nodes][]',
                            $assigned_nodes,
                            [],
                            ['id' => 'assigned_nodes']
                        )
                        . '
					</td>
					<td><a href="#" class="move_left">&nbsp;</a> &nbsp; <a href="#" class="move_right">&nbsp;</a></td>
					<td>
						'
                        . $this->Form->fieldMultiSelect(
                            'available_nodes[]',
                            $nodes ?? [],
                            [],
                            ['id' => 'available_nodes']
                        )
                        . "
					</td>
				</tr>
			</table>

			<script type=\"text/javascript\">
				$(document).ready(function() {
					toggleProxmoxFields();

					$('#proxmox_type').change(function() {
						toggleProxmoxFields();
					});

					$('#proxmox_type').change(function() {
						selectAssignedNodes();
						fetchModuleOptions();
					});

					$('#proxmox_template_storage').change(function() {
						selectAssignedNodes();
						fetchModuleOptions();
					});

					// Select all assigned groups on submit
					$('#assigned_nodes').closest('form').submit(function() {
						selectAssignedNodes();
					});

					// Move nodes from right to left
					$('.move_left').click(function() {
						$('#available_nodes option:selected').appendTo($('#assigned_nodes'));
						selectAssignedNodes();
						fetchModuleOptions();
						return false;
					});
					// Move nodes from left to right
					$('.move_right').click(function() {
						$('#assigned_nodes option:selected').appendTo($('#available_nodes'));
						selectAssignedNodes();
						fetchModuleOptions();
						return false;
					});
				});

				function selectAssignedNodes() {
					$('#assigned_nodes option').attr('selected', 'selected');
				}

				function toggleProxmoxFields() {
					// Hide fields dependent on this value
					if ($('#proxmox_type').val() == '') {
						$('#assigned_nodes').closest('table').hide();
					}
					// Show fields dependent on this value
					else {
						$('#assigned_nodes').closest('table').show();
					}
				}

                $('#proxmox_type').parent().after($('#proxmox_node_selector'));
                $('#proxmox_node_selector').show().wrap('<li></li>');
			</script>
		");

        // Set the Proxmox type as a selectable option
        $types = ['' => Language::_('Proxmox.please_select', true)] + $this->getTypes();
        $type = $fields->label(Language::_('Proxmox.package_fields.type', true), 'proxmox_type');
        $type->attach(
            $fields->fieldSelect(
                'meta[type]',
                $types,
                $vars->meta['type'] ?? null,
                ['id' => 'proxmox_type']
            )
        );
        $fields->setField($type);
        unset($type);

        // Set Storage field
        $storage_options = $this->getNodeStorage(
            reset($assigned_nodes),
            $module_row,
            ($vars->meta['type'] ?? null) === 'lxc' ? 'rootdir' : 'images'
        );
        $storage = $fields->label(Language::_('Proxmox.package_fields.storage', true), 'proxmox_storage');
        $storage->attach(
            $fields->fieldSelect(
                'meta[storage]',
                $storage_options,
                $vars->meta['storage'] ?? 'local',
                ['id' => 'proxmox_storage']
            )
        );
        // Add tooltip
        $tooltip = $fields->tooltip(Language::_('Proxmox.package_fields.tooltip.storage', true));
        $storage->attach($tooltip);
        $fields->setField($storage);
        if (($vars->meta['type'] ?? null) === 'lxc') {
            // Set template storage field
            $lxc_storage_options = $this->getNodeStorage(reset($assigned_nodes), $module_row, 'vztmpl');
            $template_storage = $fields->label(
                Language::_('Proxmox.package_fields.template_storage', true),
                'proxmox_template_storage'
            );
            $template_storage->attach(
                $fields->fieldSelect(
                    'meta[template_storage]',
                    $lxc_storage_options,
                    $vars->meta['template_storage'] ?? 'local',
                    ['id' => 'proxmox_template_storage']
                )
            );
            // Add tooltip
            $tooltip = $fields->tooltip(Language::_('Proxmox.package_fields.tooltip.template_storage', true));
            $template_storage->attach($tooltip);
            $fields->setField($template_storage);

            // Set default template field
            $templates = $this->getServerTemplates(
                reset($assigned_nodes),
                $vars->meta['template_storage'] ?? 'local',
                $module_row
            );
            $default_template = $fields->label(
                Language::_('Proxmox.package_fields.default_template', true),
                'proxmox_default_template'
            );
            $default_template->attach(
                $fields->fieldSelect(
                    'meta[default_template]',
                    $templates,
                    $vars->meta['default_template'] ?? 'default',
                    ['id' => 'proxmox_default_template', 'class' => 'lxc_field']
                )
            );
            $fields->setField($default_template);

            // Set unprivileged field
            $unprivilegeds = $this->setUnprivileged();
            $unprivileged = $fields->label(
                Language::_('Proxmox.package_fields.unprivileged', true),
                'proxmox_unprivileged'
            );
            $unprivileged->attach(
                $fields->fieldSelect(
                    'meta[unprivileged]',
                    $unprivilegeds,
                    $vars->meta['unprivileged'] ?? null,
                    ['id' => 'proxmox_unprivileged']
                )
            );
            $fields->setField($unprivileged);
            unset($unprivileged);
        }

        // Set HDD field
        $hdd = $fields->label(Language::_('Proxmox.package_fields.hdd', true), 'proxmox_hdd');
        $hdd->attach(
            $fields->fieldText('meta[hdd]', $vars->meta['hdd'] ?? null, ['id' => 'proxmox_hdd'])
        );
        $fields->setField($hdd);

        // Set Memory field
        $memory = $fields->label(Language::_('Proxmox.package_fields.memory', true), 'proxmox_memory');
        $memory->attach(
            $fields->fieldText('meta[memory]', $vars->meta['memory'] ?? null, ['id' => 'proxmox_memory'])
        );
        $fields->setField($memory);

        // Set CPU field
        $cpu = $fields->label(Language::_('Proxmox.package_fields.cpu', true), 'proxmox_cpu');
        $cpu->attach(
            $fields->fieldText('meta[cpu]', $vars->meta['cpu'] ?? null, ['id' => 'proxmox_cpu'])
        );
        $fields->setField($cpu);

        // Set netspeed field
        $netspeed = $fields->label(Language::_('Proxmox.package_fields.netspeed', true), 'proxmox_netspeed');
        $netspeed->attach(
            $fields->fieldText('meta[netspeed]', $vars->meta['netspeed'] ?? null, ['id' => 'proxmox_netspeed'])
        );
        $fields->setField($netspeed);

        // Set cpulimit field
        $cpulimit = $fields->label(Language::_('Proxmox.package_fields.cpulimit', true), 'proxmox_cpulimit');
        $cpulimit->attach(
            $fields->fieldText('meta[cpulimit]', $vars->meta['cpulimit'] ?? null, ['id' => 'proxmox_cpulimit'])
        );
        $fields->setField($cpulimit);

        // Set cpuunits field
        $cpuunits = $fields->label(Language::_('Proxmox.package_fields.cpuunits', true), 'proxmox_cpuunits');
        $cpuunits->attach(
            $fields->fieldText('meta[cpuunits]', $vars->meta['cpuunits'] ?? null, ['id' => 'proxmox_cpuunits'])
        );
        $fields->setField($cpuunits);

        if (($vars->meta['type'] ?? null) === 'lxc') {
            // Set swap field
            $swap = $fields->label(Language::_('Proxmox.package_fields.swap', true), 'proxmox_swap');
            $swap->attach($fields->fieldText('meta[swap]', $vars->meta['swap'] ?? null, ['id' => 'proxmox_swap']));
            $fields->setField($swap);
        }

        // Set Gateway field
        $gateway = $fields->label(Language::_('Proxmox.package_fields.gateway', true), 'proxmox_gateway');
        $gateway->attach(
            $fields->fieldText(
                'meta[gateway]',
                $vars->meta['gateway'] ?? null,
                ['id' => 'proxmox_gateway', 'placeholder' => 'e.g. 127.0.0.1']
            )
        );
        $fields->setField($gateway);

        return $fields;
    }

    /**
     * Returns all fields to display to an admin attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render as
     *  well as any additional HTML markup to include
     */
    public function getAdminAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        // Fetch the module row available for this package
        $module_row = $this->getModuleRowByServer(
            (isset($package->module_row) ? $package->module_row : 0),
            (isset($package->module_group) ? $package->module_group : '')
        );

        $fields = new ModuleFields();

        // Create hostname label
        $host_name = $fields->label(Language::_('Proxmox.service_field.proxmox_hostname', true), 'proxmox_hostname');
        // Create hostname field and attach to hostname label
        $host_name->attach(
            $fields->fieldText(
                'proxmox_hostname',
                (isset($vars->proxmox_hostname) ? $vars->proxmox_hostname : null),
                ['id' => 'proxmox_hostname']
            )
        );
        $password = $fields->label(Language::_('Proxmox.service_field.proxmox_password', true), 'password');
        $password->attach(
            $fields->fieldText(
                'password',
                (isset($vars->password['password']) ? $vars->password['password'] : null),
                ['id' => 'password']
            )
        );
        // Add tooltip
        $tooltip = $fields->tooltip(Language::_('Proxmox.service_field.tooltip.password', true));
        $password->attach($tooltip);
        // Set the label as a field
        $fields->setField($host_name);
        $fields->setField($password);

        return $fields;
    }

    /**
     * Returns all fields to display to a client attempting to add a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render
     *  as well as any additional HTML markup to include
     */
    public function getClientAddFields($package, $vars = null)
    {
        Loader::loadHelpers($this, ['Html']);

        // Fetch the module row available for this package
        $module_row = $this->getModuleRowByServer(
            (isset($package->module_row) ? $package->module_row : 0),
            (isset($package->module_group) ? $package->module_group : '')
        );

        $fields = new ModuleFields();

        // Create hostname label
        $host_name = $fields->label(Language::_('Proxmox.service_field.proxmox_hostname', true), 'proxmox_hostname');
        // Create hostname field and attach to hostname label
        $host_name->attach(
            $fields->fieldText(
                'proxmox_hostname',
                (isset($vars->proxmox_hostname) ? $vars->proxmox_hostname : ($vars->domain ?? null)),
                ['id' => 'proxmox_hostname']
            )
        );
        $password = $fields->label(Language::_('Proxmox.service_field.proxmox_password', true), 'password');
        $password->attach(
            $fields->fieldText(
                'password',
                (isset($vars->password) ? $vars->password : null),
                ['id' => 'password']
            )
        );
        // Add tooltip
        $tooltip = $fields->tooltip(Language::_('Proxmox.service_field.tooltip.password', true));
        $password->attach($tooltip);
        // Set the label as a field
        $fields->setField($host_name);
        $fields->setField($password);

        return $fields;
    }

    /**
     * Returns all fields to display to an admin attempting to edit a service with the module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @param $vars stdClass A stdClass object representing a set of post fields
     * @return ModuleFields A ModuleFields object, containg the fields to render
     *  as well as any additional HTML markup to include
     */
    public function getAdminEditFields($package, $vars = null)
    {
        // No fields
        return new ModuleFields();
    }

    /**
     * Fetches the HTML content to display when viewing the service info in the
     * admin interface.
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content containing information to display when viewing the service info
     */
    public function getAdminServiceInfo($service, $package)
    {
        $row = $this->getModuleRow();

        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('admin_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        return $this->view->fetch();
    }

    /**
     * Fetches the HTML content to display when viewing the service info in the
     * client interface.
     *
     * @param stdClass $service A stdClass object representing the service
     * @param stdClass $package A stdClass object representing the service's package
     * @return string HTML content containing information to display when viewing the service info
     */
    public function getClientServiceInfo($service, $package)
    {
        $row = $this->getModuleRow();

        // Load the view into this object, so helpers can be automatically added to the view
        $this->view = new View('client_service_info', 'default');
        $this->view->base_uri = $this->base_uri;
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);

        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        $this->view->set('module_row', $row);
        $this->view->set('package', $package);
        $this->view->set('service', $service);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        return $this->view->fetch();
    }


    /**
     * Returns all tabs to display to an admin when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title.
     *  Example: array('methodName' => "Title", 'methodName2' => "Title2")
     */
    public function getAdminTabs($package)
    {
            return [
                'tabActions' => Language::_('Proxmox.tab_actions', true),
                'tabStats' => Language::_('Proxmox.tab_stats', true),
                'tabConsole' => Language::_('Proxmox.tab_console', true),
            ];
    }

    /**
     * Returns all tabs to display to a client when managing a service whose
     * package uses this module
     *
     * @param stdClass $package A stdClass object representing the selected package
     * @return array An array of tabs in the format of method => title.
     *  Example: array('methodName' => "Title", 'methodName2' => "Title2")
     */
    public function getClientTabs($package)
    {

        if (($package->meta->type ?? null) === 'qemu')  {
            return [
                'tabClientActions' => Language::_('Proxmox.tab_actions', true),
                'tabClientStats' => Language::_('Proxmox.tab_stats', true),
                'tabClientConsole' => Language::_('Proxmox.tab_console', true),
                'tabClientIsoManager' => Language::_('Proxmox.tab_isomanager', true),
            ];

        } else {
            return [
                'tabClientActions' => Language::_('Proxmox.tab_actions', true),
                'tabClientStats' => Language::_('Proxmox.tab_stats', true),
                'tabClientConsole' => Language::_('Proxmox.tab_console', true),
                'tabClientLXCReinstall' => Language::_('Proxmox.tab_lxcreinstall', true),
            ];
        }
    }

    /**
     * Actions tab (boot, shutdown, etc.)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabActions($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_actions', 'default');
        $this->view->base_uri = $this->base_uri;
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        // Perform the actions
        $this->actionsTab($package, $service, false, $get, $post);

        // Set default vars
        $vars = ['hostname' => $service_fields->proxmox_hostname];

        $this->view->set(
            'isos',
            $this->getServerISOs($service_fields->proxmox_node, $package->meta->storage, $module_row)
        );
        $this->view->set(
            'templates',
            $this->getServerTemplates($service_fields->proxmox_node, $package->meta->template_storage, $module_row)
        );

        $this->view->set('type', $service_fields->proxmox_type);

        // Fetch the server status
        $this->view->set(
            'server',
            $this->getServerState(
                $service_fields->proxmox_vserver_id,
                $service_fields->proxmox_type,
                $service_fields->proxmox_node,
                $module_row
            )
        );

        $this->view->set('vars', (object)$vars);
        $this->view->set('client_id', $service->client_id);
        $this->view->set('service_id', $service->id);

        $this->view->set('view', $this->view->view);
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);
        return $this->view->fetch();
    }

    /**
     * Client Actions tab (boot, shutdown, etc.)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientActions($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $this->view = new View('tab_client_actions', 'default');
        $this->view->base_uri = $this->base_uri;
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        // Perform the actions
        $this->actionsTab($package, $service, true, $get, $post);

        // Set default vars
        $vars = ['hostname' => $service_fields->proxmox_hostname];

        $this->view->set('type', $service_fields->proxmox_type);

        // Fetch the server status
        $this->view->set(
            'server',
            $this->getServerState(
                $service_fields->proxmox_vserver_id,
                $service_fields->proxmox_type,
                $service_fields->proxmox_node,
                $module_row
            )
        );

        $this->view->set('vars', (object)$vars);
        $this->view->set('client_id', $service->client_id);
        $this->view->set('service_id', $service->id);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        $this->view->set('view', $this->view->view);
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);
        return $this->view->fetch();
    }

    /**
     * Handles data for the actions tab in the client and admin interfaces
     * @see Proxmox::tabActions() and Proxmox::tabClientActions()
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param bool $client True if the action is being performed by the client, false otherwise
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     */
    private function actionsTab($package, $service, $client = false, array $get = null, array $post = null)
    {
        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        $get_key = '3';
        if ($client) {
            $get_key = '2';
        }

        // Perform actions
        if (array_key_exists($get_key, (array)$get)) {
            switch ($get[$get_key]) {
                case 'boot':
                    if (!$this->performAction(
                        'boot',
                        $service_fields->proxmox_vserver_id,
                        $service_fields->proxmox_type,
                        $service_fields->proxmox_node,
                        $module_row
                    )) {
                        $this->Input->setErrors(
                            ['api' => ['internal' => Language::_('Proxmox.!error.api.internal', true)]]
                        );
                    }
                    break;
                case 'shutdown':
                    if (!$this->performAction(
                        'shutdown',
                        $service_fields->proxmox_vserver_id,
                        $service_fields->proxmox_type,
                        $service_fields->proxmox_node,
                        $module_row
                    )) {
                        $this->Input->setErrors(
                            ['api' => ['internal' => Language::_('Proxmox.!error.api.internal', true)]]
                        );
                    }
                    break;
                case 'mount':
                    if ($service_fields->proxmox_type != 'qemu') {
                        break;
                    }
                    $this->performAction(
                        'mountIso',
                        $service_fields->proxmox_vserver_id,
                        $service_fields->proxmox_type,
                        $service_fields->proxmox_node,
                        $module_row,
                        ['iso' => (isset($post['iso']) ? $post['iso'] : null)]
                    );
                    break;
                case 'unmount':
                    if ($service_fields->proxmox_type != 'qemu') {
                        break;
                    }
                    $this->performAction(
                        'unmountIso',
                        $service_fields->proxmox_vserver_id,
                        $service_fields->proxmox_type,
                        $service_fields->proxmox_node,
                        $module_row
                    );
                    break;
                case 'reinstall':
                    if ($service_fields->proxmox_type != 'lxc') {
                        break;
                    }

                    $this->performAction(
                        'stop',
                        $service_fields->proxmox_vserver_id,
                        $service_fields->proxmox_type,
                        $service_fields->proxmox_node,
                        $module_row
                    );

                    sleep(5);

                    $this->performAction(
                        'terminate',
                        $service_fields->proxmox_vserver_id,
                        $service_fields->proxmox_type,
                        $service_fields->proxmox_node,
                        $module_row
                    );

                    sleep(10);

                    $api = $this->getApi(
                        $module_row->meta->user,
                        $module_row->meta->password,
                        $module_row->meta->host,
                        $module_row->meta->port
                    );

                    $params = [
                        'unprivileged' => $package->meta->unprivileged ?? 'disabled',
                        'type' => $service_fields->proxmox_type ?? 'lxc',
                        'template' => $package->meta->template_storage . ':vztmpl/' . ($post['template'] ?? ''),
                        'node' => $service_fields->proxmox_node,
                        'hostname' => $service_fields->proxmox_hostname,
                        'userid' => $service_fields->proxmox_username,
                        'password' => (!empty($post['password']) ? $post['password'] : $this->generatePassword()),
                        'memory' => $service_fields->proxmox_memory ?? 0,
                        'hdd' => $service_fields->proxmox_hdd ?? 0,
                        'storage' => $service_fields->proxmox_storage ?? 0,
                        'sockets' => $service_fields->proxmox_cpu ?? 0,
                        'netspeed' => $service_fields->proxmox_netspeed ?? 0,
                        'gateway' => $service_fields->proxmox_gateway ?? '',
                        'vmid' => $service_fields->proxmox_vserver_id,
                        'ip' => $service_fields->proxmox_ip
                    ];

                    // Load the vserver API
                    $api->loadCommand('proxmox_vserver');
                    $server_api = new ProxmoxVserver($api);
                    // $server_api->create($params);
                    if (($row = $this->getModuleRow())) {
                        $masked_params = $params;
                        $masked_params['password'] = '***';
                        $this->log($row->meta->host . '|vserver-create', serialize($masked_params), 'input', true);
                        $response = $this->parseResponse($server_api->create($params), $row);
                    }

                    sleep(5);

                    // Attempt to start the VM
                    $this->performAction('boot', $params['vmid'], $params['type'], $params['node'], $module_row, [], true);

                    break;
                default:
                    break;
            }
        }
    }

   /**
    * Handles data for the actions tab in the client and admin interfaces
    * @see Proxmox::tabActions() and Proxmox::tabClientActions()
    *
    * @param stdClass $package A stdClass object representing the current package
    * @param stdClass $service A stdClass object representing the current service
    * @param bool $client True if the action is being performed by the client, false otherwise
    * @param array $get Any GET parameters
    * @param array $post Any POST parameters
    * @param array $files Any FILES parameters
    */
    private function statsTabGraph($package, $service, $client = false, array $get = null, array $post = null)
    {
        $vars = [];

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        $get_key = '3';
        if ($client) {
            $get_key = '2';
        }

        // Perform actions
        if (array_key_exists($get_key, (array)$get)) {
            $api = $this->getApi(
                $module_row->meta->user,
                $module_row->meta->password,
                $module_row->meta->host,
                $module_row->meta->port
            );

            // Load the vserver API
            $api->loadCommand('proxmox_vserver');
            $result = false;

            try {
                $server_api = new ProxmoxVserver($api);
                $params = [
                    'vmid' => $service_fields->proxmox_vserver_id,
                    'type' => $service_fields->proxmox_type,
                    'node' => $service_fields->proxmox_node,
                    'timeframe' => 'day',
                    'ds' => $get[$get_key]
                ];
                $response = $this->parseResponse($server_api->graph($params), $module_row);
            } catch (Exception $e) {
                // Nothing to do
            }

            header('Content-Type: image/png');
            die(utf8_decode($response->data->image));
        }
    }

    /**
     * Performs an action on the virtual server.
     *
     * @param string $action The action to perform (i.e. "boot", "shutdown")
     * @param int $server_id The virtual server ID
     * @param stdClass $module_row An stdClass object representing a single server
     * @param array $data A key=>value list of data parameters to include with the action
     * @param bool $ignore_error Ignores any response error and returns the response anyway;
     *  useful when a response is expected to fail (e.g. check client exists) (optional, default false)
     * @return bool True if the action was performed successfully, false otherwise
     */
    private function performAction(
        $action,
        $server_id,
        $type,
        $node,
        $module_row,
        array $data = [],
        $ignore_error = false
    ) {
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );

        // Load the vserver API
        $api->loadCommand('proxmox_vserver');
        $result = false;

        // Set actions that will return null
        $null_actions = ['mountIso', 'unmountIso', 'hostname', 'password'];

        try {
            $server_api = new ProxmoxVserver($api);
            $params = array_merge($data, ['vmid' => $server_id, 'type' => $type, 'node' => $node]);

            $this->log($module_row->meta->host . '|vserver-' . $action, serialize($params), 'input', true);
            $response = $this->parseResponse(
                $server_api->{$action}($params),
                $module_row,
                in_array($action, $null_actions) || $ignore_error
            );

            // Accept successful responses, or responses that are null for specific actions
            if ($response && ($response->status == 'success' || in_array($action, $null_actions))) {
                return true;
            }
        } catch (Exception $e) {
            // Nothing to do
        }

        return $result;
    }

    /**
     * Statistics tab (bandwidth/disk usage)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabStats($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->statsTab($package, $service, $get, $post);
        return $view->fetch();
    }

    /**
     * Client Statistics tab (bandwidth/disk usage)
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientStats($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->statsTab($package, $service, $get, $post, true);
        return $view->fetch();
    }

    /**
     * Builds the data for the admin/client stats tabs
     * @see Proxmox::tabStats() and Proxmox::tabClientStats()
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @return View A template view to be rendered
     */
    private function statsTab($package, $service, $get = null, $post = null, $client = false)
    {
        // See if we need to show a graph instead
        $this->statsTabGraph($package, $service, $client, $get, $post);

        $template = ($client ? 'tab_client_stats' : 'tab_stats');

        $this->view = new View($template, 'default');
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        $this->view->set('client_id', $service->client_id);
        $this->view->set('service_id', $service->id);

        $this->view->base_uri = $this->base_uri;
        $this->view->set(
            'server',
            $this->getServerState(
                $service_fields->proxmox_vserver_id,
                $service_fields->proxmox_type,
                $service_fields->proxmox_node,
                $module_row,
                true
            )
        );
        $this->view->set(
            'module_hostname',
            (
                isset($module_row->meta->host) && isset($module_row->meta->port)
                    ? 'https://' . $module_row->meta->host . ':' . $module_row->meta->port
                    : ''
            )
        );

        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);
        return $this->view;
    }

    /**
     * Console tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabConsole($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->consoleTab($package, $service);
        return $view->fetch();
    }

    /**
     * Client Console tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientConsole($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->consoleTab($package, $service, true);
        return $view->fetch();
    }

    /**
     * Builds the data for the admin/client console tabs
     * @see Proxmox::tabConsole() and Proxmox::tabClientConsole()
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @return View A template view to be rendered
     */
    private function consoleTab($package, $service, $client = false)
    {
        $template = ($client ? 'tab_client_console' : 'tab_console');
        $this->view = new View($template, 'default');
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        // Load the vserver API
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );
        $api->loadCommand('proxmox_vserver');
        $server_api = new ProxmoxVserver($api);

        $params = [
            'vmid' => $service_fields->proxmox_vserver_id,
            'type' => $service_fields->proxmox_type,
            'node' => $service_fields->proxmox_node
        ];

        $response = $this->parseResponse($server_api->vnc($params), $module_row);

        // Set console info
        $session = [
            'vnc_ip' => $module_row->meta->host,
            'vnc_user' => $response->data->user,
            'vnc_password' => $response->data->ticket,
            'vnc_port' => $response->data->port,
            'vnc_cert' => str_replace("\n", '|', $response->data->cert)
        ];

        // Check whether the VNC vendor code is available
        $this->view->set('vnc_applet_available', is_dir(VENDORDIR . 'vnc'));

        $this->view->set('node_statistics', $this->getNodeStatistics($service_fields->proxmox_node, $module_row));
        $this->view->set('console', (object)$session);

        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);
        return $this->view;
    }

    /**
     * Client ISO Manager tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientIsoManager($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->isoManager($package, $service, $get, $post, true);
        return $view->fetch();
    }

    /**
     * Builds the data for the admin/client ISO manager tabs
     * @see Proxmox::tabClientIsoManager()
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @return View A template view to be rendered
     */
    private function isoManager($package, $service, $get = null, $post = null, $client = false){

        $template = ($client ? 'tab_client_isomanager' : '');
        $this->view = new View($template, 'default');
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        // Perform the actions
        $this->actionsTab($package, $service, true, $get, $post);

        // Set default vars
        $vars = ['hostname' => $service_fields->proxmox_hostname];
        $this->view->set(
            'isos',
            $this->getServerISOs($service_fields->proxmox_node, $package->meta->storage, $module_row)
        );

        $this->view->set('client_id', $service->client_id);
        $this->view->set('service_id', $service->id);

        $this->view->base_uri = $this->base_uri;
        $this->view->set(
            'server',
            $this->getServerState(
                $service_fields->proxmox_vserver_id,
                $service_fields->proxmox_type,
                $service_fields->proxmox_node,
                $module_row
            )
        );

        $this->view->set('type', $service_fields->proxmox_type);
        $this->view->set('vars', (object)$vars);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        $this->view->set('view', $this->view->view);
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);
        return $this->view;
    }

    /**
     * Client LXC Reinstall tab
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @param array $get Any GET parameters
     * @param array $post Any POST parameters
     * @param array $files Any FILES parameters
     * @return string The string representing the contents of this tab
     */
    public function tabClientLXCReinstall($package, $service, array $get = null, array $post = null, array $files = null)
    {
        $view = $this->lxcReinstall($package, $service, $get, $post, true);
        return $view->fetch();
    }

    /**
     * Builds the data for the admin/client lxc reinstall tabs
     * @see Proxmox::tabClientLXCReinstall()
     *
     * @param stdClass $package A stdClass object representing the current package
     * @param stdClass $service A stdClass object representing the current service
     * @return View A template view to be rendered
     */
    private function lxcReinstall($package, $service, $get = null, $post = null, $client = false)
    {
        $template = ($client ? 'tab_client_lxcreinstall' : '');
        $this->view = new View($template, 'default');
        // Load the helpers required for this view
        Loader::loadHelpers($this, ['Form', 'Html']);

        // Get the service fields
        $service_fields = $this->serviceFieldsToObject($service->fields);
        $module_row = $this->getModuleRow($package->module_row);

        // Perform the actions
        $this->actionsTab($package, $service, true, $get, $post);

        // Set default vars
        $vars = ['hostname' => $service_fields->proxmox_hostname];

        $this->view->set('client_id', $service->client_id);
        $this->view->set('service_id', $service->id);

        $this->view->base_uri = $this->base_uri;
        $this->view->set(
            'server',
            $this->getServerState(
                $service_fields->proxmox_vserver_id,
                $service_fields->proxmox_type,
                $service_fields->proxmox_node,
                $module_row
            )
        );
        $this->view->set(
            'templates',
            $this->getServerTemplates($service_fields->proxmox_node, $package->meta->template_storage, $module_row)
        );

        $this->view->set('type', $service_fields->proxmox_type);
        $this->view->set('vars', (object)$vars);
        $this->view->set('service_fields', $this->serviceFieldsToObject($service->fields));

        $this->view->set('view', $this->view->view);
        $this->view->setDefaultView('components' . DS . 'modules' . DS . 'proxmox' . DS);
        return $this->view;
    }

    /**
     * Converts bytes to a string representation including the type
     *
     * @param int $bytes The number of bytes
     * @return string A formatted amount including the type (B, KB, MB, GB)
     */
    private function convertBytesToString($bytes)
    {
        $step = 1024;
        $unit = 'B';

        if (($value = number_format($bytes/($step*$step*$step), 2)) >= 1) {
            $unit = 'GB';
        } elseif (($value = number_format($bytes/($step*$step), 2)) >= 1) {
            $unit = 'MB';
        } elseif (($value = number_format($bytes/($step), 2)) >= 1) {
            $unit = 'KB';
        } else {
            $value = $bytes;
        }

        return Language::_('Proxmox.!bytes.value', true, $value, $unit);
    }

    private function convertSecondsToDays($seconds)
    {

        $days = floor($seconds / 86400);
        $hours= floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return Language::_('Proxmox.!uptime.value', true, $days, $hours, $minutes);
    }

    /**
     * Initializes the API and returns an instance of that object with the given $host, $user, and $pass set
     *
     * @param string $user The of the Proxmox user
     * @param string $password The password to the Proxmox server
     * @param string $host The host to the Proxmox server
     * @param string $port The Proxmox server port number
     * @return ProxmoxApi The ProxmoxApi instance
     */
    private function getApi($user, $password, $host, $port)
    {
        Loader::load(dirname(__FILE__) . DS . 'apis' . DS . 'proxmox_api.php');

        return new ProxmoxApi($user, $password, $host, $port);
    }

    /**
     * Retrieves a list of the virtual server state fields, e.g. mem, cpu, status
     *
     * @param int $server_id The virtual server ID
     * @param string $node The node of the server
     * @param stdClass $module_row A stdClass object representing a single server
     * @param bool $fetch_graphs True to fetch graphs, false otherwise
     * @return stdClass An stdClass object representing the server state fields
     */
    private function getServerState($server_id, $type, $node, $module_row, $fetch_graphs = false)
    {
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );

        // Load the nodes API
        $api->loadCommand('proxmox_vserver');
        $response = null;

        try {
            $server_api = new ProxmoxVserver($api);
            $params = ['vmid' => $server_id, 'type' => $type, 'node' => $node];

            $this->log($module_row->meta->host . '|vserver-status', serialize($params), 'input', true);
            $response = $this->parseResponse($server_api->status($params), $module_row);
        } catch (Exception $e) {
            // Nothing to do
        }

        $data = [];
        if ($response && $response->data) {
            $percent_values = ['mem' => 'memory', 'disk' => 'space'];

            $temp_data = (array)$response->data;
            foreach ($temp_data as $key => $value) {
                $data[$key] = $value;

                // Set CPU to percent usage
                if ($key == 'cpu') {
                    $data['cpu_formatted'] = round(($value*100), 2);
                }
                if ($key == 'uptime'){
                    $data['uptime_formatted'] = $this->convertSecondsToDays($value);
                }
                elseif (array_key_exists($key, $percent_values)) {
                    // Set mem and disk stats
                    if (isset($temp_data['max' . $key])) {
                        $data[$key . '_formatted']['used_' . $percent_values[$key] . '_formatted']
                            = $this->convertBytesToString($value);
                        $data[$key . '_formatted']['total_' . $percent_values[$key] . '_formatted']
                            = $this->convertBytesToString($temp_data['max' . $key]);
                        $data[$key . '_formatted']['percent_used_' . $percent_values[$key]]
                            = round(($value/($temp_data['max' . $key] == 0 ? 1 : $temp_data['max' . $key])*100), 2);
                    }
                }
            }
        }

        return (object)$data;
    }

    /**
     * Retrieves a list of the virtual server ISOs
     *
     * @param string $node The node of the server
     * @param string $storage The storage location to search
     * @param stdClass $module_row A stdClass object representing a single server
     * @return stdClass An stdClass object representing the server ISOs
     */
    private function getServerISOs($node, $storage, $module_row)
    {
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );

        // Load the nodes API
        $api->loadCommand('proxmox_nodes');
        $response = null;

        try {
            $node_api = new ProxmoxNodes($api);
            $params = ['node' => $node, 'storage' => $storage];

            $this->log($module_row->meta->host . '|vserver-isos', serialize($params), 'input', true);
            $response = $this->parseResponse($node_api->storageContent($params), $module_row);
        } catch (Exception $e) {
            // Nothing to do
        }

        $result = [];
        foreach ($response->data as $file) {
            if ($file->content == 'iso') {
                $result[$file->volid] = $file->volid;
            }
        }
        return $result;
    }

    /**
     * Retrieves a list of the virtual server templates
     *
     * @param string $node The node of the server
     * @param string $storage The storage location to search
     * @param stdClass $module_row A stdClass object representing a single server
     * @return stdClass An stdClass object representing the server templates
     */
    private function getServerTemplates($node, $storage, $module_row)
    {
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );

        // Load the nodes API
        $api->loadCommand('proxmox_nodes');
        $response = null;

        try {
            $node_api = new ProxmoxNodes($api);
            $params = ['node' => $node, 'storage' => $storage];

            $this->log($module_row->meta->host . '|vserver-templates', serialize($params), 'input', true);
            $response = $this->parseResponse($node_api->storageContent($params), $module_row);
        } catch (Exception $e) {
            // Nothing to do
        }

        $result = [];
        foreach ($response->data ?? [] as $file) {
            if (($file->content ?? '') == 'vztmpl') {
                $template_parts = explode('/', $file->volid);
                $template = $template_parts[count($template_parts) - 1];
                $result[$template] = $template;
            }
        }
        return $result;
    }

    /**
     * Retrieves a list of node statistics, e.g. freememory, freedisk, etc.
     *
     * @param mixed $node_id The node ID or name
     * @param stdClass $module_row A stdClass object representing a single server
     * @return stdClass An stdClass object representing the node statistics
     */
    private function getNodeStatistics($node_id, $module_row)
    {
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );

        // Load the nodes API
        $api->loadCommand('proxmox_nodes');
        $response = null;

        try {
            $nodes_api = new ProxmoxNodes($api);
            $params = ['nodeid' => $node_id];

            $this->log($module_row->meta->host . '|node-statistics', serialize($params), 'input', true);
            $response = $this->parseResponse($nodes_api->statistics($params), $module_row);
        } catch (Exception $e) {
            // Nothing to do
        }

        // Return the nodes
        if ($response && $response->status == 'success') {
            return $response;
        }

        return new stdClass();
    }

    /**
     * Retrieves a list of storage options
     *
     * @param string $node The node of the server
     * @param stdClass $module_row A stdClass object representing a single server
     * @param string $content_type The content type by which to filter storage options
     * @return stdClass An stdClass object representing the server storage options
     */
    private function getNodeStorage($node, $module_row, $content_type = null)
    {
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );

        // Load the nodes API
        $api->loadCommand('proxmox_nodes');
        $response = null;

        try {
            $node_api = new ProxmoxNodes($api);
            $params = ['node' => $node];

            $this->log($module_row->meta->host . '|nodes-storage', serialize($params), 'input', true);
            $response = $this->parseResponse($node_api->storageList($params), $module_row);
        } catch (Exception $e) {
            // Nothing to do
        }

        $result = [];
        foreach ($response->data as $storage) {
            if ($content_type === null || strpos($storage->content ?? '', $content_type) !== false) {
                $result[$storage->storage] = $storage->storage;
            }
        }
        return $result;
    }

    /**
     * Fetches the nodes available for the Proxmox server of the given type
     *
     * @param string $type The type of server (i.e. lxc, qemu)
     * @param stdClass $module_row A stdClass object representing a single server
     * @return array A list of nodes
     */
    private function getNodes($type, $module_row)
    {
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );

        // Load the nodes API
        $api->loadCommand('proxmox_nodes');
        $response = null;

        try {
            $nodes_api = new ProxmoxNodes($api);
            $params = ['type' => $type];

            $this->log($module_row->meta->host . '|listnodes', serialize($params), 'input', true);
            $response = $this->parseResponse($nodes_api->getList($params), $module_row);
        } catch (Exception $e) {
            // Nothing to do
            return [];
        }

        // Return the nodes
        if ($response && $response->status == 'success') {
            return $response->data;
        }

        return [];
    }

    /**
     * Fetches the nodes available for the Proxmox server of the given type
     *
     * @param stdClass $module_row A stdClass object representing a single server
     * @return array A list of nodes
     */
    private function getStorage($module_row)
    {
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );

        // Load the nodes API
        $api->loadCommand('proxmox_storage');
        $response = null;

        try {
            $storage_api = new ProxmoxStorage($api);

            $this->log($module_row->meta->host . '|liststorage', serialize([]), 'input', true);
            $response = $this->parseResponse($storage_api->getList(), $module_row);
        } catch (Exception $e) {
            // Nothing to do
            return [];
        }

        // Return the nodes
        if ($response && $response->status == 'success') {
            return $response->data;
        }

        return [];
    }

    /**
     * Returns an array of service fields to set for the service using the given input
     *
     * @param array $vars An array of key/value input pairs
     * @param stdClass $package A stdClass object representing the package for the service
     * @return array An array of key/value pairs representing service fields
     */
    private function getFieldsFromInput(array $vars, $package)
    {
        // Determine which node to assign the service to
        $module_row = $this->getModuleRow($package->module_row);
        $node = $this->chooseNode($package->meta->nodes, $module_row);

        $fields = [
            'type' => $package->meta->type,
            'template' => ($package->meta->template_storage ?? null) . ':vztmpl/' . ($package->meta->default_template ?? null),
            'template_storage' => $package->meta->template_storage ?? null,
            'node' => $node,
            'hostname' => isset($vars['proxmox_hostname']) ? strtolower($vars['proxmox_hostname']) : null,
            'userid' => isset($vars['client_id']) ? 'vmuser' . $vars['client_id'] : null,
            'password'=> !empty($vars['password']) ? $vars['password'] : $this->generatePassword(),
            'memory' => $package->meta->memory ?? 0,
            'swap' => $package->meta->swap ?? 0,
            'hdd' => $package->meta->hdd ?? 0,
            'sockets' => $package->meta->cpu ?? 0,
            'cpulimit' => $package->meta->cpulimit ?? 0,
            'cpuunits' => $package->meta->cpuunits ?? 0,
            'unprivileged' => $package->meta->unprivileged ?? null,
            'netspeed' => $package->meta->netspeed
        ];

        return $fields;
    }

    /**
     * Chooses the best node to assign a service onto based on the resources of available nodes
     *
     * @param array $nodes A list of nodes
     * @param stdClass $module_row An stdClass object representing the module row
     * @return string The name of the selected node
     */
    private function chooseNode(array $nodes, $module_row)
    {
        $node = '';

        if (count($nodes) == 1) {
            $node = $nodes[0];
        } else {
            $best_node = [
                'name' => '',
                'value' => 0
            ];

            // 1 MB in bytes
            $megabyte = 1048576;

            // Determine the best node
            foreach ($nodes as $node_id) {
                // Fetch node stats
                $node_stats = $this->getNodeStatistics($node_id, $module_row);

                // Use disk/memory to compare which node has the most available resources
                $disk = (float)$node_stats->data->rootfs->free;
                $memory = (float)$node_stats->data->memory->free;
                $total_value = $disk + $memory;

                // If any one of the resources is too low, skip this node when we have another
                if ($best_node['value'] != 0 && ($disk <= $megabyte || $memory <= $megabyte)) {
                    continue;
                }

                // Set the best node to the one with the largest combined free resources
                if ($total_value > $best_node['value']) {
                    $best_node = ['name' => $node_id, 'value' => $total_value];
                }
            }

            $node = $best_node['name'];
        }

        return $node;
    }

    /**
     * Creates a new Proxmox Client. May set Input::errors() on error.
     *
     * @param int $client_id The client ID
     * @param string $userid The client's userid
     * @param stdClass $module_row The server module row
     * @return array An key/value array including the client's userid and password.
     *  If the client already exists in Proxmox, then the password returned is null
     */
    private function createClient($client_id, $userid, $module_row)
    {
        // Get the API
        $api = $this->getApi(
            $module_row->meta->user,
            $module_row->meta->password,
            $module_row->meta->host,
            $module_row->meta->port
        );
        $api->loadCommand('proxmox_client');

        $client_fields = ['userid' => $userid, 'password' => null];
        $response = false;

        // Check if a client exists
        try {
            // Load up the Client API
            $client_api = new ProxmoxClient($api);
            $params = ['userid' => $client_fields['userid']];

            // Check the client exists
            $this->log($module_row->meta->host . '|client-checkexists', serialize($params), 'input', true);
            $response = $this->parseResponse($client_api->checkExists($params), $module_row, true);
        } catch (Exception $e) {
            // Internal Error
            $this->Input->setErrors(['api' => ['internal' => Language::_('Proxmox.!error.api.internal', true)]]);
            return $client_fields;
        }

        // Client does not exist, attempt to create one
        if ($response && !$response->data) {
            $response = false;

            // Fetch the client to set additional client fields
            Loader::loadModels($this, ['Clients']);
            $client_params = [];
            if (($client = $this->Clients->get($client_id, false))) {
                $client_params = [
                    'email' => $client->email,
                    //'company' => $client->company,
                    'firstname' => $client->first_name,
                    'lastname' => $client->last_name
                ];
            }

            try {
                // Generate a client password
                $client_fields['password'] = $this->generatePassword();

                $params = array_merge($client_fields, $client_params);
                $masked_params = $params;
                $masked_params['password'] = '***';

                // Create a client
                $this->log($module_row->meta->host . '|client-create', serialize($masked_params), 'input', true);
                $response = $this->parseResponse($client_api->create($params), $module_row, true);
            } catch (Exception $e) {
                // Internal Error
                $this->Input->setErrors(['api' => ['internal' => Language::_('Proxmox.!error.api.internal', true)]]);
            }

            // Since creating the client returns null, assume it was successful unless we cannot retrieve the client
            if (!$this->Input->errors()) {
                try {
                    // Check the client exists
                    $params = ['userid' => $client_fields['userid']];

                    $this->log($module_row->meta->host . '|client-checkexists', serialize($params), 'input', true);
                    $response = $this->parseResponse($client_api->checkExists($params), $module_row, true);
                } catch (Exception $e) {
                    // Nothing to do
                }
            }

            // Error, client account could not be created
            if (!$response || $response->status != 'success') {
                $this->Input->setErrors(
                    ['create_client' => ['failed' => Language::_('Proxmox.!error.create_client.failed', true)]]
                );
            }
        }

        return $client_fields;
    }

    /**
     * Parses the response from Proxmox into an stdClass object
     *
     * @param ProxmoxResponse $response The response from the API
     * @param stdClass $module_row A stdClass object representing a
     *  single server (optional, required when Module::getModuleRow() is unavailable)
     * @param bool $ignore_error Ignores any response error and returns the response anyway;
     *  useful when a response is expected to fail (e.g. check client exists) (optional, default false)
     * @return stdClass A stdClass object representing the response, void if the response was an error
     */
    private function parseResponse(ProxmoxResponse $response, $module_row = null, $ignore_error = false)
    {
        Loader::loadHelpers($this, ['Html']);

        // Set the module row
        if (!$module_row) {
            $module_row = $this->getModuleRow();
        }

        $success = false;

        switch ($response->status()) {
            case 'success':
                $success = true;
                break;
            case 'error':
                $success = false;

                // Ignore generating the error
                if ($ignore_error) {
                    break;
                }

                $errors = $response->errors();
                if (!empty($errors)) {
                    $raw_output = $errors['server'];
                    $this->Input->setErrors(['api' => ['internal' => Language::_('Proxmox.!error.api.internal', true)]]);
                } else {
                    $this->Input->setErrors(
                        ['api' => ['response' => $this->Html->safe(Language::_('Proxmox.!error.api.unknown', true))]]
                    );
                }

                break;
            default:
                // Invalid response
                $success = false;

                // Ignore generating the error
                if ($ignore_error) {
                    break;
                }

                $this->Input->setErrors(['api' => ['internal' => Language::_('Proxmox.!error.api.internal', true)]]);
                break;
        }

        // Replace sensitive fields
        $masked_params = ['password', 'rootpassword', 'vncpassword', 'consolepassword'];
        $output = $response->response();
        $raw_output = $response->raw();

        foreach ($masked_params as $masked_param) {
            if (property_exists($output, $masked_param)) {
                $raw_output = preg_replace(
                    '/<' . $masked_param . ">(.*)<\/" . $masked_param . '>/',
                    '<' . $masked_param . '>***</' . $masked_param . '>',
                    $raw_output
                );
            }
        }

        // Log the response
        $this->log($module_row->meta->host, $raw_output, 'output', $success);

        if (!$success && !$ignore_error) {
            return;
        }

        return $output;
    }

    /**
     * Sets the assigned and available groups. Manipulates the $available_groups by reference.
     *
     * @param array $available_groups A key/value list of available groups
     * @param array $assigned_groups A numerically-indexed array of assigned groups
     */
    private function assignGroups(&$available_groups, $assigned_groups)
    {
        // Remove available groups if they are assigned
        foreach ($assigned_groups as $key => $value) {
            if (isset($available_groups[$value])) {
                unset($available_groups[$value]);
            }
        }
    }

    /**
     * Retrieves the module row given the server or server group
     *
     * @param string $module_row The module row ID
     * @param string $module_group The module group (optional, default "")
     * @return mixed An stdClass object representing the module row, or null if it could not be determined
     */
    private function getModuleRowByServer($module_row, $module_group = '')
    {
        // Fetch the module row available for this package
        $row = null;
        if (empty($module_group)) {
            if ($module_row > 0) {
                $row = $this->getModuleRow($module_row);
            } else {
                $rows = $this->getModuleRows();
                if (isset($rows[0])) {
                    $row = $rows[0];
                }
                unset($rows);
            }
        } else {
            // Fetch the 1st server from the list of servers in the selected group
            $rows = $this->getModuleRows($module_group);

            if (isset($rows[0])) {
                $row = $rows[0];
            }
            unset($rows);
        }

        return $row;
    }

    /**
     * Retrieves a list of server types and their language
     *
     * @return array A list of server types and their language
     */
    private function getTypes()
    {
        return [
            'lxc' => Language::_('Proxmox.types.lxc', true),
            'qemu' => Language::_('Proxmox.types.kvm', true)
        ];
    }

    private function setUnprivileged()
    {
        return [
            '0' => Language::_('Proxmox.unprivileged.disabled', true),
            '1' => Language::_('Proxmox.unprivileged.enabled', true)
        ];
    }

    /**
     * Generates a password for Proxmox client accounts
     *
     * @param int $min_chars The minimum number of characters to generate in the password (optional, default 12)
     * @param int $max_chars The maximum number of characters to generate in the password (optional, default 12)
     * @return string A randomly-generated password
     */
    private function generatePassword($min_chars = 12, $max_chars = 12)
    {
        $password = '';

        // Add 8-random characters
        $chars = ['a','b','c','d','e','f','g','h','i','j','k','l','m','n','o','p','q','r','s','t',
        'u','v','w','x','y','z','A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R',
        'S','T','U','V','W','X','Y','Z','0','1','2','3','4','5','6','7','8','9', '!', '@', '#', '$', '%',
        '^', '&', '*', '(', ')'];
        $count = count($chars) - 1;
        $num_chars = (int)abs($min_chars == $max_chars ? $min_chars : mt_rand($min_chars, $max_chars));

        for ($i=0; $i<$num_chars; $i++) {
            $password = $chars[mt_rand(0, $count)] . $password;
        }

        return $password;
    }

    /**
     * Retrieves a list of rules for validating adding/editing a module row
     *
     * @param array $vars A list of input vars
     * @return array A list of rules
     */
    private function getRowRules(array &$vars)
    {
        return [
            'server_name' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Proxmox.!error.server_name.empty', true)
                ]
            ],
            'user' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Proxmox.!error.user.empty', true)
                ]
            ],
            'password' => [
                'empty' => [
                    'rule' => 'isEmpty',
                    'negate' => true,
                    'message' => Language::_('Proxmox.!error.password.empty', true)
                ]
            ],
            'host' => [
                'format' => [
                    'rule' => [[$this, 'validateHostName']],
                    'message' => Language::_('Proxmox.!error.host.format', true)
                ]
            ],
            'port' => [
                'format' => [
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('Proxmox.!error.port.format', true)
                ]
            ],
            'vmid' => [
                'format' => [
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('Proxmox.!error.vmid.format', true)
                ]
            ],
        ];
    }

    /**
     * Retrieves a list of rules for validating adding/editing a package
     *
     * @param array $vars A list of input vars
     * @return array A list of rules
     */
    private function getPackageRules(array $vars = null)
    {
        $rules = [
            'meta[type]' => [
                'valid' => [
                    'rule' => ['in_array', array_keys($this->getTypes())],
                    'message' => Language::_('Proxmox.!error.meta[type].valid', true)
                ]
            ],
            'meta[nodes]' => [
                'empty' => [
                    'rule' => [[$this, 'validateNodeSet']],
                    'message' => Language::_('Proxmox.!error.meta[nodes].empty', true),
                ]
            ],
            'meta[memory]' => [
                'format' => [
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('Proxmox.!error.meta[memory].format', true)
                ]
            ],
            'meta[cpu]' => [
                'format' => [
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('Proxmox.!error.meta[cpu].format', true)
                ]
            ],
            'meta[hdd]' => [
                'format' => [
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('Proxmox.!error.meta[hdd].format', true)
                ]
            ],
            'meta[netspeed]' => [
                'format' => [
                    'rule' => ['matches', '/^[0-9]+$/'],
                    'message' => Language::_('Proxmox.!error.meta[netspeed].format', true)
                ]
            ],
            'meta[storage]' => [
                'format' => [
                    'rule' => ['matches', '/^[0-9a-zA-Z\-]+$/'],
                    'message' => Language::_('Proxmox.!error.meta[storage].format', true)
                ]
            ]
        ];

        if ($vars['meta']['type'] != 'qemu') {
            $rules = [
                'meta[default_template]' => [
                    'format' => [
                       'rule' => ['matches', '#^[0-9a-zA-Z.:/_-]+$#'],
                       'message' => Language::_('Proxmox.!error.meta[default_template].format', true)
                    ]
                ],
                'meta[template_storage]' => [
                    'format' => [
                        'rule' => ['matches', '#^[0-9a-zA-Z.:/_-]+$#'],
                        'message' => Language::_('Proxmox.!error.meta[template_storage].format', true)
                    ]
                ],
            ];
        }

        return $rules;
    }

    /**
     * Validates that at least one node was selected when adding a package
     *
     * @param array $nodes A list of node names
     * @return bool True if at least one node was given, false otherwise
     */
    public function validateNodeSet($nodes)
    {
        // Require at least one node
        return (isset($nodes[0]) && !empty($nodes[0]));
    }

    /**
     * Validates that the given hostname is valid
     *
     * @param string $host_name The host name to validate
     * @return bool True if the hostname is valid, false otherwise
     */
    public function validateHostName($host_name)
    {
        $validator = new Server();
        return $validator->isDomain($host_name) || $validator->isIp($host_name);
    }
}