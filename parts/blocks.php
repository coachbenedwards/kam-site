<?php
/**
 * The KAM blocks: the pieces of a page that are more than a heading or a
 * paragraph. Each is a shortcode whose every visible word is an attribute,
 * so the command channel (blocks-api.php) can read and change it, and a
 * person can edit it in the Elementor shortcode widget.
 *
 * Markup and class names are the ones kamservicing.com uses, so the theme's
 * captured stylesheet styles them without a line of new CSS.
 */
defined( 'ABSPATH' ) || exit;

/**
 * The four booking tiles across the top of every branch page.
 *
 * Lines, one per tile: image | title | price | link. The first tile's title
 * is the page's H1 (the live site does the same); the rest are H2s.
 * Attributes: h1 (default 1 -- which tile carries the H1; 0 for none).
 */
add_shortcode( 'kam_tiles', function ( $atts, $content = '' ) {
	$a = shortcode_atts( array( 'lines' => '', 'h1' => '1' ), $atts, 'kam_tiles' );
	// One tile per line; the pipe separates the fields within a line.
	$lines = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) ( $a['lines'] ?: $content ) ) ) );
	if ( ! $lines ) {
		return '';
	}
	$h1 = (int) $a['h1'];
	$out = '<div class="booking-summary-font jumbotron jumbotron-fluid"><div class="container text-center p-0"><div class="row">';
	$n = 0;
	foreach ( $lines as $line ) {
		$n++;
		$p = array_map( 'trim', explode( '|', $line ) ) + array( '', '', '', '' );
		list( $img, $title, $price, $link ) = $p;
		$tag = ( $n === $h1 ) ? 'h1' : 'h2';
		$out .= '<div class="col-12 col-md-3 branch-col-lg-3">';
		if ( $img ) {
			$out .= '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( $title ) . '" class="img-fluid mb-2">';
		}
		$out .= '<a href="' . esc_url( $link ) . '" class="text-dark"><' . $tag . ' class="branch-' . $tag . '">' . esc_html( $title ) . '</' . $tag . '></a>';
		if ( $price ) {
			$out .= '<small>' . esc_html( $price ) . '</small>';
		}
		$out .= '</div>';
	}
	return $out . '</div></div></div>';
} );

/**
 * The pair of buttons under a section: yellow "Book an Appointment" and
 * blue "Call 0115 ...". Either may be left out by leaving its text empty.
 */
add_shortcode( 'kam_buttons', function ( $atts ) {
	$a = shortcode_atts( array(
		'book'     => 'Book an Appointment',
		'book_url' => '',
		'call'     => '',
		'call_url' => '',
	), $atts, 'kam_buttons' );
	$out = '';
	if ( '' !== $a['book'] && '' !== $a['book_url'] ) {
		$out .= '<a class="secondary-btn-link text-white mb-3" href="' . esc_url( $a['book_url'] ) . '">' . esc_html( $a['book'] ) . '</a> ';
	}
	if ( '' !== $a['call'] && '' !== $a['call_url'] ) {
		$out .= '<a class="primary-btn-link text-white" href="' . esc_url( $a['call_url'] ) . '">' . esc_html( $a['call'] ) . '</a>';
	}
	return $out ? '<p class="kam-buttons">' . $out . '</p>' : '';
} );

/**
 * A branch's details card: address, phone, email, manager, opening hours.
 * address and hours are lines (pipe-separated).
 */
add_shortcode( 'kam_branch', function ( $atts ) {
	$a = shortcode_atts( array(
		'title'   => '',
		'address' => '',
		'phone'   => '',
		'email'   => '',
		'manager' => '',
		'hours'   => '',
	), $atts, 'kam_branch' );
	$out = '';
	if ( $a['title'] ) {
		$out .= '<h5 class="mb-3"><i class="fas fa-cog i-class-color"></i> ' . esc_html( $a['title'] ) . '</h5><hr>';
	}
	$addr = kam_lines( $a['address'] );
	if ( $addr ) {
		$out .= '<p class="font-16"><i class="fas fa-map-marker-alt i-class-color"></i> '
			. implode( '<br>', array_map( 'esc_html', $addr ) ) . '</p>';
	}
	$bits = array();
	if ( $a['phone'] ) {
		$tel = preg_replace( '/[^0-9+]/', '', $a['phone'] );
		$bits[] = '<a href="tel:' . esc_attr( $tel ) . '"><i class="fas fa-phone i-class-color"></i> ' . esc_html( $a['phone'] ) . '</a>';
	}
	if ( $a['email'] ) {
		$bits[] = '<i class="fas fa-envelope i-class-color"></i> ' . esc_html( $a['email'] );
	}
	if ( $a['manager'] ) {
		$bits[] = '<i class="fas fa-user i-class-color"></i> Manager: ' . esc_html( $a['manager'] );
	}
	if ( $bits ) {
		$out .= '<p class="font-16">' . implode( '<br>', $bits ) . '</p>';
	}
	$hours = kam_lines( $a['hours'] );
	if ( $hours ) {
		$out .= '<p class="font-16"><i class="fas fa-clock i-class-color"></i> '
			. implode( '<br>', array_map( 'esc_html', $hours ) ) . '</p>';
	}
	return $out;
} );

/**
 * The branch map. The live site draws it with the Google Maps script and a
 * key tied to kamservicing.com, which this domain cannot use; the embed
 * needs no key and shows the same pin at the same zoom.
 */
add_shortcode( 'kam_map', function ( $atts ) {
	$a = shortcode_atts( array( 'lat' => '', 'lng' => '', 'zoom' => '16', 'title' => 'Map', 'height' => '250' ), $atts, 'kam_map' );
	if ( '' === $a['lat'] || '' === $a['lng'] ) {
		return '';
	}
	$src = 'https://maps.google.com/maps?q=' . rawurlencode( $a['lat'] . ',' . $a['lng'] )
		. '&z=' . (int) $a['zoom'] . '&output=embed';
	return '<div id="map_canvas" style="width:100%;height:' . (int) $a['height'] . 'px;">'
		. '<iframe title="' . esc_attr( $a['title'] ) . '" src="' . esc_url( $src ) . '" width="100%" height="' . (int) $a['height'] . '" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
} );

/**
 * The branch contact form. Same fields as the live site's. It posts to this
 * WordPress (REST kam/v1/contact) which emails the branch; the live site's
 * handler is a PHP script on their host that this domain cannot call.
 *
 * depot: the live site's short code (e.g. aaaBul); depot_name: the branch
 * name on the email; to: where the message goes.
 */
add_shortcode( 'kam_contact_form', function ( $atts ) {
	$a = shortcode_atts( array( 'depot' => '', 'depot_name' => '', 'to' => '', 'intro' => 'Email us using the form below.' ), $atts, 'kam_contact_form' );
	$out = '';
	if ( $a['intro'] ) {
		$out .= '<p class="font-16">' . esc_html( $a['intro'] ) . '</p>';
	}
	$out .= '<form class="ajax-contact-form kam-contact-form" method="post" action="' . esc_url( rest_url( 'kam/v1/contact' ) ) . '">'
		. '<input type="hidden" name="depot" value="' . esc_attr( $a['depot'] ) . '">'
		. '<input type="hidden" name="full_depot_name" value="' . esc_attr( $a['depot_name'] ) . '">'
		. '<input type="hidden" name="to" value="' . esc_attr( $a['to'] ) . '">'
		. '<input type="text" name="website" value="" style="display:none" tabindex="-1" autocomplete="off">'
		. '<div class="row">'
		. '<div class="col-md-6 mb-3"><input type="text" class="form-control" name="name" placeholder="Name"></div>'
		. '<div class="col-md-6 mb-3"><input type="email" class="form-control" name="email" placeholder="Email"></div>'
		. '<div class="col-md-6 mb-3"><input type="text" class="form-control" name="phone" placeholder="Telephone"></div>'
		. '<div class="col-md-6 mb-3"><input type="text" class="form-control" name="reg" placeholder="Reg"></div>'
		. '<div class="col-md-12 mb-3"><textarea class="form-control" rows="3" name="address" placeholder="Address"></textarea></div>'
		. '<div class="col-12 mb-3"><textarea class="form-control" name="message" rows="4" placeholder="Message"></textarea></div>'
		. '<div class="col-12"><button type="submit" class="btn btn-primary">Send message</button>'
		. ' <span class="kam-contact-status font-16 ms-2" role="status"></span></div>'
		. '</div></form>';
	return $out;
} );

add_action( 'rest_api_init', function () {
	register_rest_route( 'kam/v1', '/contact', array(
		'methods'             => 'POST',
		'permission_callback' => '__return_true',
		'callback'            => function ( $req ) {
			if ( '' !== (string) $req->get_param( 'website' ) ) {
				return array( 'success' => true, 'message' => 'Thank you, your message has been sent.' );
			}
			$fields = array( 'name' => 'Name', 'email' => 'Email', 'phone' => 'Telephone', 'reg' => 'Reg', 'address' => 'Address', 'message' => 'Message' );
			$lines  = array();
			foreach ( $fields as $k => $label ) {
				$lines[] = $label . ': ' . sanitize_textarea_field( (string) $req->get_param( $k ) );
			}
			$email = sanitize_email( (string) $req->get_param( 'email' ) );
			if ( ! $email || '' === trim( (string) $req->get_param( 'message' ) ) ) {
				return new WP_Error( 'kam_contact', 'Please give an email address and a message.', array( 'status' => 400 ) );
			}
			$to = sanitize_email( (string) $req->get_param( 'to' ) );
			if ( ! $to ) {
				$to = get_option( 'admin_email' );
			}
			$depot   = sanitize_text_field( (string) $req->get_param( 'full_depot_name' ) );
			$subject = 'Website enquiry' . ( $depot ? ' - ' . $depot : '' );
			$sent    = wp_mail( $to, $subject, implode( "\n", $lines ), array( 'Reply-To: ' . $email ) );
			if ( ! $sent ) {
				return new WP_Error( 'kam_mail', 'Something went wrong sending your message. Please call us.', array( 'status' => 500 ) );
			}
			return array( 'success' => true, 'message' => 'Thank you, your message has been sent.' );
		},
	) );
} );

/**
 * The few lines of CSS that sit between Elementor's containers and KAM's
 * stylesheet, printed only on a page that has a channel-written row.
 *
 * - kam-blockrow: no gap of Elementor's own between widgets, no max-width.
 * - container: Bootstrap's .container widths, which Elementor's own
 *   container rules would otherwise override.
 * - headings: Elementor zeroes a heading's margin and line-height; KAM's
 *   h5s have Bootstrap's .5rem under them and line-height 1.3.
 */
function kam_blockrow_css() {
	return '.e-con.kam-blockrow,.e-con.kam-blockrow>.e-con-inner{gap:0;--gap:0px;--row-gap:0px;--column-gap:0px;row-gap:0;column-gap:0;max-width:none}'
		. '.e-con.kam-blockrow.container{width:100%;margin-left:auto;margin-right:auto;max-width:var(--kam-container,100%)}'
		. '@media(min-width:576px){.e-con.kam-blockrow.container{--kam-container:540px}}'
		. '@media(min-width:768px){.e-con.kam-blockrow.container{--kam-container:720px}}'
		. '@media(min-width:992px){.e-con.kam-blockrow.container{--kam-container:960px}}'
		. '@media(min-width:1200px){.e-con.kam-blockrow.container{--kam-container:1140px}}'
		/*
		 * On the live site a heading's 8px and the hr's 16px collapse into one
		 * 16px gap. Here they sit in separate widgets (flex items), where
		 * margins never collapse -- so the heading gives up its margin and
		 * the hr, which Elementor resets to 0, gets Bootstrap's back.
		 */
		. '.kam-blockrow .elementor-heading-title{margin-bottom:0;line-height:1.3}'
		. '.kam-blockrow hr{margin:1rem 0}'
		. '.kam-blockrow .elementor-widget-text-editor p:last-child{margin-bottom:1rem}'
		. '.kam-buttons{margin-bottom:0}';
}

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular() ) {
		return;
	}
	$post = get_post();
	if ( ! $post || ! kam_post_contains( $post, 'kam-blockrow' ) ) {
		return;
	}
	wp_register_style( 'kam-blockrow', false, array(), KAM_SITE_VERSION );
	wp_enqueue_style( 'kam-blockrow' );
	wp_add_inline_style( 'kam-blockrow', kam_blockrow_css() );

	if ( kam_uses_shortcode( $post, 'kam_contact_form' ) ) {
		wp_register_script( 'kam-contact', false, array(), KAM_SITE_VERSION, true );
		wp_enqueue_script( 'kam-contact' );
		wp_add_inline_script( 'kam-contact', kam_contact_js() );
	}
}, 22 );

function kam_post_contains( $post, $needle ) {
	if ( false !== strpos( (string) $post->post_content, $needle ) ) {
		return true;
	}
	$data = get_post_meta( $post->ID, '_elementor_data', true );
	return is_string( $data ) && false !== strpos( $data, $needle );
}

function kam_contact_js() {
	return <<<'JS'
document.addEventListener('submit', function (e) {
	var form = e.target.closest('.kam-contact-form');
	if (!form) { return; }
	e.preventDefault();
	var btn = form.querySelector('button[type=submit]');
	var status = form.querySelector('.kam-contact-status');
	btn.disabled = true; btn.textContent = 'Sending...'; status.textContent = '';
	fetch(form.action, { method: 'POST', body: new FormData(form) })
		.then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
		.then(function (res) {
			status.textContent = res.ok ? res.j.message : (res.j.message || 'Something went wrong.');
			status.style.color = res.ok ? '#198754' : '#dc3545';
			if (res.ok) { form.reset(); }
		})
		.catch(function () { status.textContent = 'Something went wrong. Please call us.'; status.style.color = '#dc3545'; })
		.then(function () { btn.disabled = false; btn.textContent = 'Send message'; });
});
JS;
}
