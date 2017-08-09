<?php
class Firedrum_API {
	private $url;
	private $key;
	public function __construct() {
		$this->url = get_option ( 'fd_api_url', 'api.firedrummarketing.com/api/v2.jsp' );
		$this->key = get_option ( 'fd_apikey' );
	}
	public function set_url($url) {
		$this->url = $url;
	}
	public function get_url() {
		return $this->url;
	}
	public function set_key($key) {
		$this->key = $key;
	}
	public function get_key() {
		return $this->key;
	}
	public function get_category_list_url() {
		return 'http://' . $this->url . '?action=Category_List';
	}
	public function get_add_member_url() {
		return 'http://' . $this->url . '?action=Member_Add';
	}
	public function check_api_key() {
		$result = '8';
		$data = $this->get_category_from_api ();
		if (isset ( $data ['responseCode'] ) && isset ( $data ['responseCode'] ['id'] )) {
			$result = $data ['responseCode'] ['id'];
		}
		return $result;
	}
	public function get_category_from_api() {
		$response = wp_remote_get ( esc_url_raw ( $this->get_category_list_url() . '&authAPIKey=' . $this->key ), array (
				'headers' => array (
						'Connection' => 'close' 
				) 
		) );
		$json = wp_remote_retrieve_body ( $response );
		$data = json_decode ( $json, TRUE );
		return $data;
	}
	public function get_custom_fields_from_api() {
		$response = wp_remote_get ( esc_url_raw ( 'http://' . $this->url . '?action=CustomField_List&authAPIKey=' . $this->key ), array (
				'headers' => array (
						'Connection' => 'close' 
				) 
		) );
		$json = wp_remote_retrieve_body ( $response );
		$data = json_decode ( $json, TRUE );
		return $data;
	}
	public function get_public_app_properties_from_api() {
		$response = wp_remote_get ( esc_url_raw ( 'http://' . $this->url . '?action=PublicAppProperties_Retrieve&authAPIKey=' . $this->key ), array (
				'headers' => array (
						'Connection' => 'close'
				)
		) );
		$json = wp_remote_retrieve_body ( $response );
		$data = json_decode ( $json, TRUE );
		return $data;
	}
	public function add_member($email, $reCaptchaEnabled = true) {
		$params = $email;
		if (isset ( $_POST ['efname'] )) {
			$params .= '&firstName=' . $_POST ['efname'];
		}
		if (isset ( $_POST ['elname'] )) {
			$params .= '&lastName=' . $_POST ['elname'];
		}
		if (isset ( $_POST ['company'] )) {
			$params .= '&company=' . $_POST ['company'];
		}
		if (isset ( $_POST ['address'] )) {
			$params .= '&address=' . $_POST ['address'];
		}
		if (isset ( $_POST ['address2'] )) {
			$params .= '&address2=' . $_POST ['address2'];
		}
		if (isset ( $_POST ['city'] )) {
			$params .= '&city=' . $_POST ['city'];
		}
		if (isset ( $_POST ['state'] )) {
			$params .= '&state=' . $_POST ['state'];
		}
		if (isset ( $_POST ['zip'] )) {
			$params .= '&zip=' . $_POST ['zip'];
		}
		if (isset ( $_POST ['phone'] )) {
			$params .= '&phone=' . $_POST ['phone'];
		}
		if (isset ( $_POST ['mphone'] )) {
			$params .= '&mobilePhone=' . $_POST ['mphone'];
		}
		if (isset ( $_POST ['fax'] )) {
			$params .= '&fax=' . $_POST ['fax'];
		}
		if (isset ( $_POST ['gender'] )) {
			$params .= '&gender=' . $_POST ['gender'];
		}
		if ($_POST ['bdaymonth'] != "" || $_POST ['bdayday'] != "") {
			$params .= '&birthDate=' . $_POST ['bdaymonth'] . '-' . $_POST ['bdayday'];
		}
		if ($_POST ['annvmonth'] != "" || $_POST ['annvday'] != "") {
			$params .= '&anniversaryDate=' . $_POST ['annvmonth'] . '-' . $_POST ['annvday'];
		}
		if (isset ( $_POST ['are_you_human_54'] )) {
			$params .= '&are_you_human_54=' . $_POST ['are_you_human_54'];
		}
		$category = $this->get_category_from_api ();
		if (is_array ( $category ['categories'] )) {
			foreach ( $category ['categories'] as $cat ) {
				if (get_option ( '000' . $cat ['id'] ) != '' && isset ( $_POST [get_option ( '000' . $cat ['id'] )] )) {
					$params .= '&categoryId[]=' . str_replace ( "c", "", $_POST [get_option ( '000' . $cat ['id'] )] );
				}
			}
		}
		
		foreach ( $_POST as $key => $value )
			$post_data .= $key . '=' . $value . '<br />';
		
		$custom_fields = $this->get_custom_fields_from_api ();
		if (is_array ( $custom_fields ['customFields'] )) {
			foreach ( $custom_fields ['customFields'] as $field ) {
				if (get_option ( 'cust#_' . $field ['id'] ) != '' && isset ( $_POST ['cust#_' . $field ['id']] )) {
					$params .= '&customField[]=%7B%22name%22%3A%22' . str_replace ( " ", "+", $field ['name'] ) . '%22%2C%22value%22%3A%22' . $_POST ['cust#_' . $field ['id']] . '%22%7D';
				}
				
				// For Date Type Custom Fields
				if (get_option ( 'cust#_' . $field ['id'] ) != '' && isset ( $_POST ['custmonth#_' . str_replace ( " ", "_", $field ['name'] )] ) && isset ( $_POST ['custday#_' . str_replace ( " ", "_", $field ['name'] )] )) {
					$params .= '&customField[]=%7B%22name%22%3A%22' . str_replace ( " ", "+", $field ['name'] ) . '%22%2C%22value%22%3A%220000-' . $_POST ['custmonth#_' . str_replace ( " ", "_", $field ['name'] )] . '-' . $_POST ['custmonth#_' . str_replace ( " ", "_", $field ['name'] )] . '%22%7D';
				}
			}
		}
		if ( !$reCaptchaEnabled ) {
			$params .= '&skipCaptcha=true';
		} else if ( isset( $_POST['g-recaptcha-response'] ) ) {
			$params .= '&g-recaptcha-response=' . $_POST ['g-recaptcha-response'];
		}
		
		$response = wp_remote_get ( esc_url_raw ( $this->get_add_member_url() . '&authAPIKey=' . $this->key . '&updateIfExists=true&email=' . $params ), array (
				'headers' => array (
						'Connection' => 'close' 
				) 
		) );
		$json = wp_remote_retrieve_body ( $response );
		$data = json_decode ( $json, TRUE );
		return $data;
	}
}