<?php
namespace Simply_Static_Github_Sync;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once plugin_dir_path( __FILE__ ) . '/class-ss-plugin.php';

Plugin::instance();
