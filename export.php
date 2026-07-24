<?php
require __DIR__ . '/src/bootstrap.php';
Auth::requireLogin();

$repo = new ContractRepository($CONFIG);
$search       = trim(isset($_GET['q']) ? $_GET['q'] : '');
$statutFilter = isset($_GET['statut']) ? $_GET['statut'] : '';

try {
    $rows = $repo->listContracts($search, $statutFilter);
} catch (Exception $ex) {
    http_response_code(500);
    exit('Erreur export : ' . e($ex->getMessage()));
}

$apporteur = isset($CONFIG['app']['apporteur']) ? $CONFIG['app']['apporteur'] : 'export';
$filename = 'contrats_' . $apporteur . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// BOM UTF-8 pour Excel.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, array(
    'Reference', 'Client nom', 'Client prenom', 'Produit', 'Compagnie',
    'Date effet', 'Date souscription', 'Prime', 'Apporteur',
    'Statut gestion', 'Commission', 'Commission payee', 'Notes',
), ';');

foreach ($rows as $r) {
    $g = $r['gestion'];
    fputcsv($out, array(
        isset($r['reference']) ? $r['reference'] : $r['_key'],
        isset($r['client_nom']) ? $r['client_nom'] : '',
        isset($r['client_prenom']) ? $r['client_prenom'] : '',
        isset($r['produit']) ? $r['produit'] : '',
        isset($r['compagnie']) ? $r['compagnie'] : '',
        isset($r['date_effet']) ? $r['date_effet'] : '',
        isset($r['date_souscription']) ? $r['date_souscription'] : '',
        isset($r['prime']) ? $r['prime'] : '',
        isset($r['apporteur']) ? $r['apporteur'] : '',
        statutLabel($g['statut']),
        isset($g['commission']) ? $g['commission'] : '',
        !empty($g['commission_payee']) ? 'Oui' : 'Non',
        isset($g['notes']) ? $g['notes'] : '',
    ), ';');
}
fclose($out);
exit;
