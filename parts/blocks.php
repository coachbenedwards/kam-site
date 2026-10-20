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
 * A map. With a Google Maps key (the live site's own, as a field) it is the
 * same Google map the live site draws; without one it is Google's keyless
 * embed, which can only show one pin.
 *
 *   one pin:   lat, lng, zoom (16), title
 *   many pins: lines, one per pin: name | lat | lng | address (; between
 *              lines) | phone | directions link -- the map fits them all,
 *              then sits at zoom 9 as the live one does; clicking a pin
 *              opens the same card.
 */
add_shortcode( 'kam_map', function ( $atts, $content = '' ) {
	$a = shortcode_atts( array( 'lat' => '', 'lng' => '', 'zoom' => '16', 'title' => 'Map', 'height' => '250', 'key' => '', 'lines' => '' ), $atts, 'kam_map' );
	$pins = array();
	foreach ( array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) ( $a['lines'] ?: $content ) ) ) ) as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) ) + array( '', '', '', '', '', '' );
		if ( is_numeric( $p[1] ) && is_numeric( $p[2] ) ) {
			$pins[] = array( 'title' => $p[0], 'lat' => (float) $p[1], 'lng' => (float) $p[2],
				'address' => $p[3], 'phone' => $p[4], 'url' => $p[5] );
		}
	}
	if ( ! $pins && '' !== $a['lat'] && '' !== $a['lng'] ) {
		$pins[] = array( 'title' => $a['title'], 'lat' => (float) $a['lat'], 'lng' => (float) $a['lng'] );
	}
	if ( ! $pins ) {
		return '';
	}
	static $n = 0;
	$n++;
	$id = 1 === $n ? 'map_canvas' : 'map_canvas_' . $n;
	$h  = (int) $a['height'];

	if ( '' === $a['key'] ) {
		$src = 'https://maps.google.com/maps?q=' . rawurlencode( $pins[0]['lat'] . ',' . $pins[0]['lng'] ) . '&z=' . (int) $a['zoom'] . '&output=embed';
		return '<div id="' . esc_attr( $id ) . '" style="width:100%;height:' . $h . 'px;"><iframe title="' . esc_attr( $pins[0]['title'] ) . '" src="' . esc_url( $src ) . '" width="100%" height="' . $h . '" style="border:0" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe></div>';
	}

	kam_map_script( $a['key'] );
	return '<div id="' . esc_attr( $id ) . '" class="kam-map" style="width:100%;height:' . $h . 'px;"'
		. ' data-pins="' . esc_attr( wp_json_encode( $pins ) ) . '" data-zoom="' . (int) $a['zoom'] . '"></div>';
} );

/** Loads the Google Maps script once per page and draws every .kam-map on it. */
function kam_map_script( $key ) {
	static $done = false;
	if ( $done ) {
		return;
	}
	$done = true;
	add_action( 'wp_footer', function () use ( $key ) {
		echo '<script>window.kamMapsInit=function(){document.querySelectorAll(".kam-map").forEach(function(el){'
			. 'var pins=JSON.parse(el.getAttribute("data-pins")||"[]");if(!pins.length)return;'
			. 'var map=new google.maps.Map(el,{mapTypeId:"roadmap",center:{lat:pins[0].lat,lng:pins[0].lng},zoom:parseInt(el.getAttribute("data-zoom")||"16",10)});'
			. 'if(pins.length===1){map.setTilt(45);new google.maps.Marker({position:{lat:pins[0].lat,lng:pins[0].lng},map:map,title:pins[0].title});return;}'
			. 'var b=new google.maps.LatLngBounds(),iw=new google.maps.InfoWindow();'
			. 'function esc(t){return String(t||"").replace(/[&<>"]/g,function(c){return {"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;"}[c];});}'
			. 'pins.forEach(function(p){var pos={lat:p.lat,lng:p.lng};var m=new google.maps.Marker({position:pos,map:map,title:p.title});b.extend(pos);'
			. 'm.addListener("click",function(){var h="<div class=\"text-center info_content\"><p><b>"+esc(p.title)+"</b></p>";'
			. 'if(p.address){h+="<p><i class=\"fas fa-map-marker-alt\" style=\"color:#2691D5\"></i> "+esc(p.address).split(";").join("<br>")+"</p>";}'
			. 'if(p.phone){h+="<p><i class=\"fas fa-phone\" style=\"color:#2691D5\"></i> <a href=\"tel:"+esc(p.phone.replace(/[^0-9+]/g,""))+"\">"+esc(p.phone)+"</a></p>";}'
			. 'if(p.url){h+="<a class=\"btn btn-danger btn-sm\" target=\"_blank\" href=\""+esc(p.url)+"\">Driving Directions <i class=\"fas fa-chevron-right\"></i> <i class=\"fas fa-chevron-right\"></i></a>";}'
			. 'iw.setContent(h+"</div>");iw.open(map,m);});});'
			. 'map.fitBounds(b);var once=google.maps.event.addListener(map,"bounds_changed",function(){this.setZoom(9);google.maps.event.removeListener(once);});});};</script>'
			. '<script async defer src="https://maps.googleapis.com/maps/api/js?key=' . rawurlencode( $key ) . '&callback=kamMapsInit"></script>';
	}, 50 );
}


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
 * The home-page carousel. Lines, one per slide: image | heading | line.
 * Same Bootstrap carousel as the live site, which its stylesheet hides
 * under 500px and dims with a dark wash.
 */
add_shortcode( 'kam_hero', function ( $atts, $content = '' ) {
	$a = shortcode_atts( array( 'lines' => '', 'id' => 'demo', 'interval' => '' ), $atts, 'kam_hero' );
	$lines = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) ( $a['lines'] ?: $content ) ) ) );
	if ( ! $lines ) {
		return '';
	}
	$id  = sanitize_html_class( $a['id'] );
	$out = '<div id="' . esc_attr( $id ) . '" class="carousel slide" data-bs-ride="carousel"><div class="carousel-indicators">';
	$n = 0;
	foreach ( $lines as $line ) {
		$out .= '<button type="button" data-bs-target="#' . esc_attr( $id ) . '" data-bs-slide-to="' . $n . '"' . ( 0 === $n ? ' class="active" aria-current="true"' : '' ) . ' aria-label="Slide ' . ( $n + 1 ) . '"></button>';
		$n++;
	}
	$out .= '</div><div class="carousel-inner">';
	$n = 0;
	foreach ( $lines as $line ) {
		$p = array_map( 'trim', explode( '|', $line ) ) + array( '', '', '' );
		list( $img, $h, $sub ) = $p;
		$out .= '<div class="carousel-item' . ( 0 === $n ? ' active' : '' ) . '">'
			. '<img src="' . esc_url( $img ) . '" class="d-block w-100" alt="' . esc_attr( $h ) . '">'
			. '<div class="carousel-caption d-none d-md-block">'
			. ( 0 === $n ? '<h1>' . esc_html( $h ) . '</h1>' : '<h2>' . esc_html( $h ) . '</h2>' )
			. ( $sub ? '<h3>' . esc_html( $sub ) . '</h3>' : '' )
			. '</div></div>';
		$n++;
	}
	$out .= '</div>'
		. '<button class="carousel-control-prev" type="button" data-bs-target="#' . esc_attr( $id ) . '" data-bs-slide="prev"><span class="carousel-control-prev-icon" aria-hidden="true"></span><span class="visually-hidden">Previous</span></button>'
		. '<button class="carousel-control-next" type="button" data-bs-target="#' . esc_attr( $id ) . '" data-bs-slide="next"><span class="carousel-control-next-icon" aria-hidden="true"></span><span class="visually-hidden">Next</span></button>'
		. '</div>';
	return $out;
} );

/**
 * Customer reviews. Lines, one per card: name | words. The live site pulls
 * six from its own database; here they are the block's lines, so the words
 * are the field. Same card markup, same Read More behaviour.
 */
add_shortcode( 'kam_reviews', function ( $atts, $content = '' ) {
	$a = shortcode_atts( array(
		'lines'         => '',
		// The badges under the cards: Google's mark with the rating, and the
		// Good Garage Scheme mark linking to KAM's listing.
		'google_logo'   => '',
		'google_rating' => '',
		'ggs_logo'      => '',
		'ggs_url'       => '',
	), $atts, 'kam_reviews' );
	$lines = array_filter( array_map( 'trim', preg_split( '/\r?\n/', (string) ( $a['lines'] ?: $content ) ) ) );
	if ( ! $lines ) {
		return '';
	}
	$out = '<div class="py-2 pt-3" id="ratings-container"><div class="row g-4">';
	foreach ( $lines as $line ) {
		$p = array_map( 'trim', explode( '|', $line, 2 ) ) + array( '', '' );
		list( $name, $text ) = $p;
		$initial = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 1 ) : substr( $name, 0, 1 );
		$out .= '<div class="col-md-6 col-lg-4"><div class="card shadow-sm border-0 h-100"><div class="card-body">'
			. '<div class="d-flex align-items-center mb-3"><div class="avatar rounded-circle text-white d-flex justify-content-center align-items-center me-2">' . esc_html( $initial ) . '</div>'
			. '<h6 class="mb-0">' . esc_html( $name ) . '</h6></div>'
			. '<p class="card-text text-dark feedback-text" style="overflow:hidden;max-height:60px">' . esc_html( $text ) . '</p>'
			. '<a href="javascript:void(0);" class="read-more-link">Read More</a>'
			. '</div></div></div>';
	}
	$out .= '</div>';
	if ( $a['google_logo'] || $a['ggs_logo'] ) {
		$out .= '<div class="row"><div class="col-12"><div class="review-badges">';
		if ( $a['google_logo'] ) {
			$r     = (float) $a['google_rating'];
			$stars = '';
			for ( $i = 1; $i <= 5; $i++ ) {
				// A space between the icons, as the live markup has (it is ~4px of width each).
				$stars .= ( 1 === $i ? '' : ' ' ) . '<i class="fa-solid ' . ( $r >= $i ? 'fa-star' : ( $r >= $i - 0.5 ? 'fa-star-half-stroke' : 'fa-star' ) ) . '"></i>';
			}
			$out .= '<a href="javascript:void(0);" class="trustpilot-link badge-card google-badge">'
				. '<img src="' . esc_url( $a['google_logo'] ) . '" alt="Google Reviews" class="trustpilot-logo">'
				. '<div class="google-rating"><span class="rating-text">' . esc_html( $a['google_rating'] ) . '</span><span class="stars">' . $stars . '</span></div></a>';
		}
		if ( $a['ggs_logo'] ) {
			$out .= '<a href="' . esc_url( $a['ggs_url'] ) . '" target="_blank" class="trustpilot-link badge-card">'
				. '<img src="' . esc_url( $a['ggs_logo'] ) . '" alt="Good Garage Scheme" class="trustpilot-logo"></a>';
		}
		$out .= '</div></div></div>';
	}
	return $out . '</div>';
} );

function kam_reviews_js() {
	return <<<'JS'
document.querySelectorAll('.read-more-link').forEach(function (link) {
	link.addEventListener('click', function () {
		var p = this.previousElementSibling;
		if (this.textContent === 'Read More') { p.style.maxHeight = 'none'; p.style.overflow = 'visible'; this.textContent = 'Read Less'; }
		else { p.style.maxHeight = '60px'; p.style.overflow = 'hidden'; this.textContent = 'Read More'; }
	});
});
JS;
}

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
		. '.kam-blockrow .elementor-heading-title{margin-bottom:.5rem;line-height:1.3}'
		. '.kam-blockrow .elementor-widget-heading[class*=" mb-"] .elementor-heading-title{margin-bottom:0}'
		. '.kam-blockrow hr{margin:1rem 0}'
		. '.kam-blockrow .elementor-widget-heading+.elementor-widget-text-editor hr:first-child{margin-top:.5rem}'
		. '.kam-blockrow .elementor-widget-text-editor p:last-child{margin-bottom:1rem}'
		. '.kam-buttons{margin-bottom:0}'
		/*
		 * The service grids. The live site's .service-item is the <a> round
		 * each tile; here it is the Image Box widget's class, and the tiles
		 * in the last row lose their bottom rule as they do there.
		 */
		. '.service-item .elementor-image-box-title,.service-item .elementor-image-box-description{color:#000}'
		// Bootstrap gives every <p> 1rem underneath; Elementor's description has none.
		. '.kam-blockrow .elementor-image-box-description{margin-bottom:1rem}'
		. '.kam-blockrow .elementor-image-box-title,.kam-blockrow .elementor-image-box-title a{color:#212529}'
		// An inline-block image leaves a descender gap under it; the live tiles have none.
		. '.kam-blockrow .elementor-image-box-img{display:block}'
		. '.service-item .elementor-image-box-title a{color:#000}'
		// Elementor's ".elementor img{height:auto}" outranks KAM's 50px badge logos.
		. '.kam-blockrow .trustpilot-logo{height:50px;width:auto}'
		// Terms page: each numbered term is its own widget, so each is a :last-child;
		// the rule between them comes back, and only the box's last term goes without.
		. '.kam-blockrow .elementor-widget-text-editor .warranty-item:last-child{border-bottom:1px solid #eee}'
		. '.kam-blockrow .elementor-widget-text-editor:last-child .warranty-item:last-child{border-bottom:none}'
		// Bootstrap's flex-column-reverse: text under the picture on a phone.
		. '@media(max-width:767px){.e-con.kam-reverse-mobile{--flex-direction:column-reverse!important;flex-direction:column-reverse}}'
		. '.kam-grid>.e-con:nth-last-child(-n+2) .service-item{border-bottom:none}'
		. '@media(min-width:992px){.kam-grid>.e-con:nth-last-child(-n+3) .service-item{border-bottom:none}}';
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

	if ( kam_uses_shortcode( $post, 'kam_reviews' ) ) {
		wp_register_script( 'kam-reviews', false, array(), KAM_SITE_VERSION, true );
		wp_enqueue_script( 'kam-reviews' );
		wp_add_inline_script( 'kam-reviews', kam_reviews_js() );
	}
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
