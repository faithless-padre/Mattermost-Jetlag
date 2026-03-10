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

namespace GlpiPlugin\Mattermostjetlag\Config;

use Glpi\Application\View\TemplateRenderer;
use GlpiPlugin\Mattermostjetlag\Config;
use Toolbox;

if (!defined('GLPI_ROOT')) {
    die("Sorry. You can't access this file directly");
}

class VariablesOverrideTab
{
    private const TEMPLATE = '@mattermostjetlag/config/variables_override.html.twig';

    private static function urgencyDefaults(): array
    {
        return [1 => 'Very Low', 2 => 'Low', 3 => 'Medium', 4 => 'High', 5 => 'Very High'];
    }

    private static function priorityDefaults(): array
    {
        return [1 => 'Very Low', 2 => 'Low', 3 => 'Medium', 4 => 'High', 5 => 'Very High'];
    }

    private static function impactDefaults(): array
    {
        return [1 => 'Very Low', 2 => 'Low', 3 => 'Medium', 4 => 'High', 5 => 'Very High'];
    }

    /**
     * Full defaults for all three object types.
     * Structure: ['ticket' => ['status' => [...], 'type' => [...], ...], 'change' => [...], ...]
     */
    public static function getDefaults(): array
    {
        return [
            'ticket' => [
                'label'  => 'Tickets',
                'icon'   => 'ti-ticket',
                'color'  => 'bg-blue',
                'groups' => [
                    'status'   => [
                        1  => 'New',
                        10 => 'Approval',
                        2  => 'Processing (assigned)',
                        3  => 'Processing (planned)',
                        4  => 'Pending',
                        5  => 'Solved',
                        6  => 'Closed',
                    ],
                    'type'     => [
                        1 => 'Incident',
                        2 => 'Request',
                    ],
                    'urgency'  => self::urgencyDefaults(),
                    'impact'   => self::impactDefaults(),
                    'priority' => self::priorityDefaults(),
                ],
            ],
            'change' => [
                'label'  => 'Changes',
                'icon'   => 'ti-switch-3',
                'color'  => 'bg-orange',
                'groups' => [
                    'status'   => [
                        1  => 'New',
                        4  => 'Pending',
                        5  => 'Applied',
                        6  => 'Closed',
                        7  => 'Accepted',
                        8  => 'Review',
                        9  => 'Evaluation',
                        10 => 'Approval',
                        11 => 'Testing',
                        12 => 'Qualification',
                        13 => 'Refused',
                        14 => 'Cancelled',
                    ],
                    'urgency'  => self::urgencyDefaults(),
                    'impact'   => self::impactDefaults(),
                    'priority' => self::priorityDefaults(),
                ],
            ],
            'problem' => [
                'label'  => 'Problems',
                'icon'   => 'ti-bug',
                'color'  => 'bg-red',
                'groups' => [
                    'status'   => [
                        1 => 'New',
                        7 => 'Accepted',
                        2 => 'Processing (assigned)',
                        3 => 'Processing (planned)',
                        4 => 'Pending',
                        5 => 'Solved',
                        8 => 'Under observation',
                        6 => 'Closed',
                    ],
                    'urgency'  => self::urgencyDefaults(),
                    'impact'   => self::impactDefaults(),
                    'priority' => self::priorityDefaults(),
                ],
            ],
            'member_type' => [
                'label'  => 'Member Roles',
                'icon'   => 'ti-user',
                'color'  => 'bg-purple',
                'groups' => [
                    'member_type' => [
                        1 => 'Requester',
                        2 => 'Assignee',
                        3 => 'Observer',
                    ],
                ],
            ],
        ];
    }

    /**
     * Load saved overrides from config fields. Returns nested array keyed by type → group.
     */
    public static function loadOverrides(array $fields): array
    {
        $raw = $fields['variables_override'] ?? null;
        if (!$raw) {
            return [];
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public static function render(Config $config): void
    {
        if (!$config->canView()) {
            return;
        }

        $fields    = $config->fields ?? [];
        $overrides = self::loadOverrides($fields);
        $defaults  = self::getDefaults();

        // Build object_types array for template
        $objectTypes = [];
        foreach ($defaults as $typeKey => $typeDef) {
            $groups = [];
            foreach ($typeDef['groups'] as $groupKey => $items) {
                $rows = [];
                foreach ($items as $code => $englishLabel) {
                    $rows[] = [
                        'code'     => $code,
                        'default'  => $englishLabel,
                        'override' => $overrides[$typeKey][$groupKey][(string) $code] ?? '',
                    ];
                }
                $groups[] = [
                    'key'  => $groupKey,
                    'rows' => $rows,
                ];
            }
            $objectTypes[] = [
                'key'    => $typeKey,
                'label'  => $typeDef['label'],
                'icon'   => $typeDef['icon'],
                'color'  => $typeDef['color'],
                'groups' => $groups,
            ];
        }

        TemplateRenderer::getInstance()->display(self::TEMPLATE, [
            'config_id'    => (int) ($fields['id'] ?? 1),
            'object_types' => $objectTypes,
            'form_action'  => Toolbox::getItemTypeFormURL(Config::getType()),
            'can_update'   => Config::canCreate(),
        ]);
    }
}
