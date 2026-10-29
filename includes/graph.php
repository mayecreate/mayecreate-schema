<?php
/**
 * Builds the connected @graph for the current request.
 *
 * Every page gets:   Organization  ←publisher─  WebSite  ←isPartOf─  WebPage  ─breadcrumb→  BreadcrumbList
 * Then, by page type: Service / BlogPosting / Article / DigitalDocument / Person / ItemList, all
 * referencing the Organization and WebPage by @id.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* ===========================================================================
 * Key pages
 * ======================================================================== */

/**
 * ID of a key page: the setting if chosen, otherwise a page with a common slug.
 */
function mcs_key_page( $which ) {
	static $cache = array();
	if ( isset( $cache[ $which ] ) ) {
		return $cache[ $which ];
	}

	$slugs = array(
		'about'     => array( 'about', 'about-us', 'who-we-are' ),
		'contact'   => array( 'contact', 'contact-us' ),
		'services'  => array( 'services', 'our-services', 'what-we-do' ),
		'resources' => array( 'resources' ),
		'blog'      => array( 'blog', 'news', 'news-blog', 'articles', 'insights' ),
		'team'      => array( 'team', 'our-team', 'staff', 'meet-the-team', 'leadership', 'about/team', 'about/staff', 'about-us/team' ),
	);

	$id = mcs_post_id( mcs_opt( $which . '_page', 0 ) );
	if ( ! $id && isset( $slugs[ $which ] ) ) {
		foreach ( $slugs[ $which ] as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				$id = (int) $page->ID;
				break;
			}
		}
	}

	$cache[ $which ] = (int) apply_filters( 'mcs_key_page', $id, $which );
	return $cache[ $which ];
}

/* ===========================================================================
 * Post types (Settings → Schema → Post Types)
 * ======================================================================== */

/**
 * Post type settings, keyed by post type:
 * [ 'main_page' => page ID, 'item_type' => webpage|article|document|event, 'file_field' => ACF field name ].
 */
function mcs_pt_map() {
	static $map = null;
	if ( null !== $map ) {
		return $map;
	}
	$map  = array();
	$rows = mcs_opt( 'post_types', array() );
	if ( is_array( $rows ) ) {
		foreach ( $rows as $row ) {
			if ( empty( $row['post_type'] ) ) {
				continue;
			}
			$map[ $row['post_type'] ] = array(
				'main_page'  => mcs_post_id( isset( $row['main_page'] ) ? $row['main_page'] : 0 ),
				'item_type'  => ! empty( $row['item_type'] ) ? $row['item_type'] : 'webpage',
				'file_field' => isset( $row['file_field'] ) ? trim( (string) $row['file_field'] ) : '',
				'link_field' => isset( $row['link_field'] ) ? trim( (string) $row['link_field'] ) : '',
			);
		}
	}
	$map = apply_filters( 'mcs_post_type_map', $map );
	return $map;
}

/**
 * The page that lists a post type's items (a regular page with a posts block).
 * Posts fall back to the Posts page in Settings → Reading.
 */
function mcs_main_page_for( $post_type ) {
	static $cache = array();
	if ( isset( $cache[ $post_type ] ) ) {
		return $cache[ $post_type ];
	}

	$map = mcs_pt_map();
	$id  = 0;
	if ( ! empty( $map[ $post_type ]['main_page'] ) ) {
		$id = (int) $map[ $post_type ]['main_page'];
	} elseif ( $marked = mcs_page_marked_as_collection_of( $post_type ) ) {
		$id = $marked;
	} elseif ( 'post' === $post_type ) {
		if ( 'page' === get_option( 'show_on_front' ) && get_option( 'page_for_posts' ) ) {
			$id = (int) get_option( 'page_for_posts' );
		} else {
			$id = mcs_key_page( 'blog' );
		}
	} else {
		// A page whose slug matches the post type: /projects/ for "projects", its URL base, or its plural label.
		$object = get_post_type_object( $post_type );
		$slugs  = array( $post_type );
		if ( $object ) {
			if ( ! empty( $object->rewrite['slug'] ) ) {
				array_unshift( $slugs, trim( $object->rewrite['slug'], '/' ) );
			}
			$slugs[] = sanitize_title( $object->labels->name );
		}
		foreach ( array_unique( array_filter( $slugs ) ) as $slug ) {
			$page = get_page_by_path( $slug );
			if ( $page && 'publish' === $page->post_status ) {
				$id = (int) $page->ID;
				break;
			}
		}
	}

	$cache[ $post_type ] = $id;
	return $id;
}

/**
 * A published page whose Schema box says "Collection, lists <post type>".
 */
function mcs_page_marked_as_collection_of( $post_type ) {
	$pages = get_posts(
		array(
			'post_type'      => 'page',
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'fields'         => 'ids',
			'no_found_rows'  => true,
			'orderby'        => 'menu_order date',
			'order'          => 'ASC',
			'meta_query'     => array(
				array( 'key' => 'mcs_page_type', 'value' => 'collection' ),
				array( 'key' => 'mcs_collection_of', 'value' => $post_type ),
			),
		)
	);
	return $pages ? (int) $pages[0] : 0;
}

/**
 * If this page is the main page for a post type, return that post type.
 */
function mcs_listing_post_type( $page_id ) {
	if ( ! $page_id ) {
		return '';
	}
	foreach ( mcs_pt_map() as $post_type => $settings ) {
		if ( (int) $settings['main_page'] === (int) $page_id ) {
			return $post_type;
		}
	}
	// A regular page called Blog or News lists posts.
	if ( (int) $page_id === mcs_main_page_for( 'post' ) && (int) $page_id !== (int) get_option( 'page_for_posts' ) ) {
		return 'post';
	}
	// A page named after a custom post type lists that type.
	foreach ( get_post_types( array( 'public' => true, '_builtin' => false ) ) as $post_type ) {
		if ( (int) $page_id === mcs_main_page_for( $post_type ) ) {
			return $post_type;
		}
	}
	return '';
}

/* ===========================================================================
 * Context: what kind of page is this?
 * ======================================================================== */

function mcs_context() {
	$ctx = array(
		'kind'    => 'none',
		'post_id' => 0,
		'url'     => '',
		'name'    => '',
	);

	if ( is_404() || is_search() ) {
		return $ctx;
	}

	if ( is_front_page() && is_home() ) {
		// Latest posts on the homepage.
		$ctx['kind'] = 'blog_index';
		$ctx['url']  = home_url( '/' );
		$ctx['name'] = mcs_site_name();
		$ctx['home'] = true;
		return $ctx;
	}

	if ( is_home() ) {
		$id             = (int) get_option( 'page_for_posts' );
		$ctx['kind']    = 'blog_index';
		$ctx['post_id'] = $id;
		$ctx['url']     = $id ? get_permalink( $id ) : home_url( '/' );
		$ctx['name']    = $id ? mcs_text( get_the_title( $id ) ) : 'Blog';
		return $ctx;
	}

	if ( is_category() || is_tag() || is_tax() ) {
		$term = get_queried_object();
		if ( $term && isset( $term->term_id ) ) {
			$link        = get_term_link( $term );
			$ctx['kind'] = 'term_archive';
			$ctx['url']  = is_string( $link ) ? $link : '';
			$ctx['name'] = mcs_text( $term->name );
			$ctx['term'] = $term;
		}
		return $ctx;
	}

	if ( is_author() ) {
		$author = get_queried_object();
		if ( $author && isset( $author->ID ) ) {
			$ctx['kind']   = 'author_archive';
			$ctx['url']    = get_author_posts_url( $author->ID );
			$ctx['name']   = mcs_text( $author->display_name );
			$ctx['author'] = (int) $author->ID;
		}
		return $ctx;
	}

	if ( is_post_type_archive() ) {
		$post_type   = get_query_var( 'post_type' );
		$post_type   = is_array( $post_type ) ? reset( $post_type ) : $post_type;
		$object      = get_post_type_object( $post_type );
		$ctx['kind'] = 'pt_archive';
		$ctx['url']  = get_post_type_archive_link( $post_type );
		$ctx['name'] = $object ? mcs_text( $object->labels->name ) : '';
		$ctx['collection_post_type'] = $post_type;
		return $ctx;
	}

	if ( ! is_singular() ) {
		return $ctx;
	}

	$id = (int) get_queried_object_id();
	if ( ! $id ) {
		return $ctx;
	}

	if ( mcs_meta( $id, 'exclude', false ) ) {
		$ctx['kind'] = 'excluded';
		return $ctx;
	}

	$ctx['post_id'] = $id;
	$ctx['url']     = is_front_page() ? home_url( '/' ) : get_permalink( $id );
	$ctx['name']    = mcs_text( get_the_title( $id ) );

	list( $ctx['kind'], $ctx['reason'] ) = mcs_classify_post( $id, is_front_page() );
	if ( 'home' === $ctx['kind'] ) {
		$ctx['home'] = true;
		$ctx['name'] = mcs_site_name();
	}

	if ( 'collection' === $ctx['kind'] ) {
		$lists = mcs_meta( $id, 'collection_of', '' );
		$ctx['collection_post_type'] = ( $lists && 'children' !== $lists ) ? $lists : ( $lists ? '' : mcs_listing_post_type( $id ) );
	}

	return apply_filters( 'mcs_context', $ctx );
}

/**
 * Page types a designer can choose in the Schema box.
 */
function mcs_page_type_choices() {
	return array(
		'webpage'       => 'Standard page',
		'about'         => 'About page',
		'contact'       => 'Contact page',
		'collection'    => 'Collection',
		'service'       => 'Service',
		'article'       => 'Article',
		'document'      => 'Downloadable document',
		'staff_profile' => 'Staff profile',
		'team'          => 'Team / staff page',
	);
}

/**
 * Classify a single post/page. Returns [ kind, reason ].
 */
function mcs_classify_post( $id, $is_front = null ) {
	if ( null === $is_front ) {
		$is_front = 'page' === get_option( 'show_on_front' ) && (int) get_option( 'page_on_front' ) === (int) $id;
	}
	if ( $is_front ) {
		return array( 'home', 'front page' );
	}
	$override = mcs_meta( $id, 'page_type', 'auto' );
	if ( isset( mcs_page_type_choices()[ $override ] ) ) {
		return array( $override, 'chosen in this page\'s Schema box' );
	}
	$kind = mcs_auto_kind( $id );
	return array( $kind, $GLOBALS['mcs_last_reason'] ?? 'automatic' );
}

/**
 * Automatic detection for a singular post/page. Sets $GLOBALS['mcs_last_reason'].
 */
function mcs_auto_kind( $id ) {
	$GLOBALS['mcs_last_reason'] = 'automatic';
	$why = function ( $reason ) {
		$GLOBALS['mcs_last_reason'] = $reason;
	};
	$post_type = get_post_type( $id );

	if ( 'post' === $post_type ) {
		$why( 'blog post' );
		return 'post';
	}

	// Items of a custom post type: as set on the Post Types tab.
	if ( 'page' !== $post_type ) {
		$map = mcs_pt_map();
		if ( mcs_is_resource_type( $post_type ) ) {
			// Each resource decides for itself: uploaded file, outside link, or content on the page.
			$target = mcs_resource_target( $id );
			$why( 'resource: ' . ( 'file' === $target['type'] ? 'downloadable file' : ( 'link' === $target['type'] ? 'outside link' : 'content on the page' ) ) );
			return 'file' === $target['type'] ? 'document' : ( 'link' === $target['type'] ? 'webpage' : 'article' );
		}
		if ( isset( $map[ $post_type ] ) && 'webpage' !== $map[ $post_type ]['item_type'] ) {
			$type = $map[ $post_type ]['item_type'];
			$why( 'Post Types tab' );
			return in_array( $type, array( 'article', 'document', 'event' ), true ) ? $type : 'webpage';
		}
		// Anything with an event start date filled in is an event.
		$fields = mcs_event_fields();
		if ( ! isset( $map[ $post_type ] ) && ! empty( $fields['start_date'] ) && function_exists( 'get_field' ) && get_field( $fields['start_date'], $id, false ) ) {
			$why( 'event start date is filled in' );
			return 'event';
		}
		$why( '"' . $post_type . '" item' );
		return 'webpage';
	}

	// Key Pages chosen in Settings → Schema come first.
	if ( $id === mcs_key_page( 'about' ) ) {
		$why( mcs_key_page_reason( 'about' ) );
		return 'about';
	}
	if ( $id === mcs_key_page( 'contact' ) ) {
		$why( mcs_key_page_reason( 'contact' ) );
		return 'contact';
	}
	if ( $id === mcs_key_page( 'team' ) ) {
		$why( mcs_key_page_reason( 'team' ) );
		return 'team';
	}

	// A post type's main page lists its items.
	$listing = mcs_listing_post_type( $id );
	if ( $listing ) {
		$why( 'main page for the "' . $listing . '" post type' );
		return 'collection';
	}

	if ( $id === mcs_key_page( 'services' ) ) {
		$why( mcs_key_page_reason( 'services' ) );
		return 'collection';
	}
	if ( $id === mcs_key_page( 'resources' ) ) {
		$why( mcs_key_page_reason( 'resources' ) );
		return 'collection';
	}

	if ( 'page' === $post_type ) {
		$ancestors = array_map( 'intval', (array) get_post_ancestors( $id ) );
		$services  = mcs_key_page( 'services' );
		$resources = mcs_key_page( 'resources' );

		if ( $services && in_array( $services, $ancestors, true ) ) {
			// A page under Services that has its own children is a sub-section landing page.
			$why( 'under the Services page' );
			return mcs_child_pages( $id ) ? 'collection' : 'service';
		}
		if ( $resources && in_array( $resources, $ancestors, true ) ) {
			$why( 'under the Resources page' );
			return mcs_child_pages( $id ) ? 'collection' : 'article';
		}
	}

	$why( 'no special type found' );
	return 'webpage';
}

/**
 * Why a page counts as a key page: the setting, or its slug.
 */
function mcs_key_page_reason( $which ) {
	$label = ucfirst( $which ) . ' page';
	return mcs_post_id( mcs_opt( $which . '_page', 0 ) ) ? $label . ' in Settings → Schema' : $label . ', found by its URL';
}

/**
 * Published direct child pages, in menu order.
 */
function mcs_child_pages( $id ) {
	return get_pages(
		array(
			'parent'      => $id,
			'post_status' => 'publish',
			'sort_column' => 'menu_order,post_title',
		)
	);
}

/* ===========================================================================
 * Graph
 * ======================================================================== */

function mcs_build_graph() {
	$ctx             = mcs_context();
	$GLOBALS['mcs_ctx'] = $ctx;

	if ( 'excluded' === $ctx['kind'] ) {
		return array();
	}

	$graph = array(
		'organization' => mcs_node_organization(),
		'website'      => mcs_node_website(),
	);

	if ( 'none' !== $ctx['kind'] && $ctx['url'] ) {
		$graph['webpage'] = mcs_node_webpage( $ctx );

		$crumbs = mcs_node_breadcrumbs( $ctx );
		if ( $crumbs ) {
			$graph['breadcrumbs'] = $crumbs;
		}

		foreach ( mcs_nodes_for_kind( $ctx ) as $key => $node ) {
			$graph[ $key ] = $node;
		}

		// Staff: from team blocks on this page, or the Team list in settings on the team page.
		$people = $ctx['post_id'] ? mcs_team_from_blocks( $ctx['post_id'] ) : array();
		if ( ! $people && ( ( $ctx['post_id'] && $ctx['post_id'] === mcs_key_page( 'team' ) ) || 'team' === $ctx['kind'] ) ) {
			$people = mcs_opt( 'team', array() );
		}
		if ( $people ) {
			foreach ( mcs_nodes_team( $ctx, $people ) as $key => $node ) {
				$graph[ $key ] = $node;
			}
		}

		// Point the page at its list when it has no other main entity.
		if ( empty( $graph['webpage']['mainEntity'] ) ) {
			if ( isset( $graph['itemlist'] ) ) {
				$graph['webpage']['mainEntity'] = mcs_ref( $graph['itemlist']['@id'] );
			} elseif ( isset( $graph['team_list'] ) ) {
				$graph['webpage']['mainEntity'] = mcs_ref( $graph['team_list']['@id'] );
			}
		}
	}

	$graph = apply_filters( 'mcs_graph', $graph, $ctx );

	return array_values( array_filter( array_map( 'mcs_clean', $graph ) ) );
}

/* ---------------------------------------------------------------------------
 * Organization
 * ------------------------------------------------------------------------ */

function mcs_org_type() {
	$type  = mcs_opt( 'org_type', 'Organization' );
	$types = mcs_org_types();
	if ( 'custom' === $type ) {
		$custom = preg_replace( '/[^A-Za-z]/', '', (string) mcs_opt( 'custom_type', '' ) );
		return $custom ? $custom : 'LocalBusiness';
	}
	return isset( $types[ $type ] ) ? $type : 'Organization';
}

function mcs_is_local() {
	$type  = mcs_opt( 'org_type', 'Organization' );
	$types = mcs_org_types();
	return isset( $types[ $type ] ) && $types[ $type ]['local'];
}

function mcs_logo_id() {
	$id = mcs_attachment_id( mcs_opt( 'logo', 0 ) );
	if ( ! $id ) {
		$id = (int) get_theme_mod( 'custom_logo' );
	}
	return $id;
}

function mcs_address() {
	$hide = (bool) mcs_opt( 'hide_street', false );
	$address = array(
		'@type'           => 'PostalAddress',
		'streetAddress'   => $hide ? '' : mcs_text( mcs_opt( 'street' ) ),
		'addressLocality' => mcs_text( mcs_opt( 'city' ) ),
		'addressRegion'   => mcs_text( mcs_opt( 'state' ) ),
		'postalCode'      => mcs_text( mcs_opt( 'zip' ) ),
		'addressCountry'  => mcs_text( mcs_opt( 'country', 'US' ) ),
	);
	// Only output an address if there's more than a country.
	if ( ! $address['streetAddress'] && ! $address['addressLocality'] && ! $address['postalCode'] ) {
		return null;
	}
	return $address;
}

function mcs_area_served() {
	$areas = array();
	foreach ( mcs_lines( mcs_opt( 'area_served' ) ) as $line ) {
		$areas[] = array(
			'@type' => 'Place',
			'name'  => mcs_text( $line ),
		);
	}
	return $areas;
}

function mcs_opening_hours() {
	$rows = mcs_opt( 'hours', array() );
	$out  = array();
	if ( ! is_array( $rows ) ) {
		return $out;
	}
	foreach ( $rows as $row ) {
		if ( empty( $row['days'] ) || empty( $row['opens'] ) || empty( $row['closes'] ) ) {
			continue;
		}
		$out[] = array(
			'@type'     => 'OpeningHoursSpecification',
			'dayOfWeek' => array_values( (array) $row['days'] ),
			'opens'     => $row['opens'],
			'closes'    => $row['closes'],
		);
	}
	return $out;
}

function mcs_node_organization() {
	$logo_id = mcs_logo_id();
	$logo    = mcs_image( $logo_id, mcs_id( 'logo' ) );

	$same_as = mcs_lines( mcs_opt( 'same_as' ) );
	$maps    = trim( (string) mcs_opt( 'maps_url' ) );
	if ( $maps ) {
		array_unshift( $same_as, $maps );
	}
	$same_as = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $same_as ) ) ) );

	$description = mcs_text( mcs_opt( 'description' ) );
	if ( ! $description ) {
		$description = mcs_tagline();
	}

	$type = mcs_org_type();
	$node = array(
		'@type'       => $type,
		'@id'         => mcs_id( 'organization' ),
		'name'        => mcs_site_name(),
		'legalName'   => mcs_text( mcs_opt( 'legal_name' ) ),
		'url'         => home_url( '/' ),
		'description' => $description,
		'logo'        => $logo,
		'image'       => $logo ? mcs_ref( mcs_id( 'logo' ) ) : null,
		'telephone'   => mcs_text( mcs_opt( 'phone' ) ),
		'email'       => sanitize_email( (string) mcs_opt( 'email' ) ),
		'address'     => mcs_address(),
		'areaServed'  => mcs_area_served(),
		'sameAs'      => $same_as,
	);

	if ( 'NGO' === $type ) {
		$status = mcs_opt( 'nonprofit_status' );
		if ( $status ) {
			$node['nonprofitStatus'] = 'https://schema.org/' . preg_replace( '/[^A-Za-z0-9]/', '', $status );
		}
	}

	if ( mcs_is_local() ) {
		$node['openingHoursSpecification'] = mcs_opening_hours();
	}

	return apply_filters( 'mcs_node_organization', $node );
}

/* ---------------------------------------------------------------------------
 * WebSite
 * ------------------------------------------------------------------------ */

function mcs_node_website() {
	$node = array(
		'@type'       => 'WebSite',
		'@id'         => mcs_id( 'website' ),
		'url'         => home_url( '/' ),
		'name'        => mcs_site_name(),
		'description' => mcs_tagline(),
		'inLanguage'  => get_bloginfo( 'language' ),
		'publisher'   => mcs_ref( mcs_id( 'organization' ) ),
	);
	return apply_filters( 'mcs_node_website', $node );
}

/* ---------------------------------------------------------------------------
 * WebPage
 * ------------------------------------------------------------------------ */

function mcs_page_type_for( $kind ) {
	$map = array(
		'about'          => 'AboutPage',
		'contact'        => 'ContactPage',
		'collection'     => 'CollectionPage',
		'blog_index'     => 'CollectionPage',
		'term_archive'   => 'CollectionPage',
		'pt_archive'     => 'CollectionPage',
		'staff_profile'  => 'ProfilePage',
		'author_archive' => 'ProfilePage',
	);
	return isset( $map[ $kind ] ) ? $map[ $kind ] : 'WebPage';
}

function mcs_description_for( $ctx ) {
	$id = $ctx['post_id'];
	if ( $id ) {
		$text = mcs_text( mcs_meta( $id, 'description' ) );
		if ( ! $text && has_excerpt( $id ) ) {
			$text = mcs_text( get_post_field( 'post_excerpt', $id ) );
		}
		if ( $text ) {
			return $text;
		}
	}
	if ( 'term_archive' === $ctx['kind'] && ! empty( $ctx['term']->description ) ) {
		return mcs_text( $ctx['term']->description );
	}
	if ( ! empty( $ctx['home'] ) ) {
		$text = mcs_text( mcs_opt( 'description' ) );
		return $text ? $text : mcs_tagline();
	}
	return '';
}

function mcs_node_webpage( $ctx ) {
	$url  = $ctx['url'];
	$id   = $ctx['post_id'];
	$kind = $ctx['kind'];

	$node = array(
		'@type'       => mcs_page_type_for( $kind ),
		'@id'         => mcs_id( 'webpage', $url ),
		'url'         => $url,
		'name'        => $ctx['name'],
		'description' => mcs_description_for( $ctx ),
		'inLanguage'  => get_bloginfo( 'language' ),
		'isPartOf'    => mcs_ref( mcs_id( 'website' ) ),
	);

	if ( empty( $ctx['home'] ) ) {
		$node['breadcrumb'] = mcs_ref( mcs_id( 'breadcrumb', $url ) );
	}

	// Pages that are about the organization itself.
	if ( ! empty( $ctx['home'] ) || in_array( $kind, array( 'about', 'contact', 'team' ), true ) || ( $id && $id === mcs_key_page( 'services' ) ) ) {
		$node['about'] = mcs_ref( mcs_id( 'organization' ) );
	}

	if ( $id ) {
		$node['datePublished'] = get_post_time( 'c', true, $id );
		$node['dateModified']  = get_post_modified_time( 'c', true, $id );

		$thumb = mcs_thumbnail_id( $id );
		if ( $thumb ) {
			$node['primaryImageOfPage'] = mcs_image( $thumb, mcs_id( 'primaryimage', $url ) );
		}
	}

	// A resource page with a button to a file or another site.
	if ( $id && 'page' !== get_post_type( $id ) && mcs_is_resource_type( get_post_type( $id ) ) ) {
		$target = mcs_resource_target( $id );
		if ( 'page' === $target['type'] && $target['button'] ) {
			$node['relatedLink'] = $target['button'];
		}
	}

	$main = array(
		'service'       => 'service',
		'post'          => 'article',
		'article'       => 'article',
		'document'      => 'document',
		'event'         => 'event',
		'staff_profile' => 'person',
	);
	if ( 'author_archive' === $kind ) {
		$node['mainEntity'] = mcs_ref( mcs_id( 'person', $url ) );
	} elseif ( isset( $main[ $kind ] ) ) {
		$node['mainEntity'] = mcs_ref( mcs_id( $main[ $kind ], $url ) );
	}

	return apply_filters( 'mcs_node_webpage', $node, $ctx );
}

/* ---------------------------------------------------------------------------
 * Breadcrumbs
 * ------------------------------------------------------------------------ */

function mcs_node_breadcrumbs( $ctx ) {
	if ( ! empty( $ctx['home'] ) ) {
		return null;
	}

	$items = array(
		array( 'name' => 'Home', 'url' => home_url( '/' ) ),
	);

	$id = $ctx['post_id'];

	// The page (and its parents) that the current item sits under.
	$trail_page = 0;
	if ( $id && 'page' === get_post_type( $id ) ) {
		$trail_page = (int) wp_get_post_parent_id( $id );
	} elseif ( $id ) {
		$trail_page = mcs_main_page_for( get_post_type( $id ) );
	} elseif ( 'term_archive' === $ctx['kind'] && ! empty( $ctx['term']->taxonomy ) ) {
		$tax        = get_taxonomy( $ctx['term']->taxonomy );
		$trail_page = ( $tax && ! empty( $tax->object_type ) ) ? mcs_main_page_for( reset( $tax->object_type ) ) : 0;
	}

	if ( $trail_page && $trail_page !== $id ) {
		$chain = array_reverse( (array) get_post_ancestors( $trail_page ) );
		$chain[] = $trail_page;
		foreach ( $chain as $page ) {
			$items[] = array(
				'name' => mcs_text( get_the_title( $page ) ),
				'url'  => get_permalink( $page ),
			);
		}
	}

	if ( 'blog_index' !== $ctx['kind'] || $id ) {
		$items[] = array( 'name' => $ctx['name'], 'url' => $ctx['url'] );
	}

	$list = array();
	foreach ( $items as $i => $item ) {
		$list[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'name'     => $item['name'],
			'item'     => $item['url'],
		);
	}

	if ( count( $list ) < 2 ) {
		return null;
	}

	return apply_filters(
		'mcs_node_breadcrumbs',
		array(
			'@type'           => 'BreadcrumbList',
			'@id'             => mcs_id( 'breadcrumb', $ctx['url'] ),
			'itemListElement' => $list,
		),
		$ctx
	);
}

/* ---------------------------------------------------------------------------
 * Page-type specific nodes
 * ------------------------------------------------------------------------ */

function mcs_nodes_for_kind( $ctx ) {
	$nodes = array();
	$url   = $ctx['url'];
	$id    = $ctx['post_id'];
	$org   = mcs_ref( mcs_id( 'organization' ) );
	$page  = mcs_ref( mcs_id( 'webpage', $url ) );
	$thumb = $id ? mcs_thumbnail_id( $id ) : 0;
	$image = $thumb ? mcs_ref( mcs_id( 'primaryimage', $url ) ) : null;
	$desc  = mcs_description_for( $ctx );

	switch ( $ctx['kind'] ) {

		case 'service':
			$nodes['service'] = array(
				'@type'            => 'Service',
				'@id'              => mcs_id( 'service', $url ),
				'name'             => $ctx['name'],
				'serviceType'      => $ctx['name'],
				'description'      => $desc,
				'url'              => $url,
				'image'            => $image,
				'provider'         => $org,
				'areaServed'       => mcs_area_served(),
				'mainEntityOfPage' => $page,
			);
			break;

		case 'post':
		case 'article':
			$is_post = 'post' === $ctx['kind'];
			$author  = $org;
			if ( $is_post ) {
				$author_id = (int) get_post_field( 'post_author', $id );
				if ( $author_id ) {
					$person          = mcs_node_author( $author_id );
					$nodes['author'] = $person;
					$author          = mcs_ref( $person['@id'] );
				}
			}
			$headline = $ctx['name'];
			if ( function_exists( 'mb_strlen' ) && mb_strlen( $headline ) > 110 ) {
				$headline = rtrim( mb_substr( $headline, 0, 107 ) ) . '...';
			}
			$nodes['article'] = array(
				'@type'            => $is_post ? 'BlogPosting' : 'Article',
				'@id'              => mcs_id( 'article', $url ),
				'headline'         => $headline,
				'description'      => $desc,
				'url'              => $url,
				'image'            => $image,
				'datePublished'    => get_post_time( 'c', true, $id ),
				'dateModified'     => get_post_modified_time( 'c', true, $id ),
				'author'           => $author,
				'publisher'        => $org,
				'isPartOf'         => $page,
				'mainEntityOfPage' => $page,
				'inLanguage'       => get_bloginfo( 'language' ),
			);
			if ( $is_post ) {
				$cats = get_the_category( $id );
				$tags = get_the_tags( $id );
				$nodes['article']['articleSection'] = is_array( $cats ) ? wp_list_pluck( $cats, 'name' ) : array();
				$nodes['article']['keywords']       = is_array( $tags ) ? wp_list_pluck( $tags, 'name' ) : array();
			}
			break;

		case 'document':
			list( $file_id, $file_url ) = mcs_document_file( $id );
			if ( ! $file_url && mcs_is_resource_type( get_post_type( $id ) ) ) {
				$target   = mcs_resource_target( $id );
				$file_url = 'file' === $target['type'] ? $target['url'] : '';
			}
			$nodes['document'] = array(
				'@type'            => 'DigitalDocument',
				'@id'              => mcs_id( 'document', $url ),
				'name'             => $ctx['name'],
				'description'      => $desc,
				'url'              => $file_url ? $file_url : $url,
				'encodingFormat'   => $file_id ? get_post_mime_type( $file_id ) : '',
				'datePublished'    => get_post_time( 'c', true, $id ),
				'dateModified'     => get_post_modified_time( 'c', true, $id ),
				'publisher'        => $org,
				'author'           => $org,
				'mainEntityOfPage' => $page,
			);
			break;

		case 'event':
			$event = mcs_node_event( $ctx, $desc, $image );
			if ( $event ) {
				$nodes['event'] = $event;
			}
			break;

		case 'staff_profile':
			$nodes['person'] = array(
				'@type'            => 'Person',
				'@id'              => mcs_id( 'person', $url ),
				'name'             => $ctx['name'],
				'jobTitle'         => mcs_text( mcs_meta( $id, 'job_title' ) ),
				'description'      => $desc,
				'url'              => $url,
				'image'            => $image,
				'sameAs'           => array_values( array_filter( array_map( 'esc_url_raw', mcs_lines( mcs_meta( $id, 'person_same_as' ) ) ) ) ),
				'worksFor'         => $org,
				'mainEntityOfPage' => $page,
			);
			break;

		case 'collection':
		case 'blog_index':
		case 'term_archive':
		case 'pt_archive':
			$list = mcs_collection_items( $ctx );
			if ( $list ) {
				$nodes['itemlist'] = array(
					'@type'           => 'ItemList',
					'@id'             => mcs_id( 'itemlist', $url ),
					'itemListElement' => $list,
				);
			}
			break;

		case 'author_archive':
			$person          = mcs_node_author( $ctx['author'] );
			$nodes['author'] = $person;
			break;
	}

	return apply_filters( 'mcs_nodes_for_kind', $nodes, $ctx );
}

/**
 * Items for a collection page: child pages for page collections, the current query's posts for archives.
 */
function mcs_collection_items( $ctx ) {
	$posts = array();

	if ( ! empty( $ctx['collection_post_type'] ) ) {
		$posts = get_posts(
			array(
				'post_type'      => $ctx['collection_post_type'],
				'post_status'    => 'publish',
				'posts_per_page' => (int) apply_filters( 'mcs_collection_limit', 20 ),
				'no_found_rows'  => true,
			)
		);
	} elseif ( $ctx['post_id'] && 'page' === get_post_type( $ctx['post_id'] ) && 'blog_index' !== $ctx['kind'] ) {
		$posts = mcs_child_pages( $ctx['post_id'] );
	} else {
		global $wp_query;
		if ( isset( $wp_query->posts ) && is_array( $wp_query->posts ) ) {
			$posts = $wp_query->posts;
		}
	}

	$items = array();
	foreach ( $posts as $i => $post ) {
		$link = get_permalink( $post );
		if ( ! empty( $ctx['collection_post_type'] ) && mcs_is_resource_type( $ctx['collection_post_type'] ) ) {
			// Files and outside links skip the single page, so list where people actually land.
			$target = mcs_resource_target( is_object( $post ) ? $post->ID : (int) $post );
			if ( $target['url'] ) {
				$link = $target['url'];
			}
		}
		$items[] = array(
			'@type'    => 'ListItem',
			'position' => $i + 1,
			'url'      => $link,
			'name'     => mcs_text( get_the_title( $post ) ),
		);
	}
	return $items;
}

/**
 * Person node for a WordPress user (blog post author).
 */
function mcs_node_author( $user_id ) {
	$url  = get_author_posts_url( $user_id );
	$node = array(
		'@type'       => 'Person',
		'@id'         => mcs_id( 'person', $url ),
		'name'        => mcs_text( get_the_author_meta( 'display_name', $user_id ) ),
		'description' => mcs_text( get_the_author_meta( 'description', $user_id ) ),
		'url'         => $url,
		'worksFor'    => mcs_ref( mcs_id( 'organization' ) ),
	);
	return apply_filters( 'mcs_node_author', $node, $user_id );
}

/**
 * Person nodes for staff shown on a page, plus an ItemList of them.
 * Each row: name, job_title, photo, linkedin, profile_page, email, phone, bio.
 */
function mcs_nodes_team( $ctx, $rows ) {
	if ( ! is_array( $rows ) || ! $rows ) {
		return array();
	}

	$nodes = array();
	$list  = array();
	$org   = mcs_ref( mcs_id( 'organization' ) );

	foreach ( $rows as $i => $row ) {
		$name = mcs_text( isset( $row['name'] ) ? $row['name'] : '' );
		if ( ! $name ) {
			continue;
		}
		$profile = mcs_post_id( isset( $row['profile_page'] ) ? $row['profile_page'] : 0 );
		$url     = $profile ? get_permalink( $profile ) : '';
		$pid     = $url ? mcs_id( 'person', $url ) : mcs_id( 'person-' . sanitize_title( $name ), $ctx['url'] );
		$photo   = mcs_attachment_id( isset( $row['photo'] ) ? $row['photo'] : 0 );
		$photo   = mcs_is_placeholder( $photo ) ? 0 : $photo;
		$src     = $photo ? wp_get_attachment_image_src( $photo, 'full' ) : false;

		$nodes[ 'person_' . $i ] = array(
			'@type'    => 'Person',
			'@id'      => $pid,
			'name'     => $name,
			'jobTitle' => mcs_text( isset( $row['job_title'] ) ? $row['job_title'] : '' ),
			'image'       => $src ? $src[0] : '',
			'url'         => $url,
			'email'       => ! empty( $row['email'] ) ? sanitize_email( (string) $row['email'] ) : '',
			'telephone'   => ! empty( $row['phone'] ) ? mcs_text( (string) $row['phone'] ) : '',
			'description' => ! empty( $row['bio'] ) ? mcs_text( (string) $row['bio'] ) : '',
			'sameAs'      => ! empty( $row['linkedin'] ) ? array( esc_url_raw( $row['linkedin'] ) ) : array(),
			'worksFor'    => $org,
		);

		$list[] = array(
			'@type'    => 'ListItem',
			'position' => count( $list ) + 1,
			'item'     => mcs_ref( $pid ),
		);
	}

	if ( $list ) {
		$nodes['team_list'] = array(
			'@type'           => 'ItemList',
			'@id'             => mcs_id( 'team', $ctx['url'] ),
			'name'            => 'Team',
			'itemListElement' => $list,
		);
	}

	return $nodes;
}

/**
 * The file for a downloadable document: the Schema box field first, then the post type's file field.
 * Returns [ attachment ID (or 0), file URL (or '') ].
 */
function mcs_document_file( $id ) {
	$value = mcs_meta( $id, 'document_file', 0 );
	if ( ! $value ) {
		$value = mcs_first_field( $id, mcs_resource_field_names( get_post_type( $id ), 'file' ) );
	}
	if ( is_array( $value ) && ! empty( $value['url'] ) && empty( $value['ID'] ) ) {
		$value = $value['url'];
	}
	if ( is_string( $value ) && preg_match( '#^https?://#i', $value ) ) {
		return array( 0, esc_url_raw( $value ) );
	}
	$file_id = mcs_attachment_id( $value );
	return array( $file_id, $file_id ? wp_get_attachment_url( $file_id ) : '' );
}

/* ---------------------------------------------------------------------------
 * Resources: each item is a file, an outside link, or a page
 * ------------------------------------------------------------------------ */

/**
 * Is this post type handled as Resources? Set on the Post Types tab, or any unlisted post type with "resource" in its name.
 */
function mcs_is_resource_type( $post_type ) {
	$map = mcs_pt_map();
	if ( isset( $map[ $post_type ] ) ) {
		return 'resource' === $map[ $post_type ]['item_type'];
	}
	return false !== strpos( (string) $post_type, 'resource' );
}

/**
 * Field names to check for a resource's file or outside link: the Post Types tab setting first, then common names.
 */
function mcs_resource_field_names( $post_type, $which ) {
	$map     = mcs_pt_map();
	$setting = isset( $map[ $post_type ][ $which . '_field' ] ) ? $map[ $post_type ][ $which . '_field' ] : '';
	$common  = 'file' === $which
		? array( 'resource_file', 'file', 'download', 'resource_download', 'document', 'resource_document', 'pdf' )
		: array( 'alternate_link', 'external_url', 'resource_url', 'resource_link', 'external_link', 'link', 'url', 'website' );
	$names   = $setting ? array( $setting ) : $common;
	return apply_filters( 'mcs_resource_field_names', $names, $post_type, $which );
}

/**
 * First non-empty raw value among several ACF field names.
 */
function mcs_first_field( $id, $names ) {
	if ( ! function_exists( 'get_field' ) ) {
		return null;
	}
	foreach ( $names as $name ) {
		$value = get_field( $name, $id, false );
		if ( ! empty( $value ) ) {
			return $value;
		}
	}

	// Fields inside an ACF group are saved as "<group>_<field>", e.g. "resource_details_alternate_link".
	$meta = get_post_meta( $id );
	if ( is_array( $meta ) ) {
		foreach ( $names as $name ) {
			foreach ( $meta as $key => $values ) {
				if ( '_' === substr( $key, 0, 1 ) || ! preg_match( '/_' . preg_quote( $name, '/' ) . '$/', $key ) ) {
					continue;
				}
				$value = maybe_unserialize( is_array( $values ) ? reset( $values ) : $values );
				if ( ! empty( $value ) ) {
					return $value;
				}
			}
		}
	}
	return null;
}

/**
 * Where a resource sends people, following the theme's rules:
 *   no content + file/link  → straight to the file or link  (type file|link, url = destination)
 *   content + file/link     → its own page, with the link as a button (type page, button = destination)
 *   content, no link        → its own page (type page)
 * Returns [ 'type' => file|link|page, 'url' => where the list should point ('' = the post), 'button' => linked file/URL ].
 */
function mcs_resource_target( $id ) {
	static $cache = array();
	if ( isset( $cache[ $id ] ) ) {
		return $cache[ $id ];
	}
	$type = get_post_type( $id );

	list( , $destination ) = mcs_document_file( $id );
	$kind                  = $destination ? 'file' : '';

	if ( ! $destination ) {
		$link = mcs_first_field( $id, mcs_resource_field_names( $type, 'link' ) );
		if ( is_array( $link ) ) {
			$link = isset( $link['url'] ) ? $link['url'] : ''; // ACF link field
		}
		if ( is_numeric( $link ) ) {
			$link = (string) wp_get_attachment_url( (int) $link ); // a file picked from the media library
		}
		$link = is_string( $link ) ? esc_url_raw( trim( $link ) ) : '';
		if ( $link ) {
			$destination = $link;
			// A link to an uploaded file or a document counts as a download.
			$path = strtolower( (string) wp_parse_url( $link, PHP_URL_PATH ) );
			$kind = ( false !== strpos( $link, '/wp-content/uploads/' ) || preg_match( '/\.(pdf|docx?|xlsx?|pptx?|csv|txt|zip|rtf)$/', $path ) ) ? 'file' : 'link';
		}
	}

	$has_content = '' !== trim( wp_strip_all_tags( strip_shortcodes( (string) get_post_field( 'post_content', $id ) ) ) );

	if ( $destination && ! $has_content ) {
		$result = array( 'type' => $kind, 'url' => $destination, 'button' => $destination );
	} else {
		$result = array( 'type' => 'page', 'url' => '', 'button' => $destination );
	}

	$cache[ $id ] = $result;
	return $result;
}

/* ---------------------------------------------------------------------------
 * Events
 * ------------------------------------------------------------------------ */

/**
 * ACF field names used by the theme's Events post type. Filter to change them per site.
 */
function mcs_event_fields() {
	return apply_filters(
		'mcs_event_fields',
		array(
			'start_date'         => 'event_start_date',
			'end_date'           => 'event_end_date',
			'start_time'         => 'event_start_time',
			'end_time'           => 'event_end_time',
			'location'           => 'event_location',
			'address'            => 'event_address',
			'registration_link'  => 'registration_link',
			'registration_title' => 'registration_title',
			'registration_type'  => 'registration_type',
		)
	);
}

/**
 * Combine an ACF date and time (raw values) into an ISO 8601 string in the site's timezone.
 * Date only, with no time, returns Y-m-d.
 */
function mcs_event_datetime( $date, $time ) {
	$date = trim( (string) $date );
	if ( '' === $date ) {
		return '';
	}
	$tz = function_exists( 'wp_timezone' ) ? wp_timezone() : new DateTimeZone( 'UTC' );

	// ACF stores date pickers as Ymd and date-time pickers as Y-m-d H:i:s.
	$d = null;
	foreach ( array( 'Ymd', 'Y-m-d', 'Y-m-d H:i:s', 'm/d/Y' ) as $format ) {
		$d = DateTime::createFromFormat( '!' . $format, $date, $tz );
		if ( $d ) {
			break;
		}
	}
	if ( ! $d ) {
		$ts = strtotime( $date );
		if ( ! $ts ) {
			return '';
		}
		$d = new DateTime( '@' . $ts );
		$d->setTimezone( $tz );
	}

	$time = trim( (string) $time );
	if ( '' === $time && strlen( $date ) <= 10 ) {
		return $d->format( 'Y-m-d' );
	}
	// ACF stores time pickers as H:i:s; also accept "7:30 pm".
	if ( '' !== $time && preg_match( '/(\d{1,2}):(\d{2})\s*([ap]\.?m\.?)?/i', $time, $m ) ) {
		$hour = (int) $m[1];
		if ( ! empty( $m[3] ) ) {
			$pm   = 'p' === strtolower( $m[3][0] );
			$hour = ( 12 === $hour ? 0 : $hour ) + ( $pm ? 12 : 0 );
		}
		$d->setTime( $hour, (int) $m[2] );
	}
	return $d->format( 'c' );
}

/**
 * Event location: a Place for in-person events, a VirtualLocation for online ones.
 * Accepts a text field, an ACF Google Map field, or a post object (venue).
 */
function mcs_event_location( $value ) {
	if ( is_array( $value ) && isset( $value['address'] ) ) {
		$text = mcs_text( $value['address'] );
		$name = ! empty( $value['name'] ) ? mcs_text( $value['name'] ) : $text;
	} elseif ( is_object( $value ) || ( is_numeric( $value ) && (int) $value > 0 ) ) {
		$text = mcs_text( get_the_title( mcs_post_id( $value ) ) );
		$name = $text;
	} else {
		$text = mcs_text( is_string( $value ) ? $value : '' );
		$name = $text;
	}

	if ( '' === $text ) {
		return array( null, '' );
	}

	if ( preg_match( '#^https?://#i', $text ) ) {
		return array( array( '@type' => 'VirtualLocation', 'url' => esc_url_raw( $text ) ), 'online' );
	}
	if ( preg_match( '/\b(online|virtual|zoom|webinar|teams meeting)\b/i', $text ) ) {
		return array( array( '@type' => 'VirtualLocation', 'name' => $name ), 'online' );
	}

	// A real address: use it as typed.
	if ( mcs_looks_like_address( $text ) ) {
		return array(
			array(
				'@type'   => 'Place',
				'name'    => $name,
				'address' => $text,
			),
			'offline',
		);
	}

	// The business name: the business's own address.
	if ( mcs_refers_to_business( $text ) ) {
		return array(
			array(
				'@type'   => 'Place',
				'name'    => $name,
				'address' => mcs_address(),
			),
			'offline',
		);
	}

	// Any other place name: keep the name, don't guess an address.
	return array(
		array(
			'@type' => 'Place',
			'name'  => $name,
		),
		'offline',
	);
}

/**
 * Does this text look like a street address? A street number, a ZIP code, or a "City, ST" ending.
 */
function mcs_looks_like_address( $text ) {
	$states = 'AL|AK|AZ|AR|CA|CO|CT|DE|DC|FL|GA|HI|ID|IL|IN|IA|KS|KY|LA|ME|MD|MA|MI|MN|MS|MO|MT|NE|NV|NH|NJ|NM|NY|NC|ND|OH|OK|OR|PA|RI|SC|SD|TN|TX|UT|VT|VA|WA|WV|WI|WY';
	$is     = preg_match( '/\b\d{1,6}\s+[A-Za-z]/', $text )                 // 123 Main St
		|| preg_match( '/\b\d{5}(-\d{4})?\b/', $text )                   // 65240
		|| preg_match( '/[A-Za-z],?\s+(' . $states . ')\.?\s*$/', $text ); // Camdenton, MO
	return (bool) apply_filters( 'mcs_looks_like_address', $is, $text );
}

/**
 * Does this location mean the business's own place? True when it contains the business name or legal name.
 */
function mcs_refers_to_business( $text ) {
	if ( null === mcs_address() ) {
		return false;
	}
	$lower   = strtolower( trim( $text ) );
	$phrases = array( mcs_site_name(), mcs_text( (string) mcs_opt( 'legal_name' ) ) );
	$is = false;
	foreach ( array_filter( array_map( 'trim', $phrases ) ) as $phrase ) {
		if ( false !== strpos( $lower, strtolower( mcs_text( $phrase ) ) ) ) {
			$is = true;
			break;
		}
	}
	return (bool) apply_filters( 'mcs_refers_to_business', $is, $text );
}

function mcs_node_event( $ctx, $desc, $image ) {
	$id     = $ctx['post_id'];
	$url    = $ctx['url'];
	$fields = mcs_event_fields();
	$raw    = function ( $key ) use ( $fields, $id ) {
		return ( ! empty( $fields[ $key ] ) && function_exists( 'get_field' ) ) ? get_field( $fields[ $key ], $id, false ) : '';
	};

	$start = mcs_event_datetime( $raw( 'start_date' ), $raw( 'start_time' ) );
	if ( ! $start ) {
		return null; // Google requires a start date; without one, the page stays a plain WebPage.
	}
	$end_date = $raw( 'end_date' );
	$end      = mcs_event_datetime( $end_date ? $end_date : $raw( 'start_date' ), $raw( 'end_time' ) );
	if ( $end && ! $end_date && ! $raw( 'end_time' ) ) {
		$end = ''; // Same day, no end time: leave it out rather than guess.
	}

	$place_name = ! empty( $fields['location'] ) ? get_field( $fields['location'], $id ) : '';
	$address    = ! empty( $fields['address'] ) ? mcs_text( (string) get_field( $fields['address'], $id, false ) ) : '';

	if ( '' !== $address ) {
		// The Address field decides: a link means online, anything else is the address.
		$name = is_string( $place_name ) ? mcs_text( $place_name ) : '';
		if ( preg_match( '#^https?://#i', $address ) ) {
			$location = array( '@type' => 'VirtualLocation', 'url' => esc_url_raw( $address ), 'name' => $name );
			$mode     = 'online';
		} else {
			$location = array( '@type' => 'Place', 'name' => $name, 'address' => $address );
			$mode     = 'offline';
		}
	} else {
		// No Address field (older events and sites): read the Location field.
		list( $location, $mode ) = mcs_event_location( $place_name );
	}
	if ( ! $location ) {
		// Default to the organization's address.
		$address = mcs_address();
		if ( $address ) {
			$location = array( '@type' => 'Place', 'name' => mcs_site_name(), 'address' => $address );
			$mode     = 'offline';
		}
	}

	// Registration: "link" sends people to another site; "form" means the form is on this page.
	$reg_type = strtolower( mcs_text( (string) $raw( 'registration_type' ) ) );
	$link     = esc_url_raw( (string) $raw( 'registration_link' ) );
	if ( false !== strpos( $reg_type, 'form' ) ) {
		$link = $url;
	}
	$offer = $link ? array(
		'@type' => 'Offer',
		'url'   => $link,
		'name'  => mcs_text( (string) $raw( 'registration_title' ) ),
	) : null;

	$modes = array(
		'online'  => 'https://schema.org/OnlineEventAttendanceMode',
		'offline' => 'https://schema.org/OfflineEventAttendanceMode',
	);

	return array(
		'@type'               => 'Event',
		'@id'                 => mcs_id( 'event', $url ),
		'name'                => $ctx['name'],
		'description'         => $desc,
		'url'                 => $url,
		'image'               => $image,
		'startDate'           => $start,
		'endDate'             => $end,
		'eventStatus'         => 'https://schema.org/EventScheduled',
		'eventAttendanceMode' => isset( $modes[ $mode ] ) ? $modes[ $mode ] : '',
		'location'            => $location,
		'organizer'           => mcs_ref( mcs_id( 'organization' ) ),
		'offers'              => $offer,
		'mainEntityOfPage'    => mcs_ref( mcs_id( 'webpage', $url ) ),
	);
}

/* ---------------------------------------------------------------------------
 * Team blocks
 * ------------------------------------------------------------------------ */

/**
 * Field names to look for inside a team block, per value. First match wins.
 */
function mcs_team_block_fields() {
	return apply_filters(
		'mcs_team_block_fields',
		array(
			'name'      => array( 'name', 'full_name', 'team_name', 'member_name', 'person_name' ),
			'job_title' => array( 'title', 'job_title', 'position', 'team_title', 'member_title' ),
			'photo'     => array( 'headshot', 'photo', 'image', 'picture' ),
			'email'     => array( 'email', 'email_address' ),
			'phone'     => array( 'phone', 'phone_number', 'telephone' ),
			'bio'       => array( 'bio', 'biography', 'description' ),
			'linkedin'  => array( 'linkedin', 'linkedin_url' ),
		)
	);
}

/**
 * Is this block a team block? Uses the name set in Settings → Schema → Team,
 * or any ACF block with "team" in its name.
 */
function mcs_is_team_block( $block_name ) {
	if ( ! $block_name ) {
		return false;
	}
	$names = array_filter( array_map( 'trim', explode( ',', (string) mcs_opt( 'team_block', '' ) ) ) );
	$names = apply_filters( 'mcs_team_block_names', $names );
	if ( $names ) {
		return in_array( $block_name, $names, true );
	}
	return 0 === strpos( $block_name, 'acf/' ) && false !== strpos( $block_name, 'team' );
}

/**
 * Staff rows from team blocks in a post's content (including blocks nested in groups/columns).
 */
function mcs_team_from_blocks( $post_id ) {
	if ( ! function_exists( 'parse_blocks' ) ) {
		return array();
	}
	$content = get_post_field( 'post_content', $post_id );
	if ( ! $content || false === strpos( $content, '<!-- wp:' ) ) {
		return array();
	}

	$rows  = array();
	$stack = parse_blocks( $content );
	while ( $stack ) {
		$block = array_shift( $stack );
		if ( ! empty( $block['innerBlocks'] ) ) {
			$stack = array_merge( $block['innerBlocks'], $stack );
		}
		if ( empty( $block['blockName'] ) || ! mcs_is_team_block( $block['blockName'] ) ) {
			continue;
		}
		$data = isset( $block['attrs']['data'] ) && is_array( $block['attrs']['data'] ) ? $block['attrs']['data'] : array();
		foreach ( mcs_team_rows_from_data( $data ) as $row ) {
			$rows[] = $row;
		}
	}

	return apply_filters( 'mcs_team_from_blocks', $rows, $post_id );
}

/**
 * Turn an ACF block's saved data into staff rows.
 * Handles a single person ( name, title... ) and repeaters ( people_0_name, people_1_name... ).
 */
function mcs_team_rows_from_data( $data ) {
	$fields = mcs_team_block_fields();
	$lookup = array();
	foreach ( $fields as $key => $candidates ) {
		foreach ( $candidates as $candidate ) {
			$lookup[ strtolower( $candidate ) ] = isset( $lookup[ strtolower( $candidate ) ] ) ? $lookup[ strtolower( $candidate ) ] : $key;
		}
	}

	$groups = array();
	foreach ( $data as $field => $value ) {
		if ( '_' === substr( (string) $field, 0, 1 ) || is_array( $value ) ) {
			continue; // "_field" entries are ACF field keys, not values.
		}
		$field = strtolower( (string) $field );
		$group = 'single';
		$name  = $field;
		// Repeater/group sub field: "people_0_name" -> group "people_0", field "name".
		if ( preg_match( '/^(.+_\d+)_(.+)$/', $field, $m ) ) {
			$group = $m[1];
			$name  = $m[2];
		} elseif ( preg_match( '/^(.+)_(' . implode( '|', array_map( 'preg_quote', array_keys( $lookup ) ) ) . ')$/', $field, $m ) && isset( $lookup[ $m[2] ] ) ) {
			// Group field: "member_name" -> field "name".
			$name = $m[2];
		}
		if ( ! isset( $lookup[ $name ] ) ) {
			continue;
		}
		$key = $lookup[ $name ];
		if ( ! isset( $groups[ $group ][ $key ] ) ) {
			$groups[ $group ][ $key ] = $value;
		}
	}

	$rows = array();
	foreach ( $groups as $row ) {
		if ( ! empty( $row['name'] ) ) {
			$rows[] = $row;
		}
	}
	return $rows;
}
