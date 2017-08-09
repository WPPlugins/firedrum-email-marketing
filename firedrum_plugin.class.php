<?php
class FireDrum_Plugin {
	private $version = '1.36';
	private $textdomain = 'firedrum-email-marketing';
	private $dir_base;
	
	/**
	 *
	 * @var Complete filesystem path
	 */
	private $dir;
	
	/**
	 *
	 * @var Complete URL to the plugin folder
	 */
	private $url;
	private $lang_dir;
	private $api;
	private $publicAppProperties = array ();
	private $reCaptchaEnabled = false;
	private static $instance = null;
	public static function instantiate() {
		if (FireDrum_Plugin::$instance === null) {
			FireDrum_Plugin::$instance = new FireDrum_Plugin ();
		}
		return FireDrum_Plugin::$instance;
	}
	public function get_version() {
		return $this->version;
	}
	private function __construct() {
		// Set defaults
		$this->dir_base = trailingslashit ( basename ( dirname ( __FILE__ ) ) );
		$this->dir = trailingslashit ( WP_PLUGIN_DIR ) . $this->dir_base;
		$this->url = trailingslashit ( WP_PLUGIN_URL ) . $this->dir_base;
		
		// Try our hands at finding the real location
		foreach ( array (
				'plugins' => array (
						'dir' => WP_PLUGIN_DIR,
						'url' => plugins_url () 
				),
				'mu_plugins' => array (
						'dir' => WPMU_PLUGIN_DIR,
						'url' => plugins_url () 
				),
				'template' => array (
						'dir' => trailingslashit ( get_template_directory () ) . 'plugins/',
						'url' => trailingslashit ( get_template_directory_uri () ) . 'plugins/' 
				),
				'stylesheet' => array (
						'dir' => trailingslashit ( get_stylesheet_directory () ) . 'plugins/',
						'url' => trailingslashit ( get_stylesheet_directory_uri () ) . 'plugins/' 
				) 
		) as $key => $loc ) {
			$dir = trailingslashit ( $loc ['dir'] ) . $this->dir_base;
			$url = trailingslashit ( $loc ['url'] ) . $this->dir_base;
			if (is_file ( $dir . basename ( __FILE__ ) )) {
				$this->dir = $dir;
				$this->url = $url;
				break;
			}
		}
		
		/*
		 * Lang location needs to be relative *from* ABSPATH,
		 * so strip it out of our language dir location
		 */
		$this->lang_dir = trailingslashit ( $this->dir ) . 'po/';
		
		$this->api = new Firedrum_API ();
		$this->publicAppProperties = $this->api->get_public_app_properties_from_api ();
		$this->reCaptchaEnabled = (is_array ( $this->publicAppProperties ['publicAppProperties'] ) && isset ( $this->publicAppProperties ['publicAppProperties'] ['accessor'] ) && isset ( $this->publicAppProperties ['publicAppProperties'] ['accessor'] ['reCaptchaEnabled'] ) && $this->publicAppProperties ['publicAppProperties'] ['accessor'] ['reCaptchaEnabled'] == true && isset ( $this->publicAppProperties ['publicAppProperties'] ['reCaptchaSiteKey'] ));
		
		add_action ( 'init', array (
				$this,
				'do_init' 
		) );
	}
	public function get_textdomain() {
		return $this->textdomain;
	}
	public function get_api() {
		return $this->api;
	}
	public function init_common() {
		add_shortcode ( 'firedrum_marketing', array (
				$this,
				'form_shortcode' 
		) );
		add_shortcode ( 'firedrum_form', array (
				$this,
				'signup_form_shortcode' 
		) );
		add_action ( 'widgets_init', array (
				$this,
				'register_widgets' 
		) );
		add_filter ( 'widget_text', 'do_shortcode' );
		wp_enqueue_style ( 'firedrum_main_css', admin_url ( 'admin-ajax.php?action=firedrum_main_css&ver=' . $this->version ) );
		// wp_style_add_data( 'firedrum_ie_css', 'conditional', 'IE' );
	}
	public function init_admin() {
		add_action ( 'admin_menu', array (
				$this,
				'admin_menu' 
		) );
		add_action ( 'admin_head', array (
				$this,
				'stylesheet_admin' 
		) );
		
		add_action ( 'wp_ajax_firedrum_main_css', array (
				$this,
				'do_ajax_main_css' 
		) );
		add_action ( 'wp_ajax_nopriv_firedrum_main_css', array (
				$this,
				'do_ajax_main_css' 
		) );
		add_action ( 'wp_ajax_firedrum_add_api_key', array (
				$this,
				'do_ajax_add_api_key' 
		) );
		add_action ( 'wp_ajax_nopriv_firedrum_add_api_key', array (
				$this,
				'do_ajax_add_api_key' 
		) );
		add_action ( 'wp_ajax_firedrum_update_api_key', array (
				$this,
				'do_ajax_update_api_key' 
		) );
		add_action ( 'wp_ajax_nopriv_firedrum_update_api_key', array (
				$this,
				'do_ajax_update_api_key' 
		) );
		add_action ( 'wp_ajax_firedrum_update_api_url', array (
				$this,
				'do_ajax_update_api_url' 
		) );
		add_action ( 'wp_ajax_nopriv_firedrum_update_api_url', array (
				$this,
				'do_ajax_update_api_url' 
		) );
		add_action ( 'wp_ajax_firedrum_save_form_settings', array (
				$this,
				'do_ajax_save_form_settings' 
		) );
		add_action ( 'wp_ajax_nopriv_firedrum_save_form_settings', array (
				$this,
				'do_ajax_save_form_settings' 
		) );
		add_action ( 'wp_ajax_firedrum_form_submit', array (
				$this,
				'do_ajax_form_submit' 
		) );
		add_action ( 'wp_ajax_nopriv_firedrum_form_submit', array (
				$this,
				'do_ajax_form_submit' 
		) );
	}
	public function init_frontend() {
		add_action ( 'wp_enqueue_scripts', array (
				$this,
				'stylesheet_front' 
		) );
		
		wp_enqueue_script ( 'jquery_scrollto', $this->url . 'public/js/scrollTo.js', array (
				'jquery' 
		), $this->version );
		wp_enqueue_script ( 'firedrum_main_js', $this->url . 'public/js/firedrum.js', array (
				'jquery',
				'jquery-form' 
		), $this->version );
		
		$localization = array (
				'ajax_url' => admin_url ( 'admin-ajax.php' ) 
		);
		if ( $this->reCaptchaEnabled ) {
			$localization ['reCaptchaSiteKey'] = $this->publicAppProperties ['publicAppProperties'] ['reCaptchaSiteKey'];
		}
		
		wp_localize_script ( 'firedrum_main_js', 'firedrum', $localization );
	}
	public function register_widgets() {
		register_widget ( 'Firedrum_Widget' );
	}
	public function stylesheet_admin() {
		wp_enqueue_style ( 'wp-color-picker' );
		wp_enqueue_style ( 'firedrum_stylesheet', plugins_url ( 'admin/css/firedrum.css', __FILE__ ) );
	}
	public function js_admin() {
		wp_enqueue_script ( 'my-script-handle', plugins_url ( 'admin/js/my-colorpicker.js', __FILE__ ), array (
				'wp-color-picker' 
		), false, true );
		wp_enqueue_script ( 'firedrum_addinput', $this->url . 'admin/js/addinput.js', array (
				'jquery' 
		), $this->version );
	}
	public function stylesheet_front() {
		wp_enqueue_style ( 'firedrum_stylesheet_front', plugins_url ( 'public/css/firedrum.css', __FILE__ ) );
		wp_enqueue_style ( 'firedrum_google_roboto_font', 'https://fonts.googleapis.com/css?family=Roboto:400,500', array (), null );
	}
	public function do_init() {
		$this->init_common ();
		if (is_admin ()) {
			$this->init_admin ();
		} else {
			$this->init_frontend ();
		}
	}
	public function do_ajax_main_css() {
		header ( 'Content-Type: text/css; charset=utf-8' );
		?>
	.fd_error_msg {
		color: red;
	}
	.fd_success_msg {
		color: green;
	}
	.success_msg {
		color: green;
		font-weight: bold;
		margin: 0;
	}
	.unsuccess_msg {
		color: red;
		font-weight: bold;
		margin: 0;
	}
	.apiNotCorrect {
		color: red;
		font-weight: bold;
		margin: 10px 0 0 0 ;
	}
	.fd_merge_var{
		padding:0;
		margin:0;
	}<?php
		// If we're utilizing custom styles
		if (get_option ( 'fd_form_styling' ) == 'Y') {
			?>
	.fd_signup_form {
		padding:5px;
<?php
			if (get_option ( 'fd_form_border' ) == 'Y') {
				?>
		border-width: 2px;
		border-style: solid;
		border-color: <?php echo get_option('fd_form_border_color'); ?>;<?php
			}
			?>
		color: <?php echo get_option('fd_form_text_color'); ?>;
		background-color: <?php echo get_option('fd_form_background_color'); ?>;
		font-size: <?php echo (get_option('fd_form_text_size')=='') ? '12px' : get_option('fd_form_text_size').'px' ; ?>;
		font-family:<?php echo get_option('fd_form_text_font'); ?>;
	}<?php
		}
		?>
	.fd_signup_container {}
	.fd_signup_form {}
	.fd_signup_form .fd_var_label {}
	.fd_signup_form .fd_input {}
	.fd_signup_form select {}
	.fd_signup_submit {
		text-align:center;
	}

	/* These don't seem to be used anymore */
	.fd_signup_container {}
	.fd_display_rewards {}
	.fd_signup_form input.fd_interest {}
	.fd_signup_form label.fd_interest_label {
		display:inline;
	}
	.fd_interests_header {
		font-weight:bold;
	}
	div.fd_interest {
		width:100%;
	}
	.fd-indicates-required {
		width:100%;
	}
	ul.fd_list {
		list-style-type: none;
	}
	ul.fd_list li {
		font-size: 12px;
	}<?php
		exit ();
	}
	public function do_ajax_add_api_key() {
		$msg = '';
		if (! isset ( $_POST ['fd_apikey'] ) || $_POST ['fd_apikey'] == '') {
			$msg = base64_encode ( '<p class="unsuccess_msg">' . esc_html ( __ ( 'Please Enter API Key', $this->textdomain ) ) . '</p>' );
		} else {
			update_option ( 'fd_apikey', strip_tags ( stripslashes ( $_POST ['fd_apikey'] ) ) );
			$msg = base64_encode ( '<p class="success_msg">' . esc_html ( __ ( 'API Key Successfully Saved', $this->textdomain ) ) . '</p>' );
		}
		wp_redirect ( add_query_arg ( 'fdm', urlencode( $msg ), wp_get_referer () ) );
		exit ();
	}
	public function do_ajax_update_api_key() {
		$msg = '';
		if ($_POST ['new_api_key'] != '') {
			update_option ( 'fd_apikey', strip_tags ( stripslashes ( $_POST ['new_api_key'] ) ) );
			$msg = base64_encode ( '<p class="success_msg">' . esc_html ( __ ( 'API Key Successfully Updated', $this->textdomain ) ) . '</p>' );
		} elseif (isset ( $_POST ['new_api_key'] ) && $_POST ['new_api_key'] == '') {
			$msg = base64_encode ( '<p class="unsuccess_msg">' . esc_html ( __ ( 'Please Enter API Key', $this->textdomain ) ) . '</p>' );
		}
		wp_redirect ( add_query_arg ( 'fdm', urlencode( $msg ), wp_get_referer () ) );
		exit ();
	}
	public function do_ajax_update_api_url() {
		$msg = '';
		if ($_POST ['new_api_url'] != '') {
			update_option ( 'fd_api_url', strip_tags ( stripslashes ( $_POST ['new_api_url'] ) ) );
			$msg = base64_encode ( '<p class="success_msg">' . esc_html ( __ ( 'API Url Successfully Updated', $this->textdomain ) ) . '</p>' );
		} elseif (isset ( $_POST ['new_api_url'] ) && $_POST ['new_api_url'] == '') {
			$msg = base64_encode ( '<p class="unsuccess_msg">' . esc_html ( __ ( 'Please Enter API Url', $this->textdomain ) ) . '</p>' );
		}
		wp_redirect ( add_query_arg ( 'fdm', urlencode( $msg ), wp_get_referer () ) );
		exit ();
	}
	public function do_ajax_save_form_settings() {
		$msg = '';
		$category = $this->api->get_category_from_api ();
		$custom_fields = $this->api->get_custom_fields_from_api ();
		$flag = 0;
		foreach ( $category ['categories'] as $cat ) {
			if ($_POST ['000' . $cat ['id']] != '') {
				$flag = 1;
			}
		}
		if ($flag == 0) {
			$msg = base64_encode ( '<p class="unsuccess_msg">' . esc_html ( __ ( 'ERROR: Please Select At Least One Category', $this->textdomain ) ) . '</p>' );
		} else {
			update_option ( 'fd_efname', strip_tags ( stripslashes ( $_POST ['fd_efname'] ) ) );
			update_option ( 'fd_elname', strip_tags ( stripslashes ( $_POST ['fd_elname'] ) ) );
			update_option ( 'fd_email', strip_tags ( stripslashes ( $_POST ['fd_email'] ) ) );
			update_option ( 'fd_company', strip_tags ( stripslashes ( $_POST ['fd_company'] ) ) );
			update_option ( 'fd_address', strip_tags ( stripslashes ( $_POST ['fd_address'] ) ) );
			update_option ( 'fd_address2', strip_tags ( stripslashes ( $_POST ['fd_address2'] ) ) );
			update_option ( 'fd_city', strip_tags ( stripslashes ( $_POST ['fd_city'] ) ) );
			update_option ( 'fd_state', strip_tags ( stripslashes ( $_POST ['fd_state'] ) ) );
			update_option ( 'fd_zip', strip_tags ( stripslashes ( $_POST ['fd_zip'] ) ) );
			update_option ( 'fd_phone', strip_tags ( stripslashes ( $_POST ['fd_phone'] ) ) );
			update_option ( 'fd_mphone', strip_tags ( stripslashes ( $_POST ['fd_mphone'] ) ) );
			update_option ( 'fd_fax', strip_tags ( stripslashes ( $_POST ['fd_fax'] ) ) );
			update_option ( 'fd_gender', strip_tags ( stripslashes ( $_POST ['fd_gender'] ) ) );
			update_option ( 'fd_bday', strip_tags ( stripslashes ( $_POST ['fd_bday'] ) ) );
			update_option ( 'fd_annv', strip_tags ( stripslashes ( $_POST ['fd_annv'] ) ) );
			update_option ( 'fd_cat_use', strip_tags ( stripslashes ( $_POST ['fd_cat_use'] ) ) );
			
			if (is_array ( $category ['categories'] )) {
				foreach ( $category ['categories'] as $cat ) {
					update_option ( '000' . $cat ['id'], strip_tags ( stripslashes ( $_POST ['000' . $cat ['id']] ) ) );
				}
			}
			if (is_array ( $custom_fields ['customFields'] )) {
				foreach ( $custom_fields ['customFields'] as $field ) {
					update_option ( 'cust#_' . $field ['id'], strip_tags ( stripslashes ( $_POST ['cust#_' . $field ['id']] ) ) );
				}
			}
			update_option ( 'fd_header_content', $_POST ['fd_header_content'] );
			update_option ( 'fd_subheader_content', $_POST ['fd_subheader_content'] );
			update_option ( 'fd_submit_text', strip_tags ( stripslashes ( $_POST ['fd_submit_text'] ) ) );
			update_option ( 'fd_form_honeypot', strip_tags ( stripslashes ( $_POST ['fd_form_honeypot'] ) ) );
			update_option ( 'fd_form_poweredby', strip_tags ( stripslashes ( $_POST ['fd_form_poweredby'] ) ) );
			update_option ( 'fd_form_styling', strip_tags ( stripslashes ( $_POST ['fd_form_styling'] ) ) );
			update_option ( 'fd_form_border', strip_tags ( stripslashes ( $_POST ['fd_form_border'] ) ) );
			update_option ( 'fd_form_border_color', strip_tags ( stripslashes ( $_POST ['fd_form_border_color'] ) ) );
			update_option ( 'fd_form_background_color', strip_tags ( stripslashes ( $_POST ['fd_form_background_color'] ) ) );
			update_option ( 'fd_form_text_color', strip_tags ( stripslashes ( $_POST ['fd_form_text_color'] ) ) );
			update_option ( 'fd_form_text_size', strip_tags ( stripslashes ( $_POST ['fd_form_text_size'] ) ) );
			update_option ( 'fd_form_text_font', strip_tags ( stripslashes ( $_POST ['fd_form_text_font'] ) ) );
			$msg = base64_encode ( '<p class="success_msg">' . esc_html ( __ ( 'Form Settings Successfully Updated', $this->textdomain ) ) . '</p>' );
		}
		wp_redirect ( add_query_arg ( 'fdm', urlencode( $msg ), wp_get_referer () ) );
		exit ();
	}
	
	public function set_content_type($content_type) {
		return "text/html";
	}
	
	/**
	 * Attempts to signup a user, per the $_POST args.
	 *
	 * This sets a global message, that is then used in the widget
	 * output to retrieve and display that message.
	 */
	public function do_ajax_form_submit() {
		// Attempt the signup
		header ( 'Content-Type: application/json; charset=utf-8' );
		
		$result = array (
				'success' => true,
				'message' => '' 
		);
		$email = $_POST ['email'];
		$errs = $html_errs = array (); // Set up some vars
		$flag = 0;
		if (! filter_var ( $email, FILTER_VALIDATE_EMAIL )) {
			// invalid e-mail address
			$result ['success'] = false;
			$errs [] = sprintf ( __ ( "You Must fill Valid  %s.", $this->textdomain ), esc_html ( 'Email Address' ) );
		}
		if (get_option ( 'fd_cat_use' ) != '' && get_option ( 'fd_cat_use' ) == 'Y') {
			$category = $this->api->get_category_from_api ();
			foreach ( $category ['categories'] as $cat ) {
				if (get_option ( '000' . $cat ['id'] ) != '' && isset ( $_POST [get_option ( '000' . $cat ['id'] )] )) {
					$flag = 1;
				}
			}
			if ($flag == 0) {
				$result ['success'] = false;
				$errs [] = sprintf ( __ ( "You Must Select At Least One  %s.", $this->textdomain ), esc_html ( 'Interest' ) );
			}
		}
		// If we have errors, then show them
		if (count ( $errs ) > 0) {
			$result ['message'] = '<span class="fd_error_msg">';
			foreach ( $errs as $error_index => $error ) {
				if (! in_array ( $error_index, $html_errs )) {
					$error = esc_html ( $error );
				}
				$result ['message'] .= '&raquo; ' . $error . '<br />';
			}
			$result ['message'] .= '</span>';
		} else {
			$data = $this->api->add_member( $email, $this->reCaptchaEnabled );
			
			if (isset ( $data ['responseCode'] ['name'] ) && $data ['responseCode'] ['name'] == 'SUCCESS') {
				$headers = 'From: ' . $_POST ['efname'] . ' <' . $_POST ['email'] . '>' . "\r\n";
				$msgBody = "Hi <br/><br/> New user signup on newsletter form. <br/> Name: " . $_POST ['efname'] . "<br/> Email: " . $_POST ['email'] . " <br/>.... <br/><br/>Regards<br/> Firedrum Plugin";
				add_filter ( 'wp_mail_content_type', array( this, set_content_type ) );
				wp_mail ( get_option ( 'admin_email' ), ' New User Signup for Newsletter', $msgBody, $headers );
				$result ['message'] = "<strong class='fd_success_msg'>" . esc_html ( __ ( "Success, you've been signed up!", $this->textdomain ) ) . "</strong>";
			} else if ($data ['validationErrors'] [0] ['properties'] ['birthDate'] [0] == 'pattern' || $data ['validationErrors'] [0] ['properties'] ['anniversaryDate'] [0] == 'pattern') {
				$result ['message'] = "<strong class='fd_error_msg'>" . esc_html ( __ ( "Either your Birthdate or Anniversary is Incorrect", $this->textdomain ) ) . "</strong>";
			} else {
				$result ['message'] = "<strong class='fd_error_msg'>" . esc_html ( __ ( "Error: " . $data ['responseCode'] ['name'], $this->textdomain ) ) . "</strong>";
			}
		}
		
		if (! headers_sent ()) { // just in case...
			header ( 'Last-Modified: ' . gmdate ( 'D, d M Y H:i:s' ) . ' GMT', true, 200 );
		}
		echo json_encode ( $result );
		exit ();
	}
	
	/**
	 * Display the Firedrum Admin Menu
	 */
	public function admin_menu() {
		$page_hook_suffix = add_menu_page ( 'FireDrum', 'FireDrum', 'manage_options', 'firedrum-page', array (
				$this,
				'render_list_page' 
		) );
		add_action ( 'admin_print_scripts-' . $page_hook_suffix, array (
				$this,
				'js_admin' 
		) );
	}
	public function render_list_page() {
		$ajax_url = admin_url ( 'admin-ajax.php' );
		?>
<div class="wrap">
	<div id="icon-options-general" class="icon32">
		<br />
	</div>
	<h2>Firedrum Setup</h2>
	<br />
	<div id="fd_message" class=""><?php echo ( isset( $_GET['fdm'] ) ? base64_decode( $_GET['fdm'] ) : '' ); ?></div>
<?php if (get_option('fd_apikey') == '') { ?>      
	<form action="<?php echo $ajax_url; ?>" method="POST">
		<h3><?php esc_html_e('Login Info', $this->textdomain);?></h3>
		<table class="form-table">
			<tr valign="top">
				<th scope="row"><?php esc_html_e('API Key', $this->textdomain); ?>:</th>
				<td><input name="fd_apikey" type="text" id="fd_apikey" class="code"
					value="" size="32" /> <br /> <input type="hidden" name="action"
					value="firedrum_add_api_key" /> <input type="submit" name="Submit"
					value="<?php esc_attr_e('Save & Check', $this->textdomain);?>"
					class="button" /></td>
			</tr>
		</table>
	</form>
	<?php
		} else {
?>
	<br />
	<br />
	<form method="POST" action="<?php echo $ajax_url; ?>">
		<div style="width: 600px;">
			<input type="hidden" name="action" value="firedrum_update_api_key">
			<table class="widefat">
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Current API Key', $this->textdomain); ?>:</th>
					<td id="fd_api_key" onclick="javascript:fdAddInput(this,'api_key');"><?php echo get_option('fd_apikey');?></td>
					<td><input type="submit"
						value="<?php esc_attr_e('Update API Key', $this->textdomain); ?>"
						class="button" /></td>
				</tr>
			</table>
		</div>
	</form>
	<br>
	<form method="POST" action="<?php echo $ajax_url; ?>">
		<div style="width: 600px;">
			<input type="hidden" name="action" value="firedrum_update_api_url">
			<table class="widefat">
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Current API Url', $this->textdomain); ?>:</th>
					<td id="fd_api_url" onclick="javascript:fdAddInput(this,'api_url');"><?php echo get_option('fd_api_url','api.firedrummarketing.com/api/v2.jsp');?></td>
					<td><input type="submit"
						value="<?php esc_attr_e('Update API Url', $this->textdomain); ?>"
						class="button" /></td>
				</tr>
			</table>
		</div>
	</form>
      <?php if($this->api->check_api_key() == "8") { ?>
      <div class="apiNotCorrect"><?php esc_html_e('API is not correct. Please cross check the API details.', $this->textdomain); ?></div>
      <?php } else { ?>
	<br />
	<div>
		Short Code: <span style="font-weight: bold; font-size: 14px;">
			[firedrum_marketing] </span> <br /> Use this short code in template
		or post or pages to display this newsletter form.
	</div>
	<br />
	<form method="POST" action="<?php echo $ajax_url; ?>">
		<div style="width: 600px;">
			<input type="hidden" name="action"
				value="firedrum_save_form_settings"> <input type="submit"
				value="<?php esc_attr_e('Update Form Settings', $this->textdomain); ?>"
				class="button" /><br /> <br />
			<table class="widefat">
				<tr valign="top">
					<th scope="row" colspan="2"><strong>General Information</strong></th>
				</tr>
				<tr valign="top">
					<th width="70%" scope="row"><?php esc_html_e('First Name', $this->textdomain); ?>:</th>
					<td width="30%"><input name="fd_efname" type="checkbox"
						<?php if (get_option('fd_efname')=='efname' || $_POST['fd_efname']=='efname') { echo ' checked="checked"'; } ?>
						id="fd_efname" class="code" value="efname"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Last Name', $this->textdomain); ?>:</th>
					<td><input name="fd_elname" type="checkbox"
						<?php if (get_option('fd_elname')=='elname' || $_POST['fd_elname']=='elname') { echo ' checked="checked"'; } ?>
						id="fd_elname" class="code" value="elname"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Email Address*', $this->textdomain); ?>:</th>
					<td><input name="fd_email" type="checkbox"
						<?php  echo 'checked="checked"';  ?> id="fd_email" class="code"
						disabled="" value="email" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Company', $this->textdomain); ?>:</th>
					<td><input name="fd_company" type="checkbox"
						<?php if (get_option('fd_company')=='company' || $_POST['fd_company']=='company') { echo ' checked="checked"'; } ?>
						id="fd_company" class="code" value="company"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Address', $this->textdomain); ?>:</th>
					<td><input name="fd_address" type="checkbox"
						<?php if (get_option('fd_address')=='address' || $_POST['fd_address']=='address') { echo ' checked="checked"'; } ?>
						id="fd_address" class="code" value="address"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Address 2', $this->textdomain); ?>:</th>
					<td><input name="fd_address2" type="checkbox"
						<?php if (get_option('fd_address2')=='address2' || $_POST['fd_address2']=='address2') { echo ' checked="checked"'; } ?>
						id="fd_address2" class="code" value="address2"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('City', $this->textdomain); ?>:</th>
					<td><input name="fd_city" type="checkbox"
						<?php if (get_option('fd_city')=='city' || $_POST['fd_city']=='city') { echo ' checked="checked"'; } ?>
						id="fd_city" class="code" value="city" style="cursor: pointer;" />

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('State', $this->textdomain); ?>:</th>
					<td><input name="fd_state" type="checkbox"
						<?php if (get_option('fd_state')=='state' || $_POST['fd_state']=='state') { echo ' checked="checked"'; } ?>
						id="fd_state" class="code" value="state" style="cursor: pointer;" />

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Zip', $this->textdomain); ?>:</th>
					<td><input name="fd_zip" type="checkbox"
						<?php if (get_option('fd_zip')=='zip' || $_POST['fd_zip']=='zip') { echo ' checked="checked"'; } ?>
						id="fd_zip" class="code" value="zip" style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Phone', $this->textdomain); ?>:</th>
					<td><input name="fd_phone" type="checkbox"
						<?php if (get_option('fd_phone')=='phone' || $_POST['fd_phone']=='phone') { echo ' checked="checked"'; } ?>
						id="fd_phone" class="code" value="phone" style="cursor: pointer;" />

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Mobile', $this->textdomain); ?>:</th>
					<td><input name="fd_mphone" type="checkbox"
						<?php if (get_option('fd_mphone')=='mphone' || $_POST['fd_mphone']=='mphone') { echo ' checked="checked"'; } ?>
						id="fd_mphone" class="code" value="mphone"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Fax', $this->textdomain); ?>:</th>
					<td><input name="fd_fax" type="checkbox"
						<?php if (get_option('fd_fax')=='fax' || $_POST['fd_fax']=='fax') { echo ' checked="checked"'; } ?>
						id="fd_fax" class="code" value="fax" style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Gender', $this->textdomain); ?>:</th>
					<td><input name="fd_gender" type="checkbox"
						<?php if (get_option('fd_gender')=='gender' || $_POST['fd_gender']=='gender') { echo ' checked="checked"'; } ?>
						id="fd_gender" class="code" value="gender"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Birthday', $this->textdomain); ?>:</th>
					<td><input name="fd_bday" type="checkbox"
						<?php if (get_option('fd_bday')=='bday' || $_POST['fd_bday']=='bday') { echo ' checked="checked"'; } ?>
						id="fd_bday" class="code" value="bday" style="cursor: pointer;" />

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Anniversary', $this->textdomain); ?>:</th>
					<td><input name="fd_annv" type="checkbox"
						<?php if (get_option('fd_annv')=='annv' || $_POST['fd_annv']=='annv') { echo ' checked="checked"'; } ?>
						id="fd_annv" class="code" value="annv" style="cursor: pointer;" />

					</td>
				</tr>
			</table>
			<br />
			<table class="widefat">
				<tr valign="top">
					<th scope="row" colspan="2"><strong>Custom Fields</strong></th>
				</tr> 
            <?php
				
				$custom_fields = $this->api->get_custom_fields_from_api ();
				/*
				 * echo "<pre>";
				 * print_r($custom_fields);
				 * echo "</pre>";
				 */
				
				?>
                      
              <?php
				if (is_array ( $custom_fields ['customFields'] )) {
					foreach ( $custom_fields ['customFields'] as $field ) {
						?>
				<tr>
					<th width="70%" scope="row"><label><?php echo $field['name'];?></label></th>
					<td width="30%"><input type="checkbox"
						<?php if (get_option('cust#_'.$field['id'])==$field['name'] || $_POST['cust#_'.$field['id']]==$field['name']) { echo ' checked="checked"'; } ?>
						value="<?php echo $field['name'] ;?>"
						name="<?php echo 'cust#_'.$field['id'];?>"></td>
				</tr>
				  <?php
					}
				}
?>
		</table>
			<br />
			<table class="widefat">
				<tr>
					<th scope="row" colspan="2"><strong>Categories</strong></th>
				</tr>
				<tr>
				<tr valign="top">
					<th colspan="2" scope="row"><input name="fd_cat_use" type="radio"
						checked="" id="fd_cat_use" class="code" value="Y"
						style="cursor: pointer;" />  <?php esc_html_e('Use Checkboxes', $this->textdomain); ?>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;
    <input name="fd_cat_use" type="radio"
						<?php if (get_option('fd_cat_use')=='N' || $_POST['fd_cat_use']=='N') { echo ' checked="checked"'; } ?>
						id="fd_cat_use" class="code" value="N" style="cursor: pointer;" />  <?php esc_html_e('Hide Categories', $this->textdomain); ?>:</th>

				</tr>

            <?php
				$category = $this->api->get_category_from_api ();
				
				?>
              <?php
				if (is_array ( $category ['categories'] )) {
					foreach ( $category ['categories'] as $cat ) {
						?>
			     <tr>
					<th width="70%" scope="row"><label><?php echo $cat['name'];?></label></th>
					<td><input type="checkbox"
						<?php if (get_option('000'.$cat['id'])=='c000'.$cat['id'] || $_POST['000'.$cat['id']]=='c000'.$cat['id']) { echo ' checked="checked"'; } ?>
						value="<?php echo 'c000'.$cat['id'] ;?>"
						name="000<?php echo $cat['id'];?>"></td>
				</tr>
				  <?php
					}
				}
?>
			</table>
			<br />
			<table class="widefat">
				<tr>
					<th scope="row" colspan="2"><strong><?php esc_html_e( 'Style Formatting', $this->textdomain ); ?></strong></th>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Header content', $this->textdomain); ?>:</th>
					<td><textarea name="fd_header_content" rows="2" cols="50"><?php if (get_option('fd_header_content')!='') { echo esc_html(get_option('fd_header_content')); } elseif($_POST['fd_header_content']!='') { echo esc_html($_POST['fd_header_content']); }  ?></textarea>

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Sub-header content', $this->textdomain); ?>:</th>
					<td><textarea name="fd_subheader_content" rows="2" cols="50"><?php if (get_option('fd_subheader_content')!='') { echo esc_html(get_option('fd_subheader_content')); } elseif($_POST['fd_subheader_content']!='') { echo esc_html($_POST['fd_subheader_content']); }  ?></textarea>

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Submit Button Text', $this->textdomain); ?>:</th>
					<td><input type="text" name="fd_submit_text" size="30"
						value="<?php if (get_option('fd_submit_text')!='') { echo esc_attr(get_option('fd_submit_text')); } elseif($_POST['fd_submit_text']!='') { echo esc_attr($_POST['fd_submit_text']); }  ?>" />

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Enable anti-spam honeypot', $this->textdomain); ?>:</th>
					<td><input name="fd_form_honeypot" type="checkbox"
						<?php if (get_option('fd_form_honeypot')=='Y' || $_POST['fd_form_honeypot']=='Y') { echo ' checked="checked"'; } ?>
						id="fd_form_honeypot" class="code" value="Y"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Display a "powered by FireDrum" link on your form?', $this->textdomain); ?>:</th>
					<td><input name="fd_form_poweredby" type="checkbox"
						<?php if (get_option('fd_form_poweredby')=='Y' || $_POST['fd_form_poweredby']=='Y') { echo ' checked="checked"'; } ?>
						id="fd_form_poweredby" class="code" value="Y"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Enable Custom Styling', $this->textdomain); ?>:</th>
					<td><input name="fd_form_styling"
						onclick="return disabledCustomStyle();" type="checkbox"
						<?php if (get_option('fd_form_styling')=='Y' || $_POST['fd_form_styling']=='Y') { echo ' checked="checked"'; } ?>
						id="fd_form_styling" class="code" value="Y"
						style="cursor: pointer;" /></td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Enable Form Border', $this->textdomain); ?>:</th>
					<td><input name="fd_form_border"
						onclick="return disabledBoreder();" type="checkbox"
						<?php if (get_option('fd_form_border')=='Y' || $_POST['fd_form_border']=='Y') { echo ' checked="checked"'; } ?>
						id="fd_form_border" class="code" value="Y"
						style="cursor: pointer;" /> &nbsp;&nbsp;&nbsp;&nbsp; <input
						type="text" name="fd_form_border_color" id="fd_form_border_color"
						class="fd_form_border_color" size="30"
						value="<?php if (get_option('fd_form_border_color')!='') { echo esc_attr(get_option('fd_form_border_color')); } elseif($_POST['fd_form_border_color']!='') { echo esc_attr($_POST['fd_form_border_color']); }  ?>"
						disabled="" /></td>
				</tr>

				<tr valign="top">
					<th scope="row"><?php esc_html_e('Form Background Color', $this->textdomain); ?>:</th>
					<td><input type="text" id="fd_form_background_color"
						class="fd_form_background_color" name="fd_form_background_color"
						size="30" disabled=""
						value="<?php if (get_option('fd_form_background_color')!='') { echo esc_attr(get_option('fd_form_background_color')); } elseif($_POST['fd_form_background_color']!='') { echo esc_attr($_POST['fd_form_background_color']); }  ?>" />

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Text Color', $this->textdomain); ?>:</th>
					<td><input type="text" id="fd_form_text_color"
						name="fd_form_text_color" class="fd_form_text_color" disabled=""
						size="30"
						value="<?php if (get_option('fd_form_text_color')!='') { echo esc_attr(get_option('fd_form_text_color')); } elseif($_POST['fd_form_text_color']!='') { echo esc_attr($_POST['fd_form_text_color']); }  ?>" />

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Text Size', $this->textdomain); ?>:</th>
					<td><input type="text" id="fd_form_text_size"
						name="fd_form_text_size" disabled="" size="30"
						value="<?php if (get_option('fd_form_text_size')!='') { echo esc_attr(get_option('fd_form_text_size')); } elseif($_POST['fd_form_text_size']!='') { echo esc_attr($_POST['fd_form_text_size']); }  ?>" />

					</td>
				</tr>
				<tr valign="top">
					<th scope="row"><?php esc_html_e('Text Font', $this->textdomain); ?>:</th>
					<td><select name="fd_form_text_font" id="fd_form_text_font"
						disabled="">
							<option value="Arial"
								<?php if (get_option('fd_form_text_font')=='Arial') { echo 'selected="selected"'; } elseif($_POST['fd_form_text_font']=='Arial') { echo 'selected="selected"'; }  ?>>Arial</option>
							<option value="Courier"
								<?php if (get_option('fd_form_text_font')=='Courier') { echo 'selected="selected"'; } elseif($_POST['fd_form_text_font']=='Courier') { echo 'selected="selected"'; }  ?>>Courier</option>
							<option value="Georgia"
								<?php if (get_option('fd_form_text_font')=='Georgia') { echo 'selected="selected"'; } elseif($_POST['fd_form_text_font']=='Georgia') { echo 'selected="selected"'; }  ?>>Georgia</option>
							<option value="Helvetica"
								<?php if (get_option('fd_form_text_font')=='Helvetica') { echo 'selected="selected"'; } elseif($_POST['fd_form_text_font']=='Helvetica') { echo 'selected="selected"'; }  ?>>Helvetica</option>
							<option value="Verdana"
								<?php if (get_option('fd_form_text_font')=='Verdana') { echo 'selected="selected"'; } elseif($_POST['fd_form_text_font']=='Verdana') { echo 'selected="selected"'; }  ?>>Verdana</option>
					</select></td>
				</tr>

			</table>
		</div>
		<br /> <input type="submit"
			value="<?php esc_attr_e('Update Form Settings', $this->textdomain); ?>"
			class="button" />
	</form>
	
	<?php
			}
		}
		?>
</div>
<?php
	}
	
	/**
	 * Displays a Firedrum Signup Form
	 */
	public function signup_form($atts = array()) {
		$atts = shortcode_atts ( $atts, array (
				'show_header' => true 
		) );
		if (get_option ( 'fd_apikey' ) != '') {
			$sub_heading = false;
			if ($atts ['show_header'] === true) {
				$header = get_option ( 'fd_header_content' );
				// See if we have custom header content
				if (! empty ( $header )) {
					// See if we need to wrap the header content in our own div
					if (strlen ( $header ) == strlen ( strip_tags ( $header ) )) {
						echo ! empty ( $atts ['before_title'] ) ? $atts ['before_title'] : '<div class="fd_custom_border_hdr">';
						echo $header; // don't escape $header b/c it may have HTML allowed
						echo ! empty ( $atts ['after_title'] ) ? $atts ['after_title'] : '</div><!-- /fd_custom_border_hdr -->';
					} else {
						echo $header; // don't escape $header b/c it may have HTML allowed
					}
				}
				$sub_heading = trim ( get_option ( 'fd_subheader_content' ) );
			}
?>
<div class="fd_signup" id="fd_signup">
	<form method="POST" action="" class="fd_signup_form"
		id="fd_signup_form">
		<input type="hidden" name="action" value="firedrum_form_submit" /><?php
			if (get_option ( 'fd_form_honeypot' ) != '') {
?>
		<span style="display: none; visibility: hidden;"> Please ignore this
			text box. It is used to detect spammers. If you enter anything into
			this text box, your message will not be sent. <input type="text"
			name="are_you_human_54" size="1" value="">
		</span><?php
			}
			
			if ($sub_heading) {
?>
		<div id="fd_subheader">
			<?php echo $sub_heading; ?>
		</div>
		<!-- /fd_subheader -->
		<?php
			}
?>
		<div class="fd_form_inside">
			<div class="updated fd_message" id="fd_message">
        		<?php echo ( isset( $_GET['fdm'] ) ? base64_decode ( $_GET['fdm'] ) : '' ); ?>
			</div>
			<!-- /fd_message -->
			<div class="fd_merge_var">
				<label class="fd_var_label" for="email">Email Address<span
					class="fd_required">*</span></label> <input name="email" id="email"
					type="text" size="25" maxlength="64" class="fd_input">
			</div><?php
			if (get_option ( 'fd_efname' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="efname">First Name</label> <input
					name="efname" id="efname" type="text" size="25" maxlength="64">
			</div><?php
			}
			if (get_option ( 'fd_elname' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="elname">Last Name</label> <input
					name="elname" id="elname" type="text" size="25" maxlength="64">
			</div><?php
			}
			if (get_option ( 'fd_company' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="company">Company</label> <input
					name="company" id="company" type="text" size="25" maxlength="255">
			</div><?php
			}
			if (get_option ( 'fd_address' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="address">Address</label> <input
					name="address" id="address" type="text" size="25" maxlength="255">
			</div><?php
			}
			if (get_option ( 'fd_address2' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="address2">Address 2</label> <input
					name="address2" id="address2" type="text" size="25" maxlength="64">
			</div><?php
			}
			if (get_option ( 'fd_city' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="city">City</label> <input
					name="city" id="city" type="text" size="25" maxlength="64">
			</div><?php
			}
			if (get_option ( 'fd_state' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="state">State</label> <select
					name="state" id="state">
					<option value="">-</option>
					<option value="Alabama">Alabama</option>
					<option value="Alaska">Alaska</option>
					<option value="Alberta">Alberta</option>
					<option value="American Samoa">American Samoa</option>
					<option value="Arizona">Arizona</option>
					<option value="Arkansas">Arkansas</option>
					<option value="British Columbia">British Columbia</option>
					<option value="California">California</option>
					<option value="Colorado">Colorado</option>
					<option value="Connecticut">Connecticut</option>
					<option value="Delaware">Delaware</option>
					<option value="District of Columbia">District of Columbia</option>
					<option value="Florida">Florida</option>
					<option value="Georgia">Georgia</option>
					<option value="Guam">Guam</option>
					<option value="Hawaii">Hawaii</option>
					<option value="Idaho">Idaho</option>
					<option value="Illinois">Illinois</option>
					<option value="Indiana">Indiana</option>
					<option value="Iowa">Iowa</option>
					<option value="Kansas">Kansas</option>
					<option value="Kentucky">Kentucky</option>
					<option value="Louisiana">Louisiana</option>
					<option value="Maine">Maine</option>
					<option value="Manitoba">Manitoba</option>
					<option value="Maryland">Maryland</option>
					<option value="Massachusetts">Massachusetts</option>
					<option value="Michigan">Michigan</option>
					<option value="Minnesota">Minnesota</option>
					<option value="Mississippi">Mississippi</option>
					<option value="Missouri">Missouri</option>
					<option value="Montana">Montana</option>
					<option value="Nebraska">Nebraska</option>
					<option value="Nevada">Nevada</option>
					<option value="New Brunswick">New Brunswick</option>
					<option value="New Hampshire">New Hampshire</option>
					<option value="New Jersey">New Jersey</option>
					<option value="New Mexico">New Mexico</option>
					<option value="New York">New York</option>
					<option value="Newfoundland">Newfoundland</option>
					<option value="North Carolina">North Carolina</option>
					<option value="North Dakota">North Dakota</option>
					<option value="Northern Mariana Islands">Northern Mariana Islands</option>
					<option value="North West Territory">North West Territory</option>
					<option value="Nova Scotia">Nova Scotia</option>
					<option value="Ohio">Ohio</option>
					<option value="Oklahoma">Oklahoma</option>
					<option value="Ontario">Ontario</option>
					<option value="Oregon">Oregon</option>
					<option value="Pennsylvania">Pennsylvania</option>
					<option value="Puerto Rico">Puerto Rico</option>
					<option value="Quebec">Quebec</option>
					<option value="Rhode Island">Rhode Island</option>
					<option value="Saskatchewan">Saskatchewan</option>
					<option value="South Carolina">South Carolina</option>
					<option value="South Dakota">South Dakota</option>
					<option value="Tennessee">Tennessee</option>
					<option value="Texas">Texas</option>
					<option value="Trust Territories">Trust Territories</option>
					<option value="Utah">Utah</option>
					<option value="Vermont">Vermont</option>
					<option value="Virgin Islands">Virgin Islands</option>
					<option value="Virginia">Virginia</option>
					<option value="Washington">Washington</option>
					<option value="West Virginia">West Virginia</option>
					<option value="Wisconsin">Wisconsin</option>
					<option value="Wyoming">Wyoming</option>
					<option value="Yukon">Yukon</option>
					<option value="Other">Other</option>
				</select>
			</div><?php
			}
			if (get_option ( 'fd_zip' ) != '') {
				?>
				<div class="fd_merge_var">
				<label class="fd_var_label" for="zip">Zip</label> <input name="zip"
					id="zip" type="text" size="10" maxlength="64">
			</div><?php
			}
			if (get_option ( 'fd_phone' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="phone">Phone</label> <input
					name="phone" id="phone" type="text" size="10" maxlength="64">
			</div><?php
			}
			if (get_option ( 'fd_mphone' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="mphone">Mobile</label> <input
					name="mphone" id="mphone" type="text" size="10" maxlength="64">
			</div><?php
			}
			if (get_option ( 'fd_fax' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="fax">Fax</label> <input name="fax"
					id="fax" type="text" size="10" maxlength="64">
			</div><?php
			}
			if (get_option ( 'fd_gender' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="gender">Gender</label> <select
					name="gender" id="gender">
					<option value="">-</option>
					<option value="male">Male</option>
					<option value="female">Female</option>
				</select>
			</div><?php
			}
			if (get_option ( 'fd_bday' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="bdaymonth">Birthday</label> <select
					name="bdaymonth" id="bdaymonth">
					<option selected value="">Month</option>
					<option value="01">January</option>
					<option value="02">February</option>
					<option value="03">March</option>
					<option value="04">April</option>
					<option value="05">May</option>
					<option value="06">June</option>
					<option value="07">July</option>
					<option value="08">August</option>
					<option value="09">September</option>
					<option value="10">October</option>
					<option value="11">November</option>
					<option value="12">December</option>
				</select> <select name="bdayday">
					<option selected value="">Day</option>
					<option value="01">01</option>
					<option value="02">02</option>
					<option value="03">03</option>
					<option value="04">04</option>
					<option value="05">05</option>
					<option value="06">06</option>
					<option value="07">07</option>
					<option value="08">08</option>
					<option value="09">09</option>
					<option value="10">10</option>
					<option value="11">11</option>
					<option value="12">12</option>
					<option value="13">13</option>
					<option value="14">14</option>
					<option value="15">15</option>
					<option value="16">16</option>
					<option value="17">17</option>
					<option value="18">18</option>
					<option value="19">19</option>
					<option value="20">20</option>
					<option value="21">21</option>
					<option value="22">22</option>
					<option value="23">23</option>
					<option value="24">24</option>
					<option value="25">25</option>
					<option value="26">26</option>
					<option value="27">27</option>
					<option value="28">28</option>
					<option value="29">29</option>
					<option value="30">30</option>
					<option value="31">31</option>
				</select>
			</div><?php
			}
			if (get_option ( 'fd_annv' ) != '') {
				?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="annvmonth">Anniversary</label> <select
					name="annvmonth" id="annvmonth">
					<option selected value="">Month</option>
					<option value="01">January</option>
					<option value="02">February</option>
					<option value="03">March</option>
					<option value="04">April</option>
					<option value="05">May</option>
					<option value="06">June</option>
					<option value="07">July</option>
					<option value="08">August</option>
					<option value="09">September</option>
					<option value="10">October</option>
					<option value="11">November</option>
					<option value="12">December</option>
				</select> <select name="annvday">
					<option selected value="">Day</option>
					<option value="01">01</option>
					<option value="02">02</option>
					<option value="03">03</option>
					<option value="04">04</option>
					<option value="05">05</option>
					<option value="06">06</option>
					<option value="07">07</option>
					<option value="08">08</option>
					<option value="09">09</option>
					<option value="10">10</option>
					<option value="11">11</option>
					<option value="12">12</option>
					<option value="13">13</option>
					<option value="14">14</option>
					<option value="15">15</option>
					<option value="16">16</option>
					<option value="17">17</option>
					<option value="18">18</option>
					<option value="19">19</option>
					<option value="20">20</option>
					<option value="21">21</option>
					<option value="22">22</option>
					<option value="23">23</option>
					<option value="24">24</option>
					<option value="25">25</option>
					<option value="26">26</option>
					<option value="27">27</option>
					<option value="28">28</option>
					<option value="29">29</option>
					<option value="30">30</option>
					<option value="31">31</option>
				</select>
			</div><?php
			}
			$custom_fields = $this->api->get_custom_fields_from_api ();
			if (is_array ( $custom_fields ['customFields'] )) {
				foreach ( $custom_fields ['customFields'] as $field ) {
					if (get_option ( 'cust#_' . $field ['id'] ) != '') {
						if ($field ['dataType'] != 'date') {
							?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="cust#_<?php echo $field ['id']; ?>"><?php echo $field ['name']; ?></label>
				<input name="cust#_<?php echo $field ['id']; ?>"
					id="cust#_<?php echo $field ['id']; ?>" type="text" size="25"
					maxlength="64">
			</div><?php
						} else {
							?>
			<div class="fd_merge_var">
				<label class="fd_var_label" for="cust#_<?php echo $field ['id']; ?>"><?php echo $field ['name']; ?></label>
				<select name="custmonth#_<?php echo $field ['name']; ?>"
					id="cust#_<?php echo $field ['name']; ?>">
					<option selected value="">Month</option>
					<option value="01">January</option>
					<option value="02">February</option>
					<option value="03">March</option>
					<option value="04">April</option>
					<option value="05">May</option>
					<option value="06">June</option>
					<option value="07">July</option>
					<option value="08">August</option>
					<option value="09">September</option>
					<option value="10">October</option>
					<option value="11">November</option>
					<option value="12">December</option>
				</select> <select name="custday#_<?php echo $field ['name']; ?>"
					id="cust#_<?php echo $field ['name']; ?>">
					<option selected value="">Day</option>
					<option value="01">01</option>
					<option value="02">02</option>
					<option value="03">03</option>
					<option value="04">04</option>
					<option value="05">05</option>
					<option value="06">06</option>
					<option value="07">07</option>
					<option value="08">08</option>
					<option value="09">09</option>
					<option value="10">10</option>
					<option value="11">11</option>
					<option value="12">12</option>
					<option value="13">13</option>
					<option value="14">14</option>
					<option value="15">15</option>
					<option value="16">16</option>
					<option value="17">17</option>
					<option value="18">18</option>
					<option value="19">19</option>
					<option value="20">20</option>
					<option value="21">21</option>
					<option value="22">22</option>
					<option value="23">23</option>
					<option value="24">24</option>
					<option value="25">25</option>
					<option value="26">26</option>
					<option value="27">27</option>
					<option value="28">28</option>
					<option value="29">29</option>
					<option value="30">30</option>
					<option value="31">31</option>
				</select>

			</div><?php
						}
					}
				}
			}
			
			$category = $this->api->get_category_from_api ();
			if (get_option ( 'fd_cat_use' ) != '' && get_option ( 'fd_cat_use' ) == 'Y') {
				?>
			<div class="interestFields">
				<h3>Interests</h3><?php
				if (is_array ( $category ['categories'] )) {
					foreach ( $category ['categories'] as $cat ) {
						if (get_option ( '000' . $cat ['id'] ) != '') {
							?>
				<input type="checkbox"
					name="<?php echo get_option ( '000' . $cat ['id'] ); ?>"
					value="<?php echo get_option ( '000' . $cat ['id'] ); ?>"> <span
					class="catName"><?php echo $cat ['name']; ?></span><?php
						}
					}
				}
				?>
			</div><?php
			} else {
				foreach ( $category ['categories'] as $cat ) {
					if (get_option ( '000' . $cat ['id'] ) != '') {
						?>
						<input type="hidden"
				name="<?php echo get_option ( '000' . $cat ['id'] ); ?>"
				value="<?php echo get_option ( '000' . $cat ['id'] ); ?>"><?php
					}
				}
			}
			?>
			<div class="fd_signup_submit">
				<input type="submit" name="fd_signup_submit" id="fd_signup_submit"
					value="<?php echo ( get_option ( 'fd_submit_text' ) != '' ? get_option( 'fd_submit_text' ) : 'Submit' ); ?>"><?php
			if (get_option ( 'fd_form_poweredby' ) == 'Y') {
				?>
				<div class="poweredby"
					style="text-align: center; font-size: 12px; margin-top: 8px;">
					powered by <a href="http://www.firedrum.com/" target="_blank">FireDrum</a>
				</div><?php
			}
			?>
			</div>
		</div>
	</form>
</div><?php
		}
	}
	public function signup_form_shortcode($atts) {
		$atts = shortcode_atts ( array (
				'show_headers' => true 
		), $atts, 'firedrum_form' );
		ob_start ();
		$this->signup_form ( $atts );
		return ob_get_clean ();
	}
	public function form_shortcode($atts) {
		$atts = shortcode_atts ( array (
				'show_headers' => false 
		), $atts, 'firedrum_marketing' );
		ob_start ();
		$this->signup_form ( $atts );
		return ob_get_clean ();
	}
}