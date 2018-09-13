<?php 

function cptui_register_my_cpts_slb_list() {

	/**
	 * Post Type: Lists.
	 */

	$labels = array(
		"name" => __( "Lists", "" ),
		"singular_name" => __( "List", "" ),
	);

	$args = array(
		"label" => __( "Lists", "" ),
		"labels" => $labels,
		"description" => "List of the subcribe",
		"public" => false,
		"publicly_queryable" => true,
		"show_ui" => true,
		"show_in_rest" => false,
		"rest_base" => "",
		"has_archive" => false,
		"show_in_menu" => false,
		"show_in_nav_menus" => true,
		"exclude_from_search" => true,
		"capability_type" => "post",
		"map_meta_cap" => true,
		"hierarchical" => false,
		"rewrite" => array( "slug" => "slb_list", "with_front" => true ),
		"query_var" => true,
		"menu_icon" => "http://localhost/BootstrapToWordpress/wp-content/uploads/2018/07/icon-resources.png",
		"supports" => array( "title" ),
	);

	register_post_type( "slb_list", $args );
}

add_action( 'init', 'cptui_register_my_cpts_slb_list' );

if(function_exists("register_field_group"))
{
	register_field_group(array (
		'id' => 'acf_list-settings',
		'title' => 'List Settings',
		'fields' => array (
			array (
				'key' => 'field_5b86baf8f4007',
				'label' => 'Enable Reward Opt-in',
				'name' => 'slb_enable_reward',
				'type' => 'radio',
				'instructions' => 'choose an aption',
				'required' => 1,
				'choices' => array (
					0 => 'no',
					1 => 'yes',
				),
				'other_choice' => 0,
				'save_other_choice' => 0,
				'default_value' => '',
				'layout' => 'vertical',
			),
			array (
				'key' => 'field_5b86bba6f4008',
				'label' => 'Reward Title',
				'name' => 'slb_reward_title',
				'type' => 'text',
				'required' => 1,
				'conditional_logic' => array (
					'status' => 1,
					'rules' => array (
						array (
							'field' => 'field_5b86baf8f4007',
							'operator' => '==',
							'value' => '1',
						),
					),
					'allorany' => 'all',
				),
				'default_value' => '',
				'placeholder' => 'Write here the title',
				'prepend' => '',
				'append' => '',
				'formatting' => 'html',
				'maxlength' => '',
			),
			array (
				'key' => 'field_5b86bc22f4009',
				'label' => 'Reward File',
				'name' => 'slb_reward_file',
				'type' => 'file',
				'required' => 1,
				'conditional_logic' => array (
					'status' => 1,
					'rules' => array (
						array (
							'field' => 'field_5b86baf8f4007',
							'operator' => '==',
							'value' => '1',
						),
					),
					'allorany' => 'all',
				),
				'save_format' => 'object',
				'library' => 'all',
			),
		),
		'location' => array (
			array (
				array (
					'param' => 'post_type',
					'operator' => '==',
					'value' => 'slb_list',
					'order_no' => 0,
					'group_no' => 0,
				),
			),
		),
		'options' => array (
			'position' => 'normal',
			'layout' => 'default',
			'hide_on_screen' => array (
				0 => 'permalink',
				1 => 'the_content',
				2 => 'excerpt',
				3 => 'custom_fields',
				4 => 'discussion',
				5 => 'comments',
				6 => 'revisions',
				7 => 'slug',
				8 => 'author',
				9 => 'format',
				10 => 'featured_image',
				11 => 'categories',
				12 => 'tags',
				13 => 'send-trackbacks',
			),
		),
		'menu_order' => 0,
	));
}


?>