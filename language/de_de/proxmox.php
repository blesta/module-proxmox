<?php
// Actions
$lang['Proxmox.!actions.boot'] = 'Boot';
$lang['Proxmox.!actions.shutdown'] = 'Herunterfahren';
$lang['Proxmox.!actions.mount_iso'] = 'ISO einlesen';
$lang['Proxmox.!actions.unmount_iso'] = 'ISO auswerfen';
$lang['Proxmox.!actions.reinstall'] = 'Neuinstallieren';
$lang['Proxmox.!actions.hostname'] = 'Hostname ändern';
$lang['Proxmox.!actions.password'] = 'Passwort ändern';


// Errors
$lang['Proxmox.!error.server_name.empty'] = 'Bitte gebe einen Servernamen ein.';
$lang['Proxmox.!error.user.empty'] = 'Bitte gebe einen Benutzer ein.';
$lang['Proxmox.!error.password.empty'] = 'Bitte gebe ein Passwort ein.';
$lang['Proxmox.!error.host.format'] = 'Dieser Hostname scheint ungültig zu sein.';
$lang['Proxmox.!error.port.format'] = 'Bitte gebe eine gültige Port ein.';
$lang['Proxmox.!error.vmid.format'] = 'Bitte gebe eine gültige VMID ein.';
$lang['Proxmox.!error.storage.format'] = 'Bitte gebe einen gültigen Speicher an.';
$lang['Proxmox.!error.default_template.format'] = 'Bitte gebe ein gültiges Template an.';
$lang['Proxmox.!error.ips.empty'] = 'Bitte gebe die IP-Adressen an.';

$lang['Proxmox.!error.meta[type].valid'] = 'Bitte wähle eine gültige virtualisierungs-Art.';
$lang['Proxmox.!error.meta[nodes].empty'] = 'Bitte wähle mindenstens eine Node aus.';
$lang['Proxmox.!error.meta[memory].format'] = 'Bitte setze den RAM.';
$lang['Proxmox.!error.meta[cpu].format'] = 'Bitte setze Anzahl an vCPUs.';
$lang['Proxmox.!error.meta[hdd].format'] = 'Bitte gebe die HDD Größe an.';
$lang['Proxmox.!error.meta[netspeed].format'] = 'Bitte setze das NetSpeed.';
$lang['Proxmox.!error.meta[set_template].format'] = 'Bitte setze, ob man ein Template auswählen muss, oder clients erlaufen ein template zu setzen.';
$lang['Proxmox.!error.meta[template].empty'] = 'Bitte wähle ein Template aus.';

$lang['Proxmox.!error.api.unknown'] = 'Ein unbekannter Fehler ist aufgetreten. Bitte versuche es später nochmal.';
$lang['Proxmox.!error.api.internal'] = 'Es ist ein interner Fehler aufgetreten, oder der Server hat nicht auf die Anfrage geantwortet.';

$lang['Proxmox.!error.proxmox_hostname.format'] = 'Der Hostname scheint ungültig zu sein.';
$lang['Proxmox.!error.proxmox_template.valid'] = 'Bitte wählen Sie ein gültiges Template aus.';

$lang['Proxmox.!error.create_client.failed'] = 'Ein interner Fehler ist aufgetreten und das Kundenkonto konnte nicht erstellt werden.';

$lang['Proxmox.!error.api.template.valid'] = 'Das augewählte Template ist ungültig';
$lang['Proxmox.!error.api.confirm.valid'] = 'Sie müssen bestätigen, dass Sie die Neuinstallationsaktion verstanden haben, um die Neuinstallation des Templates durchführen zu können..';

$lang['Proxmox.!error.proxmox_root_password.length'] = 'Das root-Passwort muss mindestens 6 Zeichen lang sein.';
$lang['Proxmox.!error.proxmox_root_password.matches'] = 'Die root-Passwörter stimmen nicht überein.';


// Common
$lang['Proxmox.please_select'] = '-- Bitte wähle aus --';
$lang['Proxmox.!bytes.value'] = '%1$s%2$s'; // %1$s is a number value, %2$s is the unit of that value (i.e., one of B, KB, MB, GB)
$lang['Proxmox.!percent.used'] = '%1$s%'; // %1$s is a percentage value

// Basics
$lang['Proxmox.name'] = 'Proxmox';
$lang['Proxmox.description'] = 'Proxmox Virtual Environment ist eine Open-Source-Server-Virtualisierungsumgebung. Es ist eine Debian-basierte Linux-Distribution mit einem modifizierten Ubuntu LTS-Kernel und ermöglicht die Bereitstellung und Verwaltung von virtuellen Maschinen und Containern.';
$lang['Proxmox.module_row'] = 'Proxmox Master Server';
$lang['Proxmox.module_row_plural'] = 'Server';
$lang['Proxmox.module_group'] = 'Proxmox Master Gruppe';


// Module management
$lang['Proxmox.add_module_row'] = 'Server hinzufügen';
$lang['Proxmox.add_module_group'] = 'Servergruppe hinzufügen';
$lang['Proxmox.manage.module_rows_title'] = 'Proxmox Master Server';
$lang['Proxmox.manage.module_groups_title'] = 'Proxmox Master Server Gruppen';
$lang['Proxmox.manage.module_rows_heading.server_label'] = 'Server Label';
$lang['Proxmox.manage.module_rows_heading.host'] = 'Hostname';
$lang['Proxmox.manage.module_rows_heading.options'] = 'Optionen';
$lang['Proxmox.manage.module_groups_heading.name'] = 'Gruppenname';
$lang['Proxmox.manage.module_groups_heading.servers'] = 'Serveranzahl';
$lang['Proxmox.manage.module_groups_heading.options'] = 'Optionen';
$lang['Proxmox.manage.module_rows.edit'] = 'Bearbeiten';
$lang['Proxmox.manage.module_groups.edit'] = 'Bearbeiten';
$lang['Proxmox.manage.module_rows.delete'] = 'Löschen';
$lang['Proxmox.manage.module_groups.delete'] = 'Löschen';
$lang['Proxmox.manage.module_rows.confirm_delete'] = 'Sind Sie sicher, dass Sie diesen Server löschen möchten?';
$lang['Proxmox.manage.module_groups.confirm_delete'] = 'Sind Sie sicher, dass Sie diese Servergruppe löschen möchten?';
$lang['Proxmox.manage.module_rows_no_results'] = 'Es existieren keine Server.';
$lang['Proxmox.manage.module_groups_no_results'] = 'Es existieren keine Servergruppen';

$lang['Proxmox.order_options.first'] = 'Erster nicht-voller Server';


// Module row meta data
$lang['Proxmox.row_meta.server_name'] = 'Server Label';
$lang['Proxmox.row_meta.user'] = 'Benutzername';
$lang['Proxmox.row_meta.password'] = 'Passwort';
$lang['Proxmox.row_meta.host'] = 'Hostname';
$lang['Proxmox.row_meta.port'] = 'SSL Portnummer';
$lang['Proxmox.row_meta.vmid'] = 'Nächste VMID (nicht verändern, wenn es nicht notwendig ist!)';
$lang['Proxmox.row_meta.storage'] = 'Standard Speichername (z.B. local)';
$lang['Proxmox.row_meta.gateway'] = 'Standardgateway';
$lang['Proxmox.row_meta.default_storage'] = 'local';
$lang['Proxmox.row_meta.template_storage'] = 'Standard Template (LXC) Speicherplatz (e.g. local/local-lvm)';
$lang['Proxmox.row_meta.default_template_storage'] = 'local';
$lang['Proxmox.row_meta.default_vmid'] = '200';
$lang['Proxmox.row_meta.default_port'] = '8006';
$lang['Proxmox.row_meta.default_template'] = 'Standard Template';
$lang['Proxmox.row_meta.ips'] = 'IP-Adressen (eine pro Zeile)';


// Server types
$lang['Proxmox.types.lxc'] = 'LXC';
$lang['Proxmox.types.kvm'] = 'KVM';


// Set Unprivileged
$lang['Proxmox.unprivileged.disabled'] = 'Deaktiviert';
$lang['Proxmox.unprivileged.enabled'] = 'Aktiviert';


// Add module row
$lang['Proxmox.add_row.box_title'] = 'Proxmox Server hinzufügen';
$lang['Proxmox.add_row.basic_title'] = 'Grundeinstellungen';
$lang['Proxmox.add_row.add_btn'] = 'Server hinzufügen';


// Edit module row
$lang['Proxmox.edit_row.box_title'] = 'Proxmox Server bearbeiten.';
$lang['Proxmox.edit_row.basic_title'] = 'Grundeinstellungen';
$lang['Proxmox.edit_row.add_btn'] = 'Server aktualisieren';


// Package fields
$lang['Proxmox.package_fields.type'] = 'Typ';
$lang['Proxmox.package_fields.hdd'] = 'Speicher (GB)';
$lang['Proxmox.package_fields.memory'] = 'RAM (MB)';
$lang['Proxmox.package_fields.cpu'] = 'vCPU Anzahl';
$lang['Proxmox.package_fields.netspeed'] = 'Netzwerkgeschwindigkeit (MByte/s)';
$lang['Proxmox.package_fields.cpulimit'] = 'CPU Limit';
$lang['Proxmox.package_fields.cpuunits'] = 'CPU Einheiten';
$lang['Proxmox.package_fields.swap'] = 'SWAP (MB)';
$lang['Proxmox.package_fields.unprivileged'] = 'Unprivilegiert';

$lang['Proxmox.package_fields.assigned_nodes'] = 'Zugewiesense Nodes';
$lang['Proxmox.package_fields.available_nodes'] = 'Verfügbare Nodes';


// Service fields
$lang['Proxmox.service_field.proxmox_hostname'] = 'Hostname';
$lang['Proxmox.service_field.proxmox_password'] = 'Passwort';
$lang['Proxmox.service_field.proxmox_template'] = 'Template';


// Service Info fields
$lang['Proxmox.service_info.proxmox_ip'] = 'Primäre IP Addresss';
$lang['Proxmox.service_info.proxmox_username'] = 'Benutzername';
$lang['Proxmox.service_info.proxmox_password'] = 'Passwort';


// Tabs
$lang['Proxmox.tab_actions'] = 'Server Aktionen';
$lang['Proxmox.tab_stats'] = 'Statistik';
$lang['Proxmox.tab_console'] = 'Konsole';


// Actions Tab
$lang['Proxmox.tab_actions.heading_actions'] = 'Aktionen';

$lang['Proxmox.tab_actions.status_running'] = 'Online';
$lang['Proxmox.tab_actions.status_stopped'] = 'Offline';
$lang['Proxmox.tab_actions.status_disabled'] = 'Deaktiviert';
$lang['Proxmox.tab_actions.server_status'] = 'Server Status';

$lang['Proxmox.tab_actions.heading_mount_iso'] = 'ISO einlesen';
$lang['Proxmox.tab_actions.heading_reinstall'] = 'Neuinstallieren';
$lang['Proxmox.tab_actions.field_iso'] = 'Image';
$lang['Proxmox.tab_actions.field_mount_submit'] = 'Einlesen';
$lang['Proxmox.tab_actions.field_template'] = 'Template';
$lang['Proxmox.tab_actions.field_password'] = 'Root Passwort';
$lang['Proxmox.tab_actions.field_reinstall_submit'] = 'Neuinstallieren';


// Client Actions Tab
$lang['Proxmox.tab_client_actions.heading_actions'] = 'Server Aktionen';
$lang['Proxmox.tab_client_actions.heading_server_status'] = 'Server Status';

$lang['Proxmox.tab_client_actions.status_running'] = 'Online';
$lang['Proxmox.tab_client_actions.status_stopped'] = 'Offline';
$lang['Proxmox.tab_client_actions.status_disabled'] = 'Deaktiviert';

$lang['Proxmox.tab_client_actions.heading_mount_iso'] = 'ISO einlesen';
$lang['Proxmox.tab_client_actions.heading_reinstall'] = 'Neuinstallieren';
$lang['Proxmox.tab_client_actions.field_iso'] = 'Image';
$lang['Proxmox.tab_client_actions.field_mount_submit'] = 'Einlesen';
$lang['Proxmox.tab_client_actions.field_template'] = 'Template';
$lang['Proxmox.tab_client_actions.field_password'] = 'Root Passwort';
$lang['Proxmox.tab_client_actions.field_reinstall_submit'] = 'Neuinstallieren';


// Stats Tab
$lang['Proxmox.tab_stats.heading_stats'] = 'Statistik';

$lang['Proxmox.tab_stats.memory'] = 'Speicher:';
$lang['Proxmox.tab_stats.memory_stats'] = '%1$s/%2$s'; // %1$s is the memory used, %2$s is the total memory available
$lang['Proxmox.tab_stats.memory_percent_available'] = '(%1$s%%)'; // %1$s is the percentage of memory used. You MUST use two % signs to represent a single percent (i.e. %%)

$lang['Proxmox.tab_stats.heading_graphs'] = 'Graphen';


// Client Stats Tab
$lang['Proxmox.tab_client_stats.heading_stats'] = 'Statistik';

$lang['Proxmox.tab_client_stats.heading_graphs'] = 'Graphen';


// Console Tab
$lang['Proxmox.tab_console.heading_console'] = 'Konsole';

$lang['Proxmox.tab_console.vnc_ip'] = 'VNC Host:';
$lang['Proxmox.tab_console.vnc_port'] = 'VNC Port:';
$lang['Proxmox.tab_console.vnc_user'] = 'VNC Benutzername:';
$lang['Proxmox.tab_console.vnc_password'] = 'VNC Passwort:';


// Client Console Tab
$lang['Proxmox.tab_client_console.heading_console'] = 'Konsole';

$lang['Proxmox.tab_client_console.vnc_ip'] = 'VNC Host';
$lang['Proxmox.tab_client_console.vnc_port'] = 'VNC Port';
$lang['Proxmox.tab_client_console.vnc_user'] = 'VNC Benutzername';
$lang['Proxmox.tab_client_console.vnc_password'] = 'VNC Passwort';