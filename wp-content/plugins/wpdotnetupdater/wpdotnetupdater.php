<?php
/**
 * @package WpDotNet
 * @version 1.0
 */
/*
Plugin Name: WP.NET Updater
Plugin URI: http://www.wpdotnet.com
Description: This plugin handles automatic updates of Phalanger which is powering your WordPress
Author: DEVSENSE
Version: 1.0
Author URI: http://www.devsense.com/
*/

class WpDotNetUpdater
{
    static function load()
    {
        // Take over the update check
        add_filter('pre_set_site_transient_update_plugins', array('WpDotNetUpdater','check_for_plugin_update'));

        // Take over the Plugin info screen
        add_filter('plugins_api', array('WpDotNetUpdater','my_plugin_api_call'), 10, 3);

        // update post-process
        add_filter('upgrader_post_install', array('WpDotNetUpdater','my_plugin_post_install'), 10, 3);

        add_action( 'admin_menu', array( "WpDotNetUpdater", 'admin_menu' ) );
		add_action( 'network_admin_menu', array( "WpDotNetUpdater", 'admin_menu' ) ); //for 3.1

        add_action("rightnow_end", array( "WpDotNetUpdater", 'add_update_info' ));
        add_action("admin_notices", array( "WpDotNetUpdater", 'update_nag' ), 3);

        add_filter( 'plugin_action_links', array( "WpDotNetUpdater", 'disable_plugin_deactivation' ), 10, 4 );
        add_filter( 'network_admin_plugin_action_links', array( "WpDotNetUpdater", 'disable_plugin_deactivation' ), 10, 4 );
    }

    
    static function disable_plugin_deactivation( $actions, $plugin_file, $plugin_data, $context ) {

	    if (in_array( $plugin_file, array(
		    'wpdotnetupdater/wpdotnetupdater.php'
	    )))
        {
            // Remove deactivate link
            if (array_key_exists( 'deactivate', $actions ))
		        unset( $actions['deactivate'] );

            // Remove deactivate link
            if (array_key_exists( 'network_deactivate', $actions ))
		        unset( $actions['network_deactivate'] );

            // Remove edit link
            if ( array_key_exists( 'edit', $actions ) )
		        unset( $actions['edit'] );
        }
	    return $actions;
    }



    static function get_update_page_url()
    {
        global $wp_version;

    	//get admin page location
		if ( is_multisite() ) {
			if ( version_compare($wp_version, '3.0.9', '>') )
				return admin_url('network/update-core.php?page=phalanger');
			else
				return admin_url('ms-admin.php?page=phalanger');
		} else {
			return admin_url('options-general.php?page=phalanger');
		}
    }

    static function add_update_info()
    {
        echo Devsense\WordPress\Plugins\WpDotNet\AdminSectionUtils::$PlatformInfo;

        if (current_user_can("update_core") && self::needs_update()) 
        {
            echo " <a href='".self::get_update_page_url()."' class='button'>Update Phalanger</a>";
        }
    }

    static function update_nag()
    {
        if ( current_user_can("update_core") && self::needs_update())
            echo "<div class='update-nag'><a href='".self::get_update_page_url()."'>Update Phalanger</a></div>";
    }

    static function admin_menu()
    {
        global $wp_version;

		if ( self::needs_update() ) {
            $count = 1;
			$count_output = ' <span class="updates-menu"><span class="update-plugins"><span class="updates-count count-' . $count . '">' . $count . '</span></span></span>';
		} else {
			$count_output = ' <span class="updates-menu"></span>';
		}

		if ( is_multisite() ) {
			if ( is_super_admin() ) {
				if ( version_compare($wp_version, '3.0.9', '>') )
					$page = add_submenu_page('update-core.php', "Phalanger Updates", 'Phalanger' . $count_output, 10, 'phalanger', array( "WpDotNetUpdater", 'update_page') );
				else
					$page = add_submenu_page('ms-admin.php', "Phalanger Updates", 'Phalanger' . $count_output, 10, 'phalanger', array( "WpDotNetUpdater", 'update_page') );
			}
		} else {
			$page = add_submenu_page('options-general.php', "Phalanger Updates", 'Phalanger' . $count_output, 'manage_options', 'phalanger', array( "WpDotNetUpdater", 'update_page') );
		}
    }

    /* Wrapper for backwards compatibility with 3.0
	 *
	 */
	static function self_admin_url($path) {
		if ( function_exists('self_admin_url') )
			return self_admin_url($path);
		else
			return admin_url($path);
	}

    static function update_page()
    {
    
        ?>
      	<div class="wrap">
	    <?php screen_icon('tools'); ?>
	    <h2><?php _e('Phalanger Updates'); ?></h2>
        <br/>
        <?php

        if (!self::needs_update() )
        {
            echo '<h3>';
		    _e('You have the latest version of Phalanger.');
		    echo '</h3>';
            echo 'You have the latest version of Phalanger. You do not need to update. ';
            return;
        }
    	else 
        {
		    echo '<h3 class="response">';
		    _e( 'An updated version of Phalanger is available.' );
		    echo '</h3>';
            echo 'There is updated version of Phalanger available. It\'s recommended to update.';
            echo '<br/><br/>';
            $plugin_file = self::plugin_name(self::plugin_slug());
            echo "<a href='" . wp_nonce_url( self::self_admin_url('update.php?action=upgrade-plugin&plugin=') . $plugin_file, 'upgrade-plugin_' . $plugin_file) . "' class='button-secondary'>Update Now&raquo;</a>";

	    }
    }

    static function check_for_plugin_update($checked_data)
    {
	    if (empty($checked_data->checked))
		    return $checked_data;
	
        $plugin_slug = self::plugin_slug();
	    $plugin_name = self::plugin_name($plugin_slug);

	    $request_args = array(
		    'slug' => $plugin_slug,
		    'version' => $checked_data->checked[$plugin_name],
	    );
	
	    $request_string = self::prepare_request('basic_check', $request_args);
	
	    // Start checking for an update
	    $raw_response = wp_remote_post(self::api_url(), $request_string);
	
	    if (!is_wp_error($raw_response) && ($raw_response['response']['code'] == 200))
		    $response = unserialize($raw_response['body']);
	
	    if (is_object($response) && !empty($response)) // Feed the update data into WP updater
		    $checked_data->response[$plugin_name] = $response;
	
	    return $checked_data;
    }
    
    static function my_plugin_api_call($def, $action, $args)
    {
	    $plugin_slug = self::plugin_slug();
	    $plugin_name = self::plugin_name($plugin_slug);
        
	    if ($args->slug != $plugin_slug)
		    return false;
	
	    // Get the current version
	    $plugin_info = get_site_transient('update_plugins');
	    $current_version = $plugin_info->checked[$plugin_name];
	    $args->version = $current_version;
	
	    $request_string = self::prepare_request($action, $args);
	
	    $request = wp_remote_post(self::api_url(), $request_string);
	
	    if (is_wp_error($request)) {
		    $res = new WP_Error('plugins_api_failed', __('An Unexpected HTTP Error occurred during the API request.</p> <p><a href="?" onclick="document.location.reload(); return false;">Try again</a>'), $request->get_error_message());
	    } else {
		    $res = unserialize($request['body']);
		
		    if ($res === false)
			    $res = new WP_Error('plugins_api_failed', __('An unknown error occurred'), $request['body']);
	    }
	
	    return $res;
    }

    private static function prepare_request($action, $args)
    {
	    global $wp_version;
	
	    return array(
		    'body' => array(
			    'action' => $action, 
			    'request' => serialize($args),
			    'api-key' => md5(get_bloginfo('url'))
		    ),
		    'user-agent' => 'WordPress/' . $wp_version . '; ' . get_bloginfo('url')
	    );	
    }

    static function my_plugin_post_install($result, $hook_extra, $data)
    {
        if ($hook_extra['plugin'] == self::plugin_name(self::plugin_slug()))
            DEVSENSE\WordPress\Plugins\WpDotNet\PluginUpdate::Update( $data['remote_destination'] );

        return $result;
    }

    static function needs_update()
    {
    	$plugin_slug = self::plugin_slug();
	    $plugin_name = self::plugin_name($plugin_slug);

    	$plugin_info = get_site_transient('update_plugins');
	    //$current = $plugin_info->checked[$plugin_name];

        if (isset( $plugin_info->response[ $plugin_name ] ))
            return true;
        else
            return false; 
    }

    private static function api_url(){ return DEVSENSE\WordPress\Plugins\WpDotNet\PluginUpdate::PluginApiUrl; }
    private static function plugin_slug(){ return strtolower(basename(dirname(__FILE__))); }
    private static function plugin_name($slug){ return ( $slug . '/' . $slug . '.php' ); }
}

WpDotNetUpdater::load();