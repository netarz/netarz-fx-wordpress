/**
 * NetArz FX: [netarz_convert]. Toman <-> currency, both ways, as you type.
 *
 * No request is made here. In server mode the rates come with the markup
 * (data-netarz-rates); in browser mode they come from the single request the
 * browser-mode script already makes (window.netarzFxData / "netarz-fx:rates").
 */
(function () {
	'use strict';

	var TO_LATIN = { '۰': '0', '۱': '1', '۲': '2', '۳': '3', '۴': '4', '۵': '5', '۶': '6', '۷': '7', '۸': '8', '۹': '9', '٠': '0', '١': '1', '٢': '2', '٣': '3', '٤': '4', '٥': '5', '٦': '6', '٧': '7', '٨': '8', '٩': '9', '٫': '.', '٬': '', ',': '' };
	var TO_PERSIAN = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹', ',': '٬', '.': '٫' };

	// Accepts "1,250.5", "۱٬۲۵۰٫۵" or "1250.5"; returns NaN for anything else.
	function parse(text) {
		var s = String(text).replace(/[۰-۹٠-٩٫٬,]/g, function (c) { return TO_LATIN[c]; }).replace(/\s+/g, '');
		return s === '' || !/^\d*\.?\d*$/.test(s) ? NaN : parseFloat(s);
	}

	function format(n, decimals, persian) {
		var s = Number(n).toLocaleString('en-US', { maximumFractionDigits: decimals });
		return persian ? s.replace(/[0-9,.]/g, function (c) { return TO_PERSIAN[c]; }) : s;
	}

	function setup(box, rows) {
		if (box.getAttribute('data-netarz-ready') === '1') {
			return;
		}
		var amount = box.querySelector('.netarz-fx-amount');
		var toman = box.querySelector('.netarz-fx-toman');
		var select = box.querySelector('.netarz-fx-currency');
		if (!amount || !toman || !select) {
			return;
		}
		var field = box.getAttribute('data-netarz-field') || 'sell';
		var persian = box.getAttribute('data-netarz-persian') === '1';

		// Normalise: server rows carry `price`; browser-mode rows are API rows.
		var byCode = {};
		rows.forEach(function (r) {
			var price = r.price !== undefined ? r.price : r[field];
			if (r.code && price) {
				byCode[String(r.code).toUpperCase()] = { price: Number(price), unit: Math.max(1, Number(r.unit) || 1), name: r.name, nameEn: r.name_en };
			}
		});

		// Browser mode: the list starts as bare codes; give it names, drop codes the plan does not include.
		Array.prototype.slice.call(select.options).forEach(function (opt) {
			var row = byCode[opt.value];
			if (!row) {
				select.removeChild(opt);
				return;
			}
			var cfg = window.netarzFx || {};
			var name = (cfg.nameFa && row.name) ? row.name : (row.nameEn || row.name);
			if (name && opt.textContent === opt.value) {
				opt.textContent = name + ' (' + opt.value + ')';
			}
		});
		if (!select.options.length) {
			return;
		}

		function perUnit() {
			var row = byCode[select.value];
			return row ? row.price / row.unit : NaN;
		}

		function fromAmount() {
			var a = parse(amount.value);
			var r = perUnit();
			toman.value = isNaN(a) || isNaN(r) ? '' : format(Math.round(a * r), 0, persian);
		}

		function fromToman() {
			var t = parse(toman.value);
			var r = perUnit();
			amount.value = isNaN(t) || isNaN(r) || r <= 0 ? '' : format(t / r, 2, persian);
		}

		amount.addEventListener('input', fromAmount);
		toman.addEventListener('input', fromToman);
		select.addEventListener('change', fromAmount);
		box.setAttribute('data-netarz-ready', '1');
		fromAmount();
	}

	function fromBrowserMode(data) {
		document.querySelectorAll('[data-netarz-convert]:not([data-netarz-rates])').forEach(function (box) {
			setup(box, (data && data.rows) || []);
		});
	}

	function init() {
		document.querySelectorAll('[data-netarz-convert][data-netarz-rates]').forEach(function (box) {
			var rows;
			try {
				rows = JSON.parse(box.getAttribute('data-netarz-rates'));
			} catch (e) {
				return;
			}
			setup(box, Array.isArray(rows) ? rows : []);
		});

		if (window.netarzFxData) {
			fromBrowserMode(window.netarzFxData);
		}
		document.addEventListener('netarz-fx:rates', function (e) { fromBrowserMode(e.detail); });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
