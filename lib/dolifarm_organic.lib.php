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
 * \file    lib/dolifarm_organic.lib.php
 * \ingroup dolifarm
 * \brief   Check of the plant protection products for organic production (Reg. EU 2021/1165, annex I)
 *
 * The check of a product has three levels:
 *  1. all its active substances are in annex I (table llx_dolifarm_organic_substance), with their conditions;
 *  2. the product is authorised in Italy at the date (catalogue of the Ministry of Health, copied on the agrodrug);
 *  3. the uses of the label are recorded (crop, pest, dose): the treatment is then compared with them by doliTrace.
 * Result: yes, no, verify. A product that is only "to verify" can be confirmed by hand on its card (user and date
 * are kept); a product with result "no" cannot.
 */

/**
 * Rules of annex I, active ones, with the compiled patterns of their names
 *
 * @param	DoliDB	$db		Database handler
 * @return	array<string,stdClass>	Code => rule (with ->patterns: array<string,string> token => regex)
 */
function dolifarmOrganicRules($db)
{
	static $cache = null;

	if ($cache !== null) {
		return $cache;
	}
	$cache = array();
	$resql = $db->query("SELECT code, annex_section, annex_ref, cas, label, label_en, conditions, check_type, match_tokens, annex_version FROM ".$db->prefix()."dolifarm_organic_substance WHERE active = 1 ORDER BY position, code");
	if (!$resql) {
		return $cache;
	}
	while ($obj = $db->fetch_object($resql)) {
		$obj->patterns = array();
		foreach (explode('|', (string) $obj->match_tokens) as $token) {
			$token = strtoupper(trim($token));
			if ($token !== '') {
				// Whole words: ACETO must not match ACETOCHLOR
				$obj->patterns[$token] = '/(?<![A-Z0-9])'.preg_quote($token, '/').'(?![A-Z0-9])/u';
			}
		}
		$cache[(string) $obj->code] = $obj;
	}
	$db->free($resql);
	return $cache;
}

/**
 * Rule of annex I for the name of a substance: the longest matching token wins (TRIBASIC COPPER SULFATE before COPPER SULFATE)
 *
 * @param	string					$name	Name of the substance (catalogue or card)
 * @param	array<string,stdClass>	$rules	Rules of dolifarmOrganicRules()
 * @return	stdClass|null					Rule, null if the substance is not in annex I
 */
function dolifarmOrganicMatch($name, $rules)
{
	$name = strtoupper(trim((string) $name));
	if ($name === '') {
		return null;
	}
	$best = null;
	$bestlen = 0;
	foreach ($rules as $rule) {
		foreach ($rule->patterns as $token => $regex) {
			if (strlen($token) > $bestlen && preg_match($regex, $name)) {
				$best = $rule;
				$bestlen = strlen($token);
			}
		}
	}
	return $best;
}

/**
 * Check of an agrodrug for organic production
 *
 * @param	DoliDB		$db			Database handler
 * @param	int			$id			Id of the agrodrug
 * @param	int|null	$date		Date of the use (null = today), for the authorisation
 * @return	array{result:string,reasons:string[],conditions:string[],substances:array<int,array{name:string,code:string,label:string}>,source:string,confirmed:bool}
 */
function dolifarmOrganicAgroDrugCheck($db, $id, $date = null)
{
	global $langs;

	$langs->load('dolifarm@dolifarm');
	$check = array('result' => 'verify', 'reasons' => array(), 'conditions' => array(), 'substances' => array(), 'source' => '', 'confirmed' => false);
	$sql = "SELECT a.rowid, a.reg_number, a.active_substance, a.auth_expiration_date, a.admin_status, a.date_revocation, a.organic_allowed, a.organic_confirm_date, u.login";
	$sql .= " FROM ".$db->prefix()."dolifarm_agrodrug as a LEFT JOIN ".$db->prefix()."user as u ON u.rowid = a.organic_confirm_user";
	$sql .= " WHERE a.rowid = ".((int) $id);
	$resql = $db->query($sql);
	$agrodrug = $resql ? $db->fetch_object($resql) : null;
	if (!$agrodrug) {
		$check['reasons'][] = $langs->transnoentities('OrganicCheckNoProduct');
		return $check;
	}
	if ($date === null) {
		$date = dol_now();
	}
	$day = dol_print_date($date, '%Y-%m-%d', 'tzserver');
	$no = array();
	$verify = array();

	// Level 2: authorisation in Italy at the date
	dol_include_once('/dolifarm/class/dolifarmpppsync.class.php');
	$catalog = DoliFarmPppSync::fetchCatalog($db, (string) $agrodrug->reg_number);
	$status = DoliFarmPppSync::statusClass($agrodrug->admin_status);
	$expiry = ($agrodrug->auth_expiration_date ? substr((string) $agrodrug->auth_expiration_date, 0, 10) : '');
	$revoked = ($agrodrug->date_revocation ? substr((string) $agrodrug->date_revocation, 0, 10) : '');
	if (!$catalog) {
		$verify[] = $langs->transnoentities('OrganicCheckNotInCatalog');
	} elseif ($status == 'revoked' && ($revoked === '' || $revoked <= $day)) {
		$no[] = $langs->transnoentities('OrganicCheckRevoked', $revoked !== '' ? dol_print_date($db->jdate($revoked), 'day') : '-');
	} elseif ($status == 'suspended') {
		$no[] = $langs->transnoentities('OrganicCheckSuspended');
	} elseif ($expiry !== '' && $expiry < $day) {
		$no[] = $langs->transnoentities('OrganicCheckExpired', dol_print_date($db->jdate($expiry), 'day'));
	}

	// Level 1: substances in annex I. Official names of the catalogue, or the text of the card
	if ($catalog && trim((string) $catalog->substances) !== '') {
		$check['source'] = 'catalog';
		$names = explode('|', (string) $catalog->substances);
	} else {
		$check['source'] = 'card';
		$names = preg_split('/\s*[+;|\/]\s*/', (string) $agrodrug->active_substance);
	}
	$rules = dolifarmOrganicRules($db);
	$herbicide = ($catalog && stripos((string) $catalog->activity, 'DISERBANT') !== false);
	$pheromone = ($catalog && stripos((string) $catalog->activity, 'FEROMON') !== false);
	foreach ($names as $name) {
		$name = trim((string) $name);
		if ($name === '') {
			continue;
		}
		$rule = dolifarmOrganicMatch($name, $rules);
		$check['substances'][] = array('name' => $name, 'code' => $rule ? (string) $rule->code : '', 'label' => $rule ? (string) $rule->label : '');
		if (!$rule) {
			if ($check['source'] == 'catalog') {
				$no[] = $langs->transnoentities('OrganicCheckSubstanceNotListed', $name);
			} else {
				$verify[] = $langs->transnoentities('OrganicCheckSubstanceUnknown', $name);
			}
			continue;
		}
		if ((string) $rule->conditions !== '') {
			$check['conditions'][(string) $rule->code] = $rule->label.': '.$rule->conditions;
		}
		switch ((string) $rule->check_type) {
			case 'NO_HERBICIDE':
				if ($herbicide) {
					$no[] = $langs->transnoentities('OrganicCheckHerbicide', $name);
				}
				break;
			case 'PHEROMONE':
				if (!$pheromone) {
					$verify[] = $langs->transnoentities('OrganicCheckPheromone', $name);
				}
				break;
			case 'TRAPS':
				$verify[] = $langs->transnoentities('OrganicCheckTraps', $name);
				break;
			case 'VERIFY':
				$verify[] = $langs->transnoentities('OrganicCheckCondition', $name);
				break;
		}
	}
	if (empty($check['substances'])) {
		$verify[] = $langs->transnoentities('OrganicCheckNoSubstance');
	}

	// Level 3: uses of the label recorded on the card (crop, pest, dose). doliTrace compares the treatment with them.
	$resql = $db->query("SELECT COUNT(*) as nb FROM ".$db->prefix()."dolifarm_agrodrug_use WHERE fk_agrodrug = ".((int) $id));
	$obj = $resql ? $db->fetch_object($resql) : null;
	if (!$obj || (int) $obj->nb == 0) {
		$verify[] = $langs->transnoentities('OrganicCheckNoUses');
	}

	$check['confirmed'] = !empty($agrodrug->organic_allowed);
	if (count($no)) {
		$check['result'] = 'no';
		$check['reasons'] = array_values(array_unique($no));
	} elseif (count($verify) && !$check['confirmed']) {
		$check['result'] = 'verify';
		$check['reasons'] = array_values(array_unique($verify));
	} else {
		$check['result'] = 'yes';
		if (count($verify)) {
			$check['reasons'][] = $langs->transnoentities('OrganicCheckConfirmedBy', (string) $agrodrug->login, $agrodrug->organic_confirm_date ? dol_print_date($db->jdate($agrodrug->organic_confirm_date), 'day') : '-');
		}
	}
	return $check;
}

/**
 * Badge of the result of the check
 *
 * @param	string	$result		yes, no, verify
 * @return	string				HTML
 */
function dolifarmOrganicBadge($result)
{
	global $langs;

	$langs->load('dolifarm@dolifarm');
	$map = array('yes' => array('OrganicResultYes', 'status4'), 'no' => array('OrganicResultNo', 'status8'), 'verify' => array('OrganicResultVerify', 'status1'));
	if (!isset($map[$result])) {
		return '';
	}
	return dolGetBadge($langs->trans($map[$result][0]), '', $map[$result][1]);
}
