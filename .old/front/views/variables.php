<?php

if (!defined('GLPI_ROOT')) {
    die('Sorry. You can\'t access this file directly');
}

/** @var \GlpiPlugin\Mattermost\Config $config */

use GlpiPlugin\Mattermost\Config;

// Безопасность: вкладка должна отображаться только когда включён debug-режим.
if (!Config::isDebugMode()) {
    return;
}

// Определения переменных для переопределения
$variables = [
    'Ticket Status' => [
        1 => 'New',
        2 => 'Assigned',
        3 => 'Processing',
        4 => 'Pending',
        5 => 'Solved',
        6 => 'Closed',
    ],
    'Ticket Type' => [
        1 => 'Incident',
        2 => 'Request',
    ],
    'Event' => [
        'New' => 'New',
        'Update' => 'Update',
        'Delete' => 'Delete',
    ],
];

echo "<div class='card border-0 shadow-none p-0 m-0 mt-2'>";
echo "<div class='card-header mb-3 pt-2 border-top rounded-0' style='background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);'>";
echo "<h4 class='card-title ms-5'>";
echo "<div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'><i class='fs-2x ti ti-code'></i></div>";
echo htmlescape(__('Variables override', 'mattermost'));
echo "</h4>";
echo "</div>";
echo "</div>";

echo "<div class='card border mb-0'>";
echo "<div class='card-body'>";
echo "<div class='alert alert-info mb-3 d-flex align-items-center'>";
echo "<i class='ti ti-info-circle flex-shrink-0 me-2' style='font-size: 1.25rem;' aria-hidden='true'></i>";
echo "<span>" . htmlescape(__('This tab allows you to override variable names used in message templates. Changes will be applied when rendering messages for rules.', 'mattermost')) . "</span>";
echo "</div>";

foreach ($variables as $varGroupName => $varGroup) {
    // Определяем иконку для каждой группы
    $iconMap = [
        'Ticket Status' => 'ti ti-status-change',
        'Ticket Type' => 'ti ti-ticket',
        'Event' => 'ti ti-bolt',
    ];
    $groupIcon = $iconMap[$varGroupName] ?? 'ti ti-code';
    
    echo "<div class='card border-0 shadow-none p-0 m-0 mb-3'>";
    echo "<div class='card-header mb-3 pt-2 border-top rounded-0' style='background-color: var(--glpi-form-header-bg); color: var(--glpi-form-header-fg); border-color: var(--glpi-form-header-border-color);'>";
    echo "<h4 class='card-title ms-5'>";
    echo "<div class='ribbon ribbon-bookmark ribbon-top ribbon-start bg-blue s-1'><i class='fs-2x " . htmlescape($groupIcon) . "'></i></div>";
    echo htmlescape($varGroupName);
    echo "</h4>";
    echo "</div>";
    echo "</div>";
    echo "<div class='card border mb-0'>";
    echo "<div class='card-body'>";
    
    echo "<div class='table-responsive'>";
    echo "<table class='table table-hover table-striped'>";
    echo "<thead style='background-color: var(--glpi-form-header-bg, #e6eff7); color: var(--glpi-form-header-fg, #212529);'>";
    echo "<tr>";
    echo "<th style='width: 100px; font-weight: 600;'>" . htmlescape(__('Code', 'mattermost')) . "</th>";
    echo "<th style='width: 200px; font-weight: 600;'>" . htmlescape(__('Default name', 'mattermost')) . "</th>";
    echo "<th style='font-weight: 600;'>" . htmlescape(__('Custom name', 'mattermost')) . "</th>";
    echo "</tr>";
    echo "</thead>";
    echo "<tbody>";
    
    foreach ($varGroup as $code => $defaultName) {
        $inputId = 'var_' . md5($varGroupName . '_' . $code);
        echo "<tr>";
        echo "<td><code>" . htmlescape((string)$code) . "</code></td>";
        echo "<td>" . htmlescape($defaultName) . "</td>";
        echo "<td>";
        echo "<input type='text' id='" . htmlescape($inputId) . "' class='form-control' value='' placeholder='" . htmlescape($defaultName) . "' data-var-group='" . htmlescape($varGroupName) . "' data-var-code='" . htmlescape((string)$code) . "' style='max-width: 486px;' />";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    
    echo "</div>";
    echo "</div>";
}

echo "</div>";
echo "</div>";
