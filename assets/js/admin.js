/**
 * CSI Cookie Consent – settings page: tabs, live preview, colour presets and
 * translation helpers.
 */
(function ($) {
	'use strict';

	var data = window.gdprcnAdmin || {};
	var $root = $('#gdprcn-preview-root');
	var root = $root[0];
	var previewLang = '';
	var FONTS = {
		inherit: 'inherit',
		system: '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif',
		serif: 'Georgia, "Times New Roman", serif'
	};

	/* Tabs ---------------------------------------------------------------- */

	function showTab(slug) {
		if (!$('[data-gdprcn-panel="' + slug + '"]').length) {
			slug = 'general';
		}
		$('.gdprcn-tabs .nav-tab').removeClass('nav-tab-active').filter('[data-gdprcn-tab="' + slug + '"]').addClass('nav-tab-active');
		$('.gdprcn-panel').attr('hidden', true).filter('[data-gdprcn-panel="' + slug + '"]').removeAttr('hidden');
		$('.gdprcn-submit').prop('hidden', slug === 'tools');

		// Return to the same tab after saving (options.php redirects to the referer).
		var $ref = $('input[name="_wp_http_referer"]');
		$ref.val($ref.val().split('#')[0] + '#' + slug);
	}

	$('.gdprcn-tabs').on('click', '.nav-tab', function (e) {
		e.preventDefault();
		var slug = $(this).data('gdprcnTab');
		history.replaceState(null, '', '#' + slug);
		showTab(slug);
	});

	/* Live preview -------------------------------------------------------- */

	function setting(key) {
		var $f = $('[data-gdprcn-setting="' + key + '"]');
		return $f.is(':checkbox') ? $f.is(':checked') : $f.val();
	}

	function updateStyle() {
		if (!root) {
			return;
		}
		var vars = {
			'--gdprcn-bg': setting('bg_color'),
			'--gdprcn-text': setting('text_color'),
			'--gdprcn-accent': setting('accent_color'),
			'--gdprcn-accent-text': setting('accent_text_color'),
			'--gdprcn-font': FONTS[setting('font_family')] || 'inherit',
			'--gdprcn-font-size': setting('font_size') + 'px',
			'--gdprcn-radius': setting('box_radius') + 'px',
			'--gdprcn-btn-radius': setting('button_radius') + 'px',
			'--gdprcn-max-width': setting('max_width') + 'px'
		};
		Object.keys(vars).forEach(function (k) {
			root.style.setProperty(k, vars[k]);
		});

		var classes = [
			'gdprcn-root',
			'gdprcn-preview',
			'gdprcn-pos-' + setting('position'),
			'gdprcn-customize-' + setting('customize_style'),
			'gdprcn-order-' + setting('button_order'),
			'gdprcn-revisit-' + setting('revisit_position')
		];
		['mobile_bar', 'overlay', 'shadow', 'animation'].forEach(function (flag) {
			classes.push('gdprcn-' + (setting(flag) ? '' : 'no-') + flag.replace('_', '-'));
		});
		root.className = classes.join(' ');

		$root.find('.gdprcn-revisit')
			.prop('hidden', !setting('revisit_button'))
			.removeClass('gdprcn-revisit-style-icon gdprcn-revisit-style-text')
			.addClass('gdprcn-revisit-style-' + setting('revisit_style'));
	}

	// Only the tags the plugin allows in texts, so the preview matches the front end.
	function safeFragment(html) {
		var tpl = document.createElement('template');
		tpl.innerHTML = html;
		(function clean(node) {
			Array.prototype.slice.call(node.childNodes).forEach(function (n) {
				if (n.nodeType === 3) {
					return;
				}
				if (n.nodeType !== 1) {
					n.parentNode.removeChild(n);
					return;
				}
				var tag = n.nodeName.toLowerCase();
				if (['a', 'strong', 'em', 'br'].indexOf(tag) === -1) {
					n.parentNode.replaceChild(document.createTextNode(n.textContent), n);
					return;
				}
				Array.prototype.slice.call(n.attributes).forEach(function (a) {
					if (!(tag === 'a' && a.name === 'href' && !/^\s*javascript:/i.test(a.value))) {
						n.removeAttribute(a.name);
					}
				});
				clean(n);
			});
		})(tpl.content);
		return tpl.content;
	}

	function textFor(key) {
		var value = '';
		if (previewLang) {
			value = $('[data-gdprcn-key="' + key + '"][data-gdprcn-lang="' + previewLang + '"]').val() || '';
		}
		return value || $('[data-gdprcn-key="' + key + '"][data-gdprcn-lang=""]').val() || '';
	}

	function updateTexts() {
		$root.find('[data-gdprcn-text]').each(function () {
			var key = this.getAttribute('data-gdprcn-text');
			var value = textFor(key);
			if (!value) {
				return;
			}
			if ($('[data-gdprcn-key="' + key + '"]').first().is('textarea')) {
				this.innerHTML = '';
				this.appendChild(safeFragment(value));
			} else {
				this.textContent = value;
			}
		});
	}

	function update() {
		updateStyle();
		updateTexts();
	}

	function showView(view) {
		$('[data-gdprcn-view]').removeClass('is-active').filter('[data-gdprcn-view="' + view + '"]').addClass('is-active');
		$root.find('.gdprcn-modal').prop('hidden', view !== 'prefs');
	}

	function setPreviewLang(code) {
		previewLang = code || '';
		$('.gdprcn-preview-lang').val(previewLang);
		updateTexts();
	}

	$('[data-gdprcn-view]').on('click', function () {
		showView($(this).data('gdprcnView'));
	});

	$('.gdprcn-preview-lang').on('change', function () {
		setPreviewLang(this.value);
	});

	// The preview is static: buttons only switch views.
	$root.on('click', 'a, button', function (e) {
		e.preventDefault();
		var action = $(this).data('gdprcnAction');
		showView(action === 'customize' || $(this).is('.gdprcn-revisit') ? 'prefs' : 'banner');
	});

	$(document).on('input change', '[data-gdprcn-setting], [data-gdprcn-key]', update);

	$('[data-gdprcn-category-toggle]').on('change', function () {
		$root.find('[data-gdprcn-cat="' + $(this).data('gdprcnCategoryToggle') + '"]').prop('hidden', !this.checked);
	});

	/* Colours ------------------------------------------------------------- */

	$('.gdprcn-color').wpColorPicker({
		change: function () {
			setTimeout(update, 0);
		},
		clear: function () {
			setTimeout(update, 0);
		}
	});

	$('.gdprcn-preset').on('click', function () {
		var preset = (data.presets || {})[$(this).data('gdprcnPreset')];
		if (!preset) {
			return;
		}
		['bg_color', 'text_color', 'accent_color', 'accent_text_color'].forEach(function (key) {
			$('[data-gdprcn-setting="' + key + '"]').wpColorPicker('color', preset[key]);
		});
		update();
	});

	/* Translations -------------------------------------------------------- */

	function langPanel(code) {
		return $('[data-gdprcn-lang-panel="' + code + '"]');
	}

	function showLang(code) {
		$('.gdprcn-lang-panel').attr('hidden', true);
		var $panel = langPanel(code).removeAttr('hidden');
		var hasStarter = !!(data.starters || {})[code];
		$panel.find('.gdprcn-fill-starter').prop('disabled', !hasStarter);
		$panel.find('.gdprcn-no-starter').prop('hidden', hasStarter);
	}

	$('#gdprcn-lang-select').on('change', function () {
		showLang(this.value);
		setPreviewLang(this.value);
	});

	$('.gdprcn-fill-starter').on('click', function () {
		var code = $(this).data('gdprcnLang');
		var starter = (data.starters || {})[code] || {};
		var $panel = langPanel(code);
		var filled = 0;
		$panel.find('[data-gdprcn-key]').each(function () {
			var key = this.getAttribute('data-gdprcn-key');
			if (!this.value && starter[key]) {
				this.value = starter[key];
				filled++;
			}
		});
		$panel.find('.gdprcn-fill-status').text(filled ? data.i18n.filled.replace('%d', filled) : data.i18n.nothing);
		setPreviewLang(code);
	});

	$('.gdprcn-clear-lang').on('click', function () {
		if (!window.confirm(data.i18n.confirmClear)) {
			return;
		}
		var code = $(this).data('gdprcnLang');
		langPanel(code).find('[data-gdprcn-key]').val('');
		langPanel(code).find('.gdprcn-fill-status').text('');
		setPreviewLang(code);
	});

	/* Init ---------------------------------------------------------------- */

	showTab((location.hash || '#general').slice(1));
	if ($('#gdprcn-lang-select').length) {
		showLang($('#gdprcn-lang-select').val());
	}
	update();
})(jQuery);
