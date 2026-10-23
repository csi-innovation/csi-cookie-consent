/**
 * CSI Cookie Consent – front end.
 *
 * Public API:
 *   window.GDPRCN.open()            open the preferences dialog
 *   window.GDPRCN.hasConsent(cat)   true if the category is allowed
 *   window.GDPRCN.getConsent()      { id, r, t, c: { category: bool } } or null
 *   window.GDPRCN.reset()           forget the choice and show the banner again
 *   document event "gdprcn:consent" fires with detail { categories, action }
 */
(function () {
	'use strict';

	var cfg = window.gdprcnConfig || {};
	var CATS = cfg.categories || [];
	var FOCUSABLE = 'a[href],button:not([disabled]),input:not([disabled]),summary,[tabindex]:not([tabindex="-1"])';

	var banner, modal, dialog, revisit, lastFocus;
	var current = readConsent();

	/* Cookie storage ---------------------------------------------------- */

	function readConsent() {
		var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + cfg.cookieName + '=([^;]+)'));
		if (!m) {
			return null;
		}
		try {
			var c = JSON.parse(decodeURIComponent(m[1]));
			return c && c.r === cfg.revision && c.c && typeof c.c === 'object' ? c : null;
		} catch (e) {
			return null;
		}
	}

	function writeConsent(c) {
		var expires = new Date(Date.now() + cfg.expiryDays * 864e5).toUTCString();
		document.cookie = cfg.cookieName + '=' + encodeURIComponent(JSON.stringify(c)) +
			'; expires=' + expires + '; path=' + (cfg.cookiePath || '/') + '; SameSite=Lax' + (cfg.secure ? '; Secure' : '');
	}

	function uuid() {
		if (window.crypto && crypto.randomUUID) {
			return crypto.randomUUID();
		}
		return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function (ch) {
			var r = Math.random() * 16 | 0;
			return (ch === 'x' ? r : (r & 3 | 8)).toString(16);
		});
	}

	function deleteCookie(name) {
		var parts = location.hostname.split('.');
		var domains = [''];
		for (var i = 0; i < parts.length - 1; i++) {
			domains.push('; domain=.' + parts.slice(i).join('.'));
		}
		domains.forEach(function (d) {
			document.cookie = name + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=/' + d;
		});
	}

	function clearCookies(categories) {
		var names = document.cookie.split(';').map(function (p) {
			return p.split('=')[0].trim();
		});
		categories.forEach(function (cat) {
			(cfg.cookies[cat] || []).forEach(function (pattern) {
				var prefix = pattern.slice(-1) === '*' ? pattern.slice(0, -1) : null;
				names.forEach(function (name) {
					if (prefix !== null ? name.indexOf(prefix) === 0 : name === pattern) {
						deleteCookie(name);
					}
				});
			});
		});
	}

	/* Script gating ----------------------------------------------------- */

	function allowed(cat) {
		return cat === 'necessary' || !!(current && current.c[cat]);
	}

	function cloneScript(old) {
		var s = document.createElement('script');
		Array.prototype.forEach.call(old.attributes, function (a) {
			if ((a.name === 'type' && a.value === 'text/plain') || a.name === 'data-cookie-category' || a.name === 'data-gdprcn-type') {
				return;
			}
			s.setAttribute(a.name, a.value);
		});
		if (old.getAttribute('data-gdprcn-type')) {
			s.type = old.getAttribute('data-gdprcn-type');
		}
		s.text = old.text;
		return s;
	}

	// Insert nodes one by one, waiting for blocking external scripts so that
	// inline snippets that depend on them run in the original order.
	function runQueue(queue) {
		var item = queue.shift();
		if (!item) {
			return;
		}
		if (item.node.nodeName !== 'SCRIPT') {
			item.place(document.importNode(item.node, true));
			runQueue(queue);
			return;
		}
		var s = cloneScript(item.node);
		var wait = s.src && !item.node.hasAttribute('async');
		if (wait) {
			s.onload = s.onerror = function () {
				runQueue(queue);
			};
		}
		item.place(s);
		if (!wait) {
			runQueue(queue);
		}
	}

	function activate() {
		var queue = [];

		document.querySelectorAll('template[data-gdprcn-category]').forEach(function (tpl) {
			if (tpl.hasAttribute('data-gdprcn-done') || !allowed(tpl.getAttribute('data-gdprcn-category'))) {
				return;
			}
			tpl.setAttribute('data-gdprcn-done', '');
			Array.prototype.forEach.call(tpl.content.childNodes, function (node) {
				if (node.nodeType !== 1) {
					return;
				}
				queue.push({
					node: node,
					place: function (n) {
						(n.nodeName === 'SCRIPT' ? document.head : document.body).appendChild(n);
					}
				});
			});
		});

		document.querySelectorAll('script[type="text/plain"][data-cookie-category]').forEach(function (old) {
			if (!allowed(old.getAttribute('data-cookie-category'))) {
				return;
			}
			queue.push({
				node: old,
				place: function (n) {
					old.parentNode.replaceChild(n, old);
				}
			});
		});

		runQueue(queue);

		document.querySelectorAll('[data-gdprcn-src][data-cookie-category]').forEach(function (el) {
			if (allowed(el.getAttribute('data-cookie-category'))) {
				el.setAttribute('src', el.getAttribute('data-gdprcn-src'));
				el.removeAttribute('data-gdprcn-src');
			}
		});
	}

	function updateConsentMode() {
		if (!cfg.consentMode || !current) {
			return;
		}
		window.dataLayer = window.dataLayer || [];
		var gtag = window.gtag || function () {
			window.dataLayer.push(arguments);
		};
		var update = {};
		Object.keys(cfg.consentModeMap).forEach(function (cat) {
			cfg.consentModeMap[cat].forEach(function (signal) {
				update[signal] = current.c[cat] ? 'granted' : 'denied';
			});
		});
		gtag('consent', 'update', update);
		window.dataLayer.push({ event: 'gdprcn_consent_update' });
	}

	/* Saving a choice --------------------------------------------------- */

	function log(action) {
		if (!cfg.logUrl || !window.fetch) {
			return;
		}
		fetch(cfg.logUrl, {
			method: 'POST',
			headers: { 'Content-Type': 'application/json' },
			credentials: 'omit',
			keepalive: true,
			body: JSON.stringify({
				consent_id: current.id,
				action: action,
				categories: current.c,
				url: location.origin + location.pathname
			})
		}).catch(function () {});
	}

	function save(choices, action) {
		var prev = current;
		var withdrawn = prev ? CATS.filter(function (k) {
			return prev.c[k] && !choices[k];
		}) : [];

		current = { id: (prev && prev.id) || uuid(), r: cfg.revision, t: Date.now(), c: choices };
		writeConsent(current);
		updateConsentMode();
		log(action);

		hide(banner);
		closeModal();
		show(revisit);

		document.dispatchEvent(new CustomEvent('gdprcn:consent', { detail: { categories: choices, action: action } }));

		if (withdrawn.length) {
			clearCookies(withdrawn);
			if (cfg.reloadOnWithdraw) {
				// Give the log request a moment to leave.
				setTimeout(function () {
					location.reload();
				}, 300);
				return;
			}
		}
		activate();
	}

	function choicesFor(action) {
		var choices = {};
		CATS.forEach(function (cat) {
			if (action === 'accept') {
				choices[cat] = true;
			} else if (action === 'reject') {
				choices[cat] = false;
			} else {
				var input = modal.querySelector('input[data-gdprcn-category="' + cat + '"]');
				choices[cat] = !!(input && input.checked);
			}
		});
		return choices;
	}

	/* UI ---------------------------------------------------------------- */

	function show(el) {
		if (el) {
			el.hidden = false;
		}
	}

	function hide(el) {
		if (el) {
			el.hidden = true;
		}
	}

	function openModal() {
		if (!modal) {
			return;
		}
		CATS.forEach(function (cat) {
			var input = modal.querySelector('input[data-gdprcn-category="' + cat + '"]');
			if (input) {
				input.checked = allowed(cat);
			}
		});
		var idRow = modal.querySelector('.gdprcn-consent-id');
		if (idRow) {
			idRow.hidden = !current;
			idRow.querySelector('code').textContent = current ? current.id : '';
		}
		lastFocus = document.activeElement;
		show(modal);
		dialog.focus();
	}

	function closeModal() {
		if (!modal || modal.hidden) {
			return;
		}
		hide(modal);
		if (banner && !banner.hidden) {
			var first = banner.querySelector('button');
			if (first) {
				first.focus();
			}
		} else if (lastFocus && lastFocus.focus) {
			lastFocus.focus();
		}
	}

	function trapFocus(e) {
		var items = Array.prototype.filter.call(dialog.querySelectorAll(FOCUSABLE), function (el) {
			return el.offsetParent !== null;
		});
		if (!items.length) {
			return;
		}
		var first = items[0];
		var last = items[items.length - 1];
		if (e.shiftKey && (document.activeElement === first || document.activeElement === dialog)) {
			e.preventDefault();
			last.focus();
		} else if (!e.shiftKey && document.activeElement === last) {
			e.preventDefault();
			first.focus();
		}
	}

	function onClick(e) {
		var opener = e.target.closest('.gdprcn-open-settings, a[href$="#cookie-settings"]');
		if (opener) {
			e.preventDefault();
			openModal();
			return;
		}
		var btn = e.target.closest('[data-gdprcn-action]');
		if (!btn) {
			return;
		}
		var action = btn.getAttribute('data-gdprcn-action');
		if (action === 'customize') {
			openModal();
		} else if (action === 'close') {
			closeModal();
		} else {
			save(choicesFor(action), action);
		}
	}

	function onKeydown(e) {
		if (!modal || modal.hidden) {
			return;
		}
		if (e.key === 'Escape') {
			closeModal();
		} else if (e.key === 'Tab') {
			trapFocus(e);
		}
	}

	function init() {
		banner = document.getElementById('gdprcn-banner');
		modal = document.getElementById('gdprcn-modal');
		dialog = modal && modal.querySelector('.gdprcn-dialog');
		revisit = document.getElementById('gdprcn-revisit');

		document.addEventListener('click', onClick);
		document.addEventListener('keydown', onKeydown);

		if (current) {
			activate();
			show(revisit);
		} else {
			show(banner);
		}
	}

	window.GDPRCN = {
		open: openModal,
		hasConsent: allowed,
		getConsent: function () {
			return current;
		},
		reset: function () {
			document.cookie = cfg.cookieName + '=; expires=Thu, 01 Jan 1970 00:00:00 GMT; path=' + (cfg.cookiePath || '/');
			current = null;
			hide(revisit);
			show(banner);
		}
	};

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
