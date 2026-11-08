/**
 * Front-end widget: launcher and live chat.
 *
 * Chat talks to the plugin REST endpoints.
 */
(function () {
	'use strict';

	var cfg = window.BGLC || {};
	var rest = (cfg.restUrl || '').replace(/\/$/, '') + '/';
	// With plain permalinks rest is "index.php?rest_route=/bglc/v1/", so query
	// args must be joined with &, not ?.
	var qsep = rest.indexOf('?') >= 0 ? '&' : '?';
	var strings = cfg.strings || {};
	var headers = {
		'Content-Type': 'application/json',
		'X-BGLC-Nonce': cfg.nonce || '',
		'X-WP-Nonce': cfg.wpRestNonce || ''
	};

	function api(path, body) {
		var opts = { method: body ? 'POST' : 'GET', headers: headers };
		if (body) {
			opts.body = JSON.stringify(body);
		}
		return fetch(rest + path, opts).then(function (r) {
			return r.json();
		});
	}

	function region(el, name) {
		return el.querySelector('[data-region="' + name + '"]');
	}

	/* ------------------------------------------------------------------ */
	/* Chat                                                               */
	/* ------------------------------------------------------------------ */

	function bubble(log, role, text) {
		var div = document.createElement('div');
		div.className = 'bglc-msg bglc-msg-' + role;
		div.textContent = text;
		log.appendChild(div);
		log.scrollTop = log.scrollHeight;
		return div;
	}

	function initChat(panel) {
		var log = region(panel, 'log');
		var text = region(panel, 'text');
		var form = region(panel, 'form');
		var prechat = region(panel, 'prechat');
		var thread = parseInt(localStorage.getItem('bglc_thread') || '0', 10) || 0;
		var name = '';
		var email = '';
		var busy = false;

		if (prechat) {
			prechat.addEventListener('submit', function (e) {
				e.preventDefault();
				name = (region(prechat, 'name').value || '').trim();
				email = (region(prechat, 'email').value || '').trim();
				prechat.style.display = 'none';
				if (text) {
					text.focus();
				}
			});
		}

		// Resume the visitor's open thread if the server still has it.
		if (thread > 0) {
			api('history' + qsep + 'thread=' + encodeURIComponent(thread)).then(function (res) {
				if (res && res.messages) {
					log.innerHTML = '';
					res.messages.forEach(function (m) {
						bubble(log, m.role === 'visitor' ? 'user' : m.role, m.content);
					});
				}
			});
		}

		function send() {
			var prompt = (text.value || '').trim();
			if (!prompt || busy) {
				return;
			}
			busy = true;
			text.value = '';
			bubble(log, 'user', prompt);
			var pending = bubble(log, 'bot', strings.thinking || '…');
			pending.classList.add('bglc-pending');

			api('chat', {
				thread: thread,
				message: prompt,
				name: name,
				email: email
			}).then(function (res) {
				pending.classList.remove('bglc-pending');
				if (res.thread) {
					thread = res.thread;
					localStorage.setItem('bglc_thread', String(thread));
				}
				if (res.error) {
					pending.textContent = res.error;
					pending.classList.add('bglc-msg-error');
				} else if (res.reply) {
					pending.textContent = res.reply;
				} else if (res.pending) {
					pending.textContent = strings.pending || 'A team member will reply shortly.';
				} else {
					pending.remove();
				}
				busy = false;
				log.scrollTop = log.scrollHeight;
			}).catch(function () {
				pending.remove();
				busy = false;
			});
		}

		if (form) {
			form.addEventListener('submit', function (e) {
				e.preventDefault();
				send();
			});
		}
		if (text) {
			text.addEventListener('keydown', function (e) {
				if (e.key === 'Enter' && !e.shiftKey) {
					e.preventDefault();
					send();
				}
			});
		}
	}

	/* ------------------------------------------------------------------ */
	/* Panel wiring                                                       */
	/* ------------------------------------------------------------------ */

	function hydrate(panel) {
		if (panel.dataset.bglcReady) {
			return;
		}
		panel.dataset.bglcReady = '1';

		if (region(panel, 'log')) {
			initChat(panel);
		}
	}

	function init() {
		document.querySelectorAll('.bglc-panel').forEach(hydrate);

		var launcher = document.querySelector('.bglc-launcher');
		var popover = document.querySelector('.bglc-popover');
		if (launcher && popover) {
			launcher.addEventListener('click', function () {
				var open = !popover.hidden;
				popover.hidden = open;
				launcher.setAttribute('aria-expanded', open ? 'false' : 'true');
				if (!open) {
					hydrate(popover.querySelector('.bglc-panel'));
				}
			});
			var closeBtn = popover.querySelector('.bglc-close');
			if (closeBtn) {
				closeBtn.addEventListener('click', function () {
					popover.hidden = true;
					launcher.setAttribute('aria-expanded', 'false');
				});
			}
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
