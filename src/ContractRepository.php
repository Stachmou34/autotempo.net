<?php
declare(strict_types=1);

/**
 * Accès aux contrats.
 *
 * - Lit les contrats de l'apporteur (REYNARD) depuis JLASSURE (lecture seule)
 * - Fusionne avec les données de gestion MCJ (statut, commission, notes)
 *
 * Le mapping des colonnes JLASSURE est défini dans config/config.php
 * (section `jlassure_mapping`) afin de s'adapter au vrai schéma.
 */
final class ContractRepository
{
    private PDO $jlassure;
    private PDO $mcj;
    private array $config;
    private array $map;

    public function __construct(array $config)
    {
        $this->config   = $config;
        $this->jlassure = Database::jlassure($config);
        $this->mcj      = Database::mcj($config);
        $this->map      = $config['jlassure_mapping'];
    }

    /**
     * Protège un identifiant SQL (table/colonne) : on n'accepte que des
     * noms simples, entourés de backticks. Empêche toute injection via
     * la configuration.
     */
    private function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_]+$/', $name)) {
            throw new RuntimeException('Nom de colonne/table invalide dans le mapping : ' . $name);
        }
        return '`' . $name . '`';
    }

    /**
     * Récupère les contrats de l'apporteur depuis JLASSURE.
     *
     * @param string $search Filtre texte optionnel (nom client / référence)
     * @return array<int,array<string,mixed>>
     */
    public function fetchFromJlassure(string $search = ''): array
    {
        $table    = $this->ident($this->map['table']);
        $keyCol   = $this->map['key'];
        $appCol   = $this->map['apporteur_column'];

        // Construction de la liste des colonnes à sélectionner (alias = champ logique).
        $selects = [$this->ident($keyCol) . ' AS ' . $this->ident('_key')];
        $selects[] = $this->ident($appCol) . ' AS ' . $this->ident('apporteur');

        foreach ($this->map['fields'] as $logical => $column) {
            if ($column === null) {
                continue;
            }
            $selects[] = $this->ident($column) . ' AS ' . $this->ident($logical);
        }

        $sql = 'SELECT ' . implode(', ', $selects) . ' FROM ' . $table . ' WHERE ';

        $params = [];

        // Filtre apporteur (REYNARD).
        $apporteur = $this->config['app']['apporteur'] ?? '';
        if (($this->config['app']['apporteur_match'] ?? 'like') === 'exact') {
            $sql .= $this->ident($appCol) . ' = :apporteur';
            $params[':apporteur'] = $apporteur;
        } else {
            $sql .= $this->ident($appCol) . ' LIKE :apporteur';
            $params[':apporteur'] = '%' . $apporteur . '%';
        }

        // Recherche libre optionnelle (sur les colonnes textuelles mappées).
        $search = trim($search);
        if ($search !== '') {
            $searchable = [];
            foreach (['reference', 'client_nom', 'client_prenom', 'produit', 'compagnie'] as $f) {
                if (!empty($this->map['fields'][$f])) {
                    $searchable[] = $this->ident($this->map['fields'][$f]) . ' LIKE :search';
                }
            }
            if ($searchable) {
                $sql .= ' AND (' . implode(' OR ', $searchable) . ')';
                $params[':search'] = '%' . $search . '%';
            }
        }

        $sql .= ' ORDER BY ' . $this->ident($keyCol) . ' DESC';

        $stmt = $this->jlassure->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * Charge les données de gestion MCJ pour un ensemble de références.
     * @param array<int,string> $refs
     * @return array<string,array<string,mixed>> indexé par référence
     */
    public function fetchManagement(array $refs): array
    {
        if (!$refs) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($refs), '?'));
        $stmt = $this->mcj->prepare(
            'SELECT * FROM contrat_gestion WHERE jlassure_ref IN (' . $placeholders . ')'
        );
        $stmt->execute(array_values($refs));

        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['jlassure_ref']] = $row;
        }
        return $out;
    }

    /**
     * Liste complète : contrats JLASSURE + gestion MCJ fusionnés.
     *
     * @return array<int,array<string,mixed>>
     */
    public function list(string $search = '', string $statutFilter = ''): array
    {
        $contracts = $this->fetchFromJlassure($search);
        $refs = array_map(static fn($c) => (string) $c['_key'], $contracts);
        $management = $this->fetchManagement($refs);

        $rows = [];
        foreach ($contracts as $c) {
            $ref = (string) $c['_key'];
            $g   = $management[$ref] ?? null;
            $c['gestion'] = [
                'statut'           => $g['statut']           ?? 'nouveau',
                'commission'       => $g['commission']       ?? null,
                'commission_payee' => (int) ($g['commission_payee'] ?? 0),
                'notes'            => $g['notes']             ?? '',
                'updated_at'       => $g['updated_at']        ?? null,
            ];

            // Filtre par statut de gestion.
            if ($statutFilter !== '' && $c['gestion']['statut'] !== $statutFilter) {
                continue;
            }
            $rows[] = $c;
        }
        return $rows;
    }

    /** Récupère un seul contrat (JLASSURE + gestion). */
    public function find(string $ref): ?array
    {
        foreach ($this->list() as $row) {
            if ((string) $row['_key'] === $ref) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Crée ou met à jour les données de gestion MCJ d'un contrat.
     * (N'écrit jamais dans JLASSURE.)
     */
    public function saveManagement(string $ref, array $data, string $user): void
    {
        $stmt = $this->mcj->prepare(
            'INSERT INTO contrat_gestion
                (jlassure_ref, statut, commission, commission_payee, notes, updated_by)
             VALUES (:ref, :statut, :commission, :payee, :notes, :user)
             ON DUPLICATE KEY UPDATE
                statut = VALUES(statut),
                commission = VALUES(commission),
                commission_payee = VALUES(commission_payee),
                notes = VALUES(notes),
                updated_by = VALUES(updated_by)'
        );
        $stmt->execute([
            ':ref'        => $ref,
            ':statut'     => $data['statut'],
            ':commission' => ($data['commission'] === '' ? null : $data['commission']),
            ':payee'      => !empty($data['commission_payee']) ? 1 : 0,
            ':notes'      => $data['notes'] ?? '',
            ':user'       => $user,
        ]);

        $this->log($ref, 'maj_gestion', 'Statut=' . $data['statut'], $user);
    }

    private function log(string $ref, string $action, string $details, string $user): void
    {
        try {
            $stmt = $this->mcj->prepare(
                'INSERT INTO contrat_historique (jlassure_ref, action, details, utilisateur)
                 VALUES (:ref, :action, :details, :user)'
            );
            $stmt->execute([':ref' => $ref, ':action' => $action, ':details' => $details, ':user' => $user]);
        } catch (Throwable $e) {
            error_log('[MCJ] Historique non enregistré : ' . $e->getMessage());
        }
    }

    /** Petites statistiques pour le tableau de bord. */
    public function stats(array $rows): array
    {
        $stats = ['total' => count($rows), 'prime_totale' => 0.0, 'commission_totale' => 0.0, 'par_statut' => []];
        foreach (statutsDisponibles() as $s) {
            $stats['par_statut'][$s] = 0;
        }
        foreach ($rows as $r) {
            $stats['prime_totale']      += (float) ($r['prime'] ?? 0);
            $stats['commission_totale'] += (float) ($r['gestion']['commission'] ?? 0);
            $st = $r['gestion']['statut'];
            $stats['par_statut'][$st] = ($stats['par_statut'][$st] ?? 0) + 1;
        }
        return $stats;
    }
}
