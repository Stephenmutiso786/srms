(function () {
  var cfg = window.SRMS_PWA_INSTALL_CONFIG || {};
  var buttonId = cfg.buttonId || 'installBtn';
  var autoPrompt = cfg.autoPrompt !== false;
  var promptDelayMs = Number(cfg.promptDelayMs || 1200);
  var sessionKey = cfg.sessionKey || 'srms_pwa_prompted';
  var serviceWorkerPath = cfg.serviceWorkerPath || 'service-worker.js';
  var debugMode = cfg.debugMode === true;

  if (!('window' in self)) {
    return;
  }

  function isLocalhost(hostname) {
    return hostname === 'localhost' || hostname === '127.0.0.1' || hostname === '[::1]';
  }

  var hostname = String((window.location && window.location.hostname) || '').toLowerCase();
  var secureInstallContext = window.isSecureContext || isLocalhost(hostname);
  var serviceWorkerReady = false;
  var serviceWorkerError = '';

  // Skip prompt when running as installed PWA.
  var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
  if (isStandalone) {
    return;
  }

  var deferredPrompt = null;
  var installBtn = document.getElementById(buttonId);

  // Keep the install action visible immediately when a button exists.
  if (installBtn) {
    installBtn.style.display = 'inline-flex';
  }

  if ('serviceWorker' in navigator && secureInstallContext) {
    navigator.serviceWorker.register(serviceWorkerPath).then(function () {
      serviceWorkerReady = true;
    }).catch(function () {
      serviceWorkerReady = false;
      serviceWorkerError = 'service_worker_registration_failed';
    });
  }

  function debugInfo() {
    if (!debugMode) {
      return '';
    }
    var parts = [
      'secure=' + (secureInstallContext ? 'yes' : 'no'),
      'sw=' + (serviceWorkerReady ? 'ready' : 'not-ready'),
      'prompt=' + (deferredPrompt ? 'available' : 'missing')
    ];
    if (serviceWorkerError) {
      parts.push('sw_error=' + serviceWorkerError);
    }
    return '\n\nDebug: ' + parts.join(', ');
  }

  function installInstructionsForBrowser() {
    var ua = String(window.navigator.userAgent || '').toLowerCase();
    if (/iphone|ipad|ipod/.test(ua)) {
      return 'To install on iPhone/iPad: tap Share and choose Add to Home Screen.';
    }
    if (/android/.test(ua)) {
      return 'To install on Android: open the browser menu and tap Install app or Add to Home screen.';
    }
    return 'Open the browser menu and use Install app or Add to Home Screen if it appears.';
  }

  function showInstallButton() {
    if (installBtn) {
      installBtn.style.display = 'inline-flex';
    }
  }

  function hideInstallButton() {
    if (installBtn) {
      installBtn.style.display = 'none';
    }
  }

  function markPrompted() {
    try {
      sessionStorage.setItem(sessionKey, '1');
    } catch (e) {
      // ignore storage failures
    }
  }

  function wasPrompted() {
    try {
      return sessionStorage.getItem(sessionKey) === '1';
    } catch (e) {
      return false;
    }
  }

  function runPrompt() {
    if (!deferredPrompt) {
      return;
    }

    try {
      deferredPrompt.prompt();
      if (deferredPrompt.userChoice && typeof deferredPrompt.userChoice.then === 'function') {
        deferredPrompt.userChoice.finally(function () {
          deferredPrompt = null;
          hideInstallButton();
          markPrompted();
        });
      } else {
        deferredPrompt = null;
        hideInstallButton();
        markPrompted();
      }
    } catch (e) {
      // Keep button visible as fallback if auto prompt fails.
      showInstallButton();
    }
  }

  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredPrompt = e;
    showInstallButton();

    if (autoPrompt && !wasPrompted()) {
      window.setTimeout(runPrompt, Math.max(0, promptDelayMs));
    }
  });

  if (installBtn) {
    installBtn.addEventListener('click', function () {
      if (!secureInstallContext) {
        alert('Install App needs HTTPS on phones and other devices. If you opened this with http:// plus an IP address, switch the app to HTTPS first.');
        return;
      }

      if (deferredPrompt) {
        runPrompt();
        return;
      }

      // Fallback guidance for browsers that delay/suppress beforeinstallprompt.
      if (/iphone|ipad|ipod/i.test(window.navigator.userAgent || '')) {
        alert(installInstructionsForBrowser() + debugInfo());
        return;
      }

      if (!serviceWorkerReady) {
        alert('Install setup is still loading or was blocked. Refresh the page, open the app again over HTTPS, then try once more.' + debugInfo());
        return;
      }

      alert('Automatic install prompt is not available in this browser right now. ' + installInstructionsForBrowser() + debugInfo());
    });
  }

  window.addEventListener('appinstalled', function () {
    deferredPrompt = null;
    hideInstallButton();
    markPrompted();
  });
})();
