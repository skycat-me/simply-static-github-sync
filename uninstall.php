<?php
/**
 * Uninstall Simply Static Github Sync
 */

// exit if accessed directly
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) exit;

// Delete Simply Static Github Sync's settings
delete_option( 'simply-static-github-sync' );

require_once plugin_dir_path( __FILE__ ) . 'includes/class-ss-plugin.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/models/class-ss-model.php';
require_once plugin_dir_path( __FILE__ ) . 'includes/models/class-ss-page.php';

// Drop the Pages table
Simply_Static_Github_Sync\Page::drop_table();
