# Proxmox Module

[![Build Status](https://travis-ci.org/blesta/module-proxmox.svg?branch=master)](https://travis-ci.org/blesta/module-proxmox) [![Coverage Status](https://coveralls.io/repos/github/blesta/module-proxmox/badge.svg?branch=master)](https://coveralls.io/github/blesta/module-proxmox?branch=master)

This is a module for Blesta that integrates with [Proxmox](https://pve.proxmox.com/wiki/Main_Page).

## Install the Module

1. You can install the module via composer:

    ```
    composer require blesta/proxmox
    ```

2. OR upload the source code to a /components/modules/proxmox/ directory within
your Blesta installation path.

    For example:

    ```
    /var/www/html/blesta/components/modules/proxmox/
    ```

3. Log in to your admin Blesta account and navigate to
> Settings > Modules

4. Find the Proxmox module and click the "Install" button to install it

5. You're done!

### Blesta Compatibility

|Blesta Version|Module Version|
|--------------|--------------|
|< v4.2.0|v2.2.0|
|>= v4.2.0|v2.3.0+|
|>= v4.9.0|v2.7.0+|
|>= v5.0.0|v2.9.0+|
