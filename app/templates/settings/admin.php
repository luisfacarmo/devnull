<?php
/**
 * DevNull Admin Settings — Automation Rules
 *
 * This template renders the admin settings form.
 * Values are loaded from app config and saved via OCS API.
 */

use OCP\Util;

Util::addScript('devnull', 'devnull-admin-settings');

?>

<div id="devnull-admin-settings" class="section">
	<h2>DevNull — Automation</h2>
	<p class="settings-hint">Configure automatic behaviors when external disks are connected.</p>

	<div id="devnull-settings-app" data-settings="<?php echo htmlspecialchars(json_encode([
		'auto_mount_on_plug' => $_['auto_mount_on_plug'],
		'auto_ingest_on_mount' => $_['auto_ingest_on_mount'],
		'auto_classify_on_scan' => $_['auto_classify_on_scan'],
		'notify_on_mount' => $_['notify_on_mount'],
		'notify_on_ingest_complete' => $_['notify_on_ingest_complete'],
		'default_mount_user' => $_['default_mount_user'],
		'daemon_url' => $_['daemon_url'],
		'daemon_token' => $_['daemon_token'],
	])); ?>"></div>
</div>
