<?php
/**
 * Plugin Name: Yarns Microsub Server
 * Plugin URI: https://github.com/jackjamieson2/yarns-microsub-server
 * Description: Run a Microsub server on your WordPress site. This plugin allows you to follow and reply to many different kinds of websites using a Microsub client (like alltogether.now.io or monocole.p3k.io).  Still in development.
 * Author: Jack Jamieson
 * Author URI: https://jackjamieson.net
 * Version: 0.1.3 (beta)
 * License: MIT
 * License URI: http://opensource.org/licenses/MIT
 * Text Domain: yarns_microsub
 * Domain Path: /languages
 *
 * @package Yarns_Microsub_Server
 */

add_action( 'plugins_loaded', array( 'Yarns_MicroSub_Plugin', 'plugins_loaded' ) );
add_action( 'init', array( 'Yarns_MicroSub_Plugin', 'init' ) );

/* Functions to run upon deactivation */
register_deactivation_hook( __FILE__, array( 'Yarns_MicroSub_Plugin', 'deactivate' ) );

/**
 * Class Yarns_MicroSub_Plugin
 */
class Yarns_MicroSub_Plugin {

	/**
	 * Run when plugins are loaded.
	 */
	public static function plugins_loaded() {
		if ( WP_DEBUG ) {
			require_once dirname( __FILE__ ) . '/includes/debug.php';
		}
	}

	/**
	 * To be run on deactivation
	 */
	public static function deactivate() {
		// Disable the aggregation cron job.
		if ( wp_next_scheduled( 'yarns_microsub_server_cron' ) ) {
			wp_clear_scheduled_hook( 'yarns_microsub_server_cron' );
		}
	}

	/**
	 * Initialize Yarns Microsub Server plugin Plugin
	 */
	public static function init() {
		// Initialize Microsub endpoint.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-endpoint.php';
		Yarns_Microsub_Endpoint::init();

		// Initialize Microsub posts.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-posts.php';
		Yarns_Microsub_Posts::init();

		// Class: channels.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-channels.php';

		// Class: Parser.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-parser.php';

		// Class: Aggregator.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-aggregator.php';

		// Class: Admin.
		require_once dirname( __FILE__ ) . '/includes/class-yarns-microsub-admin.php';
		add_action( 'admin_menu', array( 'Yarns_Microsub_Admin', 'admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( 'Yarns_Microsub_Admin', 'yarns_microsub_admin_enqueue_scripts' ) );
		Yarns_Microsub_Admin::init();

		// Set timezone for plugin date functions.
		date_default_timezone_set( get_option( 'timezone_string' ) );


		// list of various public helper functions.
		require_once dirname( __FILE__ ) . '/includes/functions.php';

		// Set up cron job to check for posts.
		add_filter( 'cron_schedules', array( 'Yarns_Microsub_Plugin', 'cron_definer' ) );
		if ( ! wp_next_scheduled( 'yarns_microsub_server_cron' ) ) {
			wp_schedule_event( time(), '15mins', 'yarns_microsub_server_cron' );
		}
		add_action( 'yarns_microsub_server_cron', array( 'Yarns_Microsub_Aggregator', 'poll' ) );
	}


	/**
	 * Defines the interval for the cron job (15 minutes).
	 *
	 * @param array $schedules
	 *
	 * @return mixed
	 */
	public static function cron_definer($schedules){
		$schedules['15mins'] = array(
			'interval' => 900,
			'display'  => __( 'Once Every 15 Minutes' ),
		);
		return $schedules;
	}


	/**
	 * Load language files
	 */
	public static function plugin_textdomain() {
		load_plugin_textdomain( 'yarns_microsub', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
	}


	/**
	 * Save debug logs
	 *
	 * @param string $message Message to be written to the log.
	 */
	public static function debug_log( $message ) {
		if ( get_site_option( 'yarns_debug_log' ) ) {
			$debug_log = json_decode( get_site_option( 'yarns_debug_log' ), true );
		} else {
			$debug_log = [];
		}

		$debug_entry = date( 'Y-m-d H:i:s' ) . '  ' . $message;

		if ( is_array( $debug_log ) && ! empty( $debug_log ) ) {
			array_unshift( $debug_log, $debug_entry ); // Add item to start of array.
			$debug_log = array_slice( $debug_log, 0, 1000 ); // Limit log length to 1000 entries.
		} else {
			$debug_log[] = $debug_entry;
		}
		update_option( 'yarns_debug_log', wp_json_encode( $debug_log ) );
	}
}
