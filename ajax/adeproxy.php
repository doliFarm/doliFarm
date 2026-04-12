<?php
/**
 * Script Proxy WMS per Catasto Agenzia Entrate
 * File: custom/dolifarm/ajax/adeproxy.php
 */

// 1. Caricamento Ambiente
$res=0;
if (! $res && file_exists("../../main.inc.php")) $res=@include "../../main.inc.php";
if (! $res && file_exists("../../../main.inc.php")) $res=@include "../../../main.inc.php";
if (! $res) die("Include of main fails");

// 2. Sicurezza
if (!is_object($user) || empty($user->id)) {
    header("HTTP/1.1 403 Forbidden");
    exit;
}

// 3. Configurazione
$wms_url = "https://wms.cartografia.agenziaentrate.gov.it/inspire/wms/ows01.php";
$query_string = $_SERVER['QUERY_STRING'];

if (empty($query_string)) {
    header("HTTP/1.1 400 Bad Request");
    exit;
}

// 4. FIX TOTALE
// Sblocca i caratteri codificati (%3D -> =, %26 -> &, %2F -> /)
$query_string_clean = urldecode($query_string);
$query_string_clean = ltrim($query_string_clean, '?');

$target_url = $wms_url . "?" . $query_string_clean;

// 5. Esecuzione cURL
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $target_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');

$data = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
curl_close($ch);

// 6. Output
if (ob_get_length()) ob_clean();

if ($http_code == 200 && strpos($content_type, 'image') !== false) {
    header("Content-Type: " . $content_type);
    echo $data;
} else {
    // Fallback pixel vuoto
    header("Content-Type: image/png");
    echo base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNkYAAAAAYAAjCB0C8AAAAASUVORK5CYII=');
}
?>