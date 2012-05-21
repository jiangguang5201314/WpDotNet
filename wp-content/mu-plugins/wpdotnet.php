<?php
/**
 * @package WpDotNet
 * @version 1.0
 */
/*
Plugin Name: WP.NET
Plugin URI: http://www.wpdotnet.com
Description: This plugin adds unique features to the WordPress installation; you'll get better performance and full .NET extensibility!
Author: DEVSENSE
Version: 1.0
Author URI: http://www.devsense.com/
*/

if (!defined("PHALANGER"))
    die('WpDotNet plugin is only compatible with WordPress running on <a target="_blank" href="http://php-compiler.net/">Phalanger</a>.');

if (!class_exists("Devsense\WordPress\Plugins\WpDotNet\WpDotNet"))
    die('It is necessary to add WpDotNet.dll assembly in phpNet/ClassLibrary configuration section of the web.config file.');

// load .NET the plugin implementation:
Devsense\WordPress\Plugins\WpDotNet\WpDotNet::Load( basename(dirname(__FILE__)) );

add_action('plugins_loaded','check_updater_plugins_activation');

function check_updater_plugins_activation()
{
    if ( !class_exists("WpDotNetUpdater") ) {
        //activate WP.NET Updater

        require_once(ABSPATH . 'wp-admin/includes/plugin.php');

        $plugin_file = "wpdotnetupdater/wpdotnetupdater.php";
        activate_plugin($plugin_file,'', is_multisite());
    }
}
