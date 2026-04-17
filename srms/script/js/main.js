
(function () {

	"use strict";

	function appMainPortal() {
		return 'teacher';
	}

	function appIsElearningPage() {
		var path = (window.location.pathname || '').toLowerCase();
		return path.indexOf('/elearning') !== -1;
	}

	function initSiteLoader() {
		if (document.getElementById('siteLoader')) {
			return;
		}

		var overlay = document.createElement('div');
		overlay.id = 'siteLoader';
		overlay.className = 'site-loader';
		overlay.innerHTML = '<div class="site-loader__dots" aria-label="Loading"><span></span><span></span><span></span></div>';
		document.body.appendChild(overlay);

		var hideLoader = function () {
			overlay.classList.add('is-hidden');
			setTimeout(function () {
				if (overlay && overlay.parentNode) {
					overlay.parentNode.removeChild(overlay);
				}
			}, 320);
		};

		if (document.readyState === 'complete') {
			hideLoader();
			return;
		}

		window.addEventListener('load', hideLoader, { once: true });
	}

	function applyTopBanner(banner) {
		if (!banner || !banner.enabled || !banner.text) {
			return;
		}
		if (document.getElementById('appTopBanner')) {
			return;
		}

		var wrapper = document.createElement('div');
		wrapper.id = 'appTopBanner';
		wrapper.className = 'app-top-banner app-top-banner--' + (banner.type === 'warning' ? 'warning' : 'info');

		var track = document.createElement('div');
		track.className = 'app-top-banner__track';

		var text1 = document.createElement('span');
		text1.className = 'app-top-banner__text';
		text1.textContent = banner.text + '   •   ';

		var text2 = document.createElement('span');
		text2.className = 'app-top-banner__text';
		text2.textContent = banner.text + '   •   ';

		track.appendChild(text1);
		track.appendChild(text2);
		wrapper.appendChild(track);

		document.body.appendChild(wrapper);
		document.body.classList.add('has-top-banner');
	}

	function applyMaintenanceBadge(maintenance) {
		if (!maintenance || !maintenance.enabled) {
			return;
		}
		if (appIsElearningPage()) {
			return;
		}
		var currentPortal = appCurrentPortal();
		if (currentPortal !== 'admin' && currentPortal !== appMainPortal()) {
			return;
		}
		if (document.getElementById('appMaintenanceBadge')) {
			return;
		}

		var nav = document.querySelector('.app-header .app-nav');
		if (!nav) {
			return;
		}

		var item = document.createElement('li');
		item.className = 'app-nav__item app-maintenance-badge';
		item.id = 'appMaintenanceBadge';
		item.textContent = 'Maintenance Mode ON';
		nav.insertBefore(item, nav.firstChild);
	}

	function loadUiSettings() {
		var cacheKey = 'srms-ui-settings-v1';
		var cacheTtlMs = 5 * 60 * 1000;
		try {
			var cachedRaw = window.sessionStorage.getItem(cacheKey);
			if (cachedRaw) {
				var cached = JSON.parse(cachedRaw);
				if (cached && cached.saved_at && (Date.now() - Number(cached.saved_at) < cacheTtlMs) && cached.data) {
					if (cached.data.banner) {
						applyTopBanner(cached.data.banner);
					}
					if (cached.data.maintenance) {
						applyMaintenanceBadge(cached.data.maintenance);
					}
					return Promise.resolve(cached.data);
				}
			}
		} catch (e) {
			// Ignore cache failures and continue with network fetch.
		}

		return fetch('core/ui_settings.php', { credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (data) {
				if (data && data.ok) {
					try {
						window.sessionStorage.setItem(cacheKey, JSON.stringify({
							saved_at: Date.now(),
							data: data
						}));
					} catch (e) {
						// Ignore cache failures.
					}
					if (data.banner) {
						applyTopBanner(data.banner);
					}
					if (data.maintenance) {
						applyMaintenanceBadge(data.maintenance);
					}
				}
			})
			.catch(function () {
				return null;
			});
	}

	function appReadCookie(name) {
		var safeName = String(name || '').replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
		var match = document.cookie.match(new RegExp('(?:^|; )' + safeName + '=([^;]*)'));
		return match ? decodeURIComponent(match[1]) : '';
	}

	function appApplyImpersonationBanner() {
		if (document.getElementById('srmsImpersonationBanner')) {
			return;
		}

		var raw = appReadCookie('srms_impersonation');
		if (!raw) {
			return;
		}

		var payload = null;
		try {
			payload = JSON.parse(raw);
		} catch (e) {
			return;
		}

		if (!payload || !payload.active) {
			return;
		}

		var targetName = String(payload.target_name || 'User');
		var targetRole = String(payload.target_role || 'Account');
		var exitPath = String(payload.exit_path || 'admin/core/stop_impersonation').replace(/^\/+/, '');

		var banner = document.createElement('div');
		banner.id = 'srmsImpersonationBanner';
		banner.style.position = 'fixed';
		banner.style.top = '0';
		banner.style.left = '0';
		banner.style.right = '0';
		banner.style.zIndex = '3000';
		banner.style.background = '#8f1414';
		banner.style.color = '#fff';
		banner.style.padding = '10px 16px';
		banner.style.boxShadow = '0 10px 24px rgba(0,0,0,0.28)';

		var row = document.createElement('div');
		row.style.display = 'flex';
		row.style.alignItems = 'center';
		row.style.justifyContent = 'space-between';
		row.style.gap = '12px';
		row.style.flexWrap = 'wrap';

		var message = document.createElement('div');
		message.style.fontWeight = '700';
		message.textContent = 'Impersonation Active: You are browsing as ' + targetName + ' (' + targetRole + ').';

		var stopForm = document.createElement('form');
		stopForm.method = 'POST';
		stopForm.action = exitPath;
		stopForm.style.margin = '0';

		var stopBtn = document.createElement('button');
		stopBtn.type = 'submit';
		stopBtn.textContent = 'Stop Impersonation';
		stopBtn.style.border = 'none';
		stopBtn.style.background = '#ffffff';
		stopBtn.style.color = '#8f1414';
		stopBtn.style.fontWeight = '700';
		stopBtn.style.padding = '7px 12px';
		stopBtn.style.borderRadius = '6px';
		stopBtn.style.cursor = 'pointer';

		stopForm.appendChild(stopBtn);
		row.appendChild(message);
		row.appendChild(stopForm);
		banner.appendChild(row);
		document.body.appendChild(banner);

		var currentPadding = parseInt(window.getComputedStyle(document.body).paddingTop || '0', 10) || 0;
		document.body.style.paddingTop = (currentPadding + banner.offsetHeight) + 'px';
	}

	initSiteLoader();
	loadUiSettings();

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

		var roleNode = document.querySelector('.app-sidebar__user-designation');
		var roleText = roleNode ? String(roleNode.textContent || '').toLowerCase() : '';
		if (roleText.indexOf('teacher') !== -1) return 'teacher';
		if (roleText.indexOf('student') !== -1) return 'student';
		if (roleText.indexOf('parent') !== -1) return 'parent';
		if (roleText.indexOf('accountant') !== -1) return 'accountant';
		if (roleText.indexOf('board member') !== -1 || roleText.indexOf('bom') !== -1) return 'bom';
		if (roleText.indexOf('admin') !== -1 || roleText.indexOf('administrator') !== -1) return 'admin';

		var body = document.body;
		if (body) {
			if (body.classList.contains('teacher-page') || body.classList.contains('teacher')) return 'teacher';
			if (body.classList.contains('student-page') || body.classList.contains('student')) return 'student';
		}

		if (document.querySelector('.app-sidebar .app-menu a[href^="teacher/"]')) {
			return 'teacher';
		}

		return 'other';
	}

	function appEnsureSidebarFooter(portal) {
		if (document.querySelector('.app-sidebar__footer')) {
			return;
		}
		var sidebar = document.querySelector('.app-sidebar');
		if (!sidebar) return;
		var privacyHref = portal === 'student' ? 'student/privacy' : 'privacy';
		var termsHref = portal === 'student' ? 'student/terms' : 'terms';

		var footer = document.createElement('div');
		footer.className = 'app-sidebar__footer';
		footer.innerHTML = '<a class="app-sidebar__footer-link" href="' + privacyHref + '" target="_blank"><i class="bi bi-shield-lock me-2"></i>Privacy Policy</a>' +
			'<a class="app-sidebar__footer-link" href="' + termsHref + '" target="_blank"><i class="bi bi-file-text me-2"></i>Terms & Conditions</a>';
		sidebar.appendChild(footer);
	}

	function appEnsurePortalGuideMenu(portal) {
		if (portal === 'other') return;
		if (document.querySelector('[data-system-guide="1"]')) return;

		var menu = document.querySelector('.app-sidebar .app-menu');
		if (!menu) return;
		if (portal !== 'student' && document.querySelector('.app-sidebar a[href="how_system_works"]')) return;
		if (portal === 'student' && document.querySelector('.app-sidebar a[href="student/how_portal_works"]')) return;

		var guideHref = portal === 'student' ? 'student/how_portal_works' : 'how_system_works';
		var guideLabel = portal === 'student' ? 'How Student Portal Works' : 'How The System Works';
		var isActive = (window.location.pathname || '').toLowerCase().indexOf('/' + guideHref.toLowerCase()) !== -1;
		var item = document.createElement('li');
		item.innerHTML = '<a class="app-menu__item' + (isActive ? ' active' : '') + '" data-system-guide="1" href="' + guideHref + '"><i class="app-menu__icon feather icon-help-circle"></i><span class="app-menu__label">' + guideLabel + '</span></a>';
		menu.appendChild(item);
	}

	function appPublicWebsiteHref() {
		var path = window.location.pathname || '';
		var marker = '/script/';
		var i = path.toLowerCase().indexOf(marker);
		if (i !== -1) {
			return path.substring(0, i + marker.length) + 'school_main_website.php';
		}
		return 'school_main_website.php';
	}

	function appCoreEndpoint(fileName) {
		var base = document.baseURI || window.location.href;
		try {
			return new URL('core/' + fileName, base).toString();
		} catch (e) {
			return 'core/' + fileName;
		}
	}

	function appEnsureConnectivityBanner() {
		if (document.getElementById('appConnectivityBanner')) {
			return;
		}

		var bar = document.createElement('div');
		bar.id = 'appConnectivityBanner';
		bar.setAttribute('role', 'status');
		bar.style.position = 'fixed';
		bar.style.left = '12px';
		bar.style.right = '12px';
		bar.style.bottom = '12px';
		bar.style.zIndex = '1400';
		bar.style.background = '#b42318';
		bar.style.color = '#fff';
		bar.style.padding = '10px 14px';
		bar.style.borderRadius = '10px';
		bar.style.fontWeight = '700';
		bar.style.fontSize = '13px';
		bar.style.textAlign = 'center';
		bar.style.boxShadow = '0 8px 24px rgba(0, 0, 0, 0.25)';
		bar.style.display = 'none';
		bar.textContent = 'You are offline. Live updates are paused until internet reconnects.';

		document.body.appendChild(bar);

		function refreshState() {
			var online = (typeof navigator.onLine === 'boolean') ? navigator.onLine : true;
			bar.style.display = online ? 'none' : 'block';
		}

		window.addEventListener('online', refreshState);
		window.addEventListener('offline', refreshState);
		refreshState();
	}

	function appEnsurePublicWebsiteButton() {
		if (appCurrentPortal() !== 'other') {
			return;
		}

		if (document.getElementById('appPublicWebsiteButton')) {
			return;
		}

		var link = document.createElement('a');
		link.id = 'appPublicWebsiteButton';
		link.href = appPublicWebsiteHref();
		link.target = '_blank';
		link.rel = 'noopener';
		link.textContent = 'visit the  school main website';
		link.style.position = 'fixed';
		link.style.top = '12px';
		link.style.right = '12px';
		link.style.zIndex = '1300';
		link.style.background = '#0e6b45';
		link.style.color = '#ffffff';
		link.style.padding = '10px 14px';
		link.style.borderRadius = '999px';
		link.style.fontWeight = '700';
		link.style.fontSize = '12px';
		link.style.textDecoration = 'none';
		link.style.boxShadow = '0 10px 22px rgba(0, 0, 0, 0.22)';
		link.style.textTransform = 'none';

		document.body.appendChild(link);
	}

	function appEnsureOnlineWidgetStyles() {
		if (document.getElementById('appOnlineWidgetStyles')) {
			return;
		}
		var style = document.createElement('style');
		style.id = 'appOnlineWidgetStyles';
		style.textContent = '' +
			'.app-online-indicator{display:inline-flex;align-items:center;gap:6px;font-weight:700;}' +
			'.app-online-dot{width:9px;height:9px;border-radius:999px;background:#2bb24c;box-shadow:0 0 0 0 rgba(43,178,76,.5);animation:appOnlinePulse 1.6s infinite;}' +
			'.app-online-quick{display:flex;align-items:center;padding:0 10px;}' +
			'.app-online-quick.is-offline .app-online-dot{background:#95a59c;box-shadow:none;animation:none;}' +
			'.app-online-quick.is-offline .app-online-indicator{opacity:.8;}' +
			'.app-online-menu{min-width:290px;max-height:340px;overflow:auto;padding:6px 0;}' +
			'.app-online-row{padding:8px 12px;border-bottom:1px solid #eef2f1;display:flex;justify-content:space-between;gap:8px;}' +
			'.app-online-row:last-child{border-bottom:none;}' +
			'.app-online-name{font-weight:700;}' +
			'.app-online-meta{font-size:12px;color:#63736a;}' +
			'.app-profile-online{position:relative;}' +
			'.app-profile-online-dot{position:absolute;right:1px;bottom:3px;width:11px;height:11px;border-radius:999px;background:#2bb24c;border:2px solid #fff;box-shadow:0 0 0 0 rgba(43,178,76,.45);animation:appOnlinePulse 1.6s infinite;}' +
			'@keyframes appOnlinePulse{0%{box-shadow:0 0 0 0 rgba(43,178,76,.5);}70%{box-shadow:0 0 0 7px rgba(43,178,76,0);}100%{box-shadow:0 0 0 0 rgba(43,178,76,0);}}';
		document.head.appendChild(style);
	}

	function appInitOnlineWidget(portal) {
		if (portal === 'other') {
			return;
		}

		var nav = document.querySelector('.app-header .app-nav');
		if (!nav || document.getElementById('appOnlineNavItem')) {
			return;
		}

		appEnsureOnlineWidgetStyles();

		var profileLink = nav.querySelector('[aria-label="Open Profile Menu"]');
		if (profileLink) {
			profileLink.classList.add('app-profile-online');
			if (!document.getElementById('appProfileOnlineDot')) {
				var profileDot = document.createElement('span');
				profileDot.id = 'appProfileOnlineDot';
				profileDot.className = 'app-profile-online-dot';
				profileDot.setAttribute('aria-hidden', 'true');
				profileLink.appendChild(profileDot);
			}
		}

		if (!document.getElementById('appOnlineQuickStatus')) {
			var quickItem = document.createElement('li');
			quickItem.className = 'app-online-quick';
			quickItem.id = 'appOnlineQuickStatus';
			quickItem.innerHTML = '<span class="app-online-indicator"><span class="app-online-dot"></span><span id="appOnlineQuickLabel">Online</span></span>';
			nav.insertBefore(quickItem, nav.firstChild);
		}

		var item = document.createElement('li');
		item.className = 'dropdown';
		item.id = 'appOnlineNavItem';
		item.innerHTML = '' +
			'<a class="app-nav__item" href="#" data-bs-toggle="dropdown" aria-label="Online users">' +
				'<span class="app-online-indicator"><span class="app-online-dot"></span><span id="appOnlineLabel">Online</span></span>' +
			'</a>' +
			'<div class="dropdown-menu dropdown-menu-right app-online-menu" id="appOnlineMenu">' +
				'<div class="px-3 py-2 text-muted small">Loading online users...</div>' +
			'</div>';
		nav.insertBefore(item, nav.firstChild);

		var menu = document.getElementById('appOnlineMenu');
		var label = document.getElementById('appOnlineLabel');
		var quickStatus = document.getElementById('appOnlineQuickStatus');
		var quickLabel = document.getElementById('appOnlineQuickLabel');
		var onlineEndpoint = appCoreEndpoint('online_users.php');

		function renderOnline(data) {
			if (!menu || !label) return;
			if (!data || !data.ok) {
				menu.innerHTML = '<div class="px-3 py-2 text-muted small">Online users unavailable.</div>';
				if (quickStatus) quickStatus.classList.add('is-offline');
				if (quickLabel) quickLabel.textContent = 'Offline';
				return;
			}

			function renderSeen(value) {
				if (!value) return '';
				var d = new Date(String(value).replace(' ', 'T'));
				if (isNaN(d.getTime())) {
					return String(value);
				}
				return d.toLocaleTimeString();
			}

			var users = Array.isArray(data.users) ? data.users : [];
			var count = Number(data.count || users.length || 0);
			label.textContent = 'Online (' + count + ')';
			if (quickStatus) quickStatus.classList.toggle('is-offline', count < 1);
			if (quickLabel) quickLabel.textContent = count > 0 ? ('Online (' + count + ')') : 'Offline';

			if (!users.length) {
				menu.innerHTML = '<div class="px-3 py-2 text-muted small">No other users online.</div>';
				return;
			}

			menu.innerHTML = users.map(function (u) {
				var name = (u && u.name) ? String(u.name) : 'User';
				var role = (u && u.role) ? String(u.role) : '';
				var seen = renderSeen(u && u.last_seen ? u.last_seen : '');
				var meta = seen ? (role + ' | Last seen: ' + seen) : role;
				return '' +
					'<div class="app-online-row">' +
						'<div>' +
							'<div class="app-online-name">' + name + '</div>' +
							'<div class="app-online-meta">' + meta + '</div>' +
						'</div>' +
						'<span class="badge bg-success">Online</span>' +
					'</div>';
			}).join('');
		}

		function refreshOnline() {
			if (typeof navigator.onLine === 'boolean' && !navigator.onLine) {
				renderOnline(null);
				return;
			}
			if (document.visibilityState && document.visibilityState !== 'visible') {
				return;
			}
			fetch(onlineEndpoint, { credentials: 'same-origin' })
				.then(function (r) { return r.json(); })
				.then(renderOnline)
				.catch(function () {
					renderOnline(null);
				});
		}

		var onlineRefreshMs = 300000;  // 5 minutes instead of 90 seconds - MAJOR PERF FIX
		refreshOnline();
		var onlineTimer = window.setInterval(refreshOnline, onlineRefreshMs);

		document.addEventListener('visibilitychange', function () {
			if (document.visibilityState === 'visible') {
				refreshOnline();  // Only refresh when user returns to tab
				// Resume animations when user returns to tab
				document.body.classList.remove('visibility-hidden');
			} else {
				// Pause animations when user switches tabs - PERF FIX: Saves CPU/battery
				document.body.classList.add('visibility-hidden');
			}
		});

		window.addEventListener('beforeunload', function () {
			if (onlineTimer) {
				window.clearInterval(onlineTimer);
			}
		});
	}

	function appInitGlobalAutoRefresh() {
		var path = String(window.location.pathname || '').toLowerCase();
		if (path.indexOf('/api/') !== -1 || path.indexOf('/core/') !== -1 || path.indexOf('/setup') !== -1) {
			return;
		}

		// Refresh only authenticated portal pages to avoid hammering public pages.
		var isAuthenticated = document.cookie.indexOf('__SRMS__logged=') !== -1 && document.cookie.indexOf('__SRMS__key=') !== -1;
		if (!isAuthenticated) {
			return;
		}

		var pauseRefresh = false;
		var hasUnsavedFormInput = false;

		document.addEventListener('focusin', function (e) {
			var target = e && e.target ? e.target : null;
			if (!target) return;
			if (target.matches('input, textarea, select, [contenteditable="true"]')) {
				pauseRefresh = true;
			}
		});

		document.addEventListener('focusout', function () {
			pauseRefresh = false;
		});

		document.addEventListener('input', function (e) {
			var target = e && e.target ? e.target : null;
			if (!target) return;
			if (target.matches('input, textarea, select')) {
				hasUnsavedFormInput = true;
			}
		});

		document.addEventListener('submit', function () {
			hasUnsavedFormInput = false;
		});

		window.setInterval(function () {
			if (document.visibilityState && document.visibilityState !== 'visible') {
				return;
			}
			if (pauseRefresh || hasUnsavedFormInput) {
				return;
			}
			window.location.reload();
		}, 5000);
	}

	var portal = appCurrentPortal();
	appEnsureSidebarFooter(portal);
	appEnsurePortalGuideMenu(portal);
	appEnsurePublicWebsiteButton();
	appEnsureConnectivityBanner();
	appInitOnlineWidget(portal);
	appInitGlobalAutoRefresh();
	appApplyImpersonationBanner();

	// Restrict copying/paste/context menu only on admin/teacher portals - PERF: Reduces event overhead
	var isSensitivePortal = window.location.pathname.indexOf('/admin/') > -1 || 
	                          window.location.pathname.indexOf('/teacher/') > -1;
	
	if (isSensitivePortal) {
		// Disable right-click
		document.addEventListener('contextmenu', function(e) {
			e.preventDefault();
			return false;
		});

		// Disable copying
		document.addEventListener('copy', function(e) {
			e.preventDefault();
			return false;
		});

		// Disable cutting
		document.addEventListener('cut', function(e) {
			e.preventDefault();
			return false;
		});

		// Disable text selection with keyboard shortcuts
		document.addEventListener('keydown', function(e) {
			if ((e.ctrlKey || e.metaKey) && e.key === 'c') {
				e.preventDefault();
				return false;
			}
			if ((e.ctrlKey || e.metaKey) && e.key === 'x') {
				e.preventDefault();
				return false;
			}
		});
	}

})();
