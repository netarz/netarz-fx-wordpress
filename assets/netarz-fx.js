/**
 * NetArz FX: browser mode. Loaded only when the site owner chose
 * "From the visitor's browser" and a page contains a rate.
 *
 * One request per page for every code on it, cached per visitor in
 * sessionStorage for the configured cache time. The rows and meta are also
 * left on window.netarzFxData and announced with a "netarz-fx:rates" event,
 * so the converter can use the same answer instead of asking again.
 */
(function () {
	'use strict';

	var cfg = window.netarzFx;
	if (!cfg || !cfg.key || !cfg.codes || !cfg.codes.length) {
		return;
	}

	var FA = { '0': '۰', '1': '۱', '2': '۲', '3': '۳', '4': '۴', '5': '۵', '6': '۶', '7': '۷', '8': '۸', '9': '۹', ',': '٬', '.': '٫', '%': '٪' };

	// A shortcode may ask for its own digits (data-netarz-digits); otherwise the settings decide.
	function persianFor(el) {
		var box = el && el.closest ? el.closest('[data-netarz-digits]') : null;
		return box ? box.getAttribute('data-netarz-digits') === 'persian' : !!cfg.persian;
	}

	function digits(s, persian) {
		return persian ? String(s).replace(/[0-9,.%]/g, function (c) { return FA[c]; }) : String(s);
	}

	function number(n, persian) {
		return digits(Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 }), persian);
	}

	function priceText(row, field, persian) {
		var text = cfg.toman.replace('%s', number(row[field], persian));
		if (row.unit > 1) {
			text += ' ' + cfg.perUnits.replace('%s', number(row.unit, persian));
		}
		return text;
	}

	function nameOf(row) {
		return (cfg.nameFa && row.name) ? row.name : (row.name_en || row.code);
	}

	function paintChange(el, value) {
		el.classList.remove('netarz-fx-up', 'netarz-fx-down', 'netarz-fx-flat');
		if (value === null || value === undefined || isNaN(Number(value))) {
			el.classList.add('netarz-fx-flat');
			el.textContent = '-';
			return;
		}
		var v = Math.round(Number(value) * 100) / 100;
		var sign = v > 0 ? '+' : (v < 0 ? '−' : '');
		el.classList.add(v > 0 ? 'netarz-fx-up' : (v < 0 ? 'netarz-fx-down' : 'netarz-fx-flat'));
		el.textContent = sign + digits(Math.abs(v).toFixed(2) + '%', persianFor(el));
	}

	function when(iso, persian) {
		var d = new Date(iso);
		if (isNaN(d.getTime())) {
			return '';
		}
		var opts = { hour: '2-digit', minute: '2-digit', hourCycle: 'h23' };
		var day = { year: 'numeric', month: '2-digit', day: '2-digit' };
		var fmt;
		try {
			opts.timeZone = day.timeZone = cfg.tz;
			fmt = new Intl.DateTimeFormat('en-GB', opts);
		} catch (e) {
			delete opts.timeZone;
			delete day.timeZone;
			fmt = new Intl.DateTimeFormat('en-GB', opts);
		}
		var dayFmt = new Intl.DateTimeFormat('en-CA', day);
		var text = fmt.format(d);
		if (dayFmt.format(d) !== dayFmt.format(new Date())) {
			text = dayFmt.format(d) + ' ' + text;
		}
		return digits(text, persian);
	}

	function paintUpdated(meta) {
		document.querySelectorAll('[data-netarz-updated]').forEach(function (el) {
			if (!meta || !meta.as_of) {
				return;
			}
			var persian = persianFor(el);
			var at = when(meta.as_of, persian);
			if (!at) {
				return;
			}
			el.textContent = meta.delayed_minutes > 0
				? cfg.updatedDelayed.replace('%1$s', at).replace('%2$s', digits(meta.delayed_minutes, persian))
				: cfg.updated.replace('%s', at);
		});
	}

	function paint(rows, meta) {
		var byCode = {};
		rows.forEach(function (r) { byCode[String(r.code).toUpperCase()] = r; });

		document.querySelectorAll('.netarz-fx-rate[data-netarz-code]').forEach(function (el) {
			var row = byCode[el.getAttribute('data-netarz-code')];
			var field = el.getAttribute('data-netarz-field') || 'sell';
			if (field === 'change') {
				paintChange(el, row ? row.change_24h_percent : null);
				return;
			}
			if (!row || row[field] == null) {
				el.textContent = cfg.unavailable;
				return;
			}
			var text = priceText(row, field, persianFor(el));
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

		paintUpdated(meta);

		window.netarzFxData = { rows: rows, meta: meta || {} };
		try {
			document.dispatchEvent(new CustomEvent('netarz-fx:rates', { detail: window.netarzFxData }));
		} catch (e) { /* very old browser: the converter reads window.netarzFxData */ }
	}

	function fail() {
		document.querySelectorAll('.netarz-fx-rate[data-netarz-code]').forEach(function (el) {
			el.textContent = el.getAttribute('data-netarz-field') === 'change' ? '-' : cfg.unavailable;
		});
	}

	var codes = cfg.codes.join(',');
	var cacheKey = 'netarz-fx:' + codes;

	try {
		var hit = JSON.parse(window.sessionStorage.getItem(cacheKey) || 'null');
		if (hit && Date.now() - hit.t < cfg.cacheMs) {
			paint(hit.rows, hit.meta);
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
			paint(r.body.data, r.body.meta);
			try {
				window.sessionStorage.setItem(cacheKey, JSON.stringify({ t: Date.now(), rows: r.body.data, meta: r.body.meta }));
			} catch (e) { /* ignore */ }
		})
		.catch(fail);
})();
