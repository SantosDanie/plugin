<?php 

/*
Plugin Name:  Snappy List Builder
Plugin URI:   https://developer.wordpress.org/plugins/the-basics/
Description:  This is the best plugin to sbcribe
Version:      2.0
Author:       Santos
Author URI:   https://developer.wordpress.org/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  wporg
Domain Path:  snappy-list-builder
*/

/* 1. HOOK */
/* 1.1 HINT: REGISTER SHORTCODE WITH THE INIT EVENT */
add_action('init', 'slb_register_shortcode');

/* 1.2 HINT: REGISTER CUSTOM ADMIN COLUMNS HEADERS*/
add_filter('manage_edit-slb_subscribers_columns', 'slb_subscriber_column_headers');
add_filter('manage_edit-slb_list_columns', 'slb_list_column_headers');

/* 1.3 hint: register custom admin columns data */
add_filter('manage_slb_subscribers_posts_custom_column', 'slb_subscriber_column_data', 1, 2);
add_filter('manage_slb_list_posts_custom_column', 'slb_list_column_data', 1, 2);

/* 1.4 */
add_action('wp_ajax_nopriv_slb_save_subscription', 'slb_save_subscription');
add_action('wp_ajax_slb_save_subscription', 'slb_save_subscription');
add_action('wp_ajax_slb_download_subscribers_csv', 'slb_download_subscribers_csv');

add_action('wp_ajax_nopriv_slb_unsubscribe', 'slb_unsubscribe');
add_action('wp_ajax_slb_unsubscribe', 'slb_unsubscribe');

/* 1.5 */
add_action('wp_enqueue_scripts' ,'slb_public_scripts');

// 1.6 add ACF filters
add_filter('acf/settings/path', 'slb_acf_settings_path');
add_filter('acf/settings/dir', 'slb_acf_settings_dir');
add_filter('acf/settings/show_admin', 'slb_acf_show_admin');
if ( !defined('ACF_LITE')) define('ACF_LITE', true);

// 1.7
add_action('admin_menu', 'slb_admin_menus');

// 1.10
add_action('admin_init', 'slb_register_options');

// 1.11
// trigger reward download
add_action('wp', 'slb_trigger_reward_downloand');

//1.9 
// register active/deactivate/unsistall functions
register_activation_hook( __FILE__, 'slb_activate_plugin' );

// Shortcode
// 2.1 hint : register shortcode
function slb_register_shortcode() {
	add_shortcode('slb_form', 'slb_form_shortcode');
	add_shortcode('slb_manage_subscriptions', 'slb_manage_subscriptions_shortcode');
	add_shortcode('slb_confirm_subscription', 'slb_confirm_subscription_shortcode');
	add_shortcode('slb_download_reward', 'slb_download_reward_shortcode');
}

// 2.2
function slb_form_shortcode( $args, $content="" ) {

	if ( isset($args['id']) ) $list_id = (int)$args['id'];
	
	$title = '';
	if ( isset($args['title']) ) $title = (string)$args['title']; 

	$output = '
		<form id="slb-form" class="slb-form" method="POST" action="/BootstrapToWordpress/wp-admin/admin-ajax.php?action=slb_save_subscription">
			<input type="hidden" name="slb_list" value"'. $list_id . '">';

			if (strlen($title)):
				$output .= '<h3 class="title">' . $title . '</h3>';
			endif;

		$output .= '<p class="slb-input-container">
				<label class="slb-label">Your Name</label>
				<input type="text" name="slb_fname" class="slb-input" placeholder="First Name" />
				<input type="text" name="slb_lname" class="slb-input mt-10" placeholder="Last Name" />
			</p>
			<p>
				<label class="slb-label">Your Email</label>
				<input type="email" name="slb_email" class="slb-input" placeholder="Email" />
			</p>';

	if( strlen( $content ) ) :
		$output .= '<div class="slb-form-content">' . wpautop($content) . '</div>';
	endif;

	if ( strlen( $content ) ):
		$output .= '<div class="slb_content">'. wpautop($content) .'</div>';
	endif;

	// get reward
	$reward = slb_get_list_reward( $list_id );

	// If reward exists
	if ( $reward !== false ):

		// include message about reward
		$output .= '
			<div class="slb-content slb-reward-message">
				<p>Get a FREE DOWNLOAD of <strong>'. $reward['title'] .'</strong> when you join this list!</p>
			</div>
		';

	endif;

	$output .= '
			<p class="slb-input-container text-right">
				<input type="submit" class="slb-form-submit p-10 bg-success" value="send me up!" />
			</p>

		</form>';

	return $output; 
}

// 2.3
// Hint : displays a form for managing the users list subscriptions
// example: [slb_manage_subscriptions]
function slb_manage_subscriptions_shortcode( $args, $content="" ) {

	$output = '<div class="slb slb-manage-subscriptions">';

	try {

		// get the email address from the URL
		$email = (isset($_GET['email'] ) ) ? esc_attr($_GET['email']) : '' ;

		// get the subscriber id from the email address
		$subscriber_id = slb_get_subscriber_id( $email );
		
		// get subscriber data
		$subscriber_data = slb_get_subscriber_data($subscriber_id);

		// IF subscriber exists
		if ( $subscriber_id ):
			
			// get subscription html
			$output = slb_get_manage_subscriptions_html($subscriber_id);
		
		else:

			// invalid link 
			$output .= '<p>This link is invalid.</p>';

		endif;


	} catch (Exception $e) {
		// php Error
	}

	$output .= '</div>';

	// return our html
	return $output;
}

// 2.4	
// hint: displays subscription opt-in confirmation text and link to manage sunscriptions
// example: [slb_confirm_subscription]
function slb_confirm_subscription_shortcode( $args, $content="" ) {

	// setup output variable
	$output = '<div class="slb">';

	// setup email and list_id variable and handle of they are not defined in the GET scope
	$email 		= (isset( $_GET['email'] ) ) ? esc_attr( $_GET['email'] ) : '';
	$list_id 	= ( isset( $_GET['list'] ) ) ? esc_attr( $_GET['list'] ) : 0;

	// get subscriber id from email
	$subscriber_id = slb_get_subscriber_id( $email );
	$subscriber = get_post( $subscriber_id );

	// If we found a subscriber matching that email address
	if ( $subscriber_id && slb_validate_subscriber( $subscriber ) ):

		// get list abject
		$list = get_post( $list_id );

		// If list and subscriber are valid
		if ( slb_validate_list( $list ) ):

			if ( !slb_subscriber_has_subscription( $subscriber_id, $list_id ) ):

				// Complete opt-in
				$option_complete = slb_comfirm_subscription( $subscriber_id, $list_id );

				if ( !$option_complete ):

					$output .= slb_get_message_html('Due to an unknown error, we were anable to comfirm yout subscription.', 'error');
					$output .= '</div>';

					return $output;

				endif;
			
			endif;

			// get confirmation message html and append it to output
			$output .= slb_get_message_html('Your subscripton to '. $list->post_title. ' has now been corfirmed.', 'confirmation');

			// get manage subscription link 
			$manage_subscriptions_link = slb_get_manage_subscriptions_link( $email );

			// append link output
			$output .= '<p><a href="' . $manage_subscriptions_link . '">Click here to manage your subscriptions.</a></p>';

		else:

			$output .= slb_get_message_html('This link is invalid.', 'error');

		endif;

	else:

		$output .= slb_get_message_html('This link is invalid.<br> Invalid Subscriber ' . $email . '.', 'error');

	endif;

	// close .slb div
	$output .= '</div>';

	// return output html;
	return $output;
}

// 2.5
// [slb_download_reward]
// hint: returns a message if the download link has expired or is invalid
function slb_download_reward_shortcode($args, $content="") {

	$output = '';

	$uid = ($_GET['reward']) ? (string)$_GET['reward'] : 0;

	// get reward form link uid
	$reward = slb_get_reward( $uid );

	// if reward was found
	if ( $reward !== false ):

		if ( $reward['downloads'] >= slb_get_option('slb_download_limit') ):

			$output .= slb_get_message_html('This link has reached it\'s download limit.', 'warning' );

		endif;

	else:

		$output .= slb_get_message_html('This link is invalid.', 'error');

	endif;

	return $output;
}


/* !3 FILTER */
/* 3.1 hint: */
function slb_subscriber_column_headers( $columns ) {
	// creating custom columns header data
	$columns = array(
		'cb' 		=> '<input type="checkbox" />',
		'title'		=> __('Subscriber Name'),
		'name'		=> __('Name'),
		'email'		=> __('Email Address')
	);

	// returning new columns
	return $columns;
}

// 3.2
function slb_subscriber_column_data( $columns, $post_id ) {
	// setup our output variable
	$output = '';

	switch ($columns) {
		case 'name':
			$fname = get_field( 'slb_fname', $post_id );
			$lname = get_field( 'slb_lname', $post_id );
			$output .= $fname . ' ' . $lname;
			break;
		
		case 'email':
			$email = get_field( 'slb_email', $post_id );
			$output .= $email;
			break;
	}

	// echo the output
	echo $output;
}

// 3.3
function slb_list_column_headers( $columns ) {
	// creating custom columns header data
	$columns = array(
		'cb' 		=> '<input type="checkbox" />',
		'title'		=> __('Subscriber Name'),
		'reward'	=> __('Opt-In Reward'),
		'subscribers' => __('Subscribers'),
		'shortcode'	=> __('Shortcode')
	);

	// returning new columns
	return $columns;
}

// 3.4
function slb_list_column_data( $columns, $post_id ) {
	// setup our output variable
	$output = '';

	switch ($columns) {

		case 'reward':
			$reward = slb_get_list_reward($post_id);
			if ($reward !== false) :
				$output .= '<a href="' . $reward['file']['url'] .'" download="' . $reward['title'] . '">' . $reward['title'] . '</a>';
			endif;
			break;

		case 'subscribers':
			// get the count of current subscribers
			$subscriber_count = slb_get_list_subscriber_count( $post_id );
			// get our unique export link
			$export_href = slb_get_export_link( $post_id );
			// append the subcriber count to our output
			$output .= $subscriber_count;
			// if we have more than one subscriber, add our new export link to $output
			if ( $subscriber_count ) $output .= '<a href="'. $export_href .'"></a>';
			break; // endchanges5

		case 'shortcode':
			$output .= '[slb_form id="'. $post_id . '"][/slb_form]';
			break;
	}

	// echo the output
	echo $output;
}

// 3.5
// hint registers plugins costom admiin menun
function slb_admin_menus() {

	/*admin menus*/

	$top_menu_item = 'slb_dashboard_admin_page';

	add_menu_page('','List Builder', 'manage_options', 'slb_dashboard_admin_page' , 'slb_dashboard_admin_page', 'dashicons-email-alt');

	/*subsmenu item*/

	// dashboard 
	add_submenu_page($top_menu_item, '', 'Dashboard', 'manage_options', $top_menu_item, $top_menu_item);

	// Email list
	add_submenu_page($top_menu_item, '', 'Email List', 'manage_options', 'edit.php?post_type=slb_list');

	// ssubscribers
	add_submenu_page($top_menu_item, '', 'Subscribers', 'manage_options', 'edit.php?post_type=slb_subscribers');

	// import subscriptions
	add_submenu_page($top_menu_item, '', 'Import Subscribers', 'manage_options', 'slb_import_admin_page', 'slb_import_admin_page');

	// plugins options
	add_submenu_page($top_menu_item, '' , 'Plugin Options', 'manage_options', 'slb_options_admin_page', 'slb_options_admin_page');
}

// EXTERNAL SCRIPTS
// 4.1
// include file of acf
require_once(plugin_dir_path(__FILE__) . 'libs/advanced-custom-fields/acf.php'); 

// 4.2
// hint the script of the page
function slb_public_scripts() {

	wp_register_script('snappy-list-builder-js-public', plugins_url('/js/public/snappy-list-builder.js', __FILE__, array('jquery'), '', true));
	wp_register_style('snappy-list-builder-css-public', plugins_url('/css/public/snappy-list-builder.css', __FILE__, array('jquery')));

	wp_enqueue_script('snappy-list-builder-js-public');
	wp_enqueue_style('snappy-list-builder-css-public');
} 

//  ACTIONS
//5.1 
function slb_save_subscription() {

	// set all the result data
	$result = array(
		'status' => 0,
		'message' =>  'Subscription was not save. ',
		'error' => '',
		'errors' => ''
 	);

 	try {
 		// get list id
 		$list_id = (int)$_POST['slb_list'];

 		// prepare subscriber data
 		$subscriber_data = array(
 			'fname' => esc_attr($_POST['slb_fname']),
 			'lname' => esc_attr($_POST['slb_lname']),
 			'email' => esc_attr($_POST['slb_email'])
 		);

 		$errors = array();

 		if ( !strlen($subscriber_data['fname'] ) ) $errors['fname'] = 'First name is required.';
 		if ( !strlen($subscriber_data['email'] ) ) $errors['email'] = 'Email is required.';
 		if ( strlen($subscriber_data['email'] ) && !is_email($subscriber_data['email']) ) $errors['email'] = 'First name is required.';
 		// If the are errors
 		if (count($errors)) :
 			// Append errors to result structure for later use
 			$result['error'] = "Some fields are still required";
 			$result['errors'] = $errors;
 		else:
 			// attempt to create / dave subscribe
	 		$subscriber_id = slb_save_subscriber( $subscriber_data );

			// if subscriber was save successfully $subscriber_id will be greater than 0
	 		if ( $subscriber_id ):
	 			// if subscriber already has this subscription
	 			if ( slb_subscriber_has_subscription( $subscriber_id, $list_id ) ) :
	 				// get list object
	 				$list = get_post($list_id);
	 				// return the detailerd error
	 				$result['message'] .= esc_attr($subscriber_data['email'] . 'Is alredy subscribed to' . $list->post_title . '.' );
	 			else: 
		 			// save new subscription
		 			// $subscription_save = slb_add_subscription($subscriber_id, $list_id);

		 			// if subscription was save succssefully
		 			// if ($subscription_save) :

		 				// subscripton save
		 				// $result['status'] = 1;
		 				// $result['message'] = 'Subscription save';

						// send new subscriber a confirmation email, returns true if we were successfu
						$email_sent = slb_send_subscriber_email( $subscriber_id, 'new_subscription', $list_id);
						
						// 	IF email was send
						if ( !$email_sent ):

							// email could not be send
							$result['error'] = 'Unable to send email.';

						else:

							// email send and subscription saved
							$result['status'] = 1;
							$result['message'] = 'Success! A confirmation email has been sent to ' . $subscriber_data['email'];

							unset( $result['error'] );

						endif;
		 			// else:
		 			// 	$result['error'] = 'Unable To Save Subscriptions.';
		 			// endif;

	 			endif;
	 		
	 		endif;
	 		
 		endif;
 		
 	} catch (Exception $e) {

 	}
 	
	// // return result jason
	slb_return_json($result);
}

// 5.2
function slb_save_subscriber( $subscriber_data ) {
	//  setup default subscriber id
	// 0 means that subscriber was not save
	$subscriber_id = 0;

	try {
		$subscriber_id = slb_get_subscriber_id($subscriber_data['email']);

		// if the subscriber does not already exists
		if ( !$subscriber_id ) :
			$subscriber_id = wp_insert_post(array(
				'post_type'  => 'slb_subscribers',
				'post_title'	=> $subscriber_data['fname'] . ' ' . $subscriber_data['lname'],
				'post_status'	=> 'publish'
			),
			true);
		endif;

		// add/update custom meta data
		update_field(slb_get_acf_key('slb_fname'), $subscriber_data['fname'], $subscriber_id);
		update_field(slb_get_acf_key('slb_lname'), $subscriber_data['lname'], $subscriber_id);
		update_field(slb_get_acf_key('slb_email'), $subscriber_data['email'], $subscriber_id);

	} catch (Exception $e) {
		// php Error
	}

	// return the subscriber id
	return  $subscriber_id;
}

// 5.3
function slb_add_subscription( $subscriber_id, $list_id ) {

	$subscription_saved = false;

	// if the subscriber does not  
	if ( !slb_subscriber_has_subscription($subscriber_id, $list_id) ):

		// get_subscriptions and append new $list_id
		$subscriptions = slb_get_subscriptions($subscriber_id);

		$subscriptions[] = $list_id;

		// update slb_subscriptions
		update_field(slb_get_acf_key('slb_subscription'), $subscriptions, $subscriber_id);

		// return value
		$subscription_saved = true;

	endif;

	return $subscription_saved;	
}

// 5.4
// hint: removes one or more subscriptions from a subscriber and notifies them via email
// this function is a ajax form handler...
// expect form post data: $_POST['subscriber_id'] and $_POST['list_id']
function slb_unsubscribe() {

	// setup default result data
	$result = array(
		'status' => 0,
		'message' => 'Subscriptions were NOT updated. ',
		'error' => '',
		'errors' => array(),
	);

	$subscriber_id = ( isset($_POST['subscriber_id']) ) ? (int)$_POST['subscriber_id'] : 0;
	$list_ids = ( isset($_POST['list_ids']) ) ? $_POST['list_ids'] : 0;

	try {

		// validate nounce
		if ( check_ajax_referer( 'slb-update-subscriptions_' . $subscriber_id ) ) :

			// if there are lists to remove
			if( is_array($list_ids) ):

				// loop over lists to remove
				foreach ($list_ids as $list_id ):

					// remove this subscription
					slb_remove_subscription( $subscriber_id, $list_id );

				endforeach;

			endif;

			// setup success status and message
			$result['status'] = 1;
			$result['message'] = 'Subscriptions updated. ';

			// get the updated list of subscriptions as html
			$result['html'] = slb_get_manage_subscriptions_html( $subscriber_id );

		endif;

	} catch(Exception $e) {
		// PHP Error
	}

	slb_return_json( $result );
}

// 5.5
// hint: removes a single subscription from a subscriber
function slb_remove_subscription( $subscriber_id, $list_id ) {

	// setup defalt return value
	$subscription_saved = false;

	// If the subscriber has the current list subscription
	if( slb_subscriber_has_subscription( $subscriber_id, $list_id ) ):

		// get current subscriptions
		$subscriptions = slb_get_subscriptions( $subscriber_id );

		// get the position of the $list_id to remove
		$needle = array_search( $list_id, $subscriptions );

		// remoce $list_id from $subscriptions array
		unset( $subscriptions[$needle] );

		// update slb_subscriptions
		update_field(slb_get_acf_key('slb_subscription'), $subscriptions, $subscriber_id );

		// subscriptions updated!
		$subscription_saved = true;
	endif; 

	// return result
	return $subscription_saved;
}

// 5.6
// hint: sends a unquie customized email to a subscriber
function slb_send_subscriber_email( $subscriber_id, $email_template_name, $list_id) {

	// setup return varialble
	$email_sent = false;

	// get email template data
	$email_template_object = slb_get_email_template( $subscriber_id, $email_template_name, $list_id );


	if ( !empty( $email_template_object ) ):

		// get subscriber data
		$subscriber_data = slb_get_subscriber_data( $subscriber_id );

		// set WP_mail headers
		$wp_mail_headers = array('Content-type: text/html; charset=UTF-8');

		$mail_sent = wp_mail(array($subscriber_data['email']) , $email_template_object['subject'], $email_template_object['body'], $wp_mail_headers);

	endif;

	return $semail_sent;
}

// 5.7
// hint: adds subscription to database and email subscriber confirmation email
function slb_comfirm_subscription( $subscriber_id, $list_id ) {

	// setup return variable
	$option_complete = false;

	// add new subscriptoin
	$subscription_saved = slb_add_subscription( $subscriber_id, $list_id );

	// IF subscription was saved
	if ($subscription_saved):
		// send email
		// $email_sent = slb_send_subscriber_email( $subscriber_id, 'subscription_confirmed', $list_id );

		// if email sent
		// if ($email_sent):

			// return true
			$option_complete = true;

		// endif;

	endif;

	// return result
	return $option_complete;
}

// 5.8
// hint: creates custom table for our plugin
function slb_create_pliguin_tables() {

	global $wpdb;


	// setup return value
	$return_value = false;

	try {
		
		$table_name = $wpdb->prefix . "slb_reward_links";
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (id mediumint(11) NOT NULL AUTO_INCREMENT,
		 		uid varchar(128) NOT NULL, subscriber_id mediumint(11) NOT NULL, 
		 		list_id mediumint(11) NOT NULL, attachment_id mediumint(11) NOT NULL, 
		 		downloads mediumint(11) DEFAULT 0 NOT NULL, UNIQUE KEY id (id) ) $charset_collate;";

		// make sure we include wordpress functions for dbDelta
		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

		// dbDelta will create a new table if none exists or  update an existing one
		dbDelta($sql);

		// return true
		$return_value = true;


	} catch (Exception $e) {
		// PHP error
	}

	return $return_value;
}

// 5.9
// hint: runs on plugin activation
function slb_activate_plugin() {

	//setup custom database table
	slb_create_pliguin_tables(); 
}

// 5.10
// hint: adds new reward links to the database
function slb_add_reward_link( $uid, $subscriber_id, $list_id, $attachment_id ) {

	global $wpdb;

	//set our return value
	$return_value = false;

	try {
	  	
	  	$table_name = $wpdb->prefix . "slb_reward_links";

	  	$wpdb->insert(
	  		$table_name, array(
	  			'uid' => $uid,
	  			'subscriber_id' => $subscriber_id,
	  			'list_id' => $list_id,
	  			'attachment_id' => $attachment_id
	  		),
	  		array(
	  			'%s',
	  			'$d',
	  			'$d',
	  			'%d'

	  		)
	  	);

	  	// return value
  		$return_value = true;

	} catch (Exception $e) {
	  	
	}

	// return result 
	return $return_value;
}

// 5.11
// hint: trigger a download of the reward file
function slb_trigger_reward_downloand() {

	global $post;

	if ( $post->ID == slb_get_option('slb_reward_page_id') && isset($_GET['reward']) ):

		$uid = ($_GET['reward']) ? (string)$_GET['reward'] : 0;

		// get reward form link uid
		$reward = slb_get_reward( $uid );

		// if reward was found
		if ( $reward !== false && $reward['download'] < slb_get_option( 'slb_download_limit' ) ):

			slb_update_reward_link_download( $uid );

			// get the reward mimetype
			$mimetype = $reward['file']['mine_type'];

			// extract tge filetype from the mimetype
			$mimetype_array = explode('/', $mimetype);
			$filetype = $mimetype_array[1];

			// setup file headers
			header("Content-type:".$mimetype, true,200);
			header("Content-Dispoisition: attachment; filename=". $reward['title'] . '.' . $filetype);
			header("Progma: no-cache");
			header("Expires: 0");
			readfile($reward['file']['url']);
			exit();

		endif;
	
	endif;
}

// 5.12
// hint: incrases reward link  download count by one
function slb_update_reward_link_downloads() {

	global $wpdb;

	// setup our return value
	$return_value = false;

	try {
		
		$table_name = $wpdb->prefix . "slb_reward_links";

		// get current donwload count
		$current_count = $wpdb->get_var( $wpdb->prepare("SELECT downloads FROM $table_name WHERE uid = %s", $uid) );

		// set new count
		$new_count = (int)$current_count+1;

		// update downloads for this reward link entry
		$wpdb->query( $wpdb->prepare("UPDATE $table_name SET downloads = $new_count WHERE uid = %s", $uid) );

		$return_value = true;
	} catch (Exception $e) {
		// PHP Error 
	}

	return $return_value;
}

// 6 HELPER
// 6.1
function slb_subscriber_has_subscription($subscriber_id, $list_id) {

	// setup default return value
	$has_subscription = false;

	// Get subscriber
	$subscriber  = get_post($subscriber_id);

	// get subscription
	$subscription = slb_get_subscriptions($subscriber_id);

	if (in_array($list_id, $subscription)):
	// find the $list_id in $subscription
	// The subscriber is already subscriber to this list

		$has_subscription = true;

	else:
		// nothing
	endif;

	return $has_subscription;
}

// 6.2
function slb_get_subscriber_id( $email ) {
	
	// default value
	$subscriber_id = 0;

	try {
		// check if subscriber alreay exists
		$subscriber_query = new WP_Query(array(
			'post_type' => 'slb_subscribers',
			'posts_per_page' => 1,
			'meta_key' => 'slb_email',
			'meta_query'	=> array(
				array(
					'key' => 'slb_email',
					'value' => $email,
					'compare' => '='
					),
				),
			));
		// if the subscriber exists...
		if ($subscriber_query->have_posts() ):
			// get subscriber_id
			$subscriber_query->the_post();
			$subscriber_id = get_the_ID();
		endif;

	} catch (Exception $e) {
		// PHP Error
	}

	// reset the query
	wp_reset_query();

	// return the value of subscrver_idas an integer
	return (int)$subscriber_id;
}

// 6.3
// hint: return an array of lists objects)
function slb_get_subscriptions($subscriber_id) {
	// default $subscription value
	$subscription = array();

	// get subscription (returns an arrya of list object)
	$lists = get_field(slb_get_acf_key('slb_subscription'), $subscriber_id );

	// if $list returnsomethin
	if ($lists):

		// if $lists is an array and there is no one or more items
		if (is_array($lists) && count($lists) ):
			// build subscription: array of lists id's
			foreach ($lists as $list):
				$subscription[] = (int)$list->ID;
			endforeach;
		elseif(is_numeric($lists) ):
			// sisngle result returned
			$subscription = $lists;
		endif;

	endif;

	return (array)$subscription;
}

// 6.4
function slb_return_json( $php_array ) {

	$slb_return = json_encode( $php_array );

	die($slb_return);

	exit;
}

// 6.5
function slb_get_acf_key( $field_name ) {

	$field_key = $field_name;

		switch ($field_name) {
			case 'slb_fname':
				$field_key = 'field_5b5b3a0dd11e9';
				break;
			
			case 'slb_lname':
				$field_key = 'field_5b5b3af0d11eb';
				break;

			case 'slb_email':
				$field_key = 'field_5b5b3b23d11ec';
				break;

			case 'slb_subscription':
				$field_key = 'field_5b5b3a91d11ea';
				break;

			case 'slb_enable_reward':
				$field_key = 'field_5b86baf8f4007';
				break;

			case 'slb_reward_title':
				$field_key = 'field_5b86bba6f4008';
				break;

			case 'slb_reward_file':
				$field_key = 'field_5b86bc22f4009';
				break;
		}

	return $field_key;
}

// 6.6
// return an array of subscriber data include subscriptions
function slb_get_subscriber_data( $subscriber_id ) {

	$subscriber_data = array();
	$subscriber = get_post($subscriber_id);

	// le falta la ese por si me vieve a dar error en slb_subcriber
	if ( isset($subscriber->post_type) && $subscriber->post_type == 'slb_subscribers' ):
		
		$fname = get_field(slb_get_acf_key('slb_fname'), $subscriber_id);
		$lname = get_field(slb_get_acf_key('slb_lname'), $subscriber_id);
		$email = get_field(slb_get_acf_key('slb_email'), $subscriber_id);

		$subscriber_data = array(
			'name' 	=> $fname . ' ' . $lname,
			'fname' => $fname,
			'lname' => $lname,
			'email' => $email,
			'subscriptions' => slb_get_subscriptions( $subscriber_id )
		);
	endif;

	// RETURN SUBSCRIBER_DATA
	return $subscriber_data;
}

// 6.7
function slb_get_page_select( $input_name="slb_page", $input_id="", $parent=-1, $value_field="id", $selected_value="" ) {

	// get WP page
	$pages = get_pages(
		array(
			'sort_order'  	=> 'asc',
			'sort_column' 	=> 'post_title',
			'post_type' 	=> 'page',
			'parent' 		=> $parent,
			'status' 		=> array('draft', 'publish' ),
		)
	);

	// seup our select html
	$select = '<select name="' . $input_name . '" ';

	// Id $input_id was passed in
	if ( strlen($input_id) ):

		// add an input id to our select html
		$select .= 'id="' . $input_id . '" ';

	endif;

	// select our first select option
	$select .= '><option value="">-Select One-</option>';

	// loop over all the pages
	foreach ($pages as $page ) :

		// get the page id as our default option value
		$value = $page->ID; 

		// determine which page attribute is the desired value field
		switch ($value_field) {
			case 'slug':
					$value = $page->post_name;
				break;
			
			case 'url':
					$value = get_page_link( $page->ID );
				break;
			default:
					$value = $page->ID;
		}

		// check if this option is the currently selected option
		$selected = '';
		if ($selected_value == $value):
			$selected = ' selected="selected" ';
		endif;

		// build our option to the select html
		$option = '<option value="' . $value . '" '. $selected . '>';
		$option .= $page->post_title;
		$option .= '</option>';

		// append our option to the select html
		$select .= $option;

	endforeach;

	// close our select html tag 
	$select .= '</select>';

	// return our new select
	return $select;
}

// 6.8
// hint: returns default option value as an associative array
function slb_get_default_options() {

	$defaults = array();

	try {

		// get front page id
		$front_page_id = get_option('page_on_front');

		// setup default  email footer
		$default_email_footer = '
			<p>
				Sincerely, <br/><br/>
				The ' . get_bloginfo('name') . ' Team<br/>
				<a href="' . get_bloginfo('url') . '">' . get_bloginfo('url') . '<a/>
			</p>
		';

		// setup default array
		$defaults = array(
			'slb_manage_subscription_page_id'	=> $front_page_id,
			'slb_confirmation_page_id'			=> $front_page_id,
			'slb_reward_page_id'				=> $front_page_id,
			'slb_default_email_footer'			=> $default_email_footer,
			'slb_download_limit'				=> 3,
		);

	} catch (Exception $e) {
		// php Error
	}

	// return defaults
	return $defaults;
}

// 6.9
// hint: returns the requested page option value or it's default
function slb_get_option( $option_name ) {

	// setup return variable
	$option_value = '';

	try {

		// get default option values
		$defaults = slb_get_default_options();

		switch ($option_name) {
			case 'slb_manage_subscription_page_id':

				// subscription page id
				$option_value = (get_option('slb_manage_subscription_page_id')) ? get_option('slb_manage_subscription_page_id') : $defaults['slb_manage_subscription_page_id'];
				break;
			case 'slb_confirmation_page_id':

				// comfirmation page id
				$option_value = (get_option('slb_confirmation_page_id')) ? get_option('slb_confirmation_page_id') : $defaults['slb_confirmation_page_id'];
				break;

			case 'slb_reward_page_id':
				
				// reward page id
				$option_value = (get_option('slb_reward_page_id')) ? get_option('slb_reward_page_id') : $defaults['slb_reward_page_id'];
				break;

			case 'slb_default_email_footer':
				
				// email footer
				$option_value = (get_option('slb_default_email_footer')) ? wpautop(get_option('slb_default_email_footer')) : $defaults['slb_default_email_footer']; 
				break;

			case 'slb_download_limit':
				
				// reward download limit
				$option_value = (get_option('slb_download_limit')) ? (int)get_option('slb_download_limit') : $defaults['slb_download_limit'];
				break;
		}

	} catch (Exception $e) {
		// PHP Error
	}

	return $option_value;
}

// 6.10
// hint: get's the current options and returns value in ssociative array
function slb_get_current_options() {

	// setup our return variable
	$current_options = array();

	try {

		// build our currnet option associative array
		$current_options = array(
			'slb_manage_subscription_page_id' 	=> slb_get_option('slb_manage_subscription_page_id'),
			'slb_confirmation_page_id' 			=> slb_get_option('slb_confirmation_page_id'),
			'slb_reward_page_id' 				=> slb_get_option('slb_reward_page_id'),
			'slb_default_email_footer' 			=> slb_get_option('slb_default_email_footer'),
			'slb_download_limit' 				=> slb_get_option('slb_download_limit'), 
		);

	} catch (Exception $e) {
		// php Error
	}

	// retunr current options
	return $current_options;
}

// 6.11
// hint: genetates a html form for managing subscriptions
function slb_get_manage_subscriptions_html( $subscriber_id ) {

	$output = '';

	try {
		// get array of list_ids for  this subscripber	
		$lists = slb_get_subscriptions( $subscriber_id );

		// get the subscriber data
		$subscriber_data = slb_get_subscriber_data( $subscriber_id );

		// set the title
		$title = $subscriber_data['fname'] . '\'s Subscriptions';

		$nounce = wp_nonce_field( 'slb-update-subscriptions_' . $subscriber_id, '_wpnonce', true, false );

		// build out output html
		$output = '
			<form id="slb_manage_subscriptions_form" class="slb-form" method="POST" action="/BootstrapToWordpress/wp-admin/admin-ajax.php?action=slb_unsubscribe">
				' . $nounce . '
				<input type="hidden" name="subscriber_id" value="' . $subscriber_id . '">
				<h3 class="slb-title">' . $title . '</h3>';
				if (!count($lists)):
					
					$output .= '<p>There are no active subscriptions.<p>';

				else:

					$output .= '<table>
						</tbody>';

						// loop over lists
						foreach ($lists as $list_id):
							$list_object = get_post($list_id);

							$output .= '<tr>
									<td>'
										. $list_object->post_title .
									'</td>
									<td>
										<label>
											<input type="checkbox" name="list_ids[]" value="' . $list_object->ID .'"/> UNSUBSCRIBER
										</label>
									</td>
								</tr>';
							endforeach;

							// close op our output html
							$output .= '</tbody>
							</table>

							<p><input type="submit" value="Save changes" class="p-10"></p>';
				endif;
		$output .= '</form>';

	} catch(Exception $e) {
		// PHP Error
	}

	// return output
	return $output;
}

// 6.12
// hint: retunrs an array of email template data If the template exists
function slb_get_email_template( $subscriber_id, $email_template_name, $list_id ) {

	// setup return variable
	$template_data = array();

	// create new array to store email templates
	$email_templates = array();

	// get lists objet
	$list = get_post( $list_id );

	// get subscriber objet
	$subscriber = get_post( $subscriber_id );

	if ( !slb_validate_list( $list ) || !slb_validate_subscriber( $subscriber ) ):
		// The list of the subscriber is not valid
	else:

		// get subscriber data
		$subscriber_data = slb_get_subscriber_data( $subscriber_id );

		// get unique manage subscription link
		$manage_subscriber_link = slb_get_manage_subscriptions_link( $subscriber_data['email'], $list_id );

		// get default email header
		$default_email_header = '
			<p>
				Hello ' . $subscriber_data['fname'] . ',
			</p>
		';

		// get default email footer
		$default_email_footer = slb_get_option('slb_default_email_footer');

		// setup unsubscribe text
		$unsubscribe_text = '
			<br/><br/>
			<hr/>
			<p><a href="' . $manage_subscriber_link . '">Click here to unsubscribe</a> from this or any other email list.</p>
		';

		// get reward
		$reward = slb_get_list_reward( $list_id );

		// setup reward text
		$reward_text = '';

		// If reward exists
		if ($reward  !== false):
			// setup the appropiate reward text
			switch ( $email_template_name ) {
				case 'new_subscription':
						// setup reward text
						$reward_text = '<p>After comfirming your subscription, we will send you link for a FREE DOWNLOAD of ' . $reward['title'] . '</p>';
					break;
				
				case 'subscription_confirmed':
					// get Downloadn limit
						$download_limit = slb_get_option('slb_download_limit');
						// generate new downloand link
						$download_link = slb_get_option($subscriber_id, $list_id);
						// set reward text
						$reward_text = '<p>Here is your <a href="' . $downloand_link . '">UNIQUE DOWNLOAD LIMIT</a> for ' . $reward['title'] . '. This link will expire after ' . $download_limit . ' downloands</p>';
					break;
			}

		endif;

		// setup email templates
			// get unique opt-in link
			$option_link = slb_get_option_link( $subscriber_data['eamil'], $list_id );

		// template: new_subscription
		$email_templates['new_subscription'] = array(
			'subject' 	=> 'Thank you for subscribing	to' . $list->post_title . '! Please confirm your subscription',
			'body' 		=> '' . $default_email_header . '
							<p>Thank you for subscribing to ' . $list->post_title . '!</p>
							<p>Please <a href="' . $option_link . '">Click to comfirm your subscription.</a></p>' . $reward_text . $default_email_footer . $unsubscribe_text,
		);

		// template: subscription_comfirmed
		$email_template['subscription_comfirmed'] = array(
			'subject' 	=> 'Your ara now subscribed to ' . $list->post_title . '!',
			'body'		=> '' . $default_email_header . '
							<p>Thank you for comfirming your subscription. You are now subscribed to ' . $list->post_title . '!	</p>
							' . $reward_text . $default_email_footer . $unsubscribe_text,
		);


	endif;

	// If the request email template exists
	if ( isset($email_templates[ $email_template_name ]) ):

		// add template data  to return variable
		$template_data = $email_templates[$email_template_name];

	endif;

	// return template data
	return $template_data;
}

// 5.13
// hint: generates a.csv file of subscribers data
// expects $_GET['list_id'] to be set in the URL
function slb_download_subscribers_csv() {
	
	// Get the list id from the URL scope
	$list_id = (isset($_GET['list_id'])) ? (int)$_GET['list_id'] : 0;

	// Setup our return data
	$csv = '';

	// Get the list object
	$list = get_post($list_id);

	// Get the list's subscribers or get all subscribers if no list id is given
	$subscribers = slb_get_list_subscribers($list_id);

	// If we have confirmed subscribers
	if ($subscribers !== false):
		
		// Get the current date
		$now = new DateTime();

		// Setup a unique filename for the generated export file
		$fn1 = 'snappy-list-builder-export-list_id-'. $list_id .'-date-'. $now->format('Ymd'). '.csv';
		$fn2 = plugin_dir_path(__FILE__) .'exports/'.$fn1;

		// Open new file in write mode
		$fp = fopen($fn2, 'w');

		// Get the firs subscriber's data
		$subscriber_data = slb_get_subscriber_data($subscribers[0]);

		// remove the subscrioptions and name column from the data
		unset($subscriber_data['subscriptions']);
		unset($subscriber_data['name']);

		// Build our csv headers array from $subscriber_data;s data key
		$csv_headers = array();
		foreach ($subscriber_data as $key => $value) :
			array_push($csv_headers, $key);
		endforeach;

		// Append $csv_headers to our csv file
		fputcsv($fp, $csv_headers);

		// Loop over all our subscribers
		foreach ($subscribers as &$subscriber_id):

			// GEt the subscriber data of the current subscriber
			$subscriber_data = slb_get_subscriber_data($subscriber_id);

			// Remove the subscriptions and name columns from the data
			unset($subscriber_data['subscriptions']);
			unset($subscriber_data['name']);

			// Append this subscriber's data to our csv file
			fputcsv($fp, $subscriber_data);

		endforeach;

		// read open our new file is read mode
		$fp = fopen($fn2, 'r');

		// Read our new csv file and store it's content in $fc
		$fc = fread($fp, filesize($fn2));

		// Close our open file pointer
		fclose($fp);

		// Setup file headers
		header("Content-type: application/csv");
		header("Content-Disposition: attachment; filename=".$fn1);

		// Echo the contents of our file and return it to the browser
		echo ($fc);

		// Exit php processes
		exit;

	endif;

	// Return false if we were unable to download our csv
	return false;
}

// 6.13
// hint: validates wheter the post object exitsts and that it's a validate post_type
function slb_validate_list( $list_object ) {
	$list_valid = false;

	if ( isset($list_object->post_type) && $list_object->post_type == 'slb_list' ):

		$list_valid = true;

	endif;

	return $list_valid;
}

// 6.14
// hint: validate whether the post object exists and that it's a validate post_type
function slb_validate_subscriber( $subscriber_object ) {

	$subscriber_valid = false;

	if ( isset($subscriber_object->post_type) && $subscriber_object->post_type == 'slb_subscribers' ):

		$subscriber_valid = true;

	endif;

	return $subscriber_valid;
}

// 6.15
// hint: returns a unique link for managing a particular users subscriptions
function slb_get_manage_subscriptions_link($email, $list_id=0) {

	$link_href = '';

	try {

		$page = get_post( slb_get_option('slb_manage_subscription_page_id') );
		$slug = $page->post_name;

		$permalink = get_permalink($page);

		// get character to start querystring
		$starquery = slb_get_querystring_start($permalink);

		$link_href = $permalink . $starquery . 'email=' . urldecode($email) . '&list=' . $list_id;

	} catch (Exception $e) {
		// PHP Error $link_href = $e->getMessge();
	}

	return esc_url($link_href);
}

// 6.16
// hint: returns the appropriate character for the begining of a querystring
function slb_get_querystring_start($permalink) {

	// setup our found in the permalink
	$querystring_start = '&';

	// If ? is not found in the permalink
	if (strpos($permalink, '?') == false ):
		$querystring_start = '?';
	endif;

	return $querystring_start;
}

// 6.17
// hint: return a unique link for option an email list
function slb_get_option_link( $email, $list_id=0 ) {
	$link_href = '';

	try {

		$page = get_post( slb_get_option('slb_confirmation_page_id') );
		$slug = $page->post_name;
		$permalink = get_permalink($page);

		// get character to start querystring
		$starquery = slb_get_querystring_start($permalink);

		$link_href = $permalink . $starquery . 'email=' . urlencode($mail) . '&list=' . $list_id;
		 
	} catch (Exception $e) {
		// $link_href = $e->getMessge();
	}

	return esc_url($link_href);
}

// 6.18
// hint: return html for messages
function slb_get_message_html( $message, $message_type ) {

	$output = '';

	try {

		$message_class = 'confirmation';

		switch ($message_type) {
			case 'warning':
				$message_class = 'slb-warning';
				break;

			case 'error':
				$message_class = 'slb-error';
				break;
			
			default:
				$message_class = 'slb-confirmation';
				break;
		}

		$output .= '
			<div class="slb-message-container">
				<div class="slb-message ' . $message_class . '">
					<p>' . $message . '</p>
				</div>
			</div>
		';

	} catch (Exception $e) {
		// PHP Error
	}

	return $output;
}

// 6.10
// hint: get's an array of plugin option data (group and settings) so as to save it all in one place
function slb_get_options_settings() {

	// setup our return data
	$settings = array(
		'group'		=> 'slb_plugin_options',
		'settings'	=> array(
			'slb_manage_subscription_page_id',
			'slb_confirmation_page_id',
			'slb_reward_page_id',
			'slb_default_email_footer',
			'slb_download_limit'
		),
	);

	// return option data
	return $settings;
}

// 6.19
// hint: returns false if list has no reward or returns the object containing file and title if it does
function slb_get_list_reward( $list_id ) {

	// setup return data
	$reward_data = false;

	// get enable reward value
	$enable_reward = (get_field(slb_get_acf_key('slb_enable_reward'), $list_id) ) ? true : false;

	// If reward file
	if ( $enable_reward ):

		// get reward file
		$reward_file = (get_field(slb_get_acf_key('slb_reward_file'), $list_id) ) ? get_field(slb_get_acf_key('slb_reward_file'), $list_id) :false;

		// get reward title
		$reward_title = (get_field(slb_get_acf_key('slb_reward_title'), $list_id) ) ? get_field(slb_get_acf_key('slb_reward_title'), $list_id) : 'Reward';
		// If reward_file is a valid array
		if (is_array($reward_file) ):

			// setup return data
			$reward_data = array(
				'file' => $reward_file,
				'title' => $reward_title,
			);
		endif;
	endif;

	// return $reward_data
	return $reward_data;
}

// 6.20
// Hint: return a unique link for downloanding a reward file
function slb_get_reward_link( $subscriber_id, $list_id ) {

	$link_href = "";

	try {
		
		$page = get_post(slb_get_option('slb_reward_page_id') );
		$slug = $page->post_name;
		$permalink = get_permalink($page);

		// generate an unique uid for reward link
		$uid = slb_generate_reward_uid( $subscriber_id, $list_id );

		// get list  reward
		$reward = slb_get_list_reward( $list_id );

		if ( $uid && $reward !== false ):

			// add reward link to database
			$link_added = slb_add_reward_link($uid, $subscriber_id, $list_id, $reward['file']['id'] );

			// If link was added succesfully
			if ($link_added === true ):
				
				// get character to start querystring
				$starquery = slb_get_querystring_start($permalink);

				// build  reward link
				$link_href = $permalink . $starquery . 'Reward=' . urlencode($uid);

			endif;

		endif;

	} catch (Exception $e) {
		// PHP Error
	}

	// return reward link
	return esc_url($link_href);
}

// 6.21
// hint: generates a unique
function slb_generate_reward_uid($subscriber_id, $list_id) {

	// setup our return variable
	$uid = '';

	// get subscriber post object
	$subscriber = get_post($subscriber_id);

	// get list post object
	$list = get_post($list_id);

	// if subscriber and list are valid
	if ( slb_validate_subscriber($subscriber) && slb_validate_list($list) ):

		// get list reward
		$reward = slb_get_list_reward($list_id);

		// if Reward is not equal to false
		if ( $reward !== false ):

			// generate a unique id
			$uid = uniqid('slb', true );

		endif;

	endif;

	return $uid;
}

// 6.22
// hint: returns false if list has no reward or return the object cotaining file ant title if id does
function slb_get_reward( $uid ) {

	global $wpdb;

	// setup return data
	$reward_data = false;

	// reward links download table name
	$table_name = $wpdb->prefix . 'slb_reward_links';

	// get list id from reward link
	$list_id = $wpdb->get_var( $wpdb->prepare("SELECT list_id FROM $table_name WHERE uid = %s", $uid) );

	// get downloads from reward link
	$downloads = $wpdb->get_var( $wpdb->prepare("SELECT downloads FROM $table_name WHERE uid = $s", $uid) );

	// get reward data
	$reward = slb_get_list_reward( $list_id );

	if ( $reward !== false ):

		// set reward data
		$reward_data  = $reward;

		// add downloads to reward data
		$reward_data['dowloads']= $downloads;

	endif;

	// return $reward_data
	return $reward_data;
}

// 6.23
// hint: returns an array of subscriber_id's
function slb_get_list_subscribers( $list_id=0 ) {

	// setup return variable
	$subscribers = false;

	// get list object
	$list = get_post( $list_id );

	if ( slb_validate_list($list) ):

		// query all subscribers from post this list only
		$subscribers_query =  new WP_Query(
			array(
				'post_type' => 'slb_subscribers',
				'pliblished' => true,
				'posts_per_page' => -1,
				'orderby' => 'post_date',
				'meta_query' => array(
					array(
						'key'=> 'slb_subscription',
						'value' => ':"'. $list->ID .'"',
						'compare' => 'LIKE'
					)
				)
			)
		);

	elseif( $list_id === 0 ):

		// query all subscriber form all lists
		$subscribers_query =  new WP_Query(
			array(
				'post_type' => 'slb_subscribers',
				'pliblished' => true,
				'posts_per_page' => -1,
				'orderby' => 'post_date',
				'order' => 'DESC',
			)
		);

	endif;

	// IF $subscribers_query isset and query returns results
	if ( isset( $subscribers_query ) && $subscribers_query->have_posts() ):	

		// set subscribers array
		$subscribers = array();

		// loop over results
		while ( $subscribers_query->have_posts() ):

			// get the post object
			$subscribers_query->the_post();

			$post_id = get_the_ID();

			// append result to subscribers array
			array_push($subscribers, $post_id);

		endwhile;

	endif;

	// reset wp query/postdata
	wp_reset_query();
	wp_reset_postdata();

	// return result
	return $subscribers;
}

// 6.24
// hint: returs the amount of subscribers in this list
function slb_get_list_subscriber_count( $list_id=0 ) {

	// setup return cariable
	$count = 0;

	// get array of subscribers ids
	$subscribers = slb_get_list_subscribers( $list_id );

	// If array was returned
	if ( $subscribers !== false ):

		// update count
		$count = count($subscribers); 

	endif;

	// return result
	return $count;
}

// 6.25
// hint: returns a unique link for downloading a subscribers csv
function slb_get_export_link( $list_id=0 ) {

	$link_href = 'admin-ajax.php?action=slb_download_subscribers_csv&list_id='. $list_id;

	return esc_url($link_href);
}

/* 7 .  CUSTOM POST TYPES */
// 7.1 ADD CTP slb_subscribers
require_once(plugin_dir_path(__FILE__) . 'cpt/slb_subscribers.php');

// 7.2 Add CTP slb_lists
require_once(plugin_dir_path(__FILE__) . 'cpt/slb_list.php');

// 8. Admin pages
// 8.1 dashboard admin poge
function slb_dashboard_admin_page() {

	// get our export link
	$export_href = slb_get_export_link();

	$output = '
		<div class="wrap">
			<h2>Snappy List Builder</h2>
			<p>The ultimate email list building for Wordpress. Capture new subscriber. Reward subscribers with a custom dowload up opt-in. Build unlimited lists. Import and export	subscribers easily with .csv </p>

			<p><a href="'. $export_href .'" class="button button-primary">Export All Subscribers Data</a></p>
		</div>
	';

	echo $output;
}

// 8.2
function slb_import_admin_page() {

	// enque especial scriptsrequired for our file import field
	wp_enqueue_media();

	echo('	
		<div class="wrap" id="import_subscribers">
			<h2>Import Subscribers</h2>
			<form id="import_form_1">
				<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="slb_import_file">Import CSV</label></th>
							<td>
								<div class="wp-uploader">
									<input type="text" name="slb_import_file_url" class="file-url regular-text" accept="csv">
									<input type="hidden" name="slb_import_file_id" class="file-id" value="0">
									<input type="button" name="upload-btn" class="upload-btn button-secondary" value="Upload">
								</div>
								<p class="description" id="slb_import_file-description">Expects CSV file containing a "Name" (First, Last or Full ) And "Email Address".</p>
							</td>
						</tr>
					</tbody>
				</table>
			</form>
			<form id="import_form_2" method="POST" action="/wp-admin/admin-ajax.php?action=slb_import_subscribers"> 
				<table class="form-table">
					<tbody class="slb-dynamnic-content">
						
					</tbody>
					<tbody class="form-table show-only-on-valid" style="display: none;">
						<tr>
							<th scope="row"><label>Import To List</label></th>
							<td>
								<select name="slb_import_list_id">');
									
									//get all our email lists
									$lists = get_posts(
										array(
											'post_type'		=> 'slb_list',
											'status'		=> 'publish',
											'posts_per_page'=> -1,
											'orderby'		=> 'post_title',
											'order'			=> 'ASC',
										)
									);

									// loop over eacch email list
									foreach($lists as $list):
										// create the select option for the list
										$option = '
											<option value="'. $list->ID .'">
												'. $list->post_title .'
											</option>';

										echo $option;
									endforeach;

							echo('</select>
								<p class="description"></p>
							</td>
						</tr>
					</tbody>
				</table>
				<p class="submit show-only-on-valid" style="display: none;"><input type="submit" name="submit" id="submit" class="button button-primary" value="Import"></p>
			</form>
		</div>
	');	
}

// 8.3
function slb_options_admin_page() {

	// get the default valuesfor ouroptions
	$options = slb_get_current_options();
	
	echo ('
		<div class="wrap">
			<h2>Snappy List builder Options</h2>
			<form action="options.php" method="POST">');

				// outputs a unique nouce for our plugin options
				settings_fields('slb_plugin_options');

				// generates a unique hidden fiel with our form handling url
				// @do_settings_fields('slb_plugin_options');

				echo('<table class="form-table">
					<tbody>
						<tr>
							<th scope="row"><label for="slb_manage_subscription_page_id">Manage Subscriptions Page</label></th>
							<td>
								'. slb_get_page_select( 'slb_manage_subscription_page_id', 'slb_manage_subscription_page_id', 0, 'id', $options['slb_manage_subscription_page_id'] ) . '
								<p class="description" id="slb_manage_subscription_page_id-description">This is the page where Snappy List Builder will send subscribers to manage their subscription. <br> IMPORTANT: In order to work, the page you select must contain the shortcode: <strong>[slb_mange_subscription]</strong>.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="slb_confirmation_page_id">Opt-In Page</label></th>
							<td>
								'. slb_get_page_select( 'slb_confirmation_page_id', 'slb_confirmation_page_id', 0, 'id', $options['slb_confirmation_page_id'] ) . '
								<p class="description" id="lb_confirmation_page_id-description">This is the page where Snappy List Builder will send subscribers to confirm their subscriptions. <br> IMPORTANT: In order to work, the page you select must contain the shorcode: <strong>[slb_comfirm_subscription]</strong>.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="slb_reward_page_id">Download Reward Page</label></th>
							<td>
								'. slb_get_page_select( 'slb_reward_page_id', 'slb_reward_page_id', 0, 'id', $options['slb_reward_page_id'] ) . '
								<p class="description" id="slb_reward_page_id-description">This is the page where Snappy List builder will send subscription to retrieve their reward downloads. <br>IMPORTANT: In order to work, the page you select must contain the shortcode: <strong>[slb_download_reward]</strong>.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="slb_default_email_footer">Email Footer</label></th>
							<td>');
								
								//wp_editor will act funny if it's stored in a string  so we run it liken this...
								wp_editor( $options['slb_default_email_footer'], 'slb_default_email_footer', array('textarea_rows'=>8) );

								echo('<p class="description" id=" slb_default_email_footer-description">The default text that appers at the end of emails generated by this plugin.</p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="slb_download_limit">Reward Download Limit</label></th>
							<td>
								<input type="number" name="slb_download_limit" value="' . $options['slb_download_limit'] . '" class="" />
								<p class="description" id="slb_download_limit-description">The amount of downloads a reward link will allow before expiring.</p>
							</td>					
						</tr>
					</tbody>
				</table>');

				// outputs the WP submit button html
				@submit_button();

		echo ('</form>
		</div>');
}

// 9. settings
// 9.1 Hint: This function can register tha changes
function slb_register_options() {

	// Get pluging options settings
	$options =  slb_get_options_settings();

	// loop over setting
	foreach ($options['settings'] as $settings) : 

		// register this settings
		register_setting($options['group'], $settings);

	endforeach;
}

?>