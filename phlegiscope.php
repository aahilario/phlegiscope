<?php
/*
Plugin Name: PHLegiscope 
Plugin URI: http://avahilario.net
Description: This plugin enables a Wordpress installation to act as a PHLegiscope aggregator.
Version: 0.1 
Author: Antonio A Hilario 
Author URI: http://avahilario.net
License: Unspecified 
*/

// Determine plugin directory path and plugins URL with plugin_dir_path() and plugins_url()
// http://codex.wordpress.org/Determining_Plugin_and_Content_Directories
define( 'DEBUG_PHLEGISCOPE', TRUE );

require_once('system/wordpress-adapter.php');
// Termination may occur here, in LegiscopeBase::model_action()
require_once('system/core.php');

////////////////////////////////////////////////////////////////

global $phlegiscope_instance;

if ( !is_object($phlegiscope_instance) || !is_a($phlegiscope_instance, 'LegiscopeBase') ) {
  $phlegiscope_instance = LegiscopeBase::instantiate_by_host();
}

////////////////////////////////////////////////////////////////

register_activation_hook(__FILE__  , array($phlegiscope_instance, 'activate'));
register_deactivation_hook(__FILE__, array($phlegiscope_instance, 'deactivate'));

////////////////////////////////////////////////////////////////

add_action('admin_post'           , array($phlegiscope_instance, 'admin_post'));
add_action('admin_menu'           , array($phlegiscope_instance, 'wordpress_register_admin_menus'));
add_action('admin_init'           , array($phlegiscope_instance, 'wordpress_admin_initialize'));
add_action('admin_enqueue_scripts', array($phlegiscope_instance, 'wordpress_enqueue_admin_scripts'));
add_action('init'                 , array($phlegiscope_instance, 'wordpress_init'));
add_action('parse_request'        , array($phlegiscope_instance, 'wordpress_parse_request'));

////////////////////////////////////////////////////////////////

add_action('wp_enqueue_scripts'   , array($phlegiscope_instance, 'wordpress_enqueue_scripts'));
