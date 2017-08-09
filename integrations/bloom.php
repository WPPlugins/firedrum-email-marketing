<?php
class Firedrum_ET_Core_API_Email_Providers_Wrapper {
	private $providers = null;
	
	public function __construct($providers) {
		$this->providers = $providers;
	}
	
	public function __call( $name, $args ) {
		if ( $this->providers !== null ) {
			$result = call_user_func_array( array( $this->providers, $name ), $args );
			if ( $name === 'get' && count( $args ) > 1 && $args[1] !== '' && $result !== FALSE ) {
				// The new ET_Core_API_Email_Providers class does not handle this, so we will until they fix that bug.
				$result->set_account_name( $args[1] );
			}
			return $result;
		}
	}
}

/**
 * Firedrum Bloom Provider
 */
class Firedrum_Bloom_Provider extends ET_Core_API_Email_Provider {
	private $plugin;
	
	/**
	 * @inheritDoc
	 */
	public $name = 'Firedrum';
	
	/**
	 * @inheritDoc
	 */
	public $slug = 'firedrum';
	
	/**
	 * @inheritDoc
	 */
	public $uses_oauth = false;
	
	public function __construct( $owner = '', $account_name = '', $api_key = '' ) {
		$this->plugin = FireDrum_Plugin::instantiate();
		
		parent::__construct ( $this->plugin->get_textdomain() . '/' . $this->plugin->get_version(), $account_name, $this->plugin->get_api()->get_key() );
		$this->http->expects_json = true;
	}
	
	/**
	 * @inheritDoc
	 */
	public function get_account_fields() {
		return array(
			'api_key' => array(
				'label' => esc_html__( 'API Key', $this->plugin->get_textdomain() )
			)
		);
	}
	
	/**
	 * @inheritDoc
	 */
	public function get_data_keymap( $keymap = array(), $custom_fields_key = '' ) {
		$keymap = array(
			'list'       => array(
				'list_id'           => 'id',
				'name'              => 'name',
				'subscribers_count' => 'totalMemberCount'
			),
			'subscriber' => array(
				'email'     => 'email',
				'last_name' => 'lastName',
				'name'      => 'firstName',
				'list_id'   => 'categoryId%5B%5D'
			),
			'error'      => array(
				'error_message' => 'responseCode.message'
			)
		);
		
		return parent::get_data_keymap( $keymap, $custom_fields_key );
	}
	
	public function _set_http_timeout($timeout) {
		return 60;
	}
	
	/**
	 * @inheritDoc
	 */
	public function fetch_subscriber_lists() {
		if ( empty( $this->data['api_key'] ) ) {
			return $this->API_KEY_REQUIRED;
		}
		
		/**
		 * The maximum number of subscriber lists to request from Firedrum Email Marketing's API.
		 *
		 * @since 2.0.0
		 *
		 * @param int $max_lists
		 */
		$max_lists = (int) apply_filters( 'et_core_api_email_firedrum-internet-marketing_max_lists', 100 );
		
		$url = add_query_arg( array( 
			'authAPIKey' => $this->data['api_key'],
			'limit' => $max_lists,
			'includeMemberCount' => 'true'
		), $this->plugin->get_api()->get_category_list_url() );
		$this->prepare_request( $url, 'GET' );
		
		$this->response_data_key = 'categories';
		
		add_filter( 'http_request_timeout', array( $this, '_set_http_timeout' ) );
		$result = parent::fetch_subscriber_lists();
		remove_filter( 'http_request_timeout', array( $this, '_set_http_timeout' ) );
		return $result;
	}
	
	/**
	 * @inheritDoc
	 */
	public function subscribe( $args, $url = '' ) {
		if ( empty( $this->data['api_key'] ) ) {
			return $this->API_KEY_REQUIRED;
		}
		
		$args = array_merge( array( 
			'authAPIKey' => $this->data['api_key'],
			'updateIfExists' => 'true',
			'skipCaptcha' => 'true'
		), $this->transform_data_to_provider_format( $args, 'subscriber' ) );
		$url = add_query_arg( $args, $this->plugin->get_api()->get_add_member_url() );
		
		$this->prepare_request( $url, 'GET' );
		
		add_filter( 'http_request_timeout', array( $this, '_set_http_timeout' ) );
		$result = parent::subscribe( $args, $url );
		remove_filter( 'http_request_timeout', array( $this, '_set_http_timeout' ) );
		return $result;
	}
}