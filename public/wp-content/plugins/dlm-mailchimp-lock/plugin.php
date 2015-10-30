<?php

namespace DownloadMonitor\MailChimpLock;

// Prevent direct file access
if ( ! defined( 'ABSPATH' ) ) {
	header( 'Status: 403 Forbidden' );
	header( 'HTTP/1.1 403 Forbidden' );
	exit;
}

final class Plugin {

	/**
	 * @const VERSION
	 */
	const VERSION = '1.0';

	/**
	 * @const FILE
	 */
	const FILE = DLM_MAILCHIMP_LOCK_FILE;

	/**
	 * @const DIR
	 */
	const DIR = __DIR__;


	/**
	 * @var
	 */
	private static $instance;

	/**
	 * @return Plugin
	 */
	public static function instance() {

		if( ! self::$instance ) {
			self::$instance = new Plugin;
		}

		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {

		require __DIR__ . '/vendor/autoload.php';

		// Load plugin files on a later hook
		add_action( 'plugins_loaded', array( $this, 'load' ), 30 );
	}

	/**
	 * Let's go...
	 *
	 * Runs at `plugins_loaded` priority 30.
	 */
	public function load() {

		// check dependencies and only continue if installed
		$dependencyCheck = new DependencyCheck();
		if( ! $dependencyCheck->dependencies_installed ) {
			return false;
		}

		if( is_admin() ) {
			if( defined( 'DOING_AJAX' ) && DOING_AJAX ) {

			} else {
				new Admin\Manager();
			}
		} else {
			new DownloadLocker();
		}

	}



}

$GLOBALS['DLM_MailChimp_Lock'] = Plugin::instance();