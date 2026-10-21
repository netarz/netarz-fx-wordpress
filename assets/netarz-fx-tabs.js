/**
 * NetArz FX: tabs for the "NetArz currency and gold rates" widget.
 *
 * Without this script every panel shows, one under the other, each with its
 * own title. With it, the tab buttons appear and one panel shows at a time.
 * Arrow keys move between tabs. No request is made.
 */
(function () {
	'use strict';

	function setup(box) {
		var list = box.querySelector('[role="tablist"]');
		if (!list) {
			return;
		}
		var tabs = Array.prototype.slice.call(list.querySelectorAll('[role="tab"]'));
		var panels = tabs.map(function (tab) {
			return document.getElementById(tab.getAttribute('aria-controls'));
		});

		function show(index, focus) {
			tabs.forEach(function (tab, i) {
				var on = i === index;
				tab.setAttribute('aria-selected', on ? 'true' : 'false');
				tab.tabIndex = on ? 0 : -1;
				if (panels[i]) {
					panels[i].hidden = !on;
				}
			});
			if (focus) {
				tabs[index].focus();
			}
		}

		tabs.forEach(function (tab, i) {
			tab.addEventListener('click', function () { show(i, false); });
			tab.addEventListener('keydown', function (e) {
				var next = null;
				if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
					var forward = (e.key === 'ArrowRight') !== (getComputedStyle(box).direction === 'rtl');
					next = (i + (forward ? 1 : tabs.length - 1)) % tabs.length;
				} else if (e.key === 'Home') {
					next = 0;
				} else if (e.key === 'End') {
					next = tabs.length - 1;
				}
				if (next !== null) {
					e.preventDefault();
					show(next, true);
				}
			});
		});

		list.hidden = false;
		box.classList.add('netarz-fx-tabs-ready');
		show(0, false);
	}

	function init() {
		Array.prototype.forEach.call(document.querySelectorAll('[data-netarz-tabs]'), setup);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
