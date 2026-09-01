
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

					// Apply branding if provided
					try {
						var appName = (data.app && data.app.name) ? String(data.app.name) : '';
						var schoolName = (data.school && data.school.name) ? String(data.school.name) : appName;
						if (schoolName) {
						var footerEl = document.getElementById('appFooter');
						if (footerEl) {
							footerEl.textContent = '@' + (new Date()).getFullYear() + ' ' + schoolName;
							}
							if (appName && document.title.indexOf(appName) === -1) {
								document.title = appName + (document.title ? ' - ' + document.title : '');
							}
						}
					} catch (e) {
						// ignore branding failures
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
		footer.textContent = '@' + new Date().getFullYear() + ' School Management System';
		var content = document.querySelector('.app-content');
		if (content && content.parentNode) {
			content.parentNode.appendChild(footer);
		} else {
			document.body.appendChild(footer);
		}
		fetch('api/health', { credentials: 'same-origin' })
			.then(function(response) { return response.ok ? response.json() : null; })
			.then(function(data) {
				if (data && data.academic_year) {
					footer.textContent = '@' + data.academic_year + ' School Management System';
				}
			})
			.catch(function () { /* keep fallback year */ });
	}

	// Legacy floating Edu AI widget removed.

	function appCurrentPortal() {
		var path = (window.location.pathname || '').toLowerCase();
		if (path.indexOf('/academic') !== -1) return 'academic';
		if (path.indexOf('/teacher') !== -1) return 'teacher';
		if (path.indexOf('/student') !== -1) return 'student';
		if (path.indexOf('/parent') !== -1) return 'parent';
		if (path.indexOf('/accountant') !== -1) return 'accountant';
		if (path.indexOf('/bom') !== -1) return 'bom';
		if (path.indexOf('/admin') !== -1) return 'admin';

		var roleNode = document.querySelector('.app-sidebar__user-designation');
		var roleText = roleNode ? String(roleNode.textContent || '').toLowerCase() : '';
		if (roleText.indexOf('academic') !== -1 || roleText.indexOf('deputy') !== -1) return 'academic';
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
		if (document.querySelector('.app-sidebar .app-menu a[href^="academic/"]')) {
			return 'academic';
		}

		return 'other';
	}

	function appPortalModuleHref(module) {
		var portal = appCurrentPortal();
		var map = {
			admin: {
				notifications: 'admin/notifications',
				attendance: 'admin/attendance',
				performance: 'admin/results_analytics',
				finance: 'admin/fees',
				discipline: 'admin/discipline',
				marks: 'admin/exams'
			},
			academic: {
				notifications: 'academic/index',
				attendance: 'academic/index',
				performance: 'academic/index',
				finance: 'academic/index',
				discipline: 'academic/discipline',
				marks: 'academic/index'
			},
			teacher: {
				notifications: 'teacher/index',
				attendance: 'teacher/attendance',
				performance: 'teacher/class_report',
				finance: 'teacher/index',
				discipline: 'teacher/discipline',
				marks: 'teacher/exam_marks_entry'
			},
			accountant: {
				notifications: 'accountant/index',
				attendance: 'accountant/index',
				performance: 'accountant/index',
				finance: 'accountant/fees',
				discipline: 'accountant/index',
				marks: 'accountant/index'
			},
			bom: {
				notifications: 'bom/index',
				attendance: 'bom/index',
				performance: 'bom/index',
				finance: 'bom/index',
				discipline: 'bom/index',
				marks: 'bom/index'
			},
			student: {
				notifications: 'student/index',
				attendance: 'student/attendance',
				performance: 'student/results',
				finance: 'student/fees',
				discipline: 'student/discipline',
				marks: 'student/results'
			},
			parent: {
				notifications: 'parent/index',
				attendance: 'parent/attendance',
				performance: 'parent/report_card',
				finance: 'parent/fees',
				discipline: 'parent/discipline',
				marks: 'parent/report_card'
			}
		};
		if (!map[portal]) {
			portal = 'admin';
		}
		return map[portal][module] || map[portal].notifications;
	}

	function appEnsureHeaderNav() {
		var header = document.querySelector('.app-header');
		if (!header) {
			return null;
		}
		var nav = header.querySelector('.app-nav');
		if (nav) {
			return nav;
		}

		nav = document.createElement('ul');
		nav.className = 'app-nav';
		header.appendChild(nav);
		return nav;
	}

	function appNormalizeHeaderBranding() {
		var logos = document.querySelectorAll('.app-header__logo');
		var shortName = String(document.title || '').split(' - ')[0].trim();
		logos.forEach(function (logo) {
			logo.style.display = 'flex';
			logo.style.alignItems = 'center';
			logo.style.justifyContent = 'center';
			logo.style.height = '50px';
			logo.style.maxHeight = '50px';
			logo.style.whiteSpace = 'nowrap';
			logo.style.overflow = 'hidden';
			logo.style.textOverflow = 'ellipsis';
			var text = String(logo.textContent || '').replace(/\s+/g, ' ').trim();
			if (shortName && text.length > 18) {
				logo.title = text;
			}
		});

		fetch('api/health.php', { credentials: 'same-origin' })
			.then(function (r) { return r.ok ? r.json() : null; })
			.then(function (payload) {
				var schoolName = payload && payload.school_name ? String(payload.school_name) : shortName;
				var logoUrl = payload && payload.logo_url ? String(payload.logo_url) : '';
				if (logoUrl) {
					document.querySelectorAll('link[rel="icon"], link[rel="shortcut icon"], link[rel="apple-touch-icon"]').forEach(function (node) {
						node.setAttribute('href', logoUrl);
					});
				}
				if (!logos.length) {
					return;
				}
				logos.forEach(function (logo) {
					if (logo.dataset.brandingApplied === '1') {
						return;
					}
					logo.dataset.brandingApplied = '1';
					logo.title = schoolName || String(logo.textContent || '').trim();
					if (logoUrl) {
						logo.innerHTML = '<span class="app-header__brand"><img src="' + logoUrl + '" alt="School logo"><span class="app-header__brand-text">' + (schoolName || shortName || 'School') + '</span></span>';
					} else if (schoolName) {
						logo.textContent = schoolName;
					}
				});
			})
			.catch(function () {
				if (!logos.length) {
					return;
				}
				logos.forEach(function (logo) {
					var text = String(logo.textContent || '').replace(/\s+/g, ' ').trim();
					if (shortName && text.length > 18) {
						logo.textContent = shortName;
					}
				});
			});
	}

	function appEnsureSmartSearch() {
		var nav = appEnsureHeaderNav();
		if (!nav || document.getElementById('appSmartSearchBtn')) {
			return;
		}

		var item = document.createElement('li');
		item.style.display = 'flex';
		item.style.alignItems = 'center';
		item.style.height = '50px';
		item.innerHTML = '<button type="button" id="appSmartSearchBtn" class="app-nav__item" style="border:none;background:transparent;display:inline-flex;align-items:center;gap:8px;"><i class="bi bi-search"></i><span class="d-none d-md-inline">Search</span></button>';
		nav.insertBefore(item, nav.firstChild);

		var modal = document.createElement('div');
		modal.id = 'appSmartSearchModal';
		modal.style.display = 'none';
		modal.style.position = 'fixed';
		modal.style.inset = '0';
		modal.style.zIndex = '2600';
		modal.innerHTML = '' +
			'<div id="appSmartSearchBackdrop" style="position:absolute;inset:0;background:rgba(7,20,39,.45);"></div>' +
			'<div style="position:relative;max-width:760px;margin:10vh auto 0;background:#fff;border-radius:20px;box-shadow:0 28px 70px rgba(15,40,80,.22);overflow:hidden;">' +
				'<div style="padding:16px 18px;border-bottom:1px solid #e8eef5;">' +
					'<input id="appSmartSearchInput" class="form-control" placeholder="Search anything... e.g. Grade 8 fee balances, absent learners, Mathematics results" />' +
				'</div>' +
				'<div id="appSmartSearchResults" style="max-height:60vh;overflow:auto;padding:8px 18px 18px;"></div>' +
			'</div>';
		document.body.appendChild(modal);

		var input = document.getElementById('appSmartSearchInput');
		var results = document.getElementById('appSmartSearchResults');
		function openSearch() {
			modal.style.display = 'block';
			input.value = '';
			results.innerHTML = '<div class="text-muted py-3">Start typing to search modules, classes, learners, and staff.</div>';
			setTimeout(function () { input.focus(); }, 30);
		}
		function closeSearch() {
			modal.style.display = 'none';
		}
		function renderResults(data) {
			if (!data || !data.ok || !Array.isArray(data.results) || !data.results.length) {
				results.innerHTML = '<div class="text-muted py-3">No matching results found.</div>';
				return;
			}
			results.innerHTML = data.results.map(function (item) {
				return '<a href="' + String(item.url || '#') + '" style="display:block;padding:12px 4px;border-bottom:1px solid #eef2f7;text-decoration:none;color:inherit;">' +
					'<div style="font-weight:800;color:#173042;">' + String(item.title || 'Result') + '</div>' +
					'<div class="small text-muted">' + String(item.description || item.type || '') + '</div>' +
				'</a>';
			}).join('');
		}
		var timer = null;
		input.addEventListener('input', function () {
			var q = String(input.value || '').trim();
			clearTimeout(timer);
			timer = setTimeout(function () {
				fetch(appCoreEndpoint('smart_search.php') + '?q=' + encodeURIComponent(q), { credentials: 'same-origin' })
					.then(function (r) { return r.ok ? r.json() : null; })
					.then(renderResults)
					.catch(function () {
						results.innerHTML = '<div class="text-danger py-3">Search is unavailable right now.</div>';
					});
			}, 160);
		});
		document.getElementById('appSmartSearchBtn').addEventListener('click', openSearch);
		document.getElementById('appSmartSearchBackdrop').addEventListener('click', closeSearch);
		document.addEventListener('keydown', function (event) {
			if ((event.ctrlKey || event.metaKey) && String(event.key).toLowerCase() === 'k') {
				event.preventDefault();
				openSearch();
			}
			if (event.key === 'Escape' && modal.style.display === 'block') {
				closeSearch();
			}
		});
	}

	function appApplyUiPreferences() {
		var root = document.documentElement;
		var theme = window.localStorage.getItem('srms-theme') || 'light';
		var scale = window.localStorage.getItem('srms-ui-scale') || 'normal';
		var motion = window.localStorage.getItem('srms-ui-motion') || 'normal';
		if (theme === 'system') {
			theme = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
		}
		root.setAttribute('data-bs-theme', theme === 'dark' ? 'dark' : 'light');
		root.setAttribute('data-ui-scale', scale);
		root.setAttribute('data-ui-motion', motion);
	}

	function appEnsureThemeControls() {
		var nav = appEnsureHeaderNav();
		if (!nav || document.getElementById('appThemeControlsBtn')) {
			return;
		}
		appApplyUiPreferences();

		var item = document.createElement('li');
		item.className = 'dropdown';
		item.style.display = 'flex';
		item.style.alignItems = 'center';
		item.style.height = '50px';
		item.innerHTML = '' +
			'<a class="app-nav__item" href="#" id="appThemeControlsBtn" data-bs-toggle="dropdown" aria-label="Accessibility and theme"><i class="bi bi-sliders"></i></a>' +
			'<div class="dropdown-menu dropdown-menu-right p-3" style="min-width:280px;">' +
				'<div class="fw-bold mb-2">Display & Accessibility</div>' +
				'<label class="form-label small mb-1">Theme</label>' +
				'<select class="form-control form-control-sm mb-2" id="appThemeSelect">' +
					'<option value="light">Light</option>' +
					'<option value="dark">Dark</option>' +
					'<option value="system">System</option>' +
				'</select>' +
				'<label class="form-label small mb-1">Text size</label>' +
				'<select class="form-control form-control-sm mb-2" id="appScaleSelect">' +
					'<option value="normal">Normal</option>' +
					'<option value="large">Large</option>' +
					'<option value="xlarge">Extra Large</option>' +
				'</select>' +
				'<label class="form-label small mb-1">Motion</label>' +
				'<select class="form-control form-control-sm" id="appMotionSelect">' +
					'<option value="normal">Normal</option>' +
					'<option value="reduced">Reduced</option>' +
				'</select>' +
			'</div>';
		nav.appendChild(item);

		var themeSelect = document.getElementById('appThemeSelect');
		var scaleSelect = document.getElementById('appScaleSelect');
		var motionSelect = document.getElementById('appMotionSelect');
		if (!themeSelect || !scaleSelect || !motionSelect) {
			return;
		}
		themeSelect.value = window.localStorage.getItem('srms-theme') || 'light';
		scaleSelect.value = window.localStorage.getItem('srms-ui-scale') || 'normal';
		motionSelect.value = window.localStorage.getItem('srms-ui-motion') || 'normal';

		function persist() {
			window.localStorage.setItem('srms-theme', themeSelect.value || 'light');
			window.localStorage.setItem('srms-ui-scale', scaleSelect.value || 'normal');
			window.localStorage.setItem('srms-ui-motion', motionSelect.value || 'normal');
			appApplyUiPreferences();
		}

		themeSelect.addEventListener('change', persist);
		scaleSelect.addEventListener('change', persist);
		motionSelect.addEventListener('change', persist);
	}

	function appEnsureDashboardIntelligence() {
		var appContent = document.querySelector('.app-content');
		if (!appContent) {
			return;
		}
		var titleText = String((document.querySelector('.app-title h1') || document.querySelector('.dashboard-hero h1') || document.querySelector('h1') || {}).textContent || '').toLowerCase();
		var isDashboard = document.body.classList.contains('dashboard') || document.querySelector('.dashboard-hero') || titleText.indexOf('dashboard') !== -1;
		if (!isDashboard) {
			return;
		}

		var host = document.getElementById('appDashboardIntelligence');
		if (!host) {
			host = document.createElement('section');
			host.id = 'appDashboardIntelligence';
			host.className = 'tile';
			host.style.borderRadius = '20px';
			host.style.border = '1px solid #dbe7f2';
			host.style.boxShadow = '0 12px 26px rgba(15,40,80,.06)';
			host.style.marginBottom = '18px';
			host.innerHTML =
				'<div class="tile-body">' +
					'<div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">' +
						'<div><h3 class="tile-title" style="margin-bottom:4px;">Live Dashboard Intelligence</h3><div class="small text-muted">Role-based school signals, recommendations, and activity without leaving the dashboard.</div></div>' +
						'<button type="button" class="btn btn-outline-primary btn-sm" id="appDashboardIntelRefresh"><i class="bi bi-arrow-repeat me-1"></i>Refresh</button>' +
					'</div>' +
					'<div id="appDashboardIntelCards" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:14px;"></div>' +
					'<div class="row g-3">' +
						'<div class="col-lg-7"><div style="border:1px solid #e6edf5;border-radius:16px;padding:14px;height:100%;"><div class="fw-bold mb-2">Recommendations</div><div id="appDashboardIntelRecommendations" class="small text-muted">Loading recommendations...</div></div></div>' +
						'<div class="col-lg-5"><div style="border:1px solid #e6edf5;border-radius:16px;padding:14px;height:100%;"><div class="fw-bold mb-2">Live Timeline</div><div id="appDashboardIntelTimeline" class="small text-muted">Loading timeline...</div></div></div>' +
					'</div>' +
				'</div>';
			var anchor = document.querySelector('.dashboard-hero') || document.querySelector('.app-title');
			if (anchor && anchor.nextSibling) {
				appContent.insertBefore(host, anchor.nextSibling);
			} else {
				appContent.insertBefore(host, appContent.firstChild);
			}
		}

		var cardsBox = document.getElementById('appDashboardIntelCards');
		var recBox = document.getElementById('appDashboardIntelRecommendations');
		var timelineBox = document.getElementById('appDashboardIntelTimeline');
		var refreshBtn = document.getElementById('appDashboardIntelRefresh');
		if (!cardsBox || !recBox || !timelineBox) {
			return;
		}

		function escHtml(text) {
			return String(text || '').replace(/[&<>\"']/g, function (m) {
				return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[m];
			});
		}

		function render(data) {
			if (!data || !data.ok) {
				cardsBox.innerHTML = '<div class="text-muted">Dashboard intelligence is unavailable right now.</div>';
				recBox.innerHTML = 'Unable to load recommendations.';
				timelineBox.innerHTML = 'Unable to load timeline.';
				return;
			}
			var toneMap = {
				success: { bg: '#effaf3', border: '#b9e4c6', value: '#067647' },
				warning: { bg: '#fff9e8', border: '#f1d58a', value: '#b26a00' },
				danger: { bg: '#fff1f0', border: '#f5c2c0', value: '#b42318' },
				info: { bg: '#eef6ff', border: '#bdd6f6', value: '#175cd3' }
			};
			cardsBox.innerHTML = (data.cards || []).map(function (card) {
				var tone = toneMap[card.tone] || toneMap.info;
				return '<div style="background:' + tone.bg + ';border:1px solid ' + tone.border + ';border-radius:16px;padding:14px;">' +
					'<div class="small text-muted" style="font-weight:800;text-transform:uppercase;letter-spacing:.08em;">' + escHtml(card.label) + '</div>' +
					'<div style="font-size:1.45rem;font-weight:800;color:' + tone.value + ';margin:4px 0;">' + escHtml(card.value) + '</div>' +
					'<div class="small text-muted">' + escHtml(card.detail || '') + '</div>' +
				'</div>';
			}).join('');
			recBox.innerHTML = (data.recommendations || []).map(function (item) {
				return '<div style="padding:10px 0;border-bottom:1px solid #eef2f7;">' +
					'<div style="font-weight:800;color:#173042;">' + escHtml(item.title || 'Recommendation') + '</div>' +
					'<div class="small text-muted">' + escHtml(item.detail || '') + '</div>' +
				'</div>';
			}).join('') || 'No recommendation available right now.';
			timelineBox.innerHTML = (data.timeline || []).map(function (item) {
				return '<div style="padding:10px 0;border-bottom:1px solid #eef2f7;">' +
					'<div style="font-weight:800;color:#173042;">' + escHtml(item.title || 'Update') + '</div>' +
					'<div class="small text-muted">' + escHtml(item.detail || '') + '</div>' +
					'<div class="small text-muted mt-1">' + escHtml(item.time || '') + '</div>' +
				'</div>';
			}).join('') || 'No recent timeline entries.';
		}

		function refresh() {
			fetch(appCoreEndpoint('dashboard_intelligence.php'), { credentials: 'same-origin' })
				.then(function (r) { return r.ok ? r.json() : null; })
				.then(render)
				.catch(function () {
					render(null);
				});
		}

		if (refreshBtn && !refreshBtn.dataset.bound) {
			refreshBtn.dataset.bound = '1';
			refreshBtn.addEventListener('click', refresh);
		}

		refresh();
		if (!host.dataset.liveTimerBound) {
			host.dataset.liveTimerBound = '1';
			window.setInterval(refresh, 60000);
		}
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
		var path = window.location.pathname || '';
		var marker = '/script/';
		var index = path.toLowerCase().indexOf(marker);
		if (index !== -1) {
			return path.substring(0, index + marker.length) + 'core/' + fileName;
		}
		return 'core/' + fileName;
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
			'.app-header .app-nav{align-items:center;gap:4px;}' +
			'.app-header .app-nav>li{display:flex;align-items:center;height:50px;list-style:none;}' +
			'.app-header .app-nav .app-nav__item{display:inline-flex;align-items:center;justify-content:center;min-height:50px;padding:0 12px;}' +
			'.app-online-indicator{display:inline-flex;align-items:center;gap:6px;font-weight:700;white-space:nowrap;}' +
			'.app-online-dot{width:9px;height:9px;border-radius:999px;background:#2bb24c;box-shadow:0 0 0 0 rgba(43,178,76,.5);animation:appOnlinePulse 1.6s infinite;}' +
			'.app-online-menu{min-width:290px;max-height:340px;overflow:auto;padding:6px 0;}' +
			'.app-online-row{padding:8px 12px;border-bottom:1px solid #eef2f1;display:flex;justify-content:space-between;gap:8px;}' +
			'.app-online-row:last-child{border-bottom:none;}' +
			'.app-online-name{font-weight:700;}' +
			'.app-online-meta{font-size:12px;color:#63736a;}' +
			'.app-profile-online{position:relative;}' +
			'.app-profile-online-dot{position:absolute;right:8px;bottom:10px;width:11px;height:11px;border-radius:999px;background:#2bb24c;border:2px solid #fff;box-shadow:0 0 0 0 rgba(43,178,76,.45);animation:appOnlinePulse 1.6s infinite;}' +
			'#appOnlineNavItem{display:flex;align-items:center;height:50px;}' +
			'@keyframes appOnlinePulse{0%{box-shadow:0 0 0 0 rgba(43,178,76,.5);}70%{box-shadow:0 0 0 7px rgba(43,178,76,0);}100%{box-shadow:0 0 0 0 rgba(43,178,76,0);}}';
		document.head.appendChild(style);
	}

	function appInitOnlineWidget(portal) {
		if (portal === 'other') {
			return;
		}

		var nav = appEnsureHeaderNav();
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
		var onlineEndpoint = appCoreEndpoint('online_users.php');

		function renderOnline(data) {
			if (!menu || !label) return;
			if (!data || !data.ok) {
				menu.innerHTML = '<div class="px-3 py-2 text-muted small">Online users unavailable.</div>';
				label.textContent = 'Offline';
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

	var portal = appCurrentPortal();
	appNormalizeHeaderBranding();
	appApplyUiPreferences();
	appEnsureSmartSearch();
	appEnsureThemeControls();
	appEnsureDashboardIntelligence();
	appEnsureSidebarFooter(portal);
	appEnsurePortalGuideMenu(portal);
	appEnsurePublicWebsiteButton();
	appEnsureConnectivityBanner();
	appInitOnlineWidget(portal);
	appApplyImpersonationBanner();

	/* Auto-scale wide mark-sheet / class-list tables to fit printable area.
	   This adjusts tables inside .sheet-wrap elements and scales them via CSS transform
	   so the layout doesn't overflow when printing or viewing on narrow screens. */
	function appAdjustWideTablesForPrint() {
		var wrappers = document.querySelectorAll('.sheet-wrap');
		wrappers.forEach(function (wrap) {
			var table = wrap.querySelector('table.sheet-table');
			if (!table) return;
			// ensure wrapper for clipping
			if (!wrap.querySelector('.fit-scale-wrapper')) {
				var inner = document.createElement('div');
				inner.className = 'fit-scale-wrapper';
				// move table into wrapper
				table.parentNode.insertBefore(inner, table);
				inner.appendChild(table);
				// keep reference
				wrap._fitWrapper = inner;
			} else {
				wrap._fitWrapper = wrap.querySelector('.fit-scale-wrapper');
			}

			var clip = wrap._fitWrapper;
			// reset first
			table.style.transform = '';
			table.classList.remove('sheet-table--too-small');
			clip.style.height = '';

			// compute available width and table natural width
			var avail = Math.max(1, clip.clientWidth || wrap.clientWidth || 800) - 8;
			var natural = Math.max(1, table.scrollWidth || table.offsetWidth);
			if (natural > avail) {
				var scale = Math.max(0.45, avail / natural);
				table.style.transform = 'scale(' + scale + ')';
				// set wrapper height to preserve flow (table height * scale)
				var h = Math.ceil((table.offsetHeight || table.getBoundingClientRect().height) * scale) + 'px';
				clip.style.height = h;
				if (scale < 0.7) table.classList.add('sheet-table--too-small');
			}
		});
	}

	// Reset scaled tables (useful after printing or when layout changes)
	function appResetScaledTables() {
		var tables = document.querySelectorAll('table.sheet-table');
		tables.forEach(function (t) {
			t.style.transform = '';
			t.classList.remove('sheet-table--too-small');
			var wrapper = t.closest('.fit-scale-wrapper');
			if (wrapper) wrapper.style.height = '';
		});
	}

	// Bind to print lifecycle and resize
	if (window.matchMedia) {
		window.matchMedia('print').addEventListener('change', function (m) {
			if (m.matches) {
				appAdjustWideTablesForPrint();
			} else {
				appResetScaledTables();
			}
		});
	}
	window.addEventListener('beforeprint', appAdjustWideTablesForPrint);
	window.addEventListener('afterprint', appResetScaledTables);
	window.addEventListener('resize', function () { setTimeout(appResetScaledTables, 80); setTimeout(appAdjustWideTablesForPrint, 220); });

})();
