<?php
/**
 * KAM theme. The captured look and nothing else. Treated as read-only;
 * anything built for the site lives in the kam-site plugin.
 */
defined( 'ABSPATH' ) || exit;

const KAM_THEME_VERSION = '0.1.0';

// The captured copy has straight quotes; WordPress must not curl them.
add_filter( 'run_wptexturize', '__return_false' );

add_action( 'after_setup_theme', function () {
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'html5', array( 'search-form', 'script', 'style' ) );
	remove_theme_support( 'widgets-block-editor' );
} );

add_action( 'wp_enqueue_scripts', function () {
	// Same libraries, same versions, same order as kamservicing.com.
	wp_enqueue_style( 'bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css', array(), '5.3.8' );
	// Not 'font-awesome': Elementor registers that handle for its own copy.
	wp_enqueue_style( 'kam-font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css', array(), '6.5.1' );
	wp_enqueue_style( 'kam-fonts-open-sans', 'https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap', array(), null );
	wp_enqueue_style( 'kam-fonts-montserrat', 'https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap', array(), null );
	wp_enqueue_style( 'kam', get_template_directory_uri() . '/kam.css', array( 'bootstrap' ), KAM_THEME_VERSION );
	wp_enqueue_script( 'bootstrap', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js', array(), '5.3.8', true );
} );

/*
 * The captured markup links to .php addresses. Every one is rewritten to
 * its new path on the way out, in one place, from one list in the plugin.
 */
add_action( 'template_redirect', function () {
	if ( is_admin() || is_feed() || is_embed() ) {
		return;
	}
	ob_start( 'kam_theme_rewrite' );
}, 1 );

function kam_theme_rewrite( $html ) {
	if ( ! function_exists( 'kam_moved_paths' ) ) {
		return $html;
	}
	foreach ( kam_moved_paths() as $old => $new ) {
		$html = str_replace(
			array( 'https://kamservicing.com/' . $old, 'href="/' . $old, "href='/" . $old, 'href="' . $old ),
			array( $new, 'href="' . $new, "href='" . $new, 'href="' . $new ),
			$html
		);
	}
	return $html;
}

/** Which nav item is current, by the page's slug. */
function kam_nav_active( $slug ) {
	if ( 'home' === $slug ) {
		return is_front_page() ? ' active' : '';
	}
	return is_page( $slug ) ? ' active' : '';
}
