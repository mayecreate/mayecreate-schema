<?php
/**
 * Small helpers shared across the plugin.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Organization types offered on the settings page.
 * Keys are schema.org types. 'local' marks LocalBusiness subtypes (which may carry opening hours).
 */
function mcs_org_types() {
	$org   = 'Organizations (no storefront or walk-in customers)';
	$local = 'Local businesses (office or location with a Google Business Profile)';
	$other = 'Not listed';
	return array(
		'Organization'                => array( 'label' => 'Organization (B2B company, manufacturer, distributor, association)', 'local' => false, 'group' => $org ),
		'NGO'                         => array( 'label' => 'Nonprofit', 'local' => false, 'group' => $org ),
		'EducationalOrganization'     => array( 'label' => 'School or educational organization', 'local' => false, 'group' => $org ),
		'CollegeOrUniversity'         => array( 'label' => 'College or university', 'local' => false, 'group' => $org ),
		'GovernmentOrganization'      => array( 'label' => 'Government (city, county, agency)', 'local' => false, 'group' => $org ),
		'NewsMediaOrganization'       => array( 'label' => 'News / media publisher', 'local' => false, 'group' => $org ),
		'LocalBusiness'               => array( 'label' => 'Local business (general, e.g. agencies, consultants)', 'local' => true, 'group' => $local ),
		'GeneralContractor'           => array( 'label' => 'General contractor / builder', 'local' => true, 'group' => $local ),
		'HomeAndConstructionBusiness' => array( 'label' => 'Construction / home services business', 'local' => true, 'group' => $local ),
		'Electrician'                 => array( 'label' => 'Electrician', 'local' => true, 'group' => $local ),
		'Plumber'                     => array( 'label' => 'Plumber', 'local' => true, 'group' => $local ),
		'HVACBusiness'                => array( 'label' => 'HVAC', 'local' => true, 'group' => $local ),
		'RoofingContractor'           => array( 'label' => 'Roofing contractor', 'local' => true, 'group' => $local ),
		'HousePainter'                => array( 'label' => 'Painter', 'local' => true, 'group' => $local ),
		'MovingCompany'               => array( 'label' => 'Moving company', 'local' => true, 'group' => $local ),
		'LegalService'                => array( 'label' => 'Law firm / legal service', 'local' => true, 'group' => $local ),
		'AccountingService'           => array( 'label' => 'Accounting', 'local' => true, 'group' => $local ),
		'InsuranceAgency'             => array( 'label' => 'Insurance agency', 'local' => true, 'group' => $local ),
		'FinancialService'            => array( 'label' => 'Financial service / bank', 'local' => true, 'group' => $local ),
		'RealEstateAgent'             => array( 'label' => 'Real estate', 'local' => true, 'group' => $local ),
		'AutoRepair'                  => array( 'label' => 'Auto repair', 'local' => true, 'group' => $local ),
		'VeterinaryCare'              => array( 'label' => 'Veterinary clinic', 'local' => true, 'group' => $local ),
		'Restaurant'                  => array( 'label' => 'Restaurant', 'local' => true, 'group' => $local ),
		'Store'                       => array( 'label' => 'Store / retail', 'local' => true, 'group' => $local ),
		'custom'                      => array( 'label' => 'Other local business (type it in below)', 'local' => true, 'group' => $other ),
	);
}

/**
 * Read a site-wide setting from the Schema settings page.
 *
 * Tyler: if a site already stores this data in its own ACF fields, map it with the
 * `mcs_option` filter instead of re-entering it. See README.
 */
function mcs_opt( $key, $default = '' ) {
	$value = function_exists( 'get_field' ) ? get_field( 'mcs_' . $key, 'option' ) : null;
	$value = apply_filters( 'mcs_option', $value, $key );
	if ( null === $value || '' === $value || false === $value || array() === $value ) {
		return $default;
	}
	return $value;
}

/**
 * Read a per-page schema field.
 */
function mcs_meta( $post_id, $key, $default = '' ) {
	$value = function_exists( 'get_field' ) ? get_field( 'mcs_' . $key, $post_id ) : null;
	if ( null === $value || '' === $value || false === $value || array() === $value ) {
		return $default;
	}
	return $value;
}

/**
 * Plain text: decode entities, strip shortcodes and tags, collapse whitespace.
 */
function mcs_text( $value ) {
	if ( ! is_string( $value ) ) {
		return '';
	}
	$value = strip_shortcodes( $value );
	$value = wp_strip_all_tags( html_entity_decode( $value, ENT_QUOTES, 'UTF-8' ) );
	return trim( preg_replace( '/\s+/u', ' ', $value ) );
}

/**
 * Split a textarea into trimmed, non-empty lines.
 */
function mcs_lines( $value ) {
	if ( ! is_string( $value ) ) {
		return array();
	}
	return array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $value ) ) ) );
}

/**
 * Build a consistent @id. Every ID is "<url>#<fragment>", with the home URL always ending in "/".
 */
function mcs_id( $fragment, $url = null ) {
	if ( null === $url ) {
		$url = home_url( '/' );
	}
	return $url . '#' . $fragment;
}

/**
 * A reference to another node: { "@id": "..." }.
 */
function mcs_ref( $id ) {
	return array( '@id' => $id );
}

/**
 * Remove empty values recursively so the output never has "description": "" or "sameAs": [].
 */
function mcs_clean( $data ) {
	if ( ! is_array( $data ) ) {
		return $data;
	}
	$is_list = array() !== $data && array_keys( $data ) === range( 0, count( $data ) - 1 );
	foreach ( $data as $key => $value ) {
		if ( is_array( $value ) ) {
			$value        = mcs_clean( $value );
			$data[ $key ] = $value;
		}
		if ( null === $value || '' === $value || array() === $value || false === $value ) {
			unset( $data[ $key ] );
		}
	}
	// Re-index plain lists so they still encode as JSON arrays, not objects.
	return $is_list ? array_values( $data ) : $data;
}

/**
 * ImageObject for an attachment ID.
 */
function mcs_image( $attachment_id, $id = '' ) {
	$attachment_id = (int) $attachment_id;
	if ( ! $attachment_id ) {
		return null;
	}
	$src = wp_get_attachment_image_src( $attachment_id, 'full' );
	if ( ! $src ) {
		return null;
	}
	return array(
		'@type'      => 'ImageObject',
		'@id'        => $id,
		'url'        => $src[0],
		'contentUrl' => $src[0],
		'width'      => isset( $src[1] ) ? (int) $src[1] : null,
		'height'     => isset( $src[2] ) ? (int) $src[2] : null,
	);
}

/**
 * Normalize an ACF image/file value (ID, array or URL) to an attachment ID.
 */
function mcs_attachment_id( $value ) {
	if ( is_array( $value ) && isset( $value['ID'] ) ) {
		return (int) $value['ID'];
	}
	if ( is_array( $value ) && isset( $value['id'] ) ) {
		return (int) $value['id'];
	}
	if ( is_numeric( $value ) ) {
		return (int) $value;
	}
	return 0;
}

/**
 * Normalize an ACF post object value (ID, WP_Post or array) to a post ID.
 */
function mcs_post_id( $value ) {
	if ( is_object( $value ) && isset( $value->ID ) ) {
		return (int) $value->ID;
	}
	if ( is_array( $value ) ) {
		$first = reset( $value );
		return $first ? mcs_post_id( $first ) : 0;
	}
	return (int) $value;
}

/**
 * The site name used everywhere: the Schema setting, or the WordPress Site Title.
 */
function mcs_site_name() {
	return mcs_text( mcs_opt( 'name', get_bloginfo( 'name' ) ) );
}

/**
 * WordPress tagline, unless it's still the default.
 */
function mcs_tagline() {
	$tagline = mcs_text( get_bloginfo( 'description' ) );
	if ( '' === $tagline || 'Just another WordPress site' === $tagline ) {
		return '';
	}
	return $tagline;
}

/**
 * Is this a placeholder image (file name contains "default" or "placeholder")? Placeholders are left out.
 */
function mcs_is_placeholder( $attachment_id ) {
	$attachment_id = (int) $attachment_id;
	if ( ! $attachment_id ) {
		return false;
	}
	$file = (string) wp_get_attachment_url( $attachment_id );
	$name = strtolower( basename( (string) wp_parse_url( $file, PHP_URL_PATH ) ) );
	$is   = '' !== $name && ( false !== strpos( $name, 'default' ) || false !== strpos( $name, 'placeholder' ) );
	return (bool) apply_filters( 'mcs_is_placeholder', $is, $attachment_id );
}

/**
 * Featured image ID, unless it's a placeholder.
 */
function mcs_thumbnail_id( $post_id ) {
	$id = (int) get_post_thumbnail_id( $post_id );
	return mcs_is_placeholder( $id ) ? 0 : $id;
}
