<?php

/**
 * Правило оповещения Mattermost (имя правила, дата создания).
 * Отображается на вкладке Events.
 */

namespace GlpiPlugin\Mattermost;

use CommonDBTM;
use Glpi\Search\FilterableInterface;
use Glpi\Search\FilterableTrait;
use Html;
use Session;
use Toolbox;

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
        return $nb != 1 ? 'Notification rules' : 'Notification rule';
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

    public function getItemtypeToFilter(): string
    {
        $target = $this->getField('target');
        if ($target === null || $target === '') {
            return 'Ticket';
        }
        // GLPI has no itemtype "Approve" — approval entities are TicketValidation
        if ($target === 'Approve') {
            return 'TicketValidation';
        }
        return $target;
    }

    public function getItemtypeField(): ?string
    {
        return 'target';
    }

    public function getInfoTitle(): string
    {
        return 'Filter for this rule';
    }

    public function getInfoDescription(): string
    {
        return 'Only items matching this filter will trigger the notification.';
    }
}
