<?php
include(GLPI_ROOT . "/plugins/serverhealthcheck/src/ServerHealthCheck.php");
use Glpi\Plugin\Hooks;
use GlpiPlugin\ServerHealthCheck\ServerHealthCheck;

define('PLUGIN_SERVERHEALTHCHECK_VERSION', '1.0.0');
// Minimal GLPI version, inclusive
define("PLUGIN_SERVERHEALTHCHECK_MIN_GLPI_VERSION", "10.0.0");
// Maximum GLPI version, exclusive
define("PLUGIN_SERVERHEALTHCHECK_MAX_GLPI_VERSION", "10.0.99");


/**
 * Init hooks of the plugin.
 *
 * @return void
 */
function plugin_init_serverhealthcheck()
{
    global $PLUGIN_HOOKS;

    $PLUGIN_HOOKS['csrf_compliant']['serverhealthcheck'] = true;

    // Add the plugin tab to the Plugins menu on the sidebar
    $PLUGIN_HOOKS['menu_toadd']['serverhealthcheck'] = ['plugins' => ServerHealthCheck::class];
    // Add new report type to the reports tab
    $PLUGIN_HOOKS['reports']['serverhealthcheck'] = ['./front/report.php' => 'Servers Health Check report'];
    // Add new type for dashboard widget
    $PLUGIN_HOOKS['dashboard_types']['serverhealthcheck'] = [ServerHealthCheck::class, 'dashboardTypes'];
    // Add new type of content generator for widget
    $PLUGIN_HOOKS['dashboard_cards']['serverhealthcheck'] = [ServerHealthCheck::class, 'dashboardCards'];

}



/**
 * Get the name and the version of the plugin
 *
 * @return array
 */
function plugin_version_serverhealthcheck()
{
    return [
        'name' => 'ServerHealthCheck',
        'version' => PLUGIN_SERVERHEALTHCHECK_VERSION,
        'author' => 'Alexander Kuzmin',
        'license' => 'GPLv2+',
        'homepage' => '',
        'requirements' => [
            'glpi' => [
                'min' => PLUGIN_SERVERHEALTHCHECK_MIN_GLPI_VERSION,
                'max' => PLUGIN_SERVERHEALTHCHECK_MAX_GLPI_VERSION,
            ]
        ]
    ];
}

/**
 * Check pre-requisites before install
 *
 * @return boolean
 */
function plugin_serverhealthcheck_check_prerequisites()
{
    return true;
}