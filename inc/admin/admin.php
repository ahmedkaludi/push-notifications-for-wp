<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) exit;

require_once PUSH_NOTIFICATION_PLUGIN_DIR."inc/admin/class-function.php";
class Push_Notification_Admin{
	
	public function __construct(){}

	public function init(){
		add_action( 'admin_menu', array( $this, 'add_menu_links') );
		add_action( 'admin_init', array( $this, 'settings_init') );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_scripts' ) );

		add_action( 'wp_ajax_pn_verify_user', array( $this, 'pn_verify_user' ) ); 
		add_action( 'wp_ajax_pn_revoke_keys', array( $this, 'pn_revoke_keys' ) ); 
		add_action( 'wp_ajax_pn_subscribers_data', array( $this, 'pn_subscribers_data' ) ); 
		add_action( 'wp_ajax_pn_send_notification', array( $this, 'pn_send_notification' ) ); 
		


		//Send push on publish and update
		add_action( 'transition_post_status', array( $this, 'send_notification_on_update' ), 10, 3 ); 
	}

	function load_admin_scripts($hook_suffix){
		if($hook_suffix=='toplevel_page_push-notification'){
			wp_enqueue_script('push_notification_script', PUSH_NOTIFICATION_PLUGIN_URL.'/assets/main-admin-script.js', array('jquery'), PUSH_NOTIFICATION_PLUGIN_VERSION, true);
			wp_enqueue_style('push-notification-style', PUSH_NOTIFICATION_PLUGIN_URL.'/assets/main-admin-style.css', array('dashboard'), PUSH_NOTIFICATION_PLUGIN_VERSION, 'all');
			 if ( is_multisite() ) {
	            $link = get_site_url();              
	        }
	        else {
	            $link = home_url();
	        }    
			wp_localize_script("push_notification_script", 'pn_setings', 
								array(
									"home_url"=>  $link,
									"remote_nonce"=> wp_create_nonce("pn_notification")
									)
							);
		}
	}

	public function add_menu_links(){
		// Main menu page
		add_menu_page( esc_html__( 'Push Notification-Admin', 'push-notification' ), 
	                esc_html__( 'Push Notification', 'push-notification' ), 
	                'manage_options',
	                'push-notification',
	                array($this, 'admin_interface_render'),
	                '', 100 );
		
		// Settings page - Same as main menu page
		add_submenu_page( 'push-notification',
	                esc_html__( 'Push Notification-Admin', 'push-notification' ),
	                esc_html__( 'Settings', 'push-notification' ),
	                'manage_options',
	                'push-notification',
	                array($this, 'admin_interface_render')
	            );
	}
	function admin_interface_render(){
    
		// Authentication
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?><div class="wrap push_notification-settings-wrap">
			<h1 class="page-title"><?php echo esc_html__('Push notification', 'push-notification'); ?></h1>
			<form action="options.php" method="post" enctype="multipart/form-data" class="push_notification-settings-form">		
				<div class="form-wrap">
					<?php
					settings_fields( 'push_notification_setting_dashboard_group' );
					echo "<div class='push_notification-dashboard'>";
						// Status
						do_settings_sections( 'push_notification_dashboard_section' );	// Page slug
						$authData = push_notification_auth_settings();
						if(isset($authData['token_details']['validated']) && $authData['token_details']['validated']==1){
							$this->shownotificationData();
						}
					echo "</div>";
					?>
				</div>
			</form>
		</div><?php
	}

	public function settings_init(){
		register_setting( 'push_notification_setting_dashboard_group', 'push_notification_settings' );

		add_settings_section('push_notification_dashboard_section',
					 esc_html__('','push-notification'), 
					 '__return_false', 
					 'push_notification_dashboard_section');
		
			add_settings_field(
				'pn_key_validate_status',	// ID
				esc_html__('API', 'push-notification'),			// Title
				array( $this, 'pn_key_validate_status_callback'),// Callback
				'push_notification_dashboard_section',	// Page slug
				'push_notification_dashboard_section'	// Settings Section ID
			);

		add_settings_section('push_notification_user_settings_section',
					 esc_html__('','push-notification'), 
					 '__return_false', 
					 'push_notification_user_settings_section');
		
			add_settings_field(
				'pn_key_sendpush_edit',					// ID
				esc_html__('Send notification on editing', 'push-notification'),// Title
				array( $this, 'user_settings_callback'),// Callback
				'push_notification_user_settings_section',	// Page slug
				'push_notification_user_settings_section'	// Settings Section ID
			);
			add_settings_field(
				'pn_key_sendpush_publish',								// ID
				esc_html__('Send notification on publish', 'push-notification'),// Title
				array( $this, 'user_settings_onpublish_callback'),// Callback
				'push_notification_user_settings_section',	// Page slug
				'push_notification_user_settings_section'	// Settings Section ID
			);
		
	}

	function shownotificationData(){
		$auth_settings = push_notification_auth_settings();
		$detail_settings = push_notification_details_settings();
		if( !$detail_settings && isset( $auth_settings['user_token'] ) ){
			 PN_Server_Request::getsubscribersData( $auth_settings['user_token'] );
			 $detail_settings = push_notification_details_settings();
		}
		
		$updated_at = '';
		if(isset($detail_settings['updated_at'])){
			//echo $detail_settings['updated_at'];die;
			$updated_at = human_time_diff( strtotime( $detail_settings['updated_at'] ), strtotime( date('Y-m-d H:i:s') ) );
			if(!empty( $updated_at ) ){
				$updated_at .= " ago";
			} 


		}
		$subscriber_count = 0;
		if(isset($detail_settings['subscriber_count'])){ $subscriber_count = $detail_settings['subscriber_count']; }
		echo '<section class="pn_general_wrapper">
				<div class="action-wrapper"> '.esc_html__($updated_at, 'push-notification').' <button type="button" class="button" id="grab-subscribers-data" class="dashicons dashicons-update">'.esc_html__('Refresh data', 'push-notification').'</button>
				</div>
				<div class="pn-content">
					<div class="pn-card-wrapper">
						<div class="pn-card">
							<div class="title-name">'.esc_html__('Total Subscribers', 'push-notification').':</div>
							<div class="desc column-description">
								'.$subscriber_count.'
							</div>
						</div>
					</div>
				</div>
				';
				do_settings_sections( 'push_notification_user_settings_section' );
		echo   '<input type="submit" value="'.esc_html__('Save Settings', 'push-notification').'" class="button">
			</section>
			';
		echo '<br/><br/><div class="pn-other-settings-options">
					<div id="dashboard_right_now" class="postbox " >
						<h2 class="hndle">'.esc_html__('Send Custom Notification', 'push-notification').'</h2>
						<div class="inside">
							<div class="main">
								<div class="form-group">
									<label for="notification-title">'.esc_html__('Title','push-notification').'</label>
									<input type="text" id="notification-title" class="regular-text">
								</div>
								<div class="form-group">
									<label for="notification-link">'.esc_html__('Link', 'push-notification').'</label>
									<input type="text" id="notification-link" class="regular-text">
								</div>
								<div class="form-group">
									<label for="notification-imageurl">'.esc_html__('Image url', 'push-notification').'</label>
									<input type="text" id="notification-imageurl" class="regular-text">
								</div>
								<div class="form-group">
									<label for="notification-message">'.esc_html__('Message', 'push-notification').'</label>
									<textarea type="text" id="notification-message" class="regular-text"></textarea>
								</div>
								<input type="button" class="button" id="pn-send-custom-notification" value="'.esc_html__('Send Notification', 'push-notification').'">
								<div class="pn-send-messageDiv"></div>
							</div>
						</div>
					</div>
				</div>';
	}

	public function pn_key_validate_status_callback(){
		$authData = push_notification_auth_settings();
		if( !isset($authData['token_details']['validated']) 
			|| (isset($authData['token_details']) && $authData['token_details']['validated']!=1) ){
			echo "<fieldset>";
			PN_Field_Generator::get_input_password('user_token', 'user_auth_token_key');
			PN_Field_Generator::get_button('Validate', 'user_auth_vadation');
			echo '<span class="resp_message"></span></fieldset>
			<p>'.esc_html__('Get the API', 'push-notification').' <a target="_blank" href="'.PN_Server_Request::$notificationlanding.'">'.esc_html__('click here', 'push-notification').'</a></p>';
		}else{
			echo "<input type='text' class='regular-text' value='xxxxxxxxxxxxxxxxxx'>
				<span class='text-success resp_message' style='color:green;'>".esc_html__('User Verified', 'push-notification')."</span>
				<button type='button' class='button dashicons-before dashicons-no-alt' id='pn-remove-apikey' style='margin-left:10%; line-height: 1.4;'>".esc_html__('Revoke key', 'push-notification')."</button>";
		}

	}//function closed
	
	public function user_settings_callback(){
		$notification = push_notification_settings();
		PN_Field_Generator::get_input_checkbox('on_edit', '1', 'pn_push_on_edit', 'pn-checkbox pn_push_on_edit');

	}
	public function user_settings_onpublish_callback(){
		$notification = push_notification_settings();
		PN_Field_Generator::get_input_checkbox('on_publish', '1', 'pn_push_on_publish', 'pn-checkbox pn_push_on_publish');

	}

	public function pn_verify_user(){
		$nonce = sanitize_text_field($_POST['nonce']);
		if( !wp_verify_nonce($nonce, 'pn_notification') ){
			echo json_encode(array("status"=> 503, 'message'=>'Request not authorized'));die;
		}else{
			$user_token = sanitize_text_field($_POST['user_token']);
			$response = PN_Server_Request::varifyUser($user_token);

			echo json_encode($response);die;
		}
		

		echo json_encode(array("status"=> 503, 'message'=>'Request not identified'));die;
	}
	
	public function pn_revoke_keys(){
		$nonce = sanitize_text_field($_POST['nonce']);
		if( !wp_verify_nonce($nonce, 'pn_notification') ){
			echo json_encode(array("status"=> 503, 'message'=>'Request not authorized'));die;
		}else{
			delete_option('push_notification_auth_settings');
			echo json_encode(array("status"=> 200, 'message'=>'API key removed successfully'));die;
		}
	}

	public function pn_subscribers_data(){
		$nonce = sanitize_text_field($_POST['nonce']);
		if( !wp_verify_nonce($nonce, 'pn_notification') ){
			echo json_encode(array("status"=> 503, 'message'=>'Request not authorized'));die;
		}else{
			$auth_settings = push_notification_auth_settings();
			if( isset( $auth_settings['user_token'] ) ){
				 PN_Server_Request::getsubscribersData( $auth_settings['user_token'] );
				 echo json_encode(array("status"=> 200, 'message'=>'Data updated'));die;
			}else{
				echo json_encode(array("status"=> 503, 'message'=>'User token not found'));die;	
			}
			
		}
	}
	public function pn_send_notification(){
		$nonce = sanitize_text_field($_POST['nonce']);
		if( !wp_verify_nonce($nonce, 'pn_notification') ){
			echo json_encode(array("status"=> 503, 'message'=>'Request not authorized'));die;
		}else{
			$auth_settings = push_notification_auth_settings();
			$title = sanitize_text_field($_POST['title']);
			$message = sanitize_textarea_field($_POST['message']);
			$link_url = sanitize_text_field($_POST['link_url']);
			$image_url = sanitize_text_field($_POST['image_url']);
			if( isset( $auth_settings['user_token'] ) ){
				$response = PN_Server_Request::sendPushNotificatioData( $auth_settings['user_token'], $title,$message, $link_url, $image_url );
				 echo json_encode($response);die;
			}else{
				echo json_encode(array("status"=> 503, 'message'=>'User token not found'));die;	
			}
			
		}
	}

	public function send_notification_on_update($new_status, $old_status, $post){
		$pn_settings = push_notification_settings();
		$auth_settings = push_notification_auth_settings();

		if ( 'publish' !== $new_status ){
        	return;
		}
		
		$send_notification = false;
		//for Edit
		if(isset($pn_settings['on_edit']) && $pn_settings['on_edit']==1){
			if ( $new_status === $old_status) {
			 	$this->send_notification($post);
			 	$send_notification = true;
			}
		}
		//for publish
		if(!$send_notification && isset($pn_settings['on_publish']) && $pn_settings['on_publish']==1){
			if ( $new_status !== $old_status) {
			 	$this->send_notification($post);
			}
		}
			

	}
	protected function send_notification($post){
		$post_id = $post->ID;
		$post_content = $post->post_content;
		$post_title = $post->post_title;
		$auth_settings = push_notification_auth_settings();

		//API Data
		$title = sanitize_text_field(wp_strip_all_tags($post_title) );
		$message = wp_trim_words(wp_strip_all_tags(sanitize_text_field($post_content)), 20);
		$link_url = esc_url_raw(get_permalink( $post_id ));
		$image_url = '';
		if(has_post_thumbnail($post_id)){
			$image_url = esc_url_raw(get_the_post_thumbnail_url($post_id));
		}
		
		if( isset( $auth_settings['user_token'] ) && !empty($auth_settings['user_token']) ){
			$response = PN_Server_Request::sendPushNotificatioData( $auth_settings['user_token'], $title, $message, $link_url, $image_url );
		}//auth token check 
	
	}

	
}

if(is_admin() || wp_doing_ajax()){
	$push_Notification_Admin_Obj  = new Push_Notification_Admin(); 
	$push_Notification_Admin_Obj->init();
}

function push_notification_settings(){
	$push_notification_settings = get_option( 'push_notification_settings', array() ); 
	return $push_notification_settings;
}
function push_notification_auth_settings(){
	$push_notification_auth_settings = get_option( 'push_notification_auth_settings', array() ); 
	return $push_notification_auth_settings;
}
function push_notification_details_settings(){
	$push_notification_details_settings = get_option( 'push_notification_details_settings', array() ); 
	return $push_notification_details_settings;
}


/** 
* Server Side fields generation class
*/
class PN_Field_Generator{
	static $settingName = 'push_notification_settings';

	public static function get_input($name, $id="", $class=""){
		$settings = push_notification_settings();
		?><input type="text" name="<?php echo esc_attr(self::$settingName); ?>[<?php echo esc_attr($name); ?>]" class="regular-text" id="<?php echo esc_attr($id); ?>" value="<?php if ( isset( $settings[$name] ) && ( ! empty($settings[$name]) ) ) echo esc_attr($settings[$name]); ?>"/><?php
	}
	public static function get_input_checkbox($name, $value, $id="", $class=""){
		$settings = push_notification_settings();
		?><input type="checkbox" name="<?php echo esc_attr(self::$settingName); ?>[<?php echo esc_attr($name); ?>]" class="regular-text" id="<?php echo esc_attr($id); ?>" <?php if ( isset( $settings[$name] ) && $settings[$name]==$value ) echo esc_attr("checked"); ?> value="<?php echo esc_attr($value); ?>"/><?php
	}
	public static function get_input_password($name, $id="", $class=""){
		$settings = push_notification_settings();
		?><input type="password" name="<?php echo esc_attr(self::$settingName); ?>[<?php echo esc_attr($name); ?>]" class="regular-text" id="<?php echo esc_attr($id); ?>" value="<?php if ( isset( $settings[$name] ) && ( ! empty($settings[$name]) ) ) echo esc_attr($settings[$name]); ?>"/><?php
	}
	public static function get_button($name, $id="", $class=""){
		$settings = push_notification_settings();
		?>
		<button type="button"  class="button <?php echo esc_attr($class); ?>" id="<?php echo $id; ?>"><?php echo esc_html__($name) ?></button>
	<?php
	}
}