<?php

if (!function_exists('app_edu_ai_portal_href_map')) {
	function app_edu_ai_portal_href_map(string $portal): array
	{
		$portal = strtolower(trim($portal));
		$map = [
			'admin' => ['notifications' => 'admin/notifications'],
			'academic' => ['notifications' => 'academic/index'],
			'teacher' => ['notifications' => 'teacher/index'],
			'accountant' => ['notifications' => 'accountant/index'],
			'bom' => ['notifications' => 'bom/index'],
			'student' => ['notifications' => 'student/index'],
			'parent' => ['notifications' => 'parent/index'],
		];

		return $map[$portal] ?? ['notifications' => 'index.php'];
	}
}

if (!function_exists('app_render_portal_edu_ai')) {
	function app_render_portal_edu_ai(string $portal): void
	{
		$portal = strtolower(trim($portal));
		$hrefs = app_edu_ai_portal_href_map($portal);
		$notificationsHref = (string)($hrefs['notifications'] ?? 'index.php');
		$portalLabel = strtoupper($portal);
		?>
		<div class="app-edu-ai-dock" id="appEduAiDock">
			<button type="button" class="app-edu-ai-dock__bell" id="appEduAiBell" aria-label="Notifications">
				<i class="bi bi-bell"></i>
				<span class="app-edu-ai-dock__badge" id="appEduAiBellCount" style="display:none;">0</span>
			</button>
			<button type="button" class="app-edu-ai-dock__ask" id="appEduAiDockOpen">
				<i class="bi bi-stars"></i>
				<span>Ask Edu AI</span>
			</button>
		</div>

		<div class="app-edu-ai-shell" id="appEduAiShell" style="display:none;">
			<div class="app-edu-ai-shell__backdrop" id="appEduAiBackdrop"></div>
			<div class="app-edu-ai-shell__panel">
				<div class="app-edu-ai-shell__header">
					<div>
						<div class="app-edu-ai-shell__title">Edu AI Assistant</div>
						<div class="app-edu-ai-shell__subtitle">Context-aware help for this page and portal</div>
					</div>
					<button type="button" class="app-edu-ai-shell__close" id="appEduAiClose">×</button>
				</div>
				<div class="app-edu-ai-shell__quick" id="appEduAiQuick">
					<button type="button" data-prompt="Analyse this page and tell me what needs attention first.">Analyse this page</button>
					<button type="button" data-prompt="Show the main performance or process risk in this module.">Show main risk</button>
					<button type="button" data-prompt="Draft a professional message or action plan for this page context.">Draft message</button>
				</div>
				<div class="app-edu-ai-shell__chat" id="appEduAiChat">
					<div class="app-edu-ai-shell__empty">Edu AI is ready. Ask about this page, this module, performance, attendance, fees, discipline, or reports.</div>
				</div>
				<div class="app-edu-ai-shell__composer">
					<textarea id="appEduAiInput" rows="3" placeholder="Ask Edu AI about this page or module..."></textarea>
					<div class="app-edu-ai-shell__actions">
						<span id="appEduAiStatus">Ready</span>
						<button type="button" id="appEduAiSend">Send</button>
					</div>
				</div>
			</div>
		</div>

		<style>
		.app-edu-ai-dock{position:fixed;right:20px;bottom:20px;z-index:2200;display:flex;align-items:center;gap:10px}
		.app-edu-ai-dock__bell,.app-edu-ai-dock__ask{border:none;cursor:pointer;box-shadow:0 16px 36px rgba(15,40,80,.18)}
		.app-edu-ai-dock__bell{position:relative;width:52px;height:52px;border-radius:18px;background:#fff;color:#173042;font-size:1.2rem}
		.app-edu-ai-dock__ask{display:flex;align-items:center;gap:10px;padding:14px 18px;border-radius:18px;background:linear-gradient(160deg,#0d6efd 0%,#0a58ca 100%);color:#fff;font-weight:800}
		.app-edu-ai-dock__badge{position:absolute;top:-4px;right:-4px;min-width:20px;height:20px;padding:0 5px;border-radius:999px;background:#dc3545;color:#fff;font-size:.7rem;font-weight:800;align-items:center;justify-content:center}
		.app-edu-ai-shell{position:fixed;inset:0;z-index:2300}
		.app-edu-ai-shell__backdrop{position:absolute;inset:0;background:rgba(7,20,39,.44)}
		.app-edu-ai-shell__panel{position:absolute;right:18px;bottom:18px;width:min(50vw,760px);min-width:620px;max-width:calc(100vw - 28px);height:min(78vh,860px);max-height:min(78vh,860px);display:flex;flex-direction:column;border-radius:24px;background:#fff;box-shadow:0 28px 70px rgba(15,40,80,.26);overflow:hidden}
		.app-edu-ai-shell__header{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;padding:18px 20px;border-bottom:1px solid #e8eef5}
		.app-edu-ai-shell__title{font-weight:800;color:#173042;font-size:1.08rem}
		.app-edu-ai-shell__subtitle{font-size:.88rem;color:#667788}
		.app-edu-ai-shell__close{border:none;background:transparent;font-size:1.6rem;line-height:1;cursor:pointer;color:#617282}
		.app-edu-ai-shell__quick{display:flex;gap:8px;flex-wrap:wrap;padding:14px 18px;border-bottom:1px solid #eef3f8}
		.app-edu-ai-shell__quick button{border:none;background:#eef6ff;color:#175cd3;border-radius:999px;padding:9px 13px;font-size:.83rem;font-weight:700;cursor:pointer}
		.app-edu-ai-shell__chat{padding:16px 18px;overflow:auto;background:#f9fbfd;min-height:420px;flex:1}
		.app-edu-ai-shell__empty{color:#6f7e8f;font-size:.96rem;line-height:1.6}
		.app-edu-ai-shell__msg{margin-bottom:12px;padding:12px 14px;border-radius:16px;line-height:1.55;font-size:.95rem;white-space:pre-wrap}
		.app-edu-ai-shell__msg--user{background:#0d6efd;color:#fff;margin-left:36px}
		.app-edu-ai-shell__msg--edu{background:#fff;border:1px solid #dbe7f3;color:#173042;margin-right:24px}
		.app-edu-ai-shell__composer{padding:16px 18px;border-top:1px solid #e8eef5;background:#fff}
		.app-edu-ai-shell__composer textarea{width:100%;resize:vertical;min-height:140px;border:1px solid #dbe7f3;border-radius:16px;padding:13px 14px;outline:none}
		.app-edu-ai-shell__actions{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:12px}
		.app-edu-ai-shell__actions span{font-size:.86rem;color:#6f7e8f}
		.app-edu-ai-shell__actions button{border:none;background:#0d6efd;color:#fff;font-weight:800;border-radius:13px;padding:11px 18px;cursor:pointer}
		.app-edu-ai-nav-bell{position:relative}
		@media (max-width: 768px){
			.app-edu-ai-dock{right:12px;left:12px;bottom:12px;justify-content:flex-end}
			.app-edu-ai-dock__ask{flex:1;justify-content:center}
			.app-edu-ai-shell__panel{right:12px;left:12px;bottom:12px;width:auto;min-width:0;max-width:none;height:min(86vh,860px);max-height:min(86vh,860px)}
		}
		</style>

		<script>
		(function () {
			if (window.__srmsPortalEduAiReady) {
				return;
			}
			window.__srmsPortalEduAiReady = true;

			var shell = document.getElementById('appEduAiShell');
			var chat = document.getElementById('appEduAiChat');
			var input = document.getElementById('appEduAiInput');
			var status = document.getElementById('appEduAiStatus');
			var countNode = document.getElementById('appEduAiBellCount');
			var notificationsHref = <?php echo json_encode($notificationsHref); ?>;

			function coreEndpoint(fileName) {
				var path = window.location.pathname || '';
				var marker = '/script/';
				var index = path.toLowerCase().indexOf(marker);
				if (index !== -1) {
					return path.substring(0, index + marker.length) + 'core/' + fileName;
				}
				return 'core/' + fileName;
			}

			function escapeHtml(text) {
				return String(text || '')
					.replace(/&/g, '&amp;')
					.replace(/</g, '&lt;')
					.replace(/>/g, '&gt;')
					.replace(/"/g, '&quot;')
					.replace(/'/g, '&#39;');
			}

			function currentContext() {
				var path = String(window.location.pathname || '');
				var parts = path.split('/').filter(Boolean);
				var page = parts.length ? parts[parts.length - 1].replace(/\.php$/i, '') : 'dashboard';
				var previous = parts.length > 1 ? String(parts[parts.length - 2] || '').toLowerCase() : '';
				var module = ['admin', 'academic', 'teacher', 'student', 'parent', 'accountant', 'bom', 'script'].indexOf(previous) === -1 && previous ? previous : page;
				var titleNode = document.querySelector('.app-title h1') || document.querySelector('.dashboard-hero h1') || document.querySelector('h1');
				return {
					module: module || 'general',
					page: page || 'dashboard',
					title: titleNode ? String(titleNode.textContent || '').trim() : document.title
				};
			}

			function collectPageScan() {
				var forms = [];
				document.querySelectorAll('form').forEach(function (form, index) {
					var fields = Array.prototype.slice.call(form.querySelectorAll('input, select, textarea')).filter(function (field) {
						return field.type !== 'hidden' && !field.disabled;
					});
					var filled = 0;
					var missing = 0;
					fields.forEach(function (field) {
						var value = '';
						if (field.type === 'checkbox' || field.type === 'radio') {
							value = field.checked ? '1' : '';
						} else {
							value = String(field.value || '').trim();
						}
						if (value !== '') filled++;
						if (field.required && value === '') missing++;
					});
					forms.push({
						label: form.getAttribute('id') || form.getAttribute('aria-label') || ('Form ' + (index + 1)),
						total_fields: fields.length,
						filled_fields: filled,
						missing_required: missing
					});
				});

				var tables = [];
				document.querySelectorAll('table').forEach(function (table, index) {
					var rows = table.querySelectorAll('tbody tr').length || table.querySelectorAll('tr').length;
					tables.push({ label: 'Table ' + (index + 1), rows: rows });
				});

				var alerts = [];
				document.querySelectorAll('.alert, .text-danger').forEach(function (node, index) {
					if (index > 7) return;
					var text = String(node.textContent || '').replace(/\s+/g, ' ').trim();
					if (text) alerts.push(text);
				});

				var buttons = [];
				document.querySelectorAll('button, .btn, input[type="submit"], a.btn').forEach(function (node, index) {
					if (index > 7) return;
					var text = String(node.textContent || node.value || '').replace(/\s+/g, ' ').trim();
					if (text) buttons.push(text);
				});

				return {
					forms: forms.slice(0, 6),
					tables: tables.slice(0, 6),
					alerts: alerts.slice(0, 8),
					buttons: buttons.slice(0, 8),
					summary: {
						total_forms: forms.length,
						total_tables: tables.length,
						total_stats: 0,
						primary_action: buttons[0] || '',
						missing_required: forms.reduce(function (sum, item) { return sum + Number(item.missing_required || 0); }, 0),
						empty_table_rows: tables.filter(function (item) { return Number(item.rows || 0) < 1; }).length
					}
				};
			}

			function openShell(prefill) {
				shell.style.display = 'block';
				if (prefill && input) {
					input.value = prefill;
					input.focus();
				}
			}

			function closeShell() {
				shell.style.display = 'none';
			}

			function appendMessage(role, text) {
				if (!chat) return;
				var empty = chat.querySelector('.app-edu-ai-shell__empty');
				if (empty) {
					empty.remove();
				}
				var node = document.createElement('div');
				node.className = 'app-edu-ai-shell__msg app-edu-ai-shell__msg--' + role;
				node.textContent = String(text || '');
				chat.appendChild(node);
				chat.scrollTop = chat.scrollHeight;
			}

			function sendMessage(forcedMessage) {
				var message = String(forcedMessage || (input && input.value) || '').trim();
				if (!message) {
					return;
				}
				var context = currentContext();
				appendMessage('user', message);
				if (input) input.value = '';
				status.textContent = 'Edu AI is thinking...';
				fetch(coreEndpoint('ai_feedback.php'), {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					credentials: 'same-origin',
					body: JSON.stringify({
						message: message,
						category: 'ai',
						tool: 'general',
						language: 'English',
						module: context.module,
						page: context.page,
						title: context.title,
						page_scan: collectPageScan()
					})
				}).then(function (r) {
					return r.text().then(function (text) {
						try {
							return JSON.parse(text || '{}');
						} catch (e) {
							return { ok: false, message: text || ('HTTP ' + r.status) };
						}
					});
				}).then(function (data) {
					appendMessage('edu', data && data.ok ? (data.response || 'Ready.') : (data.message || 'Failed to reach Edu AI.'));
					status.textContent = data && data.provider ? ('Ready • ' + data.provider) : 'Ready';
				}).catch(function () {
					appendMessage('edu', 'Failed to reach Edu AI right now.');
					status.textContent = 'Ready';
				});
			}

			function autoAnalysePage(prompt) {
				var message = prompt || 'Analyse this page and tell me what needs attention first, expected outcome, warnings, and what should be done next.';
				openShell(message);
				window.setTimeout(function () {
					sendMessage(message);
				}, 60);
			}

			function injectHeaderBell(count) {
				var nav = document.querySelector('.app-header .app-nav');
				if (!nav || document.getElementById('appEduAiHeaderBell')) {
					return;
				}
				var item = document.createElement('li');
				item.className = 'app-edu-ai-nav-bell';
				item.style.display = 'flex';
				item.style.alignItems = 'center';
				item.style.height = '50px';
				item.innerHTML = '<a href="' + escapeHtml(notificationsHref) + '" class="app-nav__item" id="appEduAiHeaderBell" aria-label="Notifications"><i class="bi bi-bell fs-5"></i><span id="appEduAiHeaderBellCount" style="position:absolute;top:4px;right:0;min-width:18px;height:18px;padding:0 5px;border-radius:999px;background:#dc3545;color:#fff;font-size:.68rem;font-weight:800;' + (count > 0 ? 'display:inline-flex;' : 'display:none;') + 'align-items:center;justify-content:center;">' + (count > 99 ? '99+' : count) + '</span></a>';
				nav.insertBefore(item, nav.firstChild);
			}

			function refreshNotifications() {
				fetch(coreEndpoint('notifications_feed.php'), { credentials: 'same-origin' })
					.then(function (r) { return r.ok ? r.json() : null; })
					.then(function (data) {
						if (!data || !data.ok) {
							return;
						}
						var count = Number(data.count_unread || 0);
						if (count > 0) {
							countNode.textContent = count > 99 ? '99+' : String(count);
							countNode.style.display = 'inline-flex';
						} else {
							countNode.style.display = 'none';
						}
						injectHeaderBell(count);
						var headerCount = document.getElementById('appEduAiHeaderBellCount');
						if (headerCount) {
							headerCount.textContent = count > 99 ? '99+' : String(count);
							headerCount.style.display = count > 0 ? 'inline-flex' : 'none';
						}
					})
					.catch(function () {});
			}

			document.getElementById('appEduAiDockOpen').addEventListener('click', function () {
				autoAnalysePage();
			});
			document.getElementById('appEduAiBell').addEventListener('click', function () {
				window.location.href = notificationsHref;
			});
			document.getElementById('appEduAiBackdrop').addEventListener('click', closeShell);
			document.getElementById('appEduAiClose').addEventListener('click', closeShell);
			document.getElementById('appEduAiSend').addEventListener('click', sendMessage);

			document.getElementById('appEduAiQuick').addEventListener('click', function (event) {
				var button = event.target.closest('button[data-prompt]');
				if (!button) return;
				autoAnalysePage(button.getAttribute('data-prompt') || '');
			});

			document.addEventListener('keydown', function (event) {
				if (shell.style.display === 'block' && event.key === 'Escape') {
					closeShell();
				}
				if (shell.style.display === 'block' && event.key === 'Enter' && !event.shiftKey && document.activeElement === input) {
					event.preventDefault();
					sendMessage();
				}
			});

			refreshNotifications();
			window.setInterval(refreshNotifications, 30000);
		})();
		</script>
		<?php
	}
}
