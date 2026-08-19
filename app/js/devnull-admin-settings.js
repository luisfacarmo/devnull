(function() {
	'use strict';
	var form = document.getElementById('devnull-settings-form');
	if (!form) return;

	var msg = document.getElementById('devnull-save-msg');
	var toggleBtn = document.getElementById('devnull-toggle-token');
	var tokenInput = document.getElementById('devnull-daemon-token');

	toggleBtn.addEventListener('click', function() {
		if (tokenInput.type === 'password') {
			tokenInput.type = 'text';
			toggleBtn.textContent = 'Hide';
		} else {
			tokenInput.type = 'password';
			toggleBtn.textContent = 'Show';
		}
	});

	form.addEventListener('submit', function(e) {
		e.preventDefault();
		msg.textContent = 'Saving...';
		msg.style.color = '';

		var daemonUrl = document.getElementById('devnull-daemon-url').value.trim();
		if (daemonUrl && !/^https?:\/\/.+/.test(daemonUrl)) {
			msg.textContent = 'Invalid daemon URL format.';
			msg.style.color = 'red';
			return;
		}

		var settings = {
			daemon_url: daemonUrl,
			daemon_token: tokenInput.value,
			default_mount_user: document.getElementById('devnull-default-user').value.trim(),
			auto_mount_on_plug: document.getElementById('devnull-auto-mount').checked ? 'true' : 'false',
			auto_ingest_on_mount: document.getElementById('devnull-auto-ingest').checked ? 'true' : 'false',
			auto_classify_on_scan: document.getElementById('devnull-auto-classify').checked ? 'true' : 'false',
			notify_on_mount: document.getElementById('devnull-notify-mount').checked ? 'true' : 'false',
			notify_on_ingest_complete: document.getElementById('devnull-notify-ingest').checked ? 'true' : 'false'
		};

		// Build OCS URL using OC.generateUrl (available in NC34)
		var ocsPath = '/ocs/v2.php/apps/devnull/api/v1/settings';

		var xhr = new XMLHttpRequest();
		xhr.open('PUT', ocsPath);
		xhr.setRequestHeader('Content-Type', 'application/json');
		xhr.setRequestHeader('OCS-APIRequest', 'true');
		xhr.setRequestHeader('requesttoken', OC.requestToken);
		xhr.onload = function() {
			if (xhr.status >= 200 && xhr.status < 300) {
				msg.textContent = 'Saved successfully.';
				msg.style.color = 'green';
			} else {
				msg.textContent = 'Error: ' + xhr.status + ' ' + xhr.responseText.substring(0, 100);
				msg.style.color = 'red';
			}
			setTimeout(function() { msg.textContent = ''; }, 5000);
		};
		xhr.onerror = function() {
			msg.textContent = 'Network error.';
			msg.style.color = 'red';
		};
		xhr.send(JSON.stringify({settings: settings}));
	});
})();
