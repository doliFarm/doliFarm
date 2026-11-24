/* Copyright (C) 2026 Luigi Grillo - doliFarm.com <info@dolifarm.com>
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
 * \file    dolifarm/js/dolifarm_menu.js
 * \ingroup dolifarm
 * \brief   Top menu of the suite (doliFarm, with the sections of doliTrace and doliField) right after Home.
 *          The menu manager of Dolibarr prints its own top menus first and the list is a CSS table (no "order"):
 *          the entry is moved in the page.
 */
(function () {
	function dolifarmMoveTopMenu() {
		var home = document.getElementById('mainmenutd_home');
		var farm = document.getElementById('mainmenutd_dolifarm');
		if (home && farm && home.parentNode === farm.parentNode && home.nextElementSibling !== farm) {
			home.parentNode.insertBefore(farm, home.nextElementSibling);
		}
	}
	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', dolifarmMoveTopMenu);
	} else {
		dolifarmMoveTopMenu();
	}
})();
