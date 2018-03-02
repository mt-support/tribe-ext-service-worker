<?php
/**
 * Plugin Name:     The Events Calendar Extension: Service Worker
 * Description:     Add support for service worker
 * Version:         1.0.0
 * Extension Class: Tribe__Extension__Service_Worker
 * Author:          Modern Tribe, Inc.
 * Author URI:      http://m.tri.be/1971
 * License:         GPLv2 or later
 * License URI:     https://www.gnu.org/licenses/gpl-2.0.html
 *
 *     [Extension Name] is free software: you can redistribute it and/or modify
 *     it under the terms of the GNU General Public License as published by
 *     the Free Software Foundation, either version 2 of the License, or
 *     any later version.
 *
 *     [Extension Name] is distributed in the hope that it will be useful,
 *     but WITHOUT ANY WARRANTY; without even the implied warranty of
 *     MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *     GNU General Public License for more details.
 */

// Do not load unless Tribe Common is fully loaded.
if ( ! class_exists( 'Tribe__Extension' ) ) {
	return;
}

/**
 * Class used to register and hook to create the service worker files.
 */
class Tribe__Extension__Service_Worker extends Tribe__Extension {

	/**
	 * URL of the plugin extension
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	private $plugin_url = '';

	/**
	 * Version of the JS used to register the Service Worker
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	private $version = '1.0.0';

	/**
	 * Name of the registered script.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	private $register_script = 'tribe_service_worker';

	/**
	 * Name of the localized variable available on the FE.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	private $localization_name = 'tribe_sw';

	/**
	 * Data localized and available on the FE
	 *
	 * @since TBD
	 *
	 * @var array
	 */
	private $localization_data = array();

	/**
	 * Transient KEY to store the hash of files.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	private $cache_key_files_hash = 'tribe_sw_files_hash';

	/**
	 * Transient KEY used to store the cache version
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	private $cache_key_files_version = 'tribe_sw_version';

	/**
	 * Transient KEY used to store the hash of modified files.
	 *
	 * @since TBD
	 *
	 * @var string
	 */
	private $cache_key_files_modified = 'tribe_sw_modified';

	/**
	 * Setup the Extension's properties.
	 *
	 * @since TBD
	 */
	public function construct() {
		// Requirements and other properties such as the extension homepage can be defined here.
		// Examples:
		$this->add_required_plugin( 'Tribe__Events__Main', '4.3' );
		$this->set_url( 'https://theeventscalendar.com/extensions/example/' );

		$this->plugin_url                = plugin_dir_url( __FILE__ );
		$this->plugins_url               = trailingslashit( plugins_url() );
		$this->plugin_dir                = trailingslashit( plugin_dir_path( __FILE__ ) );
		$this->plugins_dir               = trailingslashit( dirname( $this->plugin_url ) );
		$this->localization_data['base'] = trailingslashit( home_url() );
	}

	/**
	 * Make sure ABSPATH is present before doing any work.
	 *
	 * @since TBD
	 */
	public function init() {
		if ( defined( 'ABSPATH' ) ) {
			$this->hooks();
		}
	}

	/**
	 * Attach hooks to WP functions
	 *
	 * @since TBD
	 */
	private function hooks() {
		add_action( 'wp_loaded', array( $this, 'register_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register the script used to register the service worker
	 *
	 * @since TBD
	 */
	public function register_scripts() {
		wp_register_script( $this->register_script, $this->plugin_url . 'js/tribe-register-sw.js', array(), $this->version );
	}

	/**
	 * Enqueue script to be executed, before doing so we setup the service worker file.
	 *
	 * @since TBD
	 */
	public function enqueue_scripts() {
		$this->localize_assets();
		wp_localize_script( $this->register_script, $this->localization_name, $this->localization_data );
		wp_enqueue_script( $this->register_script );
	}

	/**
	 * Creation of the service worker based on the available assets.
	 *
	 * @since TBD
	 */
	private function localize_assets() {
		global $wp_scripts;
		global $wp_styles;

		// Obtain all the files to be stored in the service worker.
		$files      = array_merge( array( '/' ), $this->get_assets( $wp_scripts ), $this->get_assets( $wp_styles ) );
		$files      = array_map( array( $this, 'to_path' ), $files );
		$files_hash = $this->to_hash( $files );

		$version = get_transient( $this->cache_key_files_modified );

		if ( false === $version ) {
			// Get a hash or version based on the modified time of the files to be cached.
			$version = $this->to_hash( array_filter(
				array_map( array( $this, 'modified_time' ), $files )
			) );
			set_transient( $this->cache_key_files_modified, $version, HOUR_IN_SECONDS );
		}

		if ( $version !== get_transient( $this->cache_key_files_version ) ) {
			// Create a service worker file with the appropriate version number.
			if ( file_exists( $this->plugin_dir . 'js/tribe-sw.js' ) ) {
				// todo: move version into a JSON endpoint as well.
				$js_data = "var CACHE_NAME = 'tribe_{$version}';\n\n";
				$js_data .= file_get_contents( $this->plugin_dir . 'js/tribe-sw.js' );
				file_put_contents( $this->plugin_dir . 'js/tribe-sw.js', $js_data );

				// Move service worker to the root.
				copy( $this->plugin_dir . 'js/tribe-sw.js', ABSPATH . 'tribe-sw.js' );
			}
			set_transient( $this->cache_key_files_version, $version, WEEK_IN_SECONDS );
		}

		// Create a JSON file with all the files to be cached.
		if ( $files_hash !== get_transient( $this->cache_key_files_hash ) ) {
			set_transient( $this->cache_key_files_hash, $files_hash, MONTH_IN_SECONDS );
			// todo: move this into an endpoint will be more useful.
			file_put_contents( ABSPATH . 'tribe-files-to-cache.json', json_encode( $files ) );
		}
	}

	/**
	 * @param WP_Dependencies $wp_dependencies
	 *
	 * @return array
	 */
	public function get_assets( WP_Dependencies $wp_dependencies ) {
		$queue        = $wp_dependencies->queue;
		$assets       = array();
		$dependencies = array();

		foreach ( $queue as $asset ) {
			if ( false !== strpos( $asset, 'tribe' ) ) {
				$assets[]           = $wp_dependencies->registered[ $asset ]->src;
				$asset_dependencies = $wp_dependencies->registered[ $asset ]->deps;
				if ( is_array( $asset_dependencies ) ) {
					foreach ( $asset_dependencies as $dependency ) {
						$dependencies[ $dependency ] = $wp_dependencies->registered[ $dependency ]->src;
					}
				}
			}
		}

		$dependencies = array_filter( array_values( $dependencies ) );

		return array_merge( $assets, $dependencies );
	}


	/**
	 * Removes the base URL from the files to make them looks like paths
	 *
	 * @since TBD
	 *
	 * @param $url
	 *
	 * @return string
	 */
	public function to_path( $url = '' ) {
		return str_replace( $this->localization_data['base'], '/', $url );
	}

	/**
	 * Convert the input into a string HASH.
	 *
	 * @since TBD
	 *
	 * @param array $array
	 *
	 * @return string
	 */
	private function to_hash( array $array = array() ) {
		return md5( maybe_serialize( $array ) );
	}

	/**
	 * Return the last modified time of specific file
	 *
	 * @since TBD
	 *
	 * @param string $path
	 *
	 * @return string
	 */
	private function modified_time( $path = '' ) {
		if ( $path === '/' && 0 !== strpos( $path, '/wp-content' ) ) {
			return '';
		}
		$full_path = ABSPATH . str_replace( '/wp-content', 'wp-content', $path );

		return file_exists( $full_path ) ? filemtime( $full_path ) : '';
	}
}
