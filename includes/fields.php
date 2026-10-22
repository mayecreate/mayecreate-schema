<?php
/**
 * Settings page (Settings → Schema) and the per-page "Schema" box, built with ACF PRO.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Warn when ACF PRO isn't available.
 */
add_action(
	'admin_notices',
	function () {
		if ( function_exists( 'acf_add_options_sub_page' ) || ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		echo '<div class="notice notice-error"><p><strong>MayeCreate Schema</strong> needs Advanced Custom Fields PRO. Activate ACF PRO to see the Schema settings page. No schema is output until then.</p></div>';
	}
);

add_action( 'acf/init', 'mcs_register_settings_page' );
add_action( 'acf/init', 'mcs_register_fields' );

function mcs_register_settings_page() {
	if ( ! function_exists( 'acf_add_options_sub_page' ) ) {
		return;
	}
	acf_add_options_sub_page(
		array(
			'page_title'  => 'Schema Settings',
			'menu_title'  => 'Schema',
			'menu_slug'   => 'mayecreate-schema',
			'parent_slug' => 'options-general.php',
			'capability'  => 'manage_options',
		)
	);
}

/**
 * Fill the "Post type" dropdown in the Schema box with child pages + the site's public post types.
 */
add_filter(
	'acf/load_field/key=field_mcs_collection_of',
	function ( $field ) {
		$choices = array( 'children' => 'Child pages' );
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $object ) {
			if ( in_array( $name, array( 'attachment', 'page' ), true ) ) {
				continue;
			}
			$choices[ $name ] = $object->labels->name;
		}
		$field['choices'] = $choices;
		return $field;
	}
);

/**
 * Fill the Post Types dropdown with the site's public post types (they're registered after ACF loads).
 */
add_filter(
	'acf/load_field/key=field_mcs_pt_post_type',
	function ( $field ) {
		$choices = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $name => $object ) {
			if ( in_array( $name, array( 'attachment', 'page' ), true ) ) {
				continue;
			}
			$choices[ $name ] = $object->labels->name . ' (' . $name . ')';
		}
		$field['choices'] = $choices;
		return $field;
	}
);

function mcs_register_fields() {
	if ( ! function_exists( 'acf_add_local_field_group' ) ) {
		return;
	}

	$org_choices = array();
	foreach ( mcs_org_types() as $type => $info ) {
		$org_choices[ $info['group'] ][ $type ] = $info['label'];
	}

	$page_picker = function ( $key, $label, $instructions ) {
		return array(
			'key'           => 'field_mcs_' . $key,
			'name'          => 'mcs_' . $key,
			'label'         => $label,
			'type'          => 'post_object',
			'post_type'     => array( 'page' ),
			'return_format' => 'id',
			'allow_null'    => 1,
			'ui'            => 1,
			'instructions'  => $instructions,
			'wrapper'       => array( 'width' => '50' ),
		);
	};

	/* ---------------------------------------------------------------------
	 * Settings → Schema
	 * ------------------------------------------------------------------- */
	acf_add_local_field_group(
		array(
			'key'                   => 'group_mcs_settings',
			'title'                 => 'Schema Settings',
			'style'                 => 'seamless',
			'label_placement'       => 'top',
			'instruction_placement' => 'label',
			'location'              => array(
				array(
					array(
						'param'    => 'options_page',
						'operator' => '==',
						'value'    => 'mayecreate-schema',
					),
				),
			),
			'fields'                => array(

				/* --- Business ------------------------------------------------ */
				array(
					'key'   => 'field_mcs_tab_business',
					'label' => 'Business',
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_mcs_org_type',
					'name'         => 'mcs_org_type',
					'label'        => 'What kind of organization is this?',
					'type'         => 'select',
					'choices'      => $org_choices,
					'default_value' => 'Organization',
					'ui'           => 1,
					'instructions' => 'Not sure? See the guide just below.',
				),
				array(
					'key'       => 'field_mcs_type_guide',
					'label'     => 'Which one do I pick?',
					'type'      => 'message',
					'esc_html'  => 0,
					'new_lines' => '',
					'message'   => mcs_type_guide_html(),
				),
				array(
					'key'               => 'field_mcs_custom_type',
					'name'              => 'mcs_custom_type',
					'label'             => 'Schema.org type',
					'type'              => 'text',
					'instructions'      => 'Copy the name exactly as schema.org shows it: no spaces, same capitals (e.g. Locksmith, DryCleaningOrLaundry). Find it in the list of local business types linked in the guide above.',
					'conditional_logic' => array( array( array( 'field' => 'field_mcs_org_type', 'operator' => '==', 'value' => 'custom' ) ) ),
				),
				array(
					'key'               => 'field_mcs_nonprofit_status',
					'name'              => 'mcs_nonprofit_status',
					'label'             => 'Nonprofit status',
					'type'              => 'select',
					'allow_null'        => 1,
					'choices'           => array(
						'Nonprofit501c3'  => '501(c)(3)',
						'Nonprofit501c4'  => '501(c)(4)',
						'Nonprofit501c5'  => '501(c)(5)',
						'Nonprofit501c6'  => '501(c)(6)',
						'Nonprofit501c7'  => '501(c)(7)',
						'Nonprofit501c19' => '501(c)(19)',
					),
					'instructions'      => 'Leave blank if you\'re not sure.',
					'conditional_logic' => array( array( array( 'field' => 'field_mcs_org_type', 'operator' => '==', 'value' => 'NGO' ) ) ),
				),
				array(
					'key'          => 'field_mcs_name',
					'name'         => 'mcs_name',
					'label'        => 'Business name',
					'type'         => 'text',
					'instructions' => 'Must match the Google Business Profile exactly. Leave blank to use the WordPress Site Title.',
					'wrapper'      => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_mcs_legal_name',
					'name'         => 'mcs_legal_name',
					'label'        => 'Legal name (optional)',
					'type'         => 'text',
					'instructions' => 'Only if different from the business name, e.g. "Smith Construction, LLC".',
					'wrapper'      => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_mcs_description',
					'name'         => 'mcs_description',
					'label'        => 'Short description',
					'type'         => 'textarea',
					'rows'         => 3,
					'instructions' => 'One or two plain sentences about what the organization does.',
				),
				array(
					'key'           => 'field_mcs_logo',
					'name'          => 'mcs_logo',
					'label'         => 'Logo',
					'type'          => 'image',
					'return_format' => 'id',
					'preview_size'  => 'medium',
					'instructions'  => 'Square or horizontal logo, at least 112px wide. Leave blank to use the theme logo.',
				),

				/* --- Contact & location --------------------------------------- */
				array(
					'key'   => 'field_mcs_tab_contact',
					'label' => 'Contact & Location',
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_mcs_street',
					'name'         => 'mcs_street',
					'label'        => 'Street address',
					'type'         => 'text',
					'instructions' => 'Format it exactly like the Google Business Profile (e.g. "Ste. 200" vs "Suite 200").',
				),
				array(
					'key'     => 'field_mcs_city',
					'name'    => 'mcs_city',
					'label'   => 'City',
					'type'    => 'text',
					'wrapper' => array( 'width' => '40' ),
				),
				array(
					'key'          => 'field_mcs_state',
					'name'         => 'mcs_state',
					'label'        => 'State',
					'type'         => 'text',
					'instructions' => 'Two letters, e.g. MO',
					'wrapper'      => array( 'width' => '20' ),
				),
				array(
					'key'     => 'field_mcs_zip',
					'name'    => 'mcs_zip',
					'label'   => 'ZIP',
					'type'    => 'text',
					'wrapper' => array( 'width' => '20' ),
				),
				array(
					'key'           => 'field_mcs_country',
					'name'          => 'mcs_country',
					'label'         => 'Country',
					'type'          => 'text',
					'default_value' => 'US',
					'wrapper'       => array( 'width' => '20' ),
				),
				array(
					'key'          => 'field_mcs_hide_street',
					'name'         => 'mcs_hide_street',
					'label'        => 'Hide street address',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'Turn on if the Google Business Profile hides the address (common for service-area contractors). Only city, state and ZIP will be shown.',
				),
				array(
					'key'          => 'field_mcs_phone',
					'name'         => 'mcs_phone',
					'label'        => 'Main phone',
					'type'         => 'text',
					'instructions' => 'Same number as the Google Business Profile, e.g. +1-573-555-0100',
					'wrapper'      => array( 'width' => '50' ),
				),
				array(
					'key'     => 'field_mcs_email',
					'name'    => 'mcs_email',
					'label'   => 'Main email (optional)',
					'type'    => 'email',
					'wrapper' => array( 'width' => '50' ),
				),
				array(
					'key'          => 'field_mcs_area_served',
					'name'         => 'mcs_area_served',
					'label'        => 'Service area (optional)',
					'type'         => 'textarea',
					'rows'         => 4,
					'instructions' => 'One per line: cities, counties or states, e.g. "Boone County, MO".',
				),
				array(
					'key'          => 'field_mcs_hours',
					'name'         => 'mcs_hours',
					'label'        => 'Hours (optional, local businesses only)',
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => 'Add hours',
					'instructions' => 'Only add hours that rarely change and match the Google Business Profile. Skip for B2B and nonprofits.',
					'sub_fields'   => array(
						array(
							'key'     => 'field_mcs_hours_days',
							'name'    => 'days',
							'label'   => 'Days',
							'type'    => 'checkbox',
							'layout'  => 'horizontal',
							'choices' => array(
								'Monday'    => 'Mon',
								'Tuesday'   => 'Tue',
								'Wednesday' => 'Wed',
								'Thursday'  => 'Thu',
								'Friday'    => 'Fri',
								'Saturday'  => 'Sat',
								'Sunday'    => 'Sun',
							),
						),
						array(
							'key'            => 'field_mcs_hours_opens',
							'name'           => 'opens',
							'label'          => 'Opens',
							'type'           => 'time_picker',
							'display_format' => 'g:i a',
							'return_format'  => 'H:i',
						),
						array(
							'key'            => 'field_mcs_hours_closes',
							'name'           => 'closes',
							'label'          => 'Closes',
							'type'           => 'time_picker',
							'display_format' => 'g:i a',
							'return_format'  => 'H:i',
						),
					),
				),

				/* --- Profiles -------------------------------------------------- */
				array(
					'key'   => 'field_mcs_tab_profiles',
					'label' => 'Online Profiles',
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_mcs_maps_url',
					'name'         => 'mcs_maps_url',
					'label'        => 'Google Business Profile / Google Maps link',
					'type'         => 'url',
					'instructions' => 'Open the business on Google Maps, click Share, and copy the link.',
				),
				array(
					'key'          => 'field_mcs_same_as',
					'name'         => 'mcs_same_as',
					'label'        => 'Social and directory profiles',
					'type'         => 'textarea',
					'rows'         => 5,
					'instructions' => 'One full URL per line: Facebook, LinkedIn, Instagram, YouTube, BBB, Candid/GuideStar, etc. Only profiles the organization actually controls.',
				),

				/* --- Pages ------------------------------------------------------ */
				array(
					'key'   => 'field_mcs_tab_pages',
					'label' => 'Key Pages',
					'type'  => 'tab',
				),
				array(
					'key'     => 'field_mcs_pages_message',
					'label'   => '',
					'type'    => 'message',
					'message' => 'Leave any of these blank and the plugin looks for a page with the usual name (about, contact, services, team, resources). Pick a page only if it has a different name. Pages under the Services page are automatically marked as services. If resources are a post type rather than pages, leave Resources blank and set it up on the Post Types tab.',
				),
				$page_picker( 'about_page', 'About page', 'Becomes an AboutPage.' ),
				$page_picker( 'contact_page', 'Contact page', 'Becomes a ContactPage.' ),
				$page_picker( 'services_page', 'Services page', 'Becomes a collection page. Its child pages become Services.' ),
				$page_picker( 'resources_page', 'Resources page (only if resources are pages)', 'Its child pages become Articles.' ),
				$page_picker( 'team_page', 'Team / Staff page', 'The page that lists staff. The people below are added to this page.' ),

				/* --- Post types ------------------------------------------------- */
				array(
					'key'   => 'field_mcs_tab_post_types',
					'label' => 'Post Types',
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_mcs_post_types',
					'name'         => 'mcs_post_types',
					'label'        => 'Post types on this site',
					'type'         => 'repeater',
					'layout'       => 'table',
					'button_label' => 'Add post type',
					'instructions' => 'Add a row for each post type the site uses (News, Projects, Resources, Events, Announcements...). The main page is the regular page with the posts block on it; a page with the same slug as the post type (like /projects/) is found automatically. Post types not listed here are treated as standard pages.',
					'sub_fields'   => array(
						array(
							'key'          => 'field_mcs_pt_post_type',
							'name'         => 'post_type',
							'label'        => 'Post type',
							'type'         => 'select',
							'choices'      => array(),
							'ui'           => 1,
						),
						array(
							'key'           => 'field_mcs_pt_main_page',
							'name'          => 'main_page',
							'label'         => 'Main page',
							'type'          => 'post_object',
							'post_type'     => array( 'page' ),
							'return_format' => 'id',
							'allow_null'    => 1,
							'ui'            => 1,
						),
						array(
							'key'           => 'field_mcs_pt_item_type',
							'name'          => 'item_type',
							'label'         => 'Each item is',
							'type'          => 'select',
							'default_value' => 'webpage',
							'choices'       => array(
								'webpage'  => 'Standard page (projects, announcements, most things)',
								'article'  => 'Article (written resources, news)',
								'document' => 'Downloadable document',
								'resource' => 'Resource (file, outside link, or page, decided per item)',
								'event'    => 'Event',
							),
							'instructions'  => 'Posts are always blog posts.',
						),
						array(
							'key'               => 'field_mcs_pt_file_field',
							'name'              => 'file_field',
							'label'             => 'File field name',
							'type'              => 'text',
							'placeholder'       => 'e.g. resource_file',
							'instructions'      => 'Optional. The ACF field that holds the download. Leave blank to check common names (resource_file, file, download...).',
							'conditional_logic' => array(
								array( array( 'field' => 'field_mcs_pt_item_type', 'operator' => '==', 'value' => 'document' ) ),
								array( array( 'field' => 'field_mcs_pt_item_type', 'operator' => '==', 'value' => 'resource' ) ),
							),
						),
						array(
							'key'               => 'field_mcs_pt_link_field',
							'name'              => 'link_field',
							'label'             => 'Outside link field name',
							'type'              => 'text',
							'placeholder'       => 'e.g. external_url',
							'instructions'      => 'Optional. Leave blank to check common names (external_url, resource_url, link...).',
							'conditional_logic' => array( array( array( 'field' => 'field_mcs_pt_item_type', 'operator' => '==', 'value' => 'resource' ) ) ),
						),
					),
				),

				/* --- Team ------------------------------------------------------- */
				array(
					'key'   => 'field_mcs_tab_team',
					'label' => 'Team',
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_mcs_team_block',
					'name'         => 'mcs_team_block',
					'label'        => 'Team block name (optional)',
					'type'         => 'text',
					'placeholder'  => 'acf/mc-team',
					'instructions' => 'Leave blank to detect any ACF block with "team" in its name. Separate several with commas.',
				),
				array(
					'key'          => 'field_mcs_team',
					'name'         => 'mcs_team',
					'label'        => 'Staff to include',
					'type'         => 'repeater',
					'layout'       => 'block',
					'button_label' => 'Add person',
					'instructions' => 'Only needed if the site doesn\'t use the MC Team block. Staff in team blocks are picked up automatically from whatever page they\'re on, and this list is ignored on that page.',
					'sub_fields'   => array(
						array(
							'key'     => 'field_mcs_team_name',
							'name'    => 'name',
							'label'   => 'Name',
							'type'    => 'text',
							'wrapper' => array( 'width' => '50' ),
						),
						array(
							'key'     => 'field_mcs_team_job_title',
							'name'    => 'job_title',
							'label'   => 'Job title',
							'type'    => 'text',
							'wrapper' => array( 'width' => '50' ),
						),
						array(
							'key'           => 'field_mcs_team_photo',
							'name'          => 'photo',
							'label'         => 'Photo (optional)',
							'type'          => 'image',
							'return_format' => 'id',
							'preview_size'  => 'thumbnail',
							'wrapper'       => array( 'width' => '30' ),
						),
						array(
							'key'     => 'field_mcs_team_linkedin',
							'name'    => 'linkedin',
							'label'   => 'LinkedIn URL (optional)',
							'type'    => 'url',
							'wrapper' => array( 'width' => '35' ),
						),
						array(
							'key'           => 'field_mcs_team_profile_page',
							'name'          => 'profile_page',
							'label'         => 'Profile page (optional)',
							'type'          => 'post_object',
							'post_type'     => array(),
							'return_format' => 'id',
							'allow_null'    => 1,
							'ui'            => 1,
							'wrapper'       => array( 'width' => '35' ),
						),
					),
				),

				/* --- Advanced ---------------------------------------------------- */
				array(
					'key'   => 'field_mcs_tab_advanced',
					'label' => 'Advanced',
					'type'  => 'tab',
				),
				array(
					'key'          => 'field_mcs_pretty',
					'name'         => 'mcs_pretty',
					'label'        => 'Pretty print',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'Makes the schema readable in View Source while testing. Fine to leave on.',
				),
				array(
					'key'          => 'field_mcs_disable',
					'name'         => 'mcs_disable',
					'label'        => 'Turn off schema output',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'Stops all output from this plugin without deactivating it.',
				),
			),
		)
	);

	/* ---------------------------------------------------------------------
	 * Per-page "Schema" box
	 * ------------------------------------------------------------------- */
	// Every post type except media.
	$locations = array(
		array(
			array(
				'param'    => 'post_type',
				'operator' => '!=',
				'value'    => 'attachment',
			),
		),
	);

	$show_if = function ( $values ) {
		$rules = array();
		foreach ( (array) $values as $value ) {
			$rules[] = array( array( 'field' => 'field_mcs_page_type', 'operator' => '==', 'value' => $value ) );
		}
		return $rules;
	};

	acf_add_local_field_group(
		array(
			'key'                   => 'group_mcs_page',
			'title'                 => 'Schema',
			'position'              => 'side',
			'style'                 => 'default',
			'menu_order'            => 99,
			'instruction_placement' => 'label',
			'location'              => $locations,
			'fields'                => array(
				array(
					'key'           => 'field_mcs_page_type',
					'name'          => 'mcs_page_type',
					'label'         => 'Page type',
					'type'          => 'select',
					'default_value' => 'auto',
					'choices'       => array(
						'auto'          => 'Automatic (recommended)',
						'webpage'       => 'Standard page',
						'about'         => 'About page',
						'contact'       => 'Contact page',
						'collection'    => 'Collection (lists other pages or posts)',
						'service'       => 'Service',
						'article'       => 'Article / written resource',
						'document'      => 'Downloadable document',
						'staff_profile' => 'Staff profile',
						'team'          => 'Team / staff page',
					),
					'instructions'  => 'Leave on Automatic unless the page is in an unusual spot.',
				),
				array(
					'key'               => 'field_mcs_collection_of',
					'name'              => 'mcs_collection_of',
					'label'             => 'Post type',
					'type'              => 'select',
					'choices'           => array( 'children' => 'Child pages' ),
					'default_value'     => 'children',
					'instructions'      => 'The type of posts this page shows and links to. Choose Child pages for a page like Services that lists the pages under it.',
					'conditional_logic' => $show_if( 'collection' ),
				),
				array(
					'key'          => 'field_mcs_page_description',
					'name'         => 'mcs_description',
					'label'        => 'Short description',
					'type'         => 'textarea',
					'rows'         => 3,
					'instructions' => 'One or two sentences. Leave blank to use the excerpt.',
				),
				array(
					'key'               => 'field_mcs_document_file',
					'name'              => 'mcs_document_file',
					'label'             => 'Document file',
					'type'              => 'file',
					'return_format'     => 'id',
					'conditional_logic' => $show_if( 'document' ),
				),
				array(
					'key'               => 'field_mcs_job_title',
					'name'              => 'mcs_job_title',
					'label'             => 'Job title',
					'type'              => 'text',
					'conditional_logic' => $show_if( 'staff_profile' ),
				),
				array(
					'key'               => 'field_mcs_person_same_as',
					'name'              => 'mcs_person_same_as',
					'label'             => 'Profile links',
					'type'              => 'textarea',
					'rows'              => 2,
					'instructions'      => 'LinkedIn etc., one per line.',
					'conditional_logic' => $show_if( 'staff_profile' ),
				),
				array(
					'key'          => 'field_mcs_exclude',
					'name'         => 'mcs_exclude',
					'label'        => 'No schema on this page',
					'type'         => 'true_false',
					'ui'           => 1,
					'instructions' => 'For thank-you pages, private pages, etc.',
				),
			),
		)
	);
}

/**
 * The "Which one do I pick?" guide shown under the organization type dropdown.
 */
function mcs_type_guide_html() {
	$rows = array(
		array( 'B2B company, manufacturer, distributor, supplier', 'Organization' ),
		array( 'Heavy civil or commercial contractor with no Google Business Profile', 'Organization' ),
		array( 'Builder or general contractor with a Google Business Profile', 'General contractor / builder' ),
		array( 'Electrician, plumber, HVAC, roofer, painter', 'That trade' ),
		array( 'Other construction or home services', 'Construction / home services business' ),
		array( 'Nonprofit, charity, foundation', 'Nonprofit (and pick its 501(c) status)' ),
		array( 'Association, chamber, economic development council', 'Nonprofit if it\'s a 501(c), otherwise Organization' ),
		array( 'College or university', 'College or university' ),
		array( 'K-12 school, training program', 'School or educational organization' ),
		array( 'City, county, state agency', 'Government' ),
		array( 'Newspaper, magazine, news site', 'News / media publisher' ),
		array( 'Web design or marketing agency, consultant, other office-based service with a Google Business Profile', 'Local business (general)' ),
		array( 'Law, accounting, insurance, financial, real estate, vet, restaurant, store', 'That type' ),
	);

	$html  = '<p><strong>Match the Google Business Profile.</strong> If the client has one, pick a local business type. If they don\'t, pick from Organizations. When nothing fits exactly, choose the general option rather than a specific one that\'s only close.</p>';
	$html .= '<table class="widefat striped" style="max-width:780px"><thead><tr><th>If the client is a...</th><th>Choose</th></tr></thead><tbody>';
	foreach ( $rows as $row ) {
		$html .= '<tr><td>' . esc_html( $row[0] ) . '</td><td><strong>' . esc_html( $row[1] ) . '</strong></td></tr>';
	}
	$html .= '</tbody></table>';
	$html .= '<p>Something else with a Google Business Profile? Look it up in the <a href="https://schema.org/LocalBusiness" target="_blank" rel="noopener">list of local business types</a> (see "More specific Types"; each links to more), then choose <strong>Other</strong> and type the name in. Avoid "ProfessionalService": schema.org has retired it.</p>';
	return $html;
}
