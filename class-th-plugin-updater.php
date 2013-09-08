<?php
/**
 * @version 0.1.0
 */

if ( !class_exists( 'TH_Plugin_Updater' ) ) {

class TH_Plugin_Updater {

	/**
	 * @since  0.1.0
	 * @access public
	 * @var    array   $config  Configuration array for the updater.
	 */
	public $config = array();

	/**
	 * @since  0.1.0
	 * @access public
	 * @var    array
	 */
	private $th_plugin_data = array();

	/**
	 * Class Constructor
	 *
	 * @since  0.1.0
	 * @param  array $config
	 * @return void
	 */
	public function __construct( $config = array() ) {

		$defaults = array(
			'slug'        => plugin_basename( __FILE__ ),
			'folder_name' => dirname( plugin_basename( __FILE__ ) ),
			'api_url'     => '',
		);

		$this->config = wp_parse_args( $config, $defaults );

		/* Development. */
		//add_filter( 'site_transient_update_plugins', array( $this, 'transient_update_plugins' ) );
		//add_filter( 'site_transient_update_plugins', array( $this, 'transient_update_plugins' ) );

		/* Production. */
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'transient_update_plugins' ) );
		add_filter( 'pre_set_site_transient_update_plugins', array( $this, 'transient_update_plugins' ) );

		// Hook into the plugin details screen
		add_filter( 'plugins_api', array( $this, 'plugins_api' ), 10, 3 );

		// set timeout
		add_filter( 'http_request_timeout', array( $this, 'http_request_timeout' ) );
	}

	/**
	 * Callback function for the http_request_timeout filter
	 *
	 * @since  0.1.0
	 * @return int
	 */
	public function http_request_timeout() {
		return 2;
	}

	/**
	 * Sets up some config data and returns the version number or false.
	 *
	 * @since  0.1.0
	 * @access public
	 * @return int|bool
	 */
	public function get_new_version() {
		global $wp_version;

		$version = false;

		$this->th_plugin_data = maybe_unserialize( get_site_transient( "th_updater_plugin_{$this->config['folder_name']}" ) );

		if ( empty( $this->th_plugin_data ) ) {

			$request = array(
				'slug'       => $this->config['folder_name'],
				'version'    => $this->config['version'],
				'wp_version' => $wp_version,
			);
			$data = array(
				'body' => array(
					'request' => serialize( $request )
				),
				'user-agent' => 'WordPress/' . $wp_version . '; ' . home_url(),
				'sslverify'  => false,
			);

			$response = wp_remote_post( $this->config['api_url'], $data );

			if ( is_wp_error( $response ) )
				$version = false;

			$response_body = wp_remote_retrieve_body( $response );

			$response_body = maybe_unserialize( $response_body );

			$this->th_plugin_data = $response_body;

			set_site_transient( "th_updater_plugin_{$this->config['folder_name']}", $this->th_plugin_data, DAY_IN_SECONDS );
		}

		if ( is_array( $this->th_plugin_data ) ) {

			if ( version_compare( $this->config['version'], $this->th_plugin_data['new_version'], '<' ) ) {
				$version = $this->config['new_version'] = $this->th_plugin_data['new_version'];

				if ( !empty( $this->th_plugin_data['url'] ) )
					$this->config['url'] = $this->th_plugin_data['url'];

				if ( !empty( $this->th_plugin_data['package'] ) )
					$this->config['zip_url'] = $this->th_plugin_data['package'];

				if ( !empty( $this->th_plugin_data['tested'] ) )
					$this->config['tested'] = $this->th_plugin_data['tested'];

				if ( !empty( $this->th_plugin_data['requires'] ) )
					$this->config['requires'] = $this->th_plugin_data['requires'];

				if ( !empty( $this->th_plugin_data['last_updated'] ) )
					$this->config['last_updated'] = $this->th_plugin_data['last_updated'];

				if ( !empty( $this->th_plugin_data['changelog'] ) )
					$this->config['changelog'] = $this->th_plugin_data['changelog'];
			}
		}

		return $version;
	}

	/**
	 * Overwrites WordPress plugin update transient.
	 *
	 * @since  0.1.0
	 * @access public
	 * @param  object  $transient Plugin data transient.
	 * @return object
	 */
	public function transient_update_plugins( $transient ) {

		if ( empty( $transient->checked ) )
			return $transient;

		$version = $this->get_new_version();

		if ( false !== $version ) {

			$response = new stdClass;

			$response->new_version = $version;
			$response->slug        = $this->config['slug'];
			$response->url         = $this->config['url'];
			$response->package     = $this->config['zip_url'];

			$transient->response[ $this->config['slug'] ] = $response;
		}

		return $transient;
	}

	/**
	 * @since  0.1.0
	 * @access public
	 * @param  bool    $response
	 * @param  string  $action
	 * @param  object  $args
	 * @return object
	 */
	public function plugins_api( $response, $action, $args ) {

		if ( $args->slug != $this->config['slug'] )
			return $response;

		$response = new stdClass;

		$response->slug          = $this->config['slug'];
		$response->plugin_name   = $this->config['plugin_name'];
		$response->version       = isset( $this->config['new_version'] ) ? $this->config['new_version'] : $this->config['version'];
		$response->author        = $this->config['author'];
		$response->homepage      = $this->config['homepage'];
		$response->requires      = $this->config['requires'];
		$response->tested        = $this->config['tested'];
		$response->downloaded    = 0;
		$response->last_updated  = $this->config['last_updated'];
		$response->download_link = $this->config['zip_url'];
		$response->sections      = array( 
			'description' => $this->config['description'], 
			'changelog'   => $this->config['changelog']
		);

		return $response;
	}
}

} // endif class_exists()
