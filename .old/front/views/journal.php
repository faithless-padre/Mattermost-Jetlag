<?php
/**
 * Вкладка Journal: журнал работы/событий плагина (заглушка).
 * Ожидает $config (GlpiPlugin\Mattermost\Config).
 */
if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access directly to this file");
}
$config = isset($config) ? $config : (isset($this) ? $this : null);
if (!$config instanceof \GlpiPlugin\Mattermost\Config) {
    return;
}
?>

<div class="card">
    <div class="card-header">
        <h4 class="card-title mb-0"><?php echo htmlescape('Journal'); ?></h4>
    </div>
    <div class="card-body">
        <p class="text-muted mb-2">
            <?php echo htmlescape('This section will contain the Mattermost notification journal.'); ?>
        </p>
        <p class="text-muted mb-0">
            <?php echo htmlescape('For now it is a placeholder tab and can be filled with detailed logs or history later.'); ?>
        </p>
    </div>
</div>

