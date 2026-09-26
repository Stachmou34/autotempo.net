<?php
/**
 * API MCJ-Courtage — fonctions communes (aucun effet de bord à l'inclusion).
 * Utilisé par api.php (HTTP) et api_token.php (ligne de commande).
 * Compatible PHP 5.6 / 7.2.
 *
 * Les données de l'API (jetons, clé secrète, suivi des relances) sont stockées
 * HORS de public_html, dans apiDataDir(), jamais dans le dossier servi par le web.
 */

define('MCJ_API_APPORTEUR', 'REYNARD');
define('MCJ_API_SCOPE_LIRE', 'echeances:lire');
define('MCJ_API_SCOPE_ECRIRE', 'relances:ecrire');

/** Dossier de données : /home/<compte>/mcj_api_data (parent de public_html). */
function apiDataDir() {
    return dirname(dirname(__FILE__)) . '/mcj_api_data';
}

/** Crée le dossier de données (0700) si besoin. */
function apiEnsureDir($dir) {
    if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
        throw new Exception('Dossier de données API non inscriptible : ' . $dir);
    }
    // Dossier créé par un autre compte (ex. root) : sans ce contrôle, les jetons seraient
    // silencieusement introuvables et tout appel répondrait 401.
    if (!is_readable($dir) || !is_writable($dir)) {
        throw new Exception('Dossier de données API inaccessible (propriétaire ?) : ' . $dir);
    }
}

function apiRandomHex($octets) {
    $b = function_exists('random_bytes') ? random_bytes($octets) : openssl_random_pseudo_bytes($octets);
    return bin2hex($b);
}

/** Lit un fichier JSON du stockage (tableau vide s'il n'existe pas). */
function apiStoreRead($file) {
    if (!is_file($file)) { return array(); }
    $raw = file_get_contents($file);
    if ($raw === '' || $raw === false) { return array(); }
    $data = json_decode($raw, true);
    if (!is_array($data)) { throw new Exception('Fichier de données illisible : ' . basename($file)); }
    return $data;
}

/**
 * Modifie un fichier JSON sous verrou exclusif.
 * $fn reçoit le tableau PAR RÉFÉRENCE et peut renvoyer une valeur.
 */
function apiStoreUpdate($file, $fn) {
    $fh = fopen($file, 'c+');
    if (!$fh) { throw new Exception('Ouverture impossible : ' . basename($file)); }
    flock($fh, LOCK_EX);
    $raw = stream_get_contents($fh);
    $data = array();
    if ($raw !== '' && $raw !== false) {
        $data = json_decode($raw, true);
        if (!is_array($data)) { flock($fh, LOCK_UN); fclose($fh); throw new Exception('Fichier de données illisible : ' . basename($file)); }
    }
    $res = $fn($data);
    ftruncate($fh, 0);
    rewind($fh);
    fwrite($fh, json_encode($data));
    fflush($fh);
    flock($fh, LOCK_UN);
    fclose($fh);
    @chmod($file, 0600);
    return $res;
}

/** Clé secrète (créée au premier appel) servant à fabriquer les références opaques. */
function apiSecret($dir) {
    $f = $dir . '/secret.key';
    if (!is_file($f)) {
        $fh = @fopen($f, 'x'); // 'x' : échoue si un autre processus l'a créée entre-temps
        if ($fh) { fwrite($fh, apiRandomHex(32)); fclose($fh); @chmod($f, 0600); }
    }
    $s = trim((string) @file_get_contents($f));
    if (strlen($s) < 32) { throw new Exception('Clé secrète API invalide'); }
    return $s;
}

/** Référence opaque, stable et non devinable d'une garantie. */
function apiRef($idGarantie, $secret) {
    return 'c_' . substr(hash_hmac('sha256', 'garantie:' . (int) $idGarantie, $secret), 0, 24);
}

function apiLog($dir, $msg) {
    @file_put_contents($dir . '/api.log', date('Y-m-d H:i:s') . '  ' . $msg . "\n", FILE_APPEND);
}

// ── Jetons ────────────────────────────────────────────────────────
// Seul le hash SHA-256 du jeton est stocké : le jeton en clair n'est affiché qu'une fois, à la création.

function apiTokenCreate($dir, $libelle, $scopes) {
    $token = 'mcj_' . apiRandomHex(24);
    $id = apiRandomHex(4);
    apiStoreUpdate($dir . '/tokens.json', function (&$t) use ($id, $token, $libelle, $scopes) {
        $t[$id] = array('libelle' => $libelle, 'hash' => hash('sha256', $token), 'scopes' => $scopes,
                        'cree_le' => date('c'), 'dernier_usage' => null, 'revoque_le' => null);
    });
    return array('id' => $id, 'token' => $token);
}

function apiTokenRevoke($dir, $id) {
    return apiStoreUpdate($dir . '/tokens.json', function (&$t) use ($id) {
        if (!isset($t[$id]) || $t[$id]['revoque_le'] !== null) { return false; }
        $t[$id]['revoque_le'] = date('c');
        return true;
    });
}

function apiTokenList($dir) {
    return apiStoreRead($dir . '/tokens.json');
}

/** Vérifie un jeton présenté ; renvoie le jeton (avec son id) ou null. Met à jour dernier_usage. */
function apiTokenCheck($dir, $presente) {
    if (!preg_match('/^mcj_[a-f0-9]{48}$/', $presente)) { return null; }
    $hash = hash('sha256', $presente);
    $trouve = null;
    foreach (apiTokenList($dir) as $id => $t) {
        if (hash_equals($t['hash'], $hash)) { $trouve = $t; $trouve['id'] = $id; break; }
    }
    if ($trouve === null || $trouve['revoque_le'] !== null) { return null; }
    // Horodatage d'usage (au plus une écriture par minute et par jeton).
    if ($trouve['dernier_usage'] === null || strtotime($trouve['dernier_usage']) < time() - 60) {
        $tid = $trouve['id'];
        apiStoreUpdate($dir . '/tokens.json', function (&$t) use ($tid) {
            if (isset($t[$tid])) { $t[$tid]['dernier_usage'] = date('c'); }
        });
    }
    return $trouve;
}

/** Extrait le jeton d'un en-tête "Authorization: Bearer ...". */
function apiBearer($enTete) {
    return preg_match('/^\s*Bearer\s+(\S+)\s*$/i', (string) $enTete, $m) ? $m[1] : '';
}

// ── Données ───────────────────────────────────────────────────────

function apiApporteurIds($pdo) {
    $st = $pdo->prepare("SELECT id FROM jl_app WHERE (nom LIKE :q OR prenom LIKE :q OR societe LIKE :q) AND status <> 'B'");
    $st->execute(array(':q' => '%' . MCJ_API_APPORTEUR . '%'));
    $ids = array();
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) { $ids[] = (int) $r['id']; }
    return $ids;
}

/** 'AAAA-MM-JJ' ou null. */
function apiDate($v) {
    $v = substr(trim((string) $v), 0, 10);
    return (preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && $v !== '0000-00-00') ? $v : null;
}

function apiErreur($status, $code, $message) {
    return array($status, array('erreur' => $code, 'message' => $message));
}

/**
 * Traite une requête. $req : method, route, auth (en-tête Authorization), query, body (brut), post, https, ip.
 * $getPdo : fonction renvoyant la connexion JLASSURE (ouverte seulement si nécessaire).
 * Renvoie array(code HTTP, tableau à encoder en JSON).
 */
function apiHandle($req, $getPdo, $dir) {
    if (empty($req['https'])) { return apiErreur(403, 'https_requis', 'Utilisez https://'); }

    $presente = apiBearer($req['auth']);
    if ($presente === '') {
        return apiErreur(401, 'jeton_absent', 'Aucun jeton reçu : envoyez "Authorization: Bearer <jeton>" (ou "X-Authorization: Bearer <jeton>")');
    }
    $jeton = apiTokenCheck($dir, $presente);
    if ($jeton === null) { return apiErreur(401, 'jeton_invalide', 'Jeton invalide ou révoqué'); }

    $routes = array('echeances' => array('GET', MCJ_API_SCOPE_LIRE), 'relances' => array('POST', MCJ_API_SCOPE_ECRIRE));
    if (!isset($routes[$req['route']])) { return apiErreur(404, 'introuvable', 'Endpoints : echeances, relances'); }
    list($methode, $scope) = $routes[$req['route']];
    if ($req['method'] !== $methode) { return apiErreur(405, 'methode', 'Méthode attendue : ' . $methode); }
    if (!in_array($scope, $jeton['scopes'], true)) { return apiErreur(403, 'hors_perimetre', 'Ce jeton ne permet pas cette action'); }

    if ($req['route'] === 'echeances') { return apiEcheances($req['query'], call_user_func($getPdo), $dir); }
    return apiRelance($req, $jeton['id'], $dir);
}

/** GET echeances?de=0&a=7 : contrats valides dont la date de fin tombe entre J+de et J+a. */
function apiEcheances($q, $pdo, $dir) {
    $de = isset($q['de']) ? $q['de'] : '0';
    $a  = isset($q['a']) ? $q['a'] : '7';
    if (!preg_match('/^-?\d{1,3}$/', $de) || !preg_match('/^-?\d{1,3}$/', $a)) {
        return apiErreur(400, 'parametre', 'de et a : nombres entiers de jours (ex. de=0&a=7)');
    }
    $de = (int) $de; $a = (int) $a;
    if ($de < -365 || $a > 365 || $a < $de || $a - $de > 92) {
        return apiErreur(400, 'plage', 'Plage invalide : -365 ≤ de ≤ a ≤ 365, 93 jours maximum');
    }
    $du = date('Y-m-d', strtotime(sprintf('%+d days', $de)));
    $au = date('Y-m-d', strtotime(sprintf('%+d days', $a)));

    $ids = apiApporteurIds($pdo);
    $rows = array();
    if ($ids) {
        $in = implode(',', array_fill(0, count($ids), '?'));
        $st = $pdo->prepare("SELECT g.id, g.date_effet, g.date_fin, g.prix_formule,
                                    cl.mail AS email, cl.prenom AS prenom, v.categorie AS categorie
                             FROM jl_garantie g
                             LEFT JOIN jl_client cl ON cl.id = g.id_cli
                             LEFT JOIN jl_vehicule v ON v.id = g.id_vehi
                             WHERE g.id_app IN ($in) AND g.num_contrat <> '' AND g.status = 'V'
                               AND DATE(g.date_fin) BETWEEN ? AND ?
                             ORDER BY g.date_fin, g.id");
        $p = $ids; $p[] = $du; $p[] = $au;
        $st->execute($p);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
    }

    $secret = apiSecret($dir);
    $aujourdhui = date('Y-m-d');
    $contrats = apiStoreUpdate($dir . '/refs.json', function (&$refs) use ($rows, $secret, $aujourdhui) {
        $out = array();
        foreach ($rows as $r) {
            $email = trim((string) $r['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { continue; } // pas d'email exploitable
            $ref = apiRef($r['id'], $secret);
            $relance = isset($refs[$ref]['relance']) ? $refs[$ref]['relance'] : null;
            $refs[$ref] = array('g' => (int) $r['id'], 'vu' => $aujourdhui, 'relance' => $relance);
            if ($relance !== null) { continue; } // déjà relancé : on ne le renvoie plus

            $debut = apiDate($r['date_effet']);
            $fin = apiDate($r['date_fin']);
            $duree = null;
            if ($debut !== null && $fin !== null) { $duree = (int) date_create($debut)->diff(date_create($fin))->days; }
            $prenom = trim((string) $r['prenom']);
            $cat = trim((string) $r['categorie']);
            $montant = (float) str_replace(array(' ', ','), array('', '.'), (string) $r['prix_formule']);
            $out[] = array(
                'ref'         => $ref,
                'email'       => $email,
                'prenom'      => $prenom !== '' ? $prenom : null,
                'date_debut'  => $debut,
                'date_fin'    => $fin,
                'duree_jours' => $duree,
                'categorie'   => $cat !== '' ? $cat : null,
                'montant'     => $montant > 0 ? round($montant, 2) : null,
            );
        }
        // Ménage : références jamais relancées et plus vues depuis 400 jours.
        $limite = date('Y-m-d', strtotime('-400 days'));
        foreach ($refs as $k => $v) { if ($v['relance'] === null && $v['vu'] < $limite) { unset($refs[$k]); } }
        return $out;
    });

    return array(200, array('du' => $du, 'au' => $au, 'nombre' => count($contrats), 'contrats' => $contrats));
}

/** POST relances {"ref": "..."} : marque la relance comme faite (idempotent). */
function apiRelance($req, $jetonId, $dir) {
    $corps = json_decode((string) $req['body'], true);
    $ref = is_array($corps) && isset($corps['ref']) ? $corps['ref'] : (isset($req['post']['ref']) ? $req['post']['ref'] : '');
    if (!is_string($ref) || !preg_match('/^c_[a-f0-9]{24}$/', $ref)) {
        return apiErreur(400, 'ref', 'Champ "ref" manquant ou invalide');
    }
    $res = apiStoreUpdate($dir . '/refs.json', function (&$refs) use ($ref, $jetonId) {
        if (!isset($refs[$ref])) { return null; }
        if ($refs[$ref]['relance'] !== null) { return array($refs[$ref]['relance'], true); }
        $refs[$ref]['relance'] = date('c');
        $refs[$ref]['par'] = $jetonId;
        return array($refs[$ref]['relance'], false);
    });
    if ($res === null) { return apiErreur(404, 'ref_inconnue', 'Référence inconnue (elle doit provenir de echeances)'); }
    return array(200, array('ref' => $ref, 'relance_le' => $res[0], 'deja_faite' => $res[1]));
}
