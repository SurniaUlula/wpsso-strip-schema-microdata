<?php
/*
 * Plugin Name: WPSSO Strip Schema Microdata (WPSSO SSM)
 * Plugin Slug: wpsso-strip-schema-microdata
 * Text Domain: wpsso-strip-schema-microdata
 * Domain Path: /languages
 * Plugin URI: https://surniaulula.com/extend/plugins/wpsso-strip-schema-microdata/
 * Assets URI: https://surniaulula.github.io/wpsso-strip-schema-microdata/assets/
 * Author: JS Morisset
 * Author URI: https://surniaulula.com/
 * License: GPLv3
 * License URI: https://www.gnu.org/licenses/gpl.txt
 * Description: WPSSO extension to remove outdated Schema Microdata, leaving the superior Schema JSON-LD markup untouched for Google and Bing.
 * Requires At Least: 3.7
 * Tested Up To: 4.7
 * Version: 1.0.3-1
 * 
 * Version Numbering Scheme: {major}.{minor}.{bugfix}-{stage}{level}
 *
 *	{major}		Major code changes / re-writes or significant feature changes.
 *	{minor}		New features / options were added or improved.
 *	{bugfix}	Bugfixes or minor improvements.
 *	{stage}{level}	dev < a (alpha) < b (beta) < rc (release candidate) < # (production).
 *
 * See PHP's version_compare() documentation at http://php.net/manual/en/function.version-compare.php.
 * 
 * Copyright 2016 Jean-Sebastien Morisset (https://surniaulula.com/)
 */

if ( ! defined( 'ABSPATH' ) ) 
	die( 'These aren\'t the droids you\'re looking for...' );

if ( ! class_exists( 'WpssoSsm' ) ) {

	class WpssoSsm {

		public $p;			// Wpsso
		public $reg;			// WpssoSsmRegister
		public $filters;		// WpssoSsmFilters

		private static $instance = null;
		private static $have_min = true;

		public static function &get_instance() {
			if ( self::$instance === null )
				self::$instance = new self;
			return self::$instance;
		}

		public function __construct() {

			require_once ( dirname( __FILE__ ).'/lib/config.php' );
			WpssoSsmConfig::set_constants( __FILE__ );
			WpssoSsmConfig::require_libs( __FILE__ );	// includes the register.php class library
			$this->reg = new WpssoSsmRegister();		// activate, deactivate, uninstall hooks

			if ( is_admin() ) {
				load_plugin_textdomain( 'wpsso-strip-schema-microdata', false, 'wpsso-strip-schema-microdata/languages/' );
				add_action( 'admin_init', array( &$this, 'required_check' ) );
			}

			add_filter( 'wpsso_get_config', array( &$this, 'wpsso_get_config' ), 10, 2 );
			add_action( 'wpsso_init_options', array( &$this, 'wpsso_init_options' ), 10 );
			add_action( 'wpsso_init_objects', array( &$this, 'wpsso_init_objects' ), 10 );
			add_action( 'wpsso_init_plugin', array( &$this, 'wpsso_init_plugin' ), 10 );
		}

		public function required_check() {
			if ( ! class_exists( 'Wpsso' ) )
				add_action( 'all_admin_notices', array( __CLASS__, 'required_notice' ) );
		}

		public static function required_notice( $deactivate = false ) {
			$info = WpssoSsmConfig::$cf['plugin']['wpssossm'];

			if ( $deactivate === true ) {
				require_once( ABSPATH.'wp-admin/includes/plugin.php' );
				deactivate_plugins( $info['base'] );
				wp_die( '<p>'.sprintf( __( '%1$s is an extension for the %2$s plugin &mdash; please install and activate the %3$s plugin before activating the %4$s extension.', 'wpsso-strip-schema-microdata' ), $info['name'], $info['req']['name'], $info['req']['short'], $info['short'] ).'</p>' );
			} else echo '<div class="notice notice-error error"><p>'.
				sprintf( __( 'The %1$s extension requires the %2$s plugin &mdash; please install and activate the %3$s plugin.',
					'wpsso-strip-schema-microdata' ), $info['name'], $info['req']['name'], $info['req']['short'] ).'</p></div>';
		}

		public function wpsso_get_config( $cf, $plugin_version = 0 ) {
			$info = WpssoSsmConfig::$cf['plugin']['wpssossm'];

			if ( version_compare( $plugin_version, $info['req']['min_version'], '<' ) ) {
				self::$have_min = false;
				return $cf;
			}

			return SucomUtil::array_merge_recursive_distinct( $cf, WpssoSsmConfig::$cf );
		}

		public function wpsso_init_options() {
			if ( method_exists( 'Wpsso', 'get_instance' ) )
				$this->p =& Wpsso::get_instance();
			else $this->p =& $GLOBALS['wpsso'];

			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			if ( self::$have_min === false )
				return;		// stop here

			$this->p->is_avail['ssm'] = true;
		}

		public function wpsso_init_objects() {
			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			if ( self::$have_min === false )
				return;		// stop here

			$this->filters = new WpssoSsmFilters( $this->p );
		}

		public function wpsso_init_plugin() {
			if ( $this->p->debug->enabled )
				$this->p->debug->mark();

			if ( self::$have_min === false )
				return $this->min_version_notice();
		}

		private function min_version_notice() {
			$info = WpssoSsmConfig::$cf['plugin']['wpssossm'];
			$wpsso_version = $this->p->cf['plugin']['wpsso']['version'];

			if ( $this->p->debug->enabled ) {
				$this->p->debug->log( $info['name'].' requires '.$info['req']['short'].' v'.
					$info['req']['min_version'].' or newer ('.$wpsso_version.' installed)' );
			}

			if ( is_admin() ) {
				$this->p->notice->err( sprintf( __( 'The %1$s extension v%2$s requires %3$s v%4$s or newer (v%5$s currently installed).',
					'wpsso-strip-schema-microdata' ), $info['name'], $info['version'], $info['req']['short'],
						$info['req']['min_version'], $wpsso_version ) );
			}
		}
	}

        global $wpssossm;
	$wpssossm =& WpssoSsm::get_instance();
}

?>
