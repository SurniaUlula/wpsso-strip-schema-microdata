<?php
/**
 * Plugin Name: WPSSO Strip Schema Microdata
 * Plugin Slug: wpsso-strip-schema-microdata
 * Text Domain: wpsso-strip-schema-microdata
 * Domain Path: /languages
 * Plugin URI: https://wpsso.com/extend/plugins/wpsso-strip-schema-microdata/
 * Assets URI: https://surniaulula.github.io/wpsso-strip-schema-microdata/assets/
 * Author: JS Morisset
 * Author URI: https://wpsso.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Description: WPSSO Core add-on to remove outdated / incomplete Schema Microdata, leaving the Google recommended Schema JSON-LD markup untouched.
 * Requires PHP: 5.4
 * Requires At Least: 3.8
 * Tested Up To: 4.9.4
 * Version: 1.3.1
 * 
 * Version Numbering: {major}.{minor}.{bugfix}[-{stage}.{level}]
 *
 *      {major}         Major structural code changes / re-writes or incompatible API changes.
 *      {minor}         New functionality was added or improved in a backwards-compatible manner.
 *      {bugfix}        Backwards-compatible bug fixes or small improvements.
 *      {stage}.{level} Pre-production release: dev < a (alpha) < b (beta) < rc (release candidate).
 * 
 * Copyright 2016-2018 Jean-Sebastien Morisset (https://wpsso.com/)
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'These aren\'t the droids you\'re looking for...' );
}

if ( ! class_exists( 'WpssoSsm' ) ) {

	class WpssoSsm {

		/**
		 * Class Object Variables
		 */
		public $p;			// Wpsso
		public $reg;			// WpssoSsmRegister
		public $filters;		// WpssoSsmFilters

		/**
		 * Reference Variables (config, options, modules, etc.).
		 */
		private $have_req_min = true;	// Have minimum wpsso version.

		private static $instance;

		public function __construct() {

			require_once ( dirname( __FILE__ ) . '/lib/config.php' );
			WpssoSsmConfig::set_constants( __FILE__ );
			WpssoSsmConfig::require_libs( __FILE__ );	// includes the register.php class library
			$this->reg = new WpssoSsmRegister();		// activate, deactivate, uninstall hooks

			if ( is_admin() ) {
				add_action( 'admin_init', array( __CLASS__, 'required_check' ) );
				add_action( 'wpsso_init_textdomain', array( __CLASS__, 'wpsso_init_textdomain' ) );
			}

			add_filter( 'wpsso_get_config', array( &$this, 'wpsso_get_config' ), 10, 2 );
			add_action( 'wpsso_init_options', array( &$this, 'wpsso_init_options' ), 10 );
			add_action( 'wpsso_init_objects', array( &$this, 'wpsso_init_objects' ), 10 );
			add_action( 'wpsso_init_plugin', array( &$this, 'wpsso_init_plugin' ), 10 );
		}

		public static function &get_instance() {
			if ( ! isset( self::$instance ) ) {
				self::$instance = new self;
			}
			return self::$instance;
		}

		public static function required_check() {
			if ( ! class_exists( 'Wpsso' ) ) {
				add_action( 'all_admin_notices', array( __CLASS__, 'required_notice' ) );
			}
		}

		// also called from the activate_plugin method with $deactivate = true
		public static function required_notice( $deactivate = false ) {

			self::wpsso_init_textdomain();

			$info = WpssoSsmConfig::$cf['plugin']['wpssossm'];

			$die_msg = __( '%1$s is an add-on for the %2$s plugin &mdash; please install and activate the %3$s plugin before activating %4$s.', 'wpsso-strip-schema-microdata' );

			$error_msg = __( 'The %1$s add-on requires the %2$s plugin &mdash; install and activate the %3$s plugin or <a href="%4$s">deactivate the %5$s add-on</a>.', 'wpsso-strip-schema-microdata' );

			if ( true === $deactivate ) {

				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once trailingslashit( ABSPATH ) . 'wp-admin/includes/plugin.php';
				}

				deactivate_plugins( $info['base'], true );	// $silent = true

				wp_die( '<p>' . sprintf( $die_msg, $info['name'], $info['req']['name'], $info['req']['short'], $info['short'] ) . '</p>' );

			} else {

				$deactivate_url = html_entity_decode( wp_nonce_url( add_query_arg( array(
					'action' => 'deactivate',
					'plugin' => $info['base'],
					'plugin_status' => 'all',
					'paged' => 1,
					's' => '',
				), admin_url( 'plugins.php' ) ), 'deactivate-plugin_' . $info['base'] ) );

				echo '<div class="notice notice-error error"><p>';
				echo sprintf( $error_msg, $info['name'], $info['req']['name'], $info['req']['short'], $deactivate_url, $info['short'] );
				echo '</p></div>';
			}
		}

		public static function wpsso_init_textdomain() {
			load_plugin_textdomain( 'wpsso-strip-schema-microdata', false, 'wpsso-strip-schema-microdata/languages/' );
		}

		public function wpsso_get_config( $cf, $plugin_version = 0 ) {

			$info = WpssoSsmConfig::$cf['plugin']['wpssossm'];

			if ( version_compare( $plugin_version, $info['req']['min_version'], '<' ) ) {
				$this->have_req_min = false;
				return $cf;
			}

			return SucomUtil::array_merge_recursive_distinct( $cf, WpssoSsmConfig::$cf );
		}

		public function wpsso_init_options() {

			$this->p =& Wpsso::get_instance();

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! $this->have_req_min ) {
				$this->p->avail['p_ext']['ssm'] = false;	// just in case
				return;	// stop here
			}

			$this->p->avail['p_ext']['ssm'] = true;
		}

		public function wpsso_init_objects() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! $this->have_req_min ) {
				return;	// stop here
			}

			$this->filters = new WpssoSsmFilters( $this->p );
		}

		public function wpsso_init_plugin() {

			if ( $this->p->debug->enabled ) {
				$this->p->debug->mark();
			}

			if ( ! $this->have_req_min ) {
				$this->min_version_notice();
				return;	// stop here
			}
		}

		private function min_version_notice() {

			$info = WpssoSsmConfig::$cf['plugin']['wpssossm'];
			$have_version = $this->p->cf['plugin']['wpsso']['version'];
			$error_msg = sprintf( __( 'The %1$s version %2$s add-on requires %3$s version %4$s or newer (version %5$s is currently installed).',
				'wpsso-strip-schema-microdata' ), $info['name'], $info['version'], $info['req']['short'], $info['req']['min_version'], $have_version );

			if ( is_admin() ) {
				$this->p->notice->err( $error_msg );
				if ( method_exists( $this->p->admin, 'get_check_for_updates_link' ) ) {
					$this->p->notice->inf( $this->p->admin->get_check_for_updates_link() );
				}
			}

			if ( method_exists( 'SucomUtil', 'safe_trigger_error' ) ) {
				// translators: %s is the short plugin name
				$error_prefix = sprintf( __( '%s warning:', 'wpsso-strip-schema-microdata' ), $info['short'] );
				SucomUtil::safe_trigger_error( $error_prefix.' '.rtrim( $error_msg, '.' ), E_USER_WARNING );
			}
		}
	}

        global $wpssossm;
	$wpssossm =& WpssoSsm::get_instance();
}
