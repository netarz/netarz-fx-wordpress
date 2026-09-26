/**
 * NetArz FX: browser mode. Loaded only when the site owner chose
 * "From the visitor's browser" and a page contains a rate.
 *
 * One request per page for every code on it, cached per visitor in
 * sessionStorage for the configured cache time.
 */
(function () {
	'use strict';

	var cfg = window.netarzFx;
	if (!cfg || !cfg.key || !cfg.codes || !cfg.codes.length) {
		return;
	}

	var FA = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹', ',': '٬', '.': '٫' };

	function number(n) {
		var s = Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });
		return cfg.persian ? s.replace(/[0-9,.]/g, function (c) { return FA[c]; }) : s;
	}

	function priceText(row, field) {
		var text = cfg.toman.replace('%s', number(row[field]));
		if (row.unit > 1) {
			text += ' ' + cfg.perUnits.replace('%s', number(row.unit));
		}
		return text;
	}

	function nameOf(row) {
		return (cfg.nameFa && row.name) ? row.name : (row.name_en || row.code);
	}

	function paint(rows) {
		var byCode = {};
		rows.forEach(function (r) { byCode[String(r.code).toUpperCase()] = r; });

		document.querySelectorAll('.netarz-fx-rate[data-netarz-code]').forEach(function (el) {
			var row = byCode[el.getAttribute('data-netarz-code')];
			var field = el.getAttribute('data-netarz-field') || 'sell';
			if (!row || row[field] == null) {
				el.textContent = cfg.unavailable;
				return;
			}
			var text = priceText(row, field);
			if (el.getAttribute('data-netarz-name') === '1') {
				text = nameOf(row) + ': ' + text;
			}
			el.textContent = text;
		});

		document.querySelectorAll('.netarz-fx-name[data-netarz-code]').forEach(function (el) {
			var row = byCode[el.getAttribute('data-netarz-code')];
			if (row) {
				el.textContent = nameOf(row);
			}
		});
	}

	function fail() {
		document.querySelectorAll('.netarz-fx-rate[data-netarz-code]').forEach(function (el) {
			el.textContent = cfg.unavailable;
		});
	}

	var codes = cfg.codes.join(',');
	var cacheKey = 'netarz-fx:' + codes;

	try {
		var hit = JSON.parse(window.sessionStorage.getItem(cacheKey) || 'null');
		if (hit && Date.now() - hit.t < cfg.cacheMs) {
			paint(hit.rows);
			return;
		}
	} catch (e) { /* storage blocked: fetch below */ }

	fetch(cfg.api + '/rates?codes=' + encodeURIComponent(codes), {
		headers: { Authorization: 'Bearer ' + cfg.key }
	})
		.then(function (res) { return res.json().then(function (body) { return { ok: res.ok, body: body }; }); })
		.then(function (r) {
			if (!r.ok || !r.body || !r.body.data) {
				// e.g. origin_not_allowed: this domain is not on the app in netarz.ir/fx
				if (window.console && r.body && r.body.error) {
					window.console.warn('NetArz FX:', r.body.error.code, r.body.error.message);
				}
				fail();
				return;
			}
			paint(r.body.data);
			try {
				window.sessionStorage.setItem(cacheKey, JSON.stringify({ t: Date.now(), rows: r.body.data }));
			} catch (e) { /* ignore */ }
		})
		.catch(fail);
})();
