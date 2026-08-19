<?php
/**
 * DevNull Admin Settings — Pure PHP form.
 *
 * Reads current values from app config (passed by AdminSettings.php).
 * Saves via OCS API (SettingsController) using devnull-admin-settings.js.
 * CSRF handled by OCS framework + OC.requestToken.
 */
use OCP\Util;
Util::addScript('devnull', 'devnull-admin-settings');
?>
<div id="devnull-admin-settings" class="section">
	<h2>DevNull — Configuration</h2>
	<p class="settings-hint">
		Configure the DevNull daemon connection and automation behaviors.
	</p>

	<form id="devnull-settings-form">
		<h3>Daemon Connection</h3>
		<p>
			<label for="devnull-daemon-url">Daemon URL</label><br>
			<input type="url" id="devnull-daemon-url" name="daemon_url"
				   value="<?php p($_['daemon_url']); ?>"
				   placeholder="http://127.0.0.1:9876" style="width:300px;">
		</p>
		<p>
			<label for="devnull-daemon-token">Daemon Token</label><br>
			<input type="password" id="devnull-daemon-token" name="daemon_token"
				   value="<?php p($_['daemon_token']); ?>"
				   style="width:300px;">
			<button type="button" id="devnull-toggle-token" class="button">Show</button>
		</p>
		<p>
			<label for="devnull-default-user">Default mount user</label><br>
			<input type="text" id="devnull-default-user" name="default_mount_user"
				   value="<?php p($_['default_mount_user']); ?>"
				   style="width:200px;">
		</p>

		<h3>Automation</h3>
		<p>
			<input type="checkbox" id="devnull-auto-mount" name="auto_mount_on_plug"
				   class="checkbox" <?php if ($_['auto_mount_on_plug'] === 'true') print_unescaped('checked'); ?>>
			<label for="devnull-auto-mount">Auto-mount disks when plugged in</label>
		</p>
		<p>
			<input type="checkbox" id="devnull-auto-ingest" name="auto_ingest_on_mount"
				   class="checkbox" <?php if ($_['auto_ingest_on_mount'] === 'true') print_unescaped('checked'); ?>>
			<label for="devnull-auto-ingest">Auto-start ingest pipeline on mount</label>
		</p>
		<p>
			<input type="checkbox" id="devnull-auto-classify" name="auto_classify_on_scan"
				   class="checkbox" <?php if ($_['auto_classify_on_scan'] === 'true') print_unescaped('checked'); ?>>
			<label for="devnull-auto-classify">Auto-classify files after scan</label>
		</p>

		<h3>Notifications</h3>
		<p>
			<input type="checkbox" id="devnull-notify-mount" name="notify_on_mount"
				   class="checkbox" <?php if ($_['notify_on_mount'] === 'true') print_unescaped('checked'); ?>>
			<label for="devnull-notify-mount">Notify when a disk is mounted</label>
		</p>
		<p>
			<input type="checkbox" id="devnull-notify-ingest" name="notify_on_ingest_complete"
				   class="checkbox" <?php if ($_['notify_on_ingest_complete'] === 'true') print_unescaped('checked'); ?>>
			<label for="devnull-notify-ingest">Notify when ingest completes</label>
		</p>

		<p style="margin-top:20px;">
			<button type="submit" class="button primary" id="devnull-save-btn">Save</button>
			<span id="devnull-save-msg" style="margin-left:10px;"></span>
		</p>
	</form>
</div>
