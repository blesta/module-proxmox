<?php

Configure::set('Proxmox.email_templates', [
    'en_us' => [
        'lang' => 'en_us',
        'text' => 'Thank you for ordering your VPS, details below:

Hostname: {service.proxmox_hostname}

Proxmox Panel: https://{module.host}:{module.port}
Username: {service.proxmox_username}
Password: {service.proxmox_password}

{service.proxmox_memory} MB RAM, {service.proxmox_hdd} GB Disk, {service.proxmox_cpu} CPU Cores',
        'html' => '<p>Thank you for ordering your VPS, details below:</p>
<p>Hostname: {service.proxmox_hostname}</p>
<p>Proxmox Panel: https://{module.host}:{module.port}<br />Username: {service.proxmox_username}<br />Password: {service.proxmox_password}</p>
<p>{service.proxmox_memory} MB RAM, {service.proxmox_hdd} GB Disk, {service.proxmox_cpu} CPU Cores</p>'
    ]
]);
