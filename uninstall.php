<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
	die;
}

require_once ('firedrum_api.class.php');
$api = new Firedrum_API();

$category = $api->get_category_from_api ();
$custom_fields = $api->get_custom_fields_from_api ();

unregister_widget ( 'Firedrum_Widget' );
delete_option ( 'fd_apikey' );
delete_option ( 'fd_api_url' );
delete_option ( 'fd_efname' );
delete_option ( 'fd_elname' );
delete_option ( 'fd_email' );
delete_option ( 'fd_company' );
delete_option ( 'fd_address' );
delete_option ( 'fd_address2' );
delete_option ( 'fd_city' );
delete_option ( 'fd_state' );
delete_option ( 'fd_zip' );
delete_option ( 'fd_phone' );
delete_option ( 'fd_mphone' );
delete_option ( 'fd_fax' );
delete_option ( 'fd_gender' );
delete_option ( 'fd_bday' );
delete_option ( 'fd_annv' );
delete_option ( 'fd_cat_use' );

if (is_array ( $category ['categories'] )) {
	foreach ( $category ['categories'] as $cat ) {
		delete_option ( '000' . $cat ['id'] );
	}
}

if (is_array ( $custom_fields ['customFields'] )) {
	foreach ( $custom_fields ['customFields'] as $field ) {
		delete_option ( 'cust#_' . $field ['id'] );
	}
}

delete_option ( 'fd_header_content' );
delete_option ( 'fd_subheader_content' );
delete_option ( 'fd_submit_text' );
delete_option ( 'fd_form_honeypot' );
delete_option ( 'fd_form_javascript' );
delete_option ( 'fd_form_poweredby' );
delete_option ( 'fd_form_styling' );
delete_option ( 'fd_form_border' );
delete_option ( 'fd_form_border_color' );
delete_option ( 'fd_form_background_color' );
delete_option ( 'fd_form_text_color' );
delete_option ( 'fd_form_text_size' );
delete_option ( 'fd_form_text_font' );