<?php 

function cptui_register_my_cpts_slb_subscribers() {

	/**
	 * Post Type: subscriptions.
	 */

	$labels = array(
		"name" => __( "subscriptions", "" ),
		"singular_name" => __( "subscription", "" ),
	);

	$args = array(
		"label" => __( "subscriptions", "" ),
		"labels" => $labels,
		"description" => "post subscription",
		"public" => false,
		"publicly_queryable" => true,
		"show_ui" => true,
		"show_in_rest" => true,
		"rest_base" => "",
		"has_archive" => false,
		"show_in_menu" => false,
		"show_in_nav_menus" => true,
		"exclude_from_search" => true,
		"capability_type" => "post",
		"map_meta_cap" => true,
		"hierarchical" => false,
		"rewrite" => array( "slug" => "slb_subscribers", "with_front" => true ),
		"query_var" => true,
		"menu_icon" => "http://localhost/BootstrapToWordpress/wp-content/uploads/2018/07/icon-resources.png",
		"supports" => array( "title" ),
	);

	register_post_type( "slb_subscribers", $args );
}

add_action( 'init', 'cptui_register_my_cpts_slb_subscribers' );


if(function_exists("register_field_group")) {
	register_field_group(array (
		'id' => 'acf_slb-subscription',
		'title' => 'SLB Subscription',
		'fields' => array (
			array (
				'key' => 'field_5b5b3a0dd11e9',
				'label' => 'First name',
				'name' => 'slb_fname',
				'type' => 'text',
				'required' => 1,
				'default_value' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
				'formatting' => 'html',
				'maxlength' => '',
			),
			array (
				'key' => 'field_5b5b3af0d11eb',
				'label' => 'Last Name',
				'name' => 'slb_lname',
				'type' => 'text',
				'default_value' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
				'formatting' => 'html',
				'maxlength' => '',
			),
			array (
				'key' => 'field_5b5b3b23d11ec',
				'label' => 'Email',
				'name' => 'slb_email',
				'type' => 'text',
				'required' => 1,
				'default_value' => '',
				'placeholder' => '',
				'prepend' => '',
				'append' => '',
				'formatting' => 'html',
				'maxlength' => '',
			),
			array (
				'key' => 'field_5b5b3a91d11ea',
				'label' => 'Subscriptions',
				'name' => 'slb_subscription',
				'type' => 'post_object',
				'required' => 1,
				'post_type' => array (0 => 'slb_list',
				),
				'taxonomy' => array (0 => 'all',
				),
				'allow_null' => 1,
				'multiple' => 1,
			),
		),
		'location' => array (
			array (
				array (
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'slb_subscribers',
					'order_no' => 0,
					'group_no' => 0,
				),
			),
		),
		'options' => array (
			'position' => 'normal',
			'layout' => 'default',
			'hide_on_screen' => array (
			),
		),
		'menu_order' => 0,
	));
}

?>