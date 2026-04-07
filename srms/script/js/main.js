
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
			'<div class="ai-panel-header">AI & Feedback <button type="button" class="btn btn-sm btn-light" id="aiClose">×</button></div>' +
			'<div class="ai-panel-body">' +
			'<label class="form-label">Mode</label>' +
			'<select class="form-control mb-2" id="aiMode">' +
			'<option value="ai">Ask AI</option>' +
			'<option value="feedback">Send Feedback</option>' +
			'</select>' +
			'<textarea class="form-control mb-2" id="aiMessage" rows="3" placeholder="Type your question or feedback..."></textarea>' +
			'<button class="btn btn-primary btn-sm" id="aiSend">Send</button>' +
			'<div class="ai-response" id="aiResponse" style="display:none;"></div>' +
			'</div>';
		document.body.appendChild(panel);

		fab.addEventListener('click', function () {
			panel.style.display = panel.style.display === 'block' ? 'none' : 'block';
		});
		document.addEventListener('click', function (e) {
			if (e.target && e.target.id === 'aiClose') {
				panel.style.display = 'none';
			}
		});

		document.addEventListener('click', function (e) {
			if (e.target && e.target.id === 'aiSend') {
				var msg = document.getElementById('aiMessage').value.trim();
				if (!msg) return;
				var mode = document.getElementById('aiMode').value;
				var responseBox = document.getElementById('aiResponse');
				responseBox.style.display = 'block';
				responseBox.textContent = 'Sending...';
				fetch('core/ai_feedback', {
					method: 'POST',
					headers: { 'Content-Type': 'application/json' },
					body: JSON.stringify({ message: msg, category: mode })
				}).then(function (r) { return r.json(); })
				.then(function (data) {
					if (data && data.ok) {
						responseBox.textContent = data.response || 'Thank you. We received your message.';
						document.getElementById('aiMessage').value = '';
					} else {
						responseBox.textContent = data.message || 'Failed to send.';
					}
				}).catch(function () {
					responseBox.textContent = 'Failed to send.';
				});
			}
		});
	}

})();
