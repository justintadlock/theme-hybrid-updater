<?php
/*
 * Plugin Name: ThemeHybrid.com Updater
 * Plugin URI: http://themehybrid.com
 * Description: Note: This plugin is not ready for live use yet. Theme updater for all themes on ThemeHybrid.com.
 * Version: 0.1.0
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
	//public $api_url = 'http://themehybrid.com/api';
	public $api_url = 'http://localhost/api';

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

		/**
		 * For now, we're going to update this plugin from GitHub.  In the future, I might offer 
		 * plugin updates from ThemeHybrid.com.  One step at a time, okay?
		 */
		require_once( trailingslashit( plugin_dir_path( __FILE__ ) ) . 'github-plugin-updater.php' );

		$config = array(
			'slug'               => plugin_basename( __FILE__ ),
			'proper_folder_name' => 'theme_hybrid_updater',
			'api_url'            => 'https://api.github.com/repos/justintadlock/theme-hybrid-updater',
			'raw_url'            => 'https://raw.github.com/justintadlock/theme-hybrid-updater/master',
			'github_url'         => 'https://github.com/justintadlock/theme-hybrid-updater',
			'zip_url'            => 'https://github.com/justintadlock/theme-hybrid-updater/zipball/master',
			'sslverify'          => true,
			'requires'           => '3.5',
			'tested'             => '3.7',
			'readme'             => 'readme.txt',
			'access_token'       => ''
		);

		new WP_GitHub_Updater( $config );
		/* =========================== */

		add_filter( 'site_transient_update_themes', array( $this, 'transient_update_themes' ) );
		add_filter( 'transient_update_themes',      array( $this, 'transient_update_themes' ) );
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
	function transient_update_themes( $value ) {

		/* Make sure $value is an object.  Otherwise, just return it. */
		if ( !is_object( $value ) )
			return $value;

		/* Check for theme updates. */
		$update = $this->theme_update_check( $value->checked );

		/* If an updates were found, add them to the transient value. */
		if ( !empty( $update ) ) {

			foreach ( $update as $theme_slug => $theme_update_data ) {
				$value->response[ $theme_slug ] = $theme_update_data;
			}
		}

		return $value;
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

		/* If there is no update at this point, check for one. */
		if ( is_null( $this->theme_update ) ) {

			/* Set up the theme data we want to send to the host site. */
			$data_themes = array();

			foreach ( $themes as $slug => $version )
				$data_themes[] = $slug;

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

			$this->theme_update = wp_remote_retrieve_body( $response );

			if ( empty( $this->theme_update ) || is_wp_error( $this->theme_update ) )
				return false;
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