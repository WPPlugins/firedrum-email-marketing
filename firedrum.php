<?php
/*
 * Plugin Name: Firedrum Email Marketing Signup Form
 * Plugin URI: http://www.firedrum.com/
 * Description: The Firedrum Internet Marketing plugin allows you to quickly and easily add a signup form for your Firedrum Internet Marketing list. Use short-code <strong>[firedrum_marketing]</strong> in template or post or pages to display this newsletter form.
 * Version: 1.36
 * Author: firedrum
 * Author URI: https://www.firedrummarketing.com/api/v2docs.jsp
 */
require_once ('firedrum_api.class.php');
require_once ('firedrum_widget.class.php');
require_once ('firedrum_plugin.class.php');

FireDrum_Plugin::instantiate();

class Firedrum_Bloom_Provider_Loader {
	public static function hooks() {
		$instance = new Firedrum_Bloom_Provider_Loader();
		add_filter( 'et_core_get_third_party_components', array( $instance, 'third_party_components_filter' ), 10, 2 );
		add_action( 'after_setup_theme', array( $instance, 'wrap_bloom_providers' ), 12 );
	}
	
	public function third_party_components_filter($components, $groups) {
		if ( ( empty( $groups ) || ( is_array( $groups ) && in_array( 'api/email', $groups ) ) ) && class_exists( 'ET_Core_API_Email_Provider' ) ) {
			require_once ('integrations/bloom.php');
			$components['Firedrum'] = new Firedrum_Bloom_Provider();
		}
		return $components;
	}
	
	public function wrap_bloom_providers() {
		if ( isset( $GLOBALS['et_bloom'] ) ) {
			$GLOBALS['et_bloom']->providers = new Firedrum_ET_Core_API_Email_Providers_Wrapper( $GLOBALS['et_bloom']->providers );
		}
	}
}
Firedrum_Bloom_Provider_Loader::hooks();