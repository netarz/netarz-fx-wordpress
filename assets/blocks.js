/**
 * NetArz FX blocks in the editor: netarz-fx/rate, netarz-fx/rates, netarz-fx/convert.
 *
 * Plain ES5 on the wp.* globals, no build step. Titles, icons and attributes
 * come from each block.json (registered in PHP); this file adds the sidebar
 * controls and a live preview through ServerSideRender. The blocks are
 * dynamic, so save() returns null and the page is drawn in PHP.
 */
(function (wp) {
	'use strict';

	if (!wp || !wp.blocks || !wp.element || !wp.blockEditor || !wp.components) {
		return;
	}

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;

	var cfg = window.netarzFxBlocks || { l: {}, currencies: [] };
	var L = cfg.l || {};

	function fieldOptions() {
		return [
			{ value: 'sell', label: L.sell || 'Sell' },
			{ value: 'buy', label: L.buy || 'Buy' },
			{ value: 'mid', label: L.mid || 'Average' }
		];
	}

	function digitsControl(a, set) {
		return el(c.SelectControl, {
			label: L.digits || 'Digits',
			value: a.digits,
			options: [
				{ value: '', label: L.digitsAuto || 'As in the plugin settings' },
				{ value: 'persian', label: L.persian || 'Persian digits' },
				{ value: 'latin', label: L.latin || 'Latin digits' }
			],
			onChange: function (v) { set({ digits: v }); }
		});
	}

	function codesControl(a, set) {
		var known = (cfg.currencies || []).map(function (o) { return o.value; }).join(', ');
		return el(c.TextControl, {
			label: L.currencies || 'Currencies',
			help: known ? (L.codesHelp || '') + ' ' + known : (L.codesHelp || ''),
			value: a.currencies,
			onChange: function (v) { set({ currencies: v.toUpperCase() }); }
		});
	}

	function toggle(label, key, a, set) {
		return el(c.ToggleControl, {
			label: label,
			checked: !!a[key],
			onChange: function (v) {
				var next = {};
				next[key] = v;
				set(next);
			}
		});
	}

	function notices() {
		var out = [];
		if (!cfg.hasKey) {
			out.push(el(c.Notice, { key: 'key', status: 'warning', isDismissible: false },
				(L.noKey || '') + ' ',
				el('a', { href: cfg.settingsUrl }, L.openSettings || 'Settings')
			));
		} else if (cfg.browserMode) {
			out.push(el(c.Notice, { key: 'browser', status: 'info', isDismissible: false }, L.browserNote || ''));
		}
		return out;
	}

	function makeEdit(name, controls) {
		return function (props) {
			var a = props.attributes;
			var set = props.setAttributes;
			return el(Fragment, null,
				el(InspectorControls, null,
					el(c.PanelBody, { title: L.settings || 'Settings', initialOpen: true }, controls(a, set))
				),
				el('div', useBlockProps(),
					notices(),
					ServerSideRender ? el(ServerSideRender, { block: name, attributes: a }) : null
				)
			);
		};
	}

	var save = function () { return null; };

	wp.blocks.registerBlockType('netarz-fx/rate', {
		edit: makeEdit('netarz-fx/rate', function (a, set) {
			var currency = cfg.currencies && cfg.currencies.length
				? el(c.SelectControl, {
					label: L.currency || 'Currency',
					value: a.currency,
					options: cfg.currencies,
					onChange: function (v) { set({ currency: v }); }
				})
				: el(c.TextControl, {
					label: L.currency || 'Currency',
					help: L.codesHelp || '',
					value: a.currency,
					onChange: function (v) { set({ currency: v.toUpperCase() }); }
				});
			return [
				el('div', { key: 'currency' }, currency),
				el(c.SelectControl, { key: 'field', label: L.rate || 'Rate', value: a.field, options: fieldOptions(), onChange: function (v) { set({ field: v }); } }),
				el('div', { key: 'name' }, toggle(L.showName || 'Show the currency name', 'showName', a, set)),
				el('div', { key: 'digits' }, digitsControl(a, set))
			];
		}),
		save: save
	});

	wp.blocks.registerBlockType('netarz-fx/rates', {
		edit: makeEdit('netarz-fx/rates', function (a, set) {
			return [
				el('div', { key: 'codes' }, codesControl(a, set)),
				el('div', { key: 'buy' }, toggle(L.buy || 'Buy', 'showBuy', a, set)),
				el('div', { key: 'sell' }, toggle(L.sell || 'Sell', 'showSell', a, set)),
				el('div', { key: 'mid' }, toggle(L.mid || 'Average', 'showMid', a, set)),
				el('div', { key: 'change' }, toggle(L.showChange || 'Show the change since yesterday', 'showChange', a, set)),
				el('div', { key: 'updated' }, toggle(L.showUpdated || 'Show when the rates were updated', 'showUpdated', a, set)),
				el('div', { key: 'digits' }, digitsControl(a, set))
			];
		}),
		save: save
	});

	wp.blocks.registerBlockType('netarz-fx/convert', {
		edit: makeEdit('netarz-fx/convert', function (a, set) {
			return [
				el('div', { key: 'codes' }, codesControl(a, set)),
				el(c.SelectControl, { key: 'field', label: L.rate || 'Rate', value: a.field, options: fieldOptions(), onChange: function (v) { set({ field: v }); } }),
				el(c.TextControl, {
					key: 'amount',
					type: 'number',
					min: 0,
					label: L.amount || 'Starting amount',
					value: String(a.amount),
					onChange: function (v) { var n = parseFloat(v); set({ amount: isNaN(n) || n < 0 ? 1 : n }); }
				}),
				el('div', { key: 'updated' }, toggle(L.showUpdated || 'Show when the rates were updated', 'showUpdated', a, set)),
				el('div', { key: 'digits' }, digitsControl(a, set))
			];
		}),
		save: save
	});
})(window.wp);
