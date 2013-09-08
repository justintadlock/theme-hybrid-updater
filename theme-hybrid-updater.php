<?php
/*
 * Plugin Name: ThemeHybrid.com Updater
 * Plugin URI: http://themehybrid.com/plugins/theme-hybrid-updater
 * Description: Note: This plugin is not ready for live use yet. Theme updater for all themes on ThemeHybrid.com.
 * Version: 0.1.0-alpha-2
 * Author: Justin Tadlock
 * Author URI: http://justintadlock.com
 */

final class TH_Updater {

	/**
	 * API URL for updates.
	 *
	 * @since  0.1.0
	 * @access public
	 * @var    string
	 */
	public $api_url = 'http://themehybrid.com/api';
	//public $api_url = 'http://localhost/api';

	/**
	 * Supported themes.  Kind of hacky, but it speeds things up.  As long as users keep this 
	 * plugin updated, this is fine for now.
	 *
	 * @since  0.1.0
	 * @access public
	 * @var    string
	 */
	public $allowed_themes = array(
		'justin-tadlock', // test
		'ascetica',
		'adroa',
		'cakifo',
		'celebrate',
		'chun',
		'hybrid',
		'live-wire',
		'my-life',
		'news',
		'path',
		'picturesque',
		'prototype',
		'retro-fitted',
		'satu',
		'shell',
		'socially-awkward',
		'spine',
		'sukelius-magazine',
		'trending',
		'unique',
		'uridimmu',
	);

	/**
	 * Update data stored so that we're not sending a request multiple times on a page load.
	 *
	 * @since  0.1.0
	 * @access public
	 * @var    string
	 */
	public $theme_update = null;

	/**
	 * Constructor method.  Sets up the plugin.
	 *
	 * @since  0.1.0
	 * @access public
	 * @return void
	 */
	public function __construct() {

		/* If not in the admin, bail. */
		if ( !is_admin() )
			return;

		add_action( 'admin_menu', array( $this, 'setup' ) );

		/* Development. */
		//add_filter( 'site_transient_update_themes', array( $this, 'transient_update_themes' ) );
		//add_filter( 'transient_update_themes',      array( $this, 'transient_update_themes' ) );

		/* Production. */
		add_filter( 'pre_set_site_transient_update_themes', array( $this, 'transient_update_themes' ) );
		add_filter( 'pre_set_transient_update_themes',      array( $this, 'transient_update_themes' ) );
	}

	public function setup() {

		/* ===================================================== */

		/* Load the updater used to actually update this plugin. */
		require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'class-th-plugin-updater.php' );

		$plugin_data = get_plugin_data( __FILE__, false );

		$config = array(
			'api_url'            => trailingslashit( $this->api_url ) . 'plugin_update',
			'zip_url'            => 'http://justintadlock.com/downloads/theme-hybrid-updater.zip',
			'slug'               => plugin_basename( __FILE__ ),
			'folder_name'        => dirname( plugin_basename( __FILE__ ) ),
			'plugin_name'        => $plugin_data['Name'],
			'author'             => $plugin_data['Author'],
			'homepage'           => $plugin_data['PluginURI'],
			'version'            => $plugin_data['Version'],
		//	'url'                => $plugin_data['PluginURI'],
			'requires'           => '',
			'tested'             => '',
			'last_updated'       => '',
			'downloaded'         => 0,
		//	'readme'             => 'readme.md',
			'description'        => $plugin_data['Description'],
			'changelog'          => '',
		);

		new TH_Plugin_Updater( $config );

		/* ===================================================== */
	}

	/**
	 * Runs when theme updates are checked for in the WordPress admin. Grabs a copy of all the theme 
	 * slugs and checks if they need to be updated via ThemeHybrid.com.
	 *
	 * @since  0.1.0
	 * @access public
	 * @param  object  $value
	 * @return object
	 */
	function transient_update_themes( $transient ) {

		if ( empty( $transient->checked ) )
			return $transient;

		/* Check for theme updates. */
		$update = $this->theme_update_check( $transient->checked );

		/* If an updates were found, add them to the transient value. */
		if ( !empty( $update ) ) {

			foreach ( $update as $theme_slug => $theme_update_data ) {
				$transient->response[ $theme_slug ] = $theme_update_data;
			}
		}

		return $transient;
	}

	/**
	 * Checks if the themes on the current site are also the host site themes and grabs update data 
	 * for them.
	 *
	 * @since  0.1.0
	 * @access public
	 * @param  array      $themes
	 * @return array|bool
	 */
	public function theme_update_check( $themes ) {
		global $wp_version;

		$this->theme_update = get_site_transient( 'th_updater_all_themes' );

		/* If there is no update at this point, check for one. */
		if ( empty( $this->theme_update ) ) {

			/* Set up the theme data we want to send to the host site. */
			$data_themes = array();

			foreach ( $themes as $slug => $version ) {

				/* Make sure this is a Theme Hybrid theme. */
				if ( in_array( $slug, $this->allowed_themes ) )
					$data_themes[] = $slug;
			}

			/* Set up the request. */
			$request = array(
				'theme_slugs' => $data_themes
			);

			/* Set up the data. */
			$data = array(
				'body' => array(
					'request' => serialize( $request )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url()
			);

			/* See if we can get a response from the host site. */
			$response = wp_remote_post( trailingslashit( $this->api_url ) . 'theme_update_all', $data );

			if ( is_wp_error( $response ) )
				return false;

			$this->theme_update = wp_remote_retrieve_body( $response );

			$this->theme_update = maybe_unserialize( $this->theme_update );

			/* Set the transient. */
			set_site_transient( 'th_updater_all_themes', $this->theme_update, DAY_IN_SECONDS );
		}

		/* Unserialize the data returned from the host site. */
		$this->theme_update = maybe_unserialize( $this->theme_update );

		if ( is_array( $this->theme_update ) ) {

			$return_data = array();

			foreach ( $this->theme_update as $updated_theme => $updated_arr ) {

				if ( !empty( $updated_arr['new_version'] ) && !empty( $updated_arr['package'] ) && !empty( $updated_arr['url'] ) ) {

					if ( version_compare( $themes[ $updated_theme ], $updated_arr['new_version'], '<' ) ) {
						$return_data[ $updated_theme ] = $updated_arr;
					}
				}
			}

			return $return_data;
		}

		return false;
	}
}

new TH_Updater();

?>