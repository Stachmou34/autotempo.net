<?php
/**
 * Client Qonto (LECTURE SEULE) + calculs de l'onglet Dépenses. Aucun effet de bord à l'inclusion.
 * Compatible PHP 5.6 / 7.2.
 *
 * Clé API rangée HORS de public_html, dans qontoConfigFile() (ex. /home/autotemnet/qonto.ini) :
 *   login  = "mcj-courtage-4402"
 *   secret = "xxxxxxxx"
 * Qonto attend l'en-tête  Authorization: login:secret  (ni "Bearer", ni Base64).
 */
if (isset($_SERVER['SCRIPT_FILENAME']) && basename($_SERVER['SCRIPT_FILENAME']) === 'qonto.php') { http_response_code(403); exit; }

if (!defined('QONTO_BASE')) { define('QONTO_BASE', 'https://thirdparty.qonto.com'); }
define('QONTO_CACHE_TTL', 1800); // 30 min : évite d'interroger Qonto à chaque affichage

function qontoConfigFile() { return dirname(dirname(__FILE__)) . '/qonto.ini'; }
function qontoCacheDir() { return dirname(dirname(__FILE__)) . '/mcj_api_data/qonto_cache'; }

/** Identifiants Qonto, ou null si qonto.ini est absent / incomplet. */
function qontoConfig() {
    $f = qontoConfigFile();
    if (!is_file($f)) { return null; }
    $c = @parse_ini_file($f);
    if (!$c || empty($c['login']) || empty($c['secret'])) { return null; }
    return array('login' => trim($c['login']), 'secret' => trim($c['secret']));
}

/** Pourquoi qontoConfig() renvoie null, en clair ('' si tout va bien). */
function qontoProbleme() {
    $f = qontoConfigFile();
    if (!file_exists($f)) { return 'le fichier ' . $f . ' n\'existe pas'; }
    if (!is_readable($f)) {
        $uid = @fileowner($f);
        $proprio = ($uid !== false && function_exists('posix_getpwuid') && ($p = @posix_getpwuid($uid))) ? $p['name'] : 'un autre compte';
        $moi = (function_exists('posix_geteuid') && ($u = @posix_getpwuid(posix_geteuid()))) ? $u['name'] : get_current_user();
        return 'le fichier ' . $f . ' appartient à ' . $proprio . ' : le site (compte ' . $moi . ') ne peut pas le lire';
    }
    $c = @parse_ini_file($f);
    if (!$c) { return 'le fichier ' . $f . ' est mal écrit (attendu : login = "…" et secret = "…")'; }
    if (empty($c['login']) || empty($c['secret'])) { return 'il manque login ou secret dans ' . $f; }
    return '';
}

/** GET JSON sur l'API Qonto ; lève une exception explicite en cas d'erreur. */
function qontoGet($cfg, $path) {
    $url = QONTO_BASE . $path;
    $hdr = array('Authorization: ' . $cfg['login'] . ':' . $cfg['secret'], 'Accept: application/json');
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_HTTPHEADER => $hdr,
                                     CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10));
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($body === false) { throw new Exception('Qonto injoignable : ' . $err); }
    } else {
        $ctx = stream_context_create(array('http' => array('method' => 'GET', 'header' => implode("\r\n", $hdr),
                                                           'timeout' => 30, 'ignore_errors' => true)));
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) { throw new Exception('Qonto injoignable'); }
        $code = (isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m)) ? (int) $m[1] : 0;
    }
    $data = json_decode($body, true);
    if ($code !== 200 || !is_array($data)) {
        $detail = (is_array($data) && isset($data['errors'][0]['detail'])) ? $data['errors'][0]['detail'] : substr((string) $body, 0, 120);
        throw new Exception('Qonto HTTP ' . $code . ' : ' . $detail);
    }
    return $data;
}

/** Âge (timestamp) de la donnée la plus ancienne servie depuis le cache pendant cette page. */
function qontoDonneesDu($ts = null) {
    static $min = null;
    if ($ts !== null && ($min === null || $ts < $min)) { $min = $ts; }
    return $min === null ? time() : $min;
}

/** Résultat de $fn mis en cache (fichier 0600 hors public_html) pendant QONTO_CACHE_TTL. */
function qontoCached($cle, $fn, $refresh) {
    $dir = qontoCacheDir();
    $file = $dir . '/' . md5($cle) . '.json';
    if (!$refresh && is_file($file) && filemtime($file) > time() - QONTO_CACHE_TTL) {
        $d = json_decode((string) file_get_contents($file), true);
        if (is_array($d)) { qontoDonneesDu(filemtime($file)); return $d; }
    }
    $d = call_user_func($fn);
    qontoDonneesDu(time());
    if ((is_dir($dir) || @mkdir($dir, 0700, true)) && is_writable($dir)) {
        @file_put_contents($file, json_encode($d), LOCK_EX);
        @chmod($file, 0600);
        foreach ((array) glob($dir . '/*.json') as $vieux) { if (@filemtime($vieux) < time() - 2 * 86400) { @unlink($vieux); } }
    }
    return $d;
}

/** Organisation : noms (pour repérer les virements internes) + comptes actifs avec solde. */
function qontoOrganisation($cfg, $refresh) {
    return qontoCached('org', function () use ($cfg) {
        $r = qontoGet($cfg, '/v2/organization');
        $o = $r['organization'];
        $noms = array();
        foreach (array('legal_name', 'name') as $k) { if (!empty($o[$k])) { $noms[] = strtoupper(trim($o[$k])); } }
        $comptes = array();
        foreach ($o['bank_accounts'] as $b) {
            if (isset($b['status']) && $b['status'] !== 'active') { continue; }
            $comptes[] = array('id' => $b['id'], 'nom' => isset($b['name']) ? $b['name'] : '',
                               'solde' => isset($b['balance_cents']) ? $b['balance_cents'] / 100 : (float) $b['balance']);
        }
        return array('noms' => array_values(array_unique($noms)), 'comptes' => $comptes);
    }, $refresh);
}

/** Libellé lisible d'une catégorie Qonto (objet ou chaîne type "online_service"). */
function qontoCategorie($t) {
    foreach (array('cashflow_category', 'category') as $k) {
        if (empty($t[$k])) { continue; }
        $v = $t[$k];
        if (is_array($v)) { $v = isset($v['name']) ? $v['name'] : (isset($v['label']) ? $v['label'] : ''); }
        $v = trim(str_replace('_', ' ', (string) $v));
        if ($v !== '') { return ucfirst($v); }
    }
    return 'Non catégorisé';
}

/** Ne garde que les champs utiles d'une transaction (date locale, montant exact en centimes). */
function qontoNormalise($t, $compte, $nomsOrg) {
    $cents = isset($t['amount_cents']) ? (int) $t['amount_cents'] : (int) round(((float) $t['amount']) * 100);
    $quand = !empty($t['settled_at']) ? $t['settled_at'] : (isset($t['emitted_at']) ? $t['emitted_at'] : '');
    $tiers = !empty($t['clean_counterparty_name']) ? $t['clean_counterparty_name'] : (isset($t['label']) ? (string) $t['label'] : '');
    // Qonto date en UTC : on la ramène à l'heure de Paris, quel que soit le fuseau par défaut du serveur.
    $d = new DateTime($quand);
    $d->setTimezone(new DateTimeZone('Europe/Paris'));
    return array(
        'date'      => $d->format('Y-m-d'),
        'sens'      => isset($t['side']) ? $t['side'] : '',
        'montant'   => $cents / 100,
        'tiers'     => $tiers,
        'categorie' => qontoCategorie($t),
        'type'      => isset($t['operation_type']) ? (string) $t['operation_type'] : '',
        'compte'    => $compte,
        'note'      => isset($t['note']) ? (string) $t['note'] : '',
        'justif'    => !empty($t['attachment_ids']),
        'interne'   => in_array(strtoupper(trim($tiers)), $nomsOrg, true), // virement entre nos propres comptes
    );
}

/** Transactions réalisées (débits et crédits) entre $deb et $fin inclus (dates locales AAAA-MM-JJ). */
function qontoTransactions($cfg, $org, $deb, $fin, $refresh) {
    if ($deb > $fin) { return array(); }
    return qontoCached('tx|' . $deb . '|' . $fin, function () use ($cfg, $org, $deb, $fin) {
        // Qonto filtre en UTC : on élargit d'un jour de chaque côté puis on refiltre sur la date locale.
        $from = date('Y-m-d', strtotime($deb . ' -1 day')) . 'T00:00:00.000Z';
        $to   = date('Y-m-d', strtotime($fin . ' +1 day')) . 'T23:59:59.999Z';
        $out = array();
        foreach ($org['comptes'] as $c) {
            $page = 1;
            while ($page > 0 && $page <= 100) {
                $r = qontoGet($cfg, '/v2/transactions?bank_account_id=' . rawurlencode($c['id'])
                    . '&status%5B%5D=completed&settled_at_from=' . rawurlencode($from) . '&settled_at_to=' . rawurlencode($to)
                    . '&sort_by=settled_at:asc&per_page=100&page=' . $page);
                foreach ($r['transactions'] as $t) {
                    $n = qontoNormalise($t, $c['nom'], $org['noms']);
                    if ($n['date'] >= $deb && $n['date'] <= $fin) { $out[] = $n; }
                }
                $page = !empty($r['meta']['next_page']) ? (int) $r['meta']['next_page'] : 0;
            }
        }
        return $out;
    }, $refresh);
}

/** Libellé français d'un operation_type Qonto. */
function qontoTypeLibelle($type) {
    $l = array('card' => 'Carte', 'transfer' => 'Virement', 'direct_debit' => 'Prélèvement', 'qonto_fee' => 'Frais Qonto',
               'income' => 'Encaissement', 'cheque' => 'Chèque', 'recall' => 'Rappel', 'swift_income' => 'Virement international reçu',
               'pagopa_payment' => 'Paiement', 'financing_installment' => 'Échéance de financement');
    return isset($l[$type]) ? $l[$type] : ucfirst(str_replace('_', ' ', (string) $type));
}

function qontoTriMontant($a, $b) { return $a['montant'] == $b['montant'] ? 0 : ($a['montant'] < $b['montant'] ? 1 : -1); }
function qontoTriDate($a, $b) { return strcmp($b['date'], $a['date']); }
function qontoCumul(&$tab, $cle, $montant) {
    if (!isset($tab[$cle])) { $tab[$cle] = array('montant' => 0.0, 'nb' => 0); }
    $tab[$cle]['montant'] += $montant;
    $tab[$cle]['nb']++;
}

/**
 * Agrège dépenses Qonto + rétro JLASSURE.
 * $tx : transactions normalisées couvrant l'année et la période. $retro : lignes array(date, gid, retro).
 * Les virements internes sont exclus des dépenses et encaissements (comptés à part).
 */
function qontoAgreger($tx, $retro, $annee, $deb, $fin) {
    $mois = array();
    for ($m = 1; $m <= 12; $m++) { $mois[$m] = array('dep' => 0.0, 'enc' => 0.0, 'retro' => 0.0, 'gids' => array()); }
    $per = array('dep' => 0.0, 'enc' => 0.0, 'retro' => 0.0, 'gids' => array(), 'interne' => 0.0, 'nbInterne' => 0, 'nbDep' => 0, 'sansJustif' => 0);
    $cats = array(); $tiers = array(); $lignes = array(); $export = array();
    $a = (string) $annee;

    foreach ($tx as $t) {
        $dansAn = substr($t['date'], 0, 4) === $a;
        $dansPer = $t['date'] >= $deb && $t['date'] <= $fin;
        if ($dansPer) { $export[] = $t; }
        if ($t['interne']) {
            if ($dansPer && $t['sens'] === 'debit') { $per['interne'] += $t['montant']; $per['nbInterne']++; }
            continue;
        }
        $cle = $t['sens'] === 'debit' ? 'dep' : 'enc';
        if ($dansAn) { $mois[(int) substr($t['date'], 5, 2)][$cle] += $t['montant']; }
        if (!$dansPer) { continue; }
        $per[$cle] += $t['montant'];
        if ($cle !== 'dep') { continue; }
        $per['nbDep']++;
        if (!$t['justif']) { $per['sansJustif']++; }
        $lignes[] = $t;
        qontoCumul($cats, $t['categorie'], $t['montant']);
        qontoCumul($tiers, $t['tiers'] !== '' ? $t['tiers'] : '(inconnu)', $t['montant']);
    }
    foreach ($retro as $r) {
        if (substr($r['date'], 0, 4) === $a) {
            $m = (int) substr($r['date'], 5, 2);
            $mois[$m]['retro'] += $r['retro'];
            $mois[$m]['gids'][$r['gid']] = 1;
        }
        if ($r['date'] >= $deb && $r['date'] <= $fin) { $per['retro'] += $r['retro']; $per['gids'][$r['gid']] = 1; }
    }
    foreach ($mois as $m => $v) { $mois[$m]['nbc'] = count($v['gids']); unset($mois[$m]['gids']); }
    $per['nbc'] = count($per['gids']); unset($per['gids']);
    uasort($cats, 'qontoTriMontant');
    uasort($tiers, 'qontoTriMontant');
    usort($lignes, 'qontoTriDate');
    usort($export, 'qontoTriDate');
    return array('mois' => $mois, 'per' => $per, 'cats' => $cats, 'tiers' => array_slice($tiers, 0, 10, true),
                 'lignes' => $lignes, 'export' => $export);
}
