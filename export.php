<?php
require __DIR__ . '/src/bootstrap.php';
Auth::require();

$repo = new ContractRepository($CONFIG);
$search       = trim((string) ($_GET['q'] ?? ''));
$statutFilter = (string) ($_GET['statut'] ?? '');

try {
    $rows = $repo->list($search, $statutFilter);
} catch (Throwable $ex) {
    http_response_code(500);
    exit('Erreur export : ' . e($ex->getMessage()));
}

$filename = 'contrats_' . ($CONFIG['app']['apporteur'] ?? 'export') . '_' . date('Ymd_His') . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
// BOM UTF-8 pour Excel.
fwrite($out, "\xEF\xBB\xBF");

fputcsv($out, [
    'Reference', 'Client nom', 'Client prenom', 'Produit', 'Compagnie',
    'Date effet', 'Date souscription', 'Prime', 'Apporteur',
    'Statut gestion', 'Commission', 'Commission payee', 'Notes',
], ';');

foreach ($rows as $r) {
    $g = $r['gestion'];
    fputcsv($out, [
        $r['reference'] ?? $r['_key'],
        $r['client_nom'] ?? '',
        $r['client_prenom'] ?? '',
        $r['produit'] ?? '',
        $r['compagnie'] ?? '',
        $r['date_effet'] ?? '',
        $r['date_souscription'] ?? '',
        $r['prime'] ?? '',
        $r['apporteur'] ?? '',
        statutLabel($g['statut']),
        $g['commission'] ?? '',
        !empty($g['commission_payee']) ? 'Oui' : 'Non',
        $g['notes'] ?? '',
    ], ';');
}
fclose($out);
exit;
