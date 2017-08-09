<?php
/**
 * Firedrum Subscribe Box widget class
 */
class Firedrum_Widget extends WP_Widget {
	private $plugin;
	public function __construct() {
		$this->plugin = FireDrum_Plugin::instantiate ();
		
		parent::__construct ( 'Firedrum_Widget', __ ( 'Firedrum Widget', $this->plugin->get_textdomain () ), array (
				'description' => __ ( 'Displays a Firedrum Subscribe Box', $this->plugin->get_textdomain () ) 
		) );
	}
	public function widget($args, $instance) {
		$args = shortcode_atts( $args, array(
			'show_header' => true	
		) );
		if (! is_array ( $instance )) {
			$instance = array ();
		}
		$this->plugin->signup_form ( array_merge ( $args, $instance ) );
	}
}