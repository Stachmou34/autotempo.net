<?php
declare(strict_types=1);

/** Échappement HTML sûr. */
function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Formate un montant en euros. */
function euros($value): string
{
    if ($value === null || $value === '') {
        return '—';
    }
    return number_format((float) $value, 2, ',', ' ') . ' €';
}

/** Formate une date (YYYY-MM-DD ou datetime) en JJ/MM/AAAA. */
function dateFr(?string $value): string
{
    if (empty($value)) {
        return '—';
    }
    $ts = strtotime($value);
    return $ts ? date('d/m/Y', $ts) : e($value);
}

/** Libellés lisibles pour les statuts de gestion. */
function statutLabel(string $statut): string
{
    static $labels = [
        'nouveau'    => 'Nouveau',
        'en_cours'   => 'En cours',
        'valide'     => 'Validé',
        'en_attente' => 'En attente',
        'impaye'     => 'Impayé',
        'resilie'    => 'Résilié',
        'annule'     => 'Annulé',
    ];
    return $labels[$statut] ?? ucfirst($statut);
}

/** Liste des statuts disponibles. */
function statutsDisponibles(): array
{
    return ['nouveau', 'en_cours', 'valide', 'en_attente', 'impaye', 'resilie', 'annule'];
}
