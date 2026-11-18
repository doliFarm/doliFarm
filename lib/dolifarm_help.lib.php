<?php
/* Copyright (C) 2026		Luigi Grillo - doliFarm.com	<info@dolifarm.com>
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
 * \file    dolifarm/lib/dolifarm_help.lib.php
 * \ingroup dolifarm
 * \brief   Help texts by role, for the whole suite: the administration help (setup, dictionaries, scheduled jobs) only
 *          for the administrators
 *
 * A help text that mentions the administration has two keys in the language files: "<key>" for the users, without the
 * setup (with "ask the administrator" when they cannot solve it themselves), and "<key>Admin" for the administrators.
 */

/**
 * Key of a help text for the user
 *
 * @param	string		$key	Key of the text for the users
 * @param	User|null	$who	User, the current user when null
 * @return	string				"<key>Admin" for an administrator, "<key>" for the other users
 */
function dolifarmHelpKey(string $key, $who = null): string
{
	global $user;

	$who = ($who ?? $user);
	return (is_object($who) && !empty($who->admin) ? $key.'Admin' : $key);
}
