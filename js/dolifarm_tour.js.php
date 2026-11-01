<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    js/dolifarm_tour.js.php
 * \ingroup dolifarm
 * \brief   Guided tours of the doliFarm and doliTrace pages: loaded on every page, active only on the pages that have
 *          a tour (button "Guida", or automatic start with the parameter dolifarmtour=1). No external library.
 */

if (!defined('NOREQUIRESOC')) {
	define('NOREQUIRESOC', '1');
}
if (!defined('NOCSRFCHECK')) {
	define('NOCSRFCHECK', 1);
}
if (!defined('NOTOKENRENEWAL')) {
	define('NOTOKENRENEWAL', 1);
}
if (!defined('NOREQUIREMENU')) {
	define('NOREQUIREMENU', 1);
}
if (!defined('NOREQUIREHTML')) {
	define('NOREQUIREHTML', 1);
}
if (!defined('NOREQUIREAJAX')) {
	define('NOREQUIREAJAX', '1');
}

// Load Dolibarr environment
$res = 0;
if (!$res && !empty($_SERVER["CONTEXT_DOCUMENT_ROOT"])) {
	$res = @include $_SERVER["CONTEXT_DOCUMENT_ROOT"]."/main.inc.php";
}
if (!$res && file_exists("../../main.inc.php")) {
	$res = @include "../../main.inc.php";
}
if (!$res && file_exists("../../../main.inc.php")) {
	$res = @include "../../../main.inc.php";
}
if (!$res) {
	die("Include of main fails");
}

top_httphead('text/javascript; charset=UTF-8');
// The texts are in the language of the user: cache only in the browser, briefly
header('Cache-Control: private, max-age=600');

if (!isModEnabled('dolifarm') || empty($user->id) || getDolGlobalInt('DOLIFARM_TOUR_DISABLED')) {
	print '/* doliFarm tours disabled */';
	exit;
}

dol_include_once('/dolifarm/lib/dolifarm_start.lib.php');
$config = array(
	'tours' => dolifarmTourDefinitions($langs),
	'button' => !getDolGlobalInt('DOLIFARM_TOUR_HIDE_BUTTON'),
	'labels' => array(
		'guide' => $langs->transnoentities('TourButton'),
		'guideHelp' => $langs->transnoentities('TourButtonHelp'),
		'next' => $langs->transnoentities('Next'),
		'prev' => $langs->transnoentities('Previous'),
		'close' => $langs->transnoentities('Close'),
		'done' => $langs->transnoentities('TourDone'),
		'continue' => $langs->transnoentities('TourContinue'),
		'of' => $langs->transnoentities('TourStepOf'),
	),
);
print 'window.dolifarmTourConfig = '.json_encode($config, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG).";\n";
?>
(function () {
	'use strict';
	var cfg = window.dolifarmTourConfig;
	if (!cfg || !cfg.tours) {
		return;
	}
	var params = new URLSearchParams(window.location.search);

	function currentTour() {
		var path = window.location.pathname;
		var action = params.get('action') || '';
		for (var key in cfg.tours) {
			var tour = cfg.tours[key];
			if (path.slice(-tour.path.length) === tour.path && (tour.action === '' || tour.action === action)) {
				return key;
			}
		}
		return '';
	}

	function tourUrl(key) {
		var tour = cfg.tours[key];
		return tour.path + '?' + (tour.action ? 'action=' + encodeURIComponent(tour.action) + '&' : '') + 'dolifarmtour=1';
	}

	function visible(el) {
		return el && el.offsetParent !== null;
	}

	function esc(text) {
		var div = document.createElement('div');
		div.textContent = text;
		return div.innerHTML;
	}

	function stepTitle(step, el) {
		if (step.title) {
			return step.title;
		}
		// The label of the row of the form
		var cell = el.querySelector('td');
		return cell ? cell.textContent.replace(/\s+/g, ' ').trim() : '';
	}

	function Tour(key) {
		this.key = key;
		this.tour = cfg.tours[key];
		this.steps = [];
		for (var i = 0; i < this.tour.steps.length; i++) {
			var el = document.querySelector(this.tour.steps[i].el);
			if (visible(el)) {
				this.steps.push({ step: this.tour.steps[i], el: el });
			}
		}
		this.index = 0;
	}

	Tour.prototype.start = function () {
		if (!this.steps.length) {
			return;
		}
		var self = this;
		this.spot = document.createElement('div');
		this.spot.className = 'dolifarm-tour-spot';
		this.pop = document.createElement('div');
		this.pop.className = 'dolifarm-tour-pop';
		this.pop.setAttribute('role', 'dialog');
		document.body.appendChild(this.spot);
		document.body.appendChild(this.pop);
		this.onKey = function (e) {
			if (e.key === 'Escape') {
				self.stop();
			} else if (e.key === 'ArrowRight') {
				self.go(self.index + 1);
			} else if (e.key === 'ArrowLeft') {
				self.go(self.index - 1);
			}
		};
		this.onResize = function () {
			self.place();
		};
		document.addEventListener('keydown', this.onKey);
		window.addEventListener('resize', this.onResize);
		this.go(0);
	};

	Tour.prototype.stop = function () {
		document.removeEventListener('keydown', this.onKey);
		window.removeEventListener('resize', this.onResize);
		if (this.spot) {
			this.spot.remove();
		}
		if (this.pop) {
			this.pop.remove();
		}
	};

	Tour.prototype.go = function (index) {
		if (index < 0 || index >= this.steps.length) {
			return;
		}
		this.index = index;
		var item = this.steps[index];
		var last = (index === this.steps.length - 1);
		var labels = cfg.labels;
		var html = '<div class="dolifarm-tour-head"><span class="dolifarm-tour-title">' + esc(stepTitle(item.step, item.el)) + '</span>'
			+ '<span class="dolifarm-tour-count">' + (index + 1) + ' ' + esc(labels.of) + ' ' + this.steps.length + '</span></div>'
			+ '<div class="dolifarm-tour-text">' + esc(item.step.text) + '</div><div class="dolifarm-tour-buttons">'
			+ '<a href="#" class="dolifarm-tour-close">' + esc(labels.close) + '</a>';
		if (index > 0) {
			html += '<a href="#" class="button smallpaddingimp dolifarm-tour-prev">' + esc(labels.prev) + '</a>';
		}
		if (!last) {
			html += '<a href="#" class="button smallpaddingimp dolifarm-tour-next">' + esc(labels.next) + '</a>';
		} else if (this.tour.next && cfg.tours[this.tour.next]) {
			html += '<a href="' + esc(tourUrl(this.tour.next)) + '" class="button smallpaddingimp">' + esc(labels['continue'] + ' ' + cfg.tours[this.tour.next].title) + '</a>';
		} else {
			html += '<a href="#" class="button smallpaddingimp dolifarm-tour-close">' + esc(labels.done) + '</a>';
		}
		html += '</div>';
		this.pop.innerHTML = html;
		var self = this;
		this.pop.querySelectorAll('.dolifarm-tour-close').forEach(function (a) {
			a.addEventListener('click', function (e) { e.preventDefault(); self.stop(); });
		});
		var prev = this.pop.querySelector('.dolifarm-tour-prev');
		if (prev) {
			prev.addEventListener('click', function (e) { e.preventDefault(); self.go(self.index - 1); });
		}
		var next = this.pop.querySelector('.dolifarm-tour-next');
		if (next) {
			next.addEventListener('click', function (e) { e.preventDefault(); self.go(self.index + 1); });
		}
		item.el.scrollIntoView({ block: 'center' });
		this.place();
	};

	Tour.prototype.place = function () {
		var el = this.steps[this.index].el;
		var r = el.getBoundingClientRect();
		var sx = window.scrollX, sy = window.scrollY, pad = 4;
		this.spot.style.left = (r.left + sx - pad) + 'px';
		this.spot.style.top = (r.top + sy - pad) + 'px';
		this.spot.style.width = (r.width + 2 * pad) + 'px';
		this.spot.style.height = (r.height + 2 * pad) + 'px';
		var pw = this.pop.offsetWidth, ph = this.pop.offsetHeight;
		var left = Math.min(Math.max(8, r.left + sx), sx + document.documentElement.clientWidth - pw - 8);
		var top = r.bottom + sy + 12;
		if (r.bottom + ph + 20 > window.innerHeight && r.top - ph - 12 > 0) {
			top = r.top + sy - ph - 12;
		}
		this.pop.style.left = left + 'px';
		this.pop.style.top = top + 'px';
	};

	function init() {
		var key = currentTour();
		if (!key) {
			return;
		}
		if (cfg.button) {
			var button = document.createElement('a');
			button.href = '#';
			button.className = 'dolifarm-tour-button';
			button.title = cfg.labels.guideHelp;
			button.innerHTML = '<span class="fa fa-question-circle"></span> ' + esc(cfg.labels.guide);
			button.addEventListener('click', function (e) {
				e.preventDefault();
				new Tour(key).start();
			});
			document.body.appendChild(button);
		}
		if (params.get('dolifarmtour') === '1') {
			// After the select2 and the other widgets of the page
			window.setTimeout(function () { new Tour(key).start(); }, 400);
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
