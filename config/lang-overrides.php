<?php
/*
 * ZHL-Sprach-Overrides (upgrade-sicher; überschreibt KEINE Sprachdateien).
 * Vereinfacht Begriffe für Standard-User: „Ressource" -> „Gerät".
 * Admin-Fachbegriffe (ResourceType, ResourceGroups, …) bleiben bewusst unangetastet.
 * Keys müssen exakt den LibreBooking-Translation-Keys entsprechen (case-sensitiv).
 */

// Global (alle Sprachen) — hier leer, wir bleiben sprachspezifisch.
$langOverrides = [];

// Pro Sprache: Deutsch.
$langOverridesByLanguage = [
    'de_de' => [
        'Resource'      => 'Gerät',
        'Resources'     => 'Geräte',
        'AllResources'  => 'Alle Geräte',
        'ResourceList'  => 'Zu reservierende Geräte',
        'AddResource'   => 'Gerät hinzufügen',
        'AddResources'  => 'Geräte hinzufügen',
        'ResourceFilter' => 'Gerätefilter',
    ],
];
