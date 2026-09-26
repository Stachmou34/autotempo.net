<?php
/**
 * Gestion des jetons de l'API MCJ-Courtage. LIGNE DE COMMANDE UNIQUEMENT.
 *
 *   php api_token.php creer "Outil relance"            jeton lecture + écriture
 *   php api_token.php creer "Tableau de bord" --lecture  jeton lecture seule (echeances)
 *   php api_token.php liste
 *   php api_token.php revoquer <id>
 *
 * Le jeton en clair n'est affiché QU'UNE FOIS, à la création : seul son hash est conservé.
 */
if (php_sapi_name() !== 'cli') { http_response_code(403); exit("CLI only\n"); }
date_default_timezone_set('Europe/Paris');
require dirname(__FILE__) . '/api_common.php';

$dir = apiDataDir();
$cmd = isset($argv[1]) ? $argv[1] : '';

try {
    apiEnsureDir($dir);
    if ($cmd === 'creer') {
        $libelle = isset($argv[2]) ? trim($argv[2]) : '';
        if ($libelle === '' || $libelle === '--lecture') { fwrite(STDERR, "Usage : php api_token.php creer \"libellé\" [--lecture]\n"); exit(1); }
        $scopes = in_array('--lecture', $argv, true)
            ? array(MCJ_API_SCOPE_LIRE)
            : array(MCJ_API_SCOPE_LIRE, MCJ_API_SCOPE_ECRIRE);
        $t = apiTokenCreate($dir, $libelle, $scopes);
        echo "Jeton créé (id " . $t['id'] . ", droits : " . implode(', ', $scopes) . ")\n\n"
           . "  " . $t['token'] . "\n\n"
           . "Copiez-le maintenant : il ne sera plus jamais affiché.\n";
    } elseif ($cmd === 'liste') {
        $liste = apiTokenList($dir);
        if (!$liste) { echo "Aucun jeton.\n"; exit(0); }
        foreach ($liste as $id => $t) {
            printf("%-9s %-8s %-25s %-30s créé %s  dernier usage %s\n", $id,
                $t['revoque_le'] ? 'RÉVOQUÉ' : 'actif', $t['libelle'], implode(',', $t['scopes']),
                substr($t['cree_le'], 0, 10), $t['dernier_usage'] ? substr($t['dernier_usage'], 0, 16) : '—');
        }
    } elseif ($cmd === 'revoquer') {
        $id = isset($argv[2]) ? $argv[2] : '';
        if (apiTokenRevoke($dir, $id)) { echo "Jeton $id révoqué : il est refusé immédiatement.\n"; }
        else { fwrite(STDERR, "Jeton $id introuvable ou déjà révoqué.\n"); exit(1); }
    } else {
        fwrite(STDERR, "Usage : php api_token.php creer \"libellé\" [--lecture] | liste | revoquer <id>\n");
        exit(1);
    }
} catch (Exception $e) {
    fwrite(STDERR, 'Erreur : ' . $e->getMessage() . "\n");
    exit(1);
}
