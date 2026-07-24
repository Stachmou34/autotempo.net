<?php
/**
 * Accès aux contrats. Compatible PHP 5.6+.
 *
 * - Lit les contrats de l'apporteur (REYNARD) depuis JLASSURE (lecture seule)
 * - Fusionne avec les données de gestion MCJ (statut, commission, notes)
 *
 * Le mapping des colonnes JLASSURE est défini dans config/config.php
 * (section `jlassure_mapping`) afin de s'adapter au vrai schéma.
 */
final class ContractRepository
{
    private $jlassure;
    private $mcj;
    private $config;
    private $map;

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
    private function ident($name)
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
     * @return array
     */
    public function fetchFromJlassure($search = '')
    {
        $table  = $this->ident($this->map['table']);
        $keyCol = $this->map['key'];
        $appCol = $this->map['apporteur_column'];

        // Construction de la liste des colonnes à sélectionner (alias = champ logique).
        $selects = array($this->ident($keyCol) . ' AS ' . $this->ident('_key'));
        $selects[] = $this->ident($appCol) . ' AS ' . $this->ident('apporteur');

        foreach ($this->map['fields'] as $logical => $column) {
            if ($column === null) {
                continue;
            }
            $selects[] = $this->ident($column) . ' AS ' . $this->ident($logical);
        }

        $sql = 'SELECT ' . implode(', ', $selects) . ' FROM ' . $table . ' WHERE ';

        $params = array();

        // Filtre apporteur (REYNARD).
        $apporteur = isset($this->config['app']['apporteur']) ? $this->config['app']['apporteur'] : '';
        $match     = isset($this->config['app']['apporteur_match']) ? $this->config['app']['apporteur_match'] : 'like';
        if ($match === 'exact') {
            $sql .= $this->ident($appCol) . ' = :apporteur';
            $params[':apporteur'] = $apporteur;
        } else {
            $sql .= $this->ident($appCol) . ' LIKE :apporteur';
            $params[':apporteur'] = '%' . $apporteur . '%';
        }

        // Recherche libre optionnelle (sur les colonnes textuelles mappées).
        $search = trim($search);
        if ($search !== '') {
            $searchable = array();
            foreach (array('reference', 'client_nom', 'client_prenom', 'produit', 'compagnie') as $f) {
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
     * @param array $refs
     * @return array indexé par référence
     */
    public function fetchManagement(array $refs)
    {
        if (!$refs) {
            return array();
        }
        $placeholders = implode(',', array_fill(0, count($refs), '?'));
        $stmt = $this->mcj->prepare(
            'SELECT * FROM contrat_gestion WHERE jlassure_ref IN (' . $placeholders . ')'
        );
        $stmt->execute(array_values($refs));

        $out = array();
        foreach ($stmt->fetchAll() as $row) {
            $out[(string) $row['jlassure_ref']] = $row;
        }
        return $out;
    }

    /**
     * Liste complète : contrats JLASSURE + gestion MCJ fusionnés.
     *
     * @return array
     */
    public function listContracts($search = '', $statutFilter = '')
    {
        $contracts = $this->fetchFromJlassure($search);

        $refs = array();
        foreach ($contracts as $c) {
            $refs[] = (string) $c['_key'];
        }
        $management = $this->fetchManagement($refs);

        $rows = array();
        foreach ($contracts as $c) {
            $ref = (string) $c['_key'];
            $g   = isset($management[$ref]) ? $management[$ref] : null;
            $c['gestion'] = array(
                'statut'           => $g !== null && isset($g['statut']) ? $g['statut'] : 'nouveau',
                'commission'       => $g !== null && isset($g['commission']) ? $g['commission'] : null,
                'commission_payee' => $g !== null && isset($g['commission_payee']) ? (int) $g['commission_payee'] : 0,
                'notes'            => $g !== null && isset($g['notes']) ? $g['notes'] : '',
                'updated_at'       => $g !== null && isset($g['updated_at']) ? $g['updated_at'] : null,
            );

            // Filtre par statut de gestion.
            if ($statutFilter !== '' && $c['gestion']['statut'] !== $statutFilter) {
                continue;
            }
            $rows[] = $c;
        }
        return $rows;
    }

    /** Récupère un seul contrat (JLASSURE + gestion). */
    public function find($ref)
    {
        foreach ($this->listContracts() as $row) {
            if ((string) $row['_key'] === (string) $ref) {
                return $row;
            }
        }
        return null;
    }

    /**
     * Crée ou met à jour les données de gestion MCJ d'un contrat.
     * (N'écrit jamais dans JLASSURE.)
     */
    public function saveManagement($ref, array $data, $user)
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
        $commission = (isset($data['commission']) && $data['commission'] !== '') ? $data['commission'] : null;
        $stmt->execute(array(
            ':ref'        => $ref,
            ':statut'     => $data['statut'],
            ':commission' => $commission,
            ':payee'      => !empty($data['commission_payee']) ? 1 : 0,
            ':notes'      => isset($data['notes']) ? $data['notes'] : '',
            ':user'       => $user,
        ));

        $this->log($ref, 'maj_gestion', 'Statut=' . $data['statut'], $user);
    }

    private function log($ref, $action, $details, $user)
    {
        try {
            $stmt = $this->mcj->prepare(
                'INSERT INTO contrat_historique (jlassure_ref, action, details, utilisateur)
                 VALUES (:ref, :action, :details, :user)'
            );
            $stmt->execute(array(':ref' => $ref, ':action' => $action, ':details' => $details, ':user' => $user));
        } catch (Exception $e) {
            error_log('[MCJ] Historique non enregistre : ' . $e->getMessage());
        }
    }

    /** Petites statistiques pour le tableau de bord. */
    public function stats(array $rows)
    {
        $stats = array('total' => count($rows), 'prime_totale' => 0.0, 'commission_totale' => 0.0, 'par_statut' => array());
        foreach (statutsDisponibles() as $s) {
            $stats['par_statut'][$s] = 0;
        }
        foreach ($rows as $r) {
            $stats['prime_totale']      += isset($r['prime']) ? (float) $r['prime'] : 0;
            $stats['commission_totale'] += isset($r['gestion']['commission']) ? (float) $r['gestion']['commission'] : 0;
            $st = $r['gestion']['statut'];
            $stats['par_statut'][$st] = (isset($stats['par_statut'][$st]) ? $stats['par_statut'][$st] : 0) + 1;
        }
        return $stats;
    }
}
