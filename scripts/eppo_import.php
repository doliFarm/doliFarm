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
 * \file    scripts/eppo_import.php
 * \ingroup dolifarm
 * \brief   Command line import of the EPPO Codes from the file "XML full" (fullcodes.xml or fullcodes.xml.gz)
 *
 * Usage: php scripts/eppo_import.php /path/to/fullcodes.xml[.gz]
 */

$sapi_type = php_sapi_name();
if (substr($sapi_type, 0, 3) == 'cgi') {
	echo "Error: you are using PHP for CGI. To execute ".basename(__FILE__)." from command line, you must use PHP for CLI mode.\n";
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
	echo "Error: include of master.inc.php fails\n";
	exit(1);
}
/**
 * @var Conf $conf
 * @var DoliDB $db
 * @var Translate $langs
 */

if (empty($argv[1])) {
	echo "Usage: php ".basename(__FILE__)." /path/to/fullcodes.xml[.gz]\n";
	exit(1);
}
if (!isModEnabled('dolifarm')) {
	echo "Error: module dolifarm not enabled\n";
	exit(1);
}

@set_time_limit(0);
$langs->setDefaultLang('auto');
$langs->loadLangs(array('main', 'errors', 'dolifarm@dolifarm'));
dol_include_once('/dolifarm/class/dolifarmeppoimporter.class.php');

$start = microtime(true);
$importer = new DoliFarmEppoImporter($db);
$result = $importer->importFile($argv[1]);
if ($result < 0) {
	echo "Error: ".$importer->error."\n";
	exit(1);
}
echo $langs->transnoentities('EppoImportDone', $importer->nbcodes, $importer->nbnames, $importer->dateexport)." (".round(microtime(true) - $start)." s)\n";
echo $langs->transnoentities('EppoAttribution')."\n";
exit(0);
