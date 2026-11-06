#!/usr/bin/env php
<?php
/* Copyright (C) 2026      Luigi Grillo - doliFarm.com	<info@dolifarm.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * \file    scripts/demo_data.php
 * \ingroup dolifarm
 * \brief   Demo data of the doliFarm suite: two farms in Sicily and an olive mill, three seasons
 *
 * Only for a demo instance, never in production. The data are made with the classes of the modules (validations,
 * stock movements, register of the treatments), so they behave like the data typed in the pages. The script can be run
 * again: what is already there (same ref) is kept.
 *
 * Usage: php scripts/demo_data.php <admin login> --demo
 * The data are made by DoliFarmDemo (class/dolifarmdemo.class.php), also used by the setup page admin/demo.php.
 */

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: you are using PHP for CGI. To execute ".basename(__FILE__)." from command line, you must use PHP for CLI mode.\n";
	exit(1);
}
if (empty($argv[1]) || empty($argv[2]) || $argv[2] !== '--demo') {
	echo "Usage: php ".basename(__FILE__)." <admin login> --demo\n";
	echo "Creates the demo data of the doliFarm suite. Only for a demo instance, never in production.\n";
	exit(1);
}

define('NOSESSION', 1);
define('NOREQUIREHTML', 1);

$res = 0;
foreach (array(__DIR__.'/../../../master.inc.php', __DIR__.'/../../../../master.inc.php') as $tmpfile) {
	if (!$res && file_exists($tmpfile)) {
		$res = include $tmpfile;
	}
}
if (!$res) {
	echo "Error: master.inc.php not found\n";
	exit(1);
}

require_once __DIR__.'/../class/dolifarmdemo.class.php';

/*
 * Main
 */

$user = new User($db);
if ($user->fetch(0, $argv[1]) <= 0) {
	echo "Error: user ".$argv[1]." not found\n";
	exit(1);
}
if (empty($user->admin)) {
	echo "Error: user ".$argv[1]." is not an administrator\n";
	exit(1);
}
$user->getrights();

$demo = new DoliFarmDemo($db, $user);
exit($demo->run() ? 1 : 0);
