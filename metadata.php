<?php

/**
 * @license   GPL3 License http://opensource.org/licenses/GPL
 * @author    Alfonsas Cirtautas / OXID Community
 */

use OxidEsales\Eshop\Core\Config;
use OxidEsales\Eshop\Core\Registry;

$sMetadataVersion = '2.0';

$aModule = [
    'id'          => 'moduleinternals',
    'title'       => [
        'de' => 'OXID Community Module Internals',
        'en' => 'OXID Community Module Internals',
    ],
    'description' => [
        'en' => 'Internal OXID eShop module system information and troubleshooting tools (V6).',
        'de' => 'Internes OXID eShop Modulsystem Informations- und Troubleshooting Werkzeuge (V6).'
     ],
    'thumbnail'   => 'module_internals.png',
    'version'      => '3.0',
    'author'      => 'OXID Community',
    'url'         => 'https://github.com/OXIDprojects/oxid-module-internals',
    'email'       => '',
    'extend'      => [
        \OxidEsales\Eshop\Core\Module\Module::class => \OxidCommunity\ModuleInternals\Core\InternalModule::class,
        \OxidEsales\Eshop\Application\Controller\Admin\NavigationController::class
            => \OxidCommunity\ModuleInternals\Controller\Admin\NavigationController::class
    ],
    'controllers' => [
        'module_internals_metadata' => \OxidCommunity\ModuleInternals\Controller\Admin\Metadata::class,
        'module_internals_state'    => \OxidCommunity\ModuleInternals\Controller\Admin\State::class,
        'module_internals_utils'    => \OxidCommunity\ModuleInternals\Controller\Admin\UtilsController::class,
        'checkconsistency'          => \OxidCommunity\ModuleInternals\Controller\CheckConsistency::class,
    ],
    'templates'   => [
        'metadata.tpl'              => 'oxcom/moduleinternals/views/admin/tpl/metadata.tpl',
        'state.tpl'                 => 'oxcom/moduleinternals/views/admin/tpl/state.tpl',
        'items.tpl'                 => 'oxcom/moduleinternals/views/admin/tpl/items.tpl',
        'utils.tpl'                 => 'oxcom/moduleinternals/views/admin/tpl/utils.tpl',
        'checkconsistency.tpl'      => 'oxcom/moduleinternals/views/flow/tpl/checkconsistency.tpl',
    ],
    'settings'    => [
        [
            'group' => 'AC_CONFIG',
            'name'  => 'blACActiveCompleteCheck',
            'type'  => 'bool',
            'value' => 'false'
        ],
        [
            'group' => 'AC_CONFIG',
            'name'  => 'sACActiveCompleteKey',
            'type'  => 'str',
            'value' => ''
        ],
    ]
];
