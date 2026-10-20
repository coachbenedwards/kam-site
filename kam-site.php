<?php
/**
 * Plugin Name: KAM Servicing — site
 * Plugin URI:  https://github.com/coachbenedwards/kam-site
 * Description: Everything built for kamservicing.com on WordPress: the blocks, the command channel that edits them, the pages, and the updater. The theme is the captured look and is read-only; this plugin is where the work goes.
 * Version:     0.1.0
 * Author:      PBA
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

/*
 * THREE PLACES CARRY THE VERSION AND ALL THREE MUST MATCH:
 *   1. the  * Version:  line in the header above (what WordPress shows);
 *   2. this constant (busts the cache on the inline CSS and JS);
 *   3. plugin.json in the repository root (the ONLY one the updater compares).
 * Bumping the header alone ships nothing. Learned the hard way on eastwood-fwp.
 */
const KAM_SITE_VERSION = '0.1.0';
const KAM_SITE_REPO    = 'coachbenedwards/kam-site';
const KAM_SITE_BRANCH  = 'main';

/*
 * Parts loader. Anything in parts/ is a small file that loads here, so a new
 * block is a new file and nothing else moves. Page templates are not parts.
 */
foreach ( (array) glob( __DIR__ . '/parts/*.php' ) as $kam_part ) {
	if ( 0 === strpos( basename( $kam_part ), 'template-' ) ) {
		continue;
	}
	$kam_head = (string) file_get_contents( $kam_part, false, null, 0, 1024 );
	if ( false !== stripos( $kam_head, 'Template Name:' ) ) {
		continue;
	}
	require_once $kam_part;
}

/* ------------------------------------------------------------------
 * One named test for "is this block on this page".
 *
 * Elementor keeps a page's words in _elementor_data, not post_content, so
 * has_shortcode() alone misses every converted page and the block renders
 * unstyled. Every gate in this plugin calls this and nothing else.
 * ------------------------------------------------------------------ */
function kam_uses_shortcode( $post, $tag ) {
	if ( ! $post ) {
		return false;
	}
	if ( has_shortcode( (string) $post->post_content, $tag ) ) {
		return true;
	}
	$data = get_post_meta( $post->ID, '_elementor_data', true );
	return is_string( $data ) && false !== strpos( $data, '[' . $tag );
}

/** Pipe/newline-delimited lines inside a block's body, cleaned. */
function kam_lines( $content ) {
	$content = str_ireplace( array( '<br>', '<br/>', '<br />', '</p>', '</div>' ), "\n", (string) $content );
	$content = wp_strip_all_tags( $content );
	$content = html_entity_decode( $content, ENT_QUOTES, 'UTF-8' );
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n|\|/', $content ) as $line ) {
		$line = trim( $line );
		if ( '' !== $line ) {
			$out[] = $line;
		}
	}
	return $out;
}

/* ------------------------------------------------------------------
 * Updater — reads plugin.json from the public repo (raw.githubusercontent
 * is reachable from the host; api.github.com is not). Same mechanism as
 * eastwood-fwp, same traps: core may run the check in a cron loopback with
 * no query string, and a cached version that merely equals the installed
 * one cannot be trusted, so it is re-read.
 * ------------------------------------------------------------------ */
function kam_site_remote_info() {
	$forced  = isset( $_GET['force-check'] );
	$cached  = get_transient( 'kam_site_remote' );
	$suspect = is_array( $cached ) && ! empty( $cached['version'] )
		&& version_compare( $cached['version'], KAM_SITE_VERSION, '<=' );
	if ( ! $forced && ! $suspect && false !== $cached ) {
		return $cached;
	}
	$url = 'https://raw.githubusercontent.com/' . KAM_SITE_REPO . '/' . KAM_SITE_BRANCH . '/plugin.json';
	$res = wp_remote_get( $url, array( 'timeout' => 15, 'headers' => array( 'Accept' => 'application/json' ) ) );
	$info = array();
	if ( ! is_wp_error( $res ) && 200 === (int) wp_remote_retrieve_response_code( $res ) ) {
		$decoded = json_decode( wp_remote_retrieve_body( $res ), true );
		if ( is_array( $decoded ) && ! empty( $decoded['version'] ) ) {
			$info = $decoded;
		}
	}
	set_transient( 'kam_site_remote', $info, empty( $info ) ? 5 * MINUTE_IN_SECONDS : HOUR_IN_SECONDS );
	return $info;
}

add_action( 'delete_site_transient_update_plugins', function () {
	delete_transient( 'kam_site_remote' );
} );
add_action( 'upgrader_process_complete', function ( $upgrader, $extra ) {
	if ( isset( $extra['type'] ) && 'plugin' === $extra['type'] ) {
		delete_transient( 'kam_site_remote' );
	}
}, 10, 2 );

add_filter( 'pre_set_site_transient_update_plugins', function ( $transient ) {
	if ( ! is_object( $transient ) ) {
		$transient = new stdClass();
	}
	$info = kam_site_remote_info();
	if ( empty( $info['version'] ) ) {
		return $transient;
	}
	if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
		$transient->response = array();
	}
	if ( version_compare( $info['version'], KAM_SITE_VERSION, '>' ) ) {
		$transient->response[ plugin_basename( __FILE__ ) ] = (object) array(
			'slug'        => 'kam-site',
			'plugin'      => plugin_basename( __FILE__ ),
			'new_version' => $info['version'],
			'url'         => 'https://github.com/' . KAM_SITE_REPO,
			'package'     => 'https://github.com/' . KAM_SITE_REPO . '/archive/refs/heads/' . KAM_SITE_BRANCH . '.zip',
		);
	}
	return $transient;
} );

/* A branch zip extracts to kam-site-main/; rename it so WordPress updates this plugin rather than installing a second one. */
add_filter( 'upgrader_source_selection', function ( $source, $remote_source, $upgrader, $args = array() ) {
	if ( false === strpos( basename( untrailingslashit( $source ) ), 'kam-site-' ) ) {
		return $source;
	}
	$fixed = trailingslashit( $remote_source ) . 'kam-site/';
	if ( trailingslashit( $source ) === $fixed ) {
		return $source;
	}
	global $wp_filesystem;
	if ( $wp_filesystem && $wp_filesystem->move( $source, $fixed, true ) ) {
		return $fixed;
	}
	return $source;
}, 10, 4 );

/* Update-channel status, so a missing update is a measurement not a guess. */
add_action( 'rest_api_init', function () {
	register_rest_route( 'kam/v1', '/update-status', array(
		'methods'             => 'GET',
		'permission_callback' => function () { return current_user_can( 'update_plugins' ); },
		'callback'            => function () {
			$url = 'https://raw.githubusercontent.com/' . KAM_SITE_REPO . '/' . KAM_SITE_BRANCH . '/plugin.json';
			$res = wp_remote_get( $url, array( 'timeout' => 15 ) );
			$fetch = is_wp_error( $res )
				? array( 'ok' => false, 'error' => $res->get_error_message() )
				: array( 'ok' => true, 'http' => (int) wp_remote_retrieve_response_code( $res ), 'body' => substr( wp_remote_retrieve_body( $res ), 0, 300 ) );
			$site = get_site_transient( 'update_plugins' );
			$me   = plugin_basename( __FILE__ );
			return array(
				'installed_version' => KAM_SITE_VERSION,
				'plugin_basename'   => $me,
				'live_fetch'        => $fetch,
				'cached_remote'     => get_transient( 'kam_site_remote' ),
				'offered_update'    => isset( $site->response[ $me ] ) ? $site->response[ $me ] : null,
			);
		},
	) );
} );

/* ------------------------------------------------------------------
 * Pages this plugin owns. Created once if the slug is free; never renamed,
 * never overwritten once a person has touched one. Content is the initial
 * seed only -- the words live on the page as widgets, laid out through
 * the command channel (parts/blocks-api.php).
 * ------------------------------------------------------------------ */
const KAM_PAGES_V = '2';

function kam_owned_pages() {
	$pages = array(
		'contact'            => 'Contact',
		'careers'            => 'Careers',
		'services'           => 'All Services',
		'payment-assist'     => 'Payment Assist',
		'abs-repairs'        => 'ABS Repairs',
		'exhausts'           => 'Exhausts',
		'airbag-faults'      => 'Airbag Faults',
		'brakes'             => 'Brake Repairs',
		'diagnostics'        => 'Diagnostics',
		'clutch-replacement' => 'Clutch Replacement',
		'privacy-policy'     => 'Privacy Policy',
		'terms'              => 'Terms & Conditions',
	);
	foreach ( kam_branches() as $slug => $b ) {
		$pages[ $slug ] = $b['name'];
	}
	return $pages;
}

/**
 * The sixteen centres. Slug -> name as the site writes it in its footer,
 * plus the old .php address so links in captured markup can be rewritten.
 */
function kam_branches() {
	return array(
		'nottingham'              => array( 'name' => 'Nottingham - Sherwood',    'old' => 'kam-servicing-nottingham.php' ),
		'bulwell'                 => array( 'name' => 'Nottingham - Bulwell',     'old' => 'kam-servicing-bulwell.php' ),
		'nuthall'                 => array( 'name' => 'Nottingham - Nuthall',     'old' => 'kam-servicing-nuthall.php' ),
		'colwick'                 => array( 'name' => 'Nottingham - Colwick',     'old' => 'kam-servicing-colwick.php' ),
		'hucknall'                => array( 'name' => 'Nottingham - Hucknall',    'old' => 'kam-servicing-hucknall.php' ),
		'beeston'                 => array( 'name' => 'Beeston',                  'old' => 'kam-servicing-beeston.php' ),
		'sutton-in-ashfield'      => array( 'name' => 'Sutton-in-Ashfield',       'old' => 'kam-servicing-sutton.php' ),
		'stapleford'              => array( 'name' => 'Stapleford',               'old' => 'kam-servicing-stapleford.php' ),
		'derby'                   => array( 'name' => 'Derby - City',             'old' => 'kam-servicing-derby.php' ),
		'derby-ascot-drive'       => array( 'name' => 'Derby - Ascot Drive',      'old' => 'kam-servicing-ascot.php' ),
		'derby-sinfin'            => array( 'name' => 'Derby - Sinfin',           'old' => 'kam-servicing-sinfin.php' ),
		'long-eaton-derby-road'   => array( 'name' => 'Long Eaton - Derby Rd',    'old' => 'kam-servicing-longeaton.php' ),
		'long-eaton-tamworth-road'=> array( 'name' => 'Long Eaton - Tamworth Rd', 'old' => 'kam-servicing-sawley.php' ),
		'heanor'                  => array( 'name' => 'Heanor',                   'old' => 'kam-servicing-heanor.php' ),
		'belper'                  => array( 'name' => 'Belper',                   'old' => 'kam-servicing-belper.php' ),
		'ambergate'               => array( 'name' => 'Ambergate',                'old' => 'kam-servicing-ambergate.php' ),
	);
}

/** Old .php address -> new path. Used by the theme to fix captured links. */
function kam_moved_paths() {
	$map = array(
		'index.php'                      => '/',
		'kam-servicing.php'              => '/contact/',
		'careers_at_kam_servicing.php'   => '/careers/',
		'all-kam-services.php'           => '/services/',
		'payment-assist.php'             => '/payment-assist/',
		'abs-repairs.php'                => '/abs-repairs/',
		'car-exhaust-prices.php'         => '/exhausts/',
		'airbag-fault-repair.php'        => '/airbag-faults/',
		'car-brakes.php'                 => '/brakes/',
		'car-diagnostics.php'            => '/diagnostics/',
		'clutch-replacement.php'         => '/clutch-replacement/',
		'gdpr-privacy-policy.php'        => '/privacy-policy/',
		'terms-conditions.php'           => '/terms/',
		// Booking, tyres and the cart stay on KAM's own system until replaced.
		'bookings.php'                   => 'https://kamservicing.com/bookings.php',
		'tyres.php'                      => 'https://kamservicing.com/tyres.php',
		'blank.php'                      => 'https://kamservicing.com/blank.php',
	);
	foreach ( kam_branches() as $slug => $b ) {
		$map[ $b['old'] ] = '/' . $slug . '/';
	}
	return $map;
}

function kam_install_pages() {
	$state = (array) get_option( 'kam_owned_pages', array() );
	foreach ( kam_owned_pages() as $slug => $title ) {
		$existing = get_page_by_path( $slug );
		if ( $existing ) {
			$state[ $slug ] = array( 'id' => $existing->ID );
			continue;
		}
		$id = wp_insert_post( array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $slug,
			'post_title'   => $title,
			'post_content' => '',
		) );
		if ( $id && ! is_wp_error( $id ) ) {
			$state[ $slug ] = array( 'id' => $id );
		}
	}
	update_option( 'kam_owned_pages', $state );

	/*
	 * Elementor's default colour and font schemes paint every heading widget
	 * light blue and every heading Roboto. KAM's look comes from its own
	 * stylesheet, so the schemes are switched off (Elementor > Settings has
	 * the same two boxes).
	 */
	update_option( 'elementor_disable_color_schemes', 'yes' );
	update_option( 'elementor_disable_typography_schemes', 'yes' );
}

add_action( 'init', function () {
	if ( get_option( 'kam_pages_v' ) === KAM_PAGES_V . '-' . KAM_SITE_VERSION ) {
		return;
	}
	kam_install_pages();
	update_option( 'kam_pages_v', KAM_PAGES_V . '-' . KAM_SITE_VERSION );
}, 30 );
