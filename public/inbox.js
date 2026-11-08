/**
 * Admin inbox for AI Chat & Live Chat.
 *
 * Lists visitor threads, shows a transcript, lets an agent reply and change
 * the thread status (take over from AI, hand back, close).
 */
(function () {
	'use strict';

	var cfg = window.BGLCInbox || {};
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

	var root = document.querySelector('.bglc-inbox');
	if (!root || !rest) {
		return;
	}

	var threadsBox = root.querySelector('[data-region="threads"]');
	var titleEl = root.querySelector('[data-region="title"]');
	var actionsEl = root.querySelector('[data-region="actions"]');
	var messagesEl = root.querySelector('[data-region="messages"]');
	var replyForm = root.querySelector('[data-region="reply"]');
	var replyText = root.querySelector('[data-region="replytext"]');

	var status = 'all';
	var current = 0;

	function api(path, body) {
		var opts = { method: body ? 'POST' : 'GET', headers: headers };
		if (body) {
			opts.body = JSON.stringify(body);
		}
		return fetch(rest + path, opts).then(function (r) {
			return r.json();
		});
	}

	function label(thread) {
		if (thread.name) {
			return thread.name + (thread.email ? ' \u00b7 ' + thread.email : '');
		}
		return '#' + thread.id;
	}

	function statusBadge(s) {
		if (s === 'human') {
			return '<span class="bglc-badge bglc-badge-human">needs you</span>';
		}
		if (s === 'closed') {
			return '<span class="bglc-badge bglc-badge-closed">closed</span>';
		}
		return '<span class="bglc-badge bglc-badge-bot">AI</span>';
	}

	function loadThreads() {
		threadsBox.innerHTML = '<div class="bglc-list-note">' + (strings.loading || 'Loading…') + '</div>';
		api('threads' + qsep + 'status=' + encodeURIComponent(status)).then(function (res) {
			threadsBox.innerHTML = '';
			var rows = res.threads || [];
			if (!rows.length) {
				threadsBox.innerHTML = '<div class="bglc-list-note">' + (strings.noThreads || 'No conversations yet.') + '</div>';
				return;
			}
			rows.forEach(function (t) {
				var btn = document.createElement('button');
				btn.type = 'button';
				btn.className = 'bglc-thread-item' + (parseInt(t.id, 10) === current ? ' is-active' : '');
				btn.innerHTML =
					'<span class="bglc-thread-name">' + escapeHtml(label(t)) + '</span>' +
					statusBadge(t.status);
				btn.addEventListener('click', function () {
					open(t.id, t.status);
				 Array.prototype.forEach.call(threadsBox.children, function (c) {
					c.classList.remove('is-active');
				 });
				 btn.classList.add('is-active');
				});
				threadsBox.appendChild(btn);
			});
		});
	}

	function open(threadId, threadStatus) {
		current = parseInt(threadId, 10);
		api('messages' + qsep + 'thread=' + current).then(function (res) {
			var t = res.thread || {};
			titleEl.textContent = label(t) || ('#' + current);
			renderActions(t.status || threadStatus);
			replyForm.hidden = (t.status === 'closed');
			messagesEl.innerHTML = '';
			(res.messages || []).forEach(function (m) {
				messagesEl.appendChild(bubble(m.role, m.content));
			});
			messagesEl.scrollTop = messagesEl.scrollHeight;
		});
	}

	function renderActions(s) {
		actionsEl.hidden = false;
		actionsEl.innerHTML = '';
		if (s !== 'human') {
			addAction(strings.takeOver || 'Take over from AI', 'human');
		} else {
			addAction(strings.handBack || 'Hand back to AI', 'bot');
		}
		if (s !== 'closed') {
			addAction(strings.close || 'Close', 'closed');
		}
	}

	function addAction(text, to) {
		var b = document.createElement('button');
		b.type = 'button';
		b.className = 'button button-small';
		b.textContent = text;
		b.addEventListener('click', function () {
			api('status', { thread: current, status: to }).then(function () {
				loadThreads();
				open(current, to);
			});
		});
		actionsEl.appendChild(b);
	}

	function bubble(role, content) {
		var div = document.createElement('div');
		var who = role === 'visitor' ? (strings.visitor || 'Visitor') : (role === 'agent' ? (strings.you || 'You') : (strings.bot || 'AI'));
		div.className = 'bglc-m bglc-m-' + role;
		div.innerHTML = '<strong>' + escapeHtml(who) + '</strong><p>' + escapeHtml(content) + '</p>';
		return div;
	}

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;');
	}

	Array.prototype.forEach.call(root.querySelectorAll('.bglc-filter'), function (f) {
		f.addEventListener('click', function () {
			root.querySelectorAll('.bglc-filter').forEach(function (x) {
				x.classList.remove('is-active');
			});
			f.classList.add('is-active');
			status = f.getAttribute('data-status');
			loadThreads();
		});
	});

	replyForm.addEventListener('submit', function (e) {
		e.preventDefault();
		var msg = (replyText.value || '').trim();
		if (!msg || !current) {
			return;
		}
		replyText.value = '';
		api('reply', { thread: current, message: msg }).then(function () {
			messagesEl.appendChild(bubble('agent', msg));
			messagesEl.scrollTop = messagesEl.scrollHeight;
		});
	});

	loadThreads();
})();
