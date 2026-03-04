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

echo "<div class='card mt-3'>";
echo "<div class='card-header'>";
echo "<h3 class='card-title d-flex align-items-center gap-2'>";
echo "<i class='ti ti-bug'></i>";
echo htmlescape(__('Mattermost plugin debug', 'mattermost'));
echo "</h3>";
echo "</div>";

echo "<div class='card-body'>";

$logFile = GLPI_PLUGIN_DOC_DIR . '/mattermost/tickets.log';

if (is_readable($logFile)) {
    echo "<p class='mb-2'>" . htmlescape(sprintf(__('Log file: %s', 'mattermost'), $logFile)) . "</p>";
    echo "<p class='text-muted mb-0'>" . htmlescape(__('This tab is only visible when GLPI debug mode is enabled. For detailed analysis, open the log file directly on the filesystem.', 'mattermost')) . "</p>";
} else {
    echo "<p class='text-muted mb-0'>" . htmlescape(__('No log file found yet. It will be created automatically when ticket events are logged.', 'mattermost')) . "</p>";
}

echo "</div>";
echo "</div>";
