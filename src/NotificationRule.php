<?php

/**
 * -------------------------------------------------------------------------
 * Mattermost Jetlag plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * @copyright  Copyright (C) 2025
 * @license    MIT https://opensource.org/licenses/MIT
 * @link       https://github.com/faithless-padre
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Mattermostjetlag;

use CommonDBTM;
use Glpi\Search\CriteriaFilter;
use Glpi\Search\FilterableInterface;
use Glpi\Search\FilterableTrait;
use Session;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

/**
 * Notification rule for Mattermost. Implements FilterableInterface so
 * the Extended Filter (CriteriaFilter) UI from GLPI is used.
 * Filter criteria are stored in glpi_criteriafilters (standard mechanism).
 */
class NotificationRule extends CommonDBTM implements FilterableInterface
{
    use FilterableTrait;

    public static function canView(): bool
    {
        return Session::haveRight('config', READ);
    }

    public static function canCreate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canUpdate(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canDelete(): bool
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function getTypeName($nb = 0)
    {
        return $nb != 1 ? __('Notification rules', 'mattermostjetlag') : __('Notification rule', 'mattermostjetlag');
    }

    public static function getType($nb = 0)
    {
        return __CLASS__;
    }

    public function prepareInputForAdd($input)
    {
        if (!isset($input['date_creation']) || empty($input['date_creation'])) {
            $input['date_creation'] = date('Y-m-d H:i:s');
        }
        return $input;
    }

    public function cleanDBonPurge()
    {
        $this->deleteFilter();
    }

    /**
     * Itemtype to use for the filter builder — determined by the rule's target field.
     * Defaults to 'Ticket' for backward compatibility.
     */
    public function getItemtypeToFilter(): string
    {
        $target = (string) ($this->fields['target'] ?? 'Ticket');
        return in_array($target, ['Ticket', 'Change'], true) ? $target : 'Ticket';
    }

    public function getItemtypeField(): ?string
    {
        return 'target';
    }

    public function getInfoTitle(): string
    {
        return '';
    }

    public function getInfoDescription(): string
    {
        return '';
    }

    /**
     * Save filter to glpi_criteriafilters (standard storage).
     * Override required because core criteria_filter endpoint returns 400 for plugin itemtypes.
     */
    public function saveFilter(array $criteria): bool
    {
        $id = (int) $this->getField('id');
        if ($id <= 0) {
            return false;
        }
        global $DB;
        $cf_table = CriteriaFilter::getTable();
        $cf_where  = ['itemtype' => static::class, 'items_id' => $id];
        $DB->delete($cf_table, $cf_where);
        if (!empty($criteria)) {
            $DB->insert($cf_table, [
                'itemtype'        => static::class,
                'items_id'        => $id,
                'search_itemtype' => $this->getItemtypeToFilter(),
                'search_criteria' => json_encode($criteria),
            ]);
        }
        return true;
    }

    /**
     * Delete filter from glpi_criteriafilters.
     * Returns true on success. Idempotent: already-empty is success.
     */
    public function deleteFilter(): bool
    {
        $id = (int) $this->getField('id');
        if ($id <= 0) {
            return false;
        }
        global $DB;
        $cf_table = CriteriaFilter::getTable();
        try {
            $DB->doQuery("DELETE FROM `$cf_table` WHERE `itemtype` = " . $DB->quote(static::class) . " AND `items_id` = " . (int) $id);
            return true;
        } catch (\Throwable $e) {
            return false;
        }
    }
}
