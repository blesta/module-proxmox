<?php
// Actions
$lang['Proxmox.!actions.boot'] = "Boot";
$lang['Proxmox.!actions.shutdown'] = "Shutdown";
$lang['Proxmox.!actions.mount_iso'] = "Mount ISO";
$lang['Proxmox.!actions.unmount_iso'] = "Unmount ISO";
$lang['Proxmox.!actions.reinstall'] = "Reinstall";
$lang['Proxmox.!actions.hostname'] = "Change Hostname";
$lang['Proxmox.!actions.password'] = "Change Password";


// Errors
$lang['Proxmox.!error.server_name.empty'] = "Please enter a server label.";
$lang['Proxmox.!error.user.empty'] = "Please enter a user.";
$lang['Proxmox.!error.password.empty'] = "Please enter a password.";
$lang['Proxmox.!error.host.format'] = "The hostname appears to be invalid.";
$lang['Proxmox.!error.port.format'] = "Please enter a valid port number.";
$lang['Proxmox.!error.vmid.format'] = "Please enter a valid VMID.";
$lang['Proxmox.!error.storage.format'] = "Please enter a valid storage.";
$lang['Proxmox.!error.default_template.format'] = "Please enter a valid default template.";
$lang['Proxmox.!error.ips.empty'] = "Please enter IPs.";

$lang['Proxmox.!error.meta[type].valid'] = "Please select a valid virtualization type.";
$lang['Proxmox.!error.meta[nodes].empty'] = "Please select at least one node.";
$lang['Proxmox.!error.meta[memory].format'] = "Please set RAM.";
$lang['Proxmox.!error.meta[cpu].format'] = "Please set vCPU count.";
$lang['Proxmox.!error.meta[hdd].format'] = "Please set HDD size.";
$lang['Proxmox.!error.meta[netspeed].format'] = "Please set NetSpeed.";
$lang['Proxmox.!error.meta[set_template].format'] = "Please set whether to select a template or to allow clients to set a template.";
$lang['Proxmox.!error.meta[template].empty'] = "Please select a template.";

$lang['Proxmox.!error.api.internal'] = "An internal error occurred, or the server did not respond to the request.";

$lang['Proxmox.!error.proxmox_hostname.format'] = "The hostname appears to be invalid.";
$lang['Proxmox.!error.proxmox_template.valid'] = "Please select a valid template.";

$lang['Proxmox.!error.create_client.failed'] = "An internal error occurred and the client account could not be created.";

$lang['Proxmox.!error.api.template.valid'] = "The selected template is invalid.";
$lang['Proxmox.!error.api.confirm.valid'] = "You must acknowledge that you understand the reinstall action in order to perform the template reinstallation.";

$lang['Proxmox.!error.proxmox_root_password.length'] = "The root password must be at least 6 characters in length.";
$lang['Proxmox.!error.proxmox_root_password.matches'] = "The root passwords do not match.";


// Common
$lang['Proxmox.please_select'] = "-- Please Select --";
$lang['Proxmox.!bytes.value'] = "%1\$s%2\$s"; // %1$s is a number value, %2$s is the unit of that value (i.e., one of B, KB, MB, GB)
$lang['Proxmox.!percent.used'] = "%1\$s%"; // %1$s is a percentage value

// Basics
$lang['Proxmox.name'] = "Proxmox";
$lang['Proxmox.module_row'] = "Proxmox Master Server";
$lang['Proxmox.module_row_plural'] = "Servers";
$lang['Proxmox.module_group'] = "Proxmox Master Group";


// Module management
$lang['Proxmox.add_module_row'] = "Add Server";
$lang['Proxmox.add_module_group'] = "Add Server Group";
$lang['Proxmox.manage.module_rows_title'] = "Proxmox Master Servers";
$lang['Proxmox.manage.module_groups_title'] = "Proxmox Master Server Groups";
$lang['Proxmox.manage.module_rows_heading.server_label'] = "Server Label";
$lang['Proxmox.manage.module_rows_heading.host'] = "Hostname";
$lang['Proxmox.manage.module_rows_heading.options'] = "Options";
$lang['Proxmox.manage.module_groups_heading.name'] = "Group Name";
$lang['Proxmox.manage.module_groups_heading.servers'] = "Server Count";
$lang['Proxmox.manage.module_groups_heading.options'] = "Options";
$lang['Proxmox.manage.module_rows.edit'] = "Edit";
$lang['Proxmox.manage.module_groups.edit'] = "Edit";
$lang['Proxmox.manage.module_rows.delete'] = "Delete";
$lang['Proxmox.manage.module_groups.delete'] = "Delete";
$lang['Proxmox.manage.module_rows.confirm_delete'] = "Are you sure you want to delete this server?";
$lang['Proxmox.manage.module_groups.confirm_delete'] = "Are you sure you want to delete this server group?";
$lang['Proxmox.manage.module_rows_no_results'] = "There are no servers.";
$lang['Proxmox.manage.module_groups_no_results'] = "There are no server groups.";

$lang['Proxmox.order_options.first'] = "First non-full server";


// Module row meta data
$lang['Proxmox.row_meta.server_name'] = "Server Label";
$lang['Proxmox.row_meta.user'] = "User";
$lang['Proxmox.row_meta.password'] = "Password";
$lang['Proxmox.row_meta.host'] = "Hostname";
$lang['Proxmox.row_meta.port'] = "SSL Port Number";
$lang['Proxmox.row_meta.vmid'] = "Next VMID (do not change unless necessary!)";
$lang['Proxmox.row_meta.storage'] = "Default storage name (e.g. local)";
$lang['Proxmox.row_meta.default_storage'] = "local";
$lang['Proxmox.row_meta.default_vmid'] = "200";
$lang['Proxmox.row_meta.default_port'] = "8006";
$lang['Proxmox.row_meta.default_template'] = "Default template";
$lang['Proxmox.row_meta.ips'] = "IPs (one per line)";


// Server types
$lang['Proxmox.types.openvz'] = "OpenVZ";
$lang['Proxmox.types.kvm'] = "KVM";


// Add module row
$lang['Proxmox.add_row.box_title'] = "Add Proxmox Server";
$lang['Proxmox.add_row.basic_title'] = "Basic Settings";
$lang['Proxmox.add_row.add_btn'] = "Add Server";


// Edit module row
$lang['Proxmox.edit_row.box_title'] = "Edit Proxmox Server";
$lang['Proxmox.edit_row.basic_title'] = "Basic Settings";
$lang['Proxmox.edit_row.add_btn'] = "Update Server";


// Package fields
$lang['Proxmox.package_fields.type'] = "Type";
$lang['Proxmox.package_fields.hdd'] = "Storage (GB)";
$lang['Proxmox.package_fields.memory'] = "RAM (MB)";
$lang['Proxmox.package_fields.cpu'] = "vCPU count";
$lang['Proxmox.package_fields.netspeed'] = "Network speed rate (MByte/s)";

$lang['Proxmox.package_fields.assigned_nodes'] = "Assigned Nodes";
$lang['Proxmox.package_fields.available_nodes'] = "Available Nodes";


// Service fields
$lang['Proxmox.service_field.proxmox_hostname'] = "Hostname";
$lang['Proxmox.service_field.proxmox_template'] = "Template";


// Service Info fields
$lang['Proxmox.service_info.proxmox_ip'] = "Primary IP Address";
$lang['Proxmox.service_info.proxmox_username'] = "Username";


// Tabs
$lang['Proxmox.tab_actions'] = "Server Actions";
$lang['Proxmox.tab_stats'] = "Stats";
$lang['Proxmox.tab_console'] = "Console";


// Actions Tab
$lang['Proxmox.tab_actions.heading_actions'] = "Actions";

$lang['Proxmox.tab_actions.status_running'] = "Online";
$lang['Proxmox.tab_actions.status_stopped'] = "Offline";
$lang['Proxmox.tab_actions.status_disabled'] = "Disabled";
$lang['Proxmox.tab_actions.server_status'] = "Server Status";

$lang['Proxmox.tab_actions.heading_mount_iso'] = "Mount ISO";
$lang['Proxmox.tab_actions.heading_reinstall'] = "Reinstall";
$lang['Proxmox.tab_actions.field_iso'] = "Image";
$lang['Proxmox.tab_actions.field_mount_submit'] = "Mount";
$lang['Proxmox.tab_actions.field_template'] = "Template";
$lang['Proxmox.tab_actions.field_password'] = "Root Password";
$lang['Proxmox.tab_actions.field_reinstall_submit'] = "Reinstall";


// Client Actions Tab
$lang['Proxmox.tab_client_actions.heading_actions'] = "Server Actions";
$lang['Proxmox.tab_client_actions.heading_server_status'] = "Server Status";

$lang['Proxmox.tab_client_actions.status_running'] = "Online";
$lang['Proxmox.tab_client_actions.status_stopped'] = "Offline";
$lang['Proxmox.tab_client_actions.status_disabled'] = "Disabled";

$lang['Proxmox.tab_client_actions.heading_mount_iso'] = "Mount ISO";
$lang['Proxmox.tab_client_actions.heading_reinstall'] = "Reinstall";
$lang['Proxmox.tab_client_actions.field_iso'] = "Image";
$lang['Proxmox.tab_client_actions.field_mount_submit'] = "Mount";
$lang['Proxmox.tab_client_actions.field_template'] = "Template";
$lang['Proxmox.tab_client_actions.field_password'] = "Root password";
$lang['Proxmox.tab_client_actions.field_reinstall_submit'] = "Reinstall";


// Stats Tab
$lang['Proxmox.tab_stats.heading_stats'] = "Statistics";

$lang['Proxmox.tab_stats.memory'] = "Memory:";
$lang['Proxmox.tab_stats.memory_stats'] = "%1\$s/%2\$s"; // %1$s is the memory used, %2$s is the total memory available
$lang['Proxmox.tab_stats.memory_percent_available'] = "(%1\$s%%)"; // %1$s is the percentage of memory used. You MUST use two % signs to represent a single percent (i.e. %%)

$lang['Proxmox.tab_stats.heading_graphs'] = "Graphs";


// Client Stats Tab
$lang['Proxmox.tab_client_stats.heading_stats'] = "Statistics";

$lang['Proxmox.tab_client_stats.heading_graphs'] = "Graphs";


// Console Tab
$lang['Proxmox.tab_console.heading_console'] = "Console";

$lang['Proxmox.tab_console.vnc_ip'] = "VNC Host:";
$lang['Proxmox.tab_console.vnc_port'] = "VNC Port:";
$lang['Proxmox.tab_console.vnc_user'] = "VNC Username:";
$lang['Proxmox.tab_console.vnc_password'] = "VNC Password:";


// Client Console Tab
$lang['Proxmox.tab_client_console.heading_console'] = "Console";

$lang['Proxmox.tab_client_console.vnc_ip'] = "VNC Host";
$lang['Proxmox.tab_client_console.vnc_port'] = "VNC Port";
$lang['Proxmox.tab_client_console.vnc_user'] = "VNC Username";
$lang['Proxmox.tab_client_console.vnc_password'] = "VNC Password";
?>