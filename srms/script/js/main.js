
(function () {

	"use strict";

	var treeviewMenu = $('.app-menu');

	// Toggle Sidebar
	$('[data-toggle="sidebar"]').click(function(event) {
		event.preventDefault();
		$('.app').toggleClass('sidenav-toggled');
	});

	// Activate sidebar treeview toggle
	$("[data-toggle='treeview']").click(function(event) {
		event.preventDefault();
		// Allow multiple treeviews to stay open
		$(this).parent().toggleClass('is-expanded');
	});

	// Global footer
	if (!document.getElementById('appFooter')) {
		var footer = document.createElement('footer');
		footer.id = 'appFooter';
		footer.className = 'app-footer';
		footer.textContent = '@2026 powered by ofx_steve';
		var content = document.querySelector('.app-content');
		if (content && content.parentNode) {
			content.parentNode.appendChild(footer);
		} else {
			document.body.appendChild(footer);
		}
	}

	// AI + Feedback widget
	if (!document.getElementById('aiWidget')) {
		var fab = document.createElement('div');
		fab.className = 'ai-fab';
		fab.id = 'aiWidget';
		fab.innerHTML = '<i class="bi bi-chat-dots"></i>';
		document.body.appendChild(fab);

		var panel = document.createElement('div');
		panel.className = 'ai-panel';
		panel.id = 'aiPanel';
		panel.innerHTML = '' +
			'<div class="ai-panel-header">' +
				'<div>' +
					'<div class="ai-panel-title">Edu Assist</div>' +
					'<div class="ai-panel-subtitle">Reports, fees, attendance, and feedback.</div>' +
				'</div>' +
				'<button type="button" class="btn btn-sm btn-light" id="aiClose">×</button>' +
			'</div>' +
			'<div class="ai-panel-body">' +
				'<div class="ai-chat-shell">' +
					'<div class="ai-suggestions" id="aiSuggestions">' +
						'<button type="button" class="ai-chip" data-ai-prompt="Show my report">Show my report</button>' +
						'<button type="button" class="ai-chip" data-ai-prompt="What is my fee balance?">Fee balance</button>' +
						'<button type="button" class="ai-chip" data-ai-prompt="How is my attendance?">Attendance</button>' +
					'</div>' +
					'<div class="ai-chat" id="aiChat"></div>' +
					'<div class="ai-status" id="aiStatus">Ready</div>' +
					'<label class="form-label mb-1">Mode</label>' +
					'<select class="form-control" id="aiMode">' +
					'<option value="ai">Ask Edu</option>' +
					'<option value="feedback">Send Feedback</option>' +
					'</select>' +
					'<textarea class="form-control" id="aiMessage" rows="3" placeholder="Type your question or feedback..."></textarea>' +
					'<div class="ai-actions">' +
						'<button class="btn btn-primary btn-sm" id="aiSend">Send</button>' +
					'</div>' +
				'</div>' +
			'</div>';
		document.body.appendChild(panel);

		var storageKey = 'srms-ai-widget-history:anon';
		var chatBox = null;
		var statusBox = null;
		var messageBox = null;

		function safeParse(jsonText) {
			try {
				return JSON.parse(jsonText || '[]');
			} catch (error) {
				return [];
			}
		}

		function loadHistory() {
			return safeParse(window.localStorage.getItem(storageKey));
		}

		function saveHistory(history) {
			window.localStorage.setItem(storageKey, JSON.stringify(history.slice(-40)));
		}

		function renderEmptyState() {
			if (!chatBox) return;
			chatBox.innerHTML = '<div class="ai-chat-empty"><div><strong>Edu Assist</strong><br>Ask about reports, fees, attendance, or use feedback mode.</div></div>';
		}

		function appendMessage(role, text) {
			if (!chatBox) return;
			var emptyState = chatBox.querySelector('.ai-chat-empty');
			if (emptyState) {
				emptyState.remove();
			}
			var message = document.createElement('div');
			message.className = 'ai-message ' + role;
			message.textContent = text;
			chatBox.appendChild(message);
			chatBox.scrollTop = chatBox.scrollHeight;
		}

		function syncHistory() {
			if (!chatBox) return;
			chatBox.innerHTML = '';
			var history = loadHistory();
			if (!history.length) {
				renderEmptyState();
				return;
			}
			history.forEach(function (entry) {
				appendMessage(entry.role, entry.text);
			});
		}

		function pushHistory(role, text) {
			var history = loadHistory();
			history.push({ role: role, text: text });
			saveHistory(history);
		}

		function setStatus(text) {
			if (statusBox) {
				statusBox.textContent = text;
			}
		}

		function loadServerHistory() {
			return fetch('core/ai_feedback?action=history', { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(function (data) {
					if (data && data.ok) {
						var userKey = (data.user_key || 'anon').replace(/[^a-zA-Z0-9:_-]/g, '');
						storageKey = 'srms-ai-widget-history:' + userKey;
						var history = Array.isArray(data.history) ? data.history : [];
						if (history.length) {
							saveHistory(history);
						}
						return history;
					}
					return [];
				})
				.catch(function () {
					return [];
				});
		}

		function sendMessage() {
			var msg = (messageBox ? messageBox.value : '').trim();
			if (!msg) return;

			var mode = document.getElementById('aiMode').value;
			pushHistory('user', msg);
			appendMessage('user', msg);
			messageBox.value = '';

			var thinking = 'Edu is thinking...';
			appendMessage('thinking', thinking);
			setStatus(thinking);

			fetch('core/ai_feedback', {
				method: 'POST',
				headers: { 'Content-Type': 'application/json' },
				credentials: 'same-origin',
				body: JSON.stringify({ message: msg, category: mode })
			}).then(function (r) { return r.json(); }).then(function (data) {
				var currentThinking = chatBox.querySelector('.ai-message.thinking');
				if (currentThinking) {
					currentThinking.remove();
				}
				var reply = data && data.ok ? (data.response || 'Thanks. We received your message.') : (data && data.message ? data.message : 'Failed to send.');
				var role = (mode === 'ai' && data && data.ok) ? 'edu' : 'system';
				appendMessage(role, reply);
				pushHistory(role, reply);
				setStatus('Ready');
			}).catch(function () {
				var currentThinking = chatBox.querySelector('.ai-message.thinking');
				if (currentThinking) {
					currentThinking.remove();
				}
				appendMessage('system', 'Failed to send.');
				pushHistory('system', 'Failed to send.');
				setStatus('Ready');
			});
		}

		fab.addEventListener('click', function () {
			panel.classList.toggle('is-open');
			if (panel.classList.contains('is-open')) {
				setTimeout(function () {
					if (!chatBox) {
						chatBox = document.getElementById('aiChat');
						statusBox = document.getElementById('aiStatus');
						messageBox = document.getElementById('aiMessage');
						loadServerHistory().then(function () {
							syncHistory();
							if (!loadHistory().length) {
								pushHistory('edu', 'Hello. Ask me about reports, fees, attendance, or send feedback.');
								syncHistory();
							}
						});
					}
					if (messageBox) {
						messageBox.focus();
					}
				}, 0);
			}
		});
		document.addEventListener('click', function (e) {
			if (e.target && e.target.id === 'aiClose') {
				panel.classList.remove('is-open');
			}
		});

		document.addEventListener('click', function (e) {
			if (e.target && e.target.getAttribute('data-ai-prompt')) {
				var prompt = e.target.getAttribute('data-ai-prompt');
				if (messageBox) {
					messageBox.value = prompt;
					messageBox.focus();
				}
			}
		});

		document.addEventListener('click', function (e) {
			if (e.target && e.target.id === 'aiSend') {
				sendMessage();
			}
		});

		document.addEventListener('keydown', function (e) {
			if (panel.classList.contains('is-open') && e.key === 'Enter' && !e.shiftKey && document.activeElement === messageBox) {
				e.preventDefault();
				sendMessage();
			}
		});
	}

	function appCurrentPortal() {
		var path = (window.location.pathname || '').toLowerCase();
		if (path.indexOf('/teacher') !== -1) return 'teacher';
		if (path.indexOf('/student') !== -1) return 'student';
		if (path.indexOf('/parent') !== -1) return 'parent';
		if (path.indexOf('/accountant') !== -1) return 'accountant';
		if (path.indexOf('/bom') !== -1) return 'bom';
		if (path.indexOf('/admin') !== -1) return 'admin';
		return 'other';
	}

	function appEnsureSidebarFooter(portal) {
		if (portal === 'student' || document.querySelector('.app-sidebar__footer')) {
			return;
		}
		var sidebar = document.querySelector('.app-sidebar');
		if (!sidebar) return;

		var footer = document.createElement('div');
		footer.className = 'app-sidebar__footer';
		footer.innerHTML = '<a class="app-sidebar__footer-link" href="privacy" target="_blank"><i class="bi bi-shield-lock me-2"></i>Privacy Policy</a>' +
			'<a class="app-sidebar__footer-link" href="terms" target="_blank"><i class="bi bi-file-text me-2"></i>Terms & Conditions</a>';
		sidebar.appendChild(footer);
	}

	function appEnsureTeacherGuideMenu(portal) {
		if (portal !== 'teacher') return;
		if (document.querySelector('[data-system-guide="1"]') || document.querySelector('.app-sidebar a[href="teacher/how_system_works"]')) return;

		var menu = document.querySelector('.app-sidebar .app-menu');
		if (!menu) return;

		var isActive = (window.location.pathname || '').toLowerCase().indexOf('/teacher/how_system_works') !== -1;
		var item = document.createElement('li');
		item.innerHTML = '<a class="app-menu__item' + (isActive ? ' active' : '') + '" data-system-guide="1" href="teacher/how_system_works"><i class="app-menu__icon feather icon-help-circle"></i><span class="app-menu__label">How The System Works</span></a>';
		menu.appendChild(item);
	}

	var portal = appCurrentPortal();
	appEnsureSidebarFooter(portal);
	appEnsureTeacherGuideMenu(portal);

})();
