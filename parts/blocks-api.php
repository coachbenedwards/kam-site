<?php
/**
 * The command channel: reading and changing the blocks on a page.
 *
 * Ben wants a person to be able to say "change this" and have the block
 * change, and an agent to be able to do the same thing without opening a
 * builder. Both need one addressable place where a page's words live.
 *
 * That place is the Elementor document. Every block this plugin provides
 * already takes all of its visible copy as shortcode attributes -- the
 * fixtures block alone has fifteen -- so the words are already fields
 * rather than prose buried in markup. This exposes them:
 *
 *   GET  /wp-json/kam/v1/blocks?page=matches
 *        every block on that page, with its element id and its fields
 *
 *   POST /wp-json/kam/v1/blocks
 *        { "page": "matches", "id": "a1b2c3", "fields": { "title": "..." } }
 *        changes those fields on that block and nothing else
 *
 *   POST /wp-json/kam/v1/page-blocks
 *        { "page": "matches", "blocks": [ { "block": "...", "fields": {...} } ] }
 *        lays a page out from scratch -- used when converting a page, and
 *        refused unless `replace` is true, because it discards what is there
 *
 * One source of truth, two ways in. The rule that cost a fortnight stands:
 * a page's words live in exactly one place, and this is it.
 *
 * @package kam-site
 */

defined( 'ABSPATH' ) || exit;

/** Shortcodes this channel is willing to write. Nothing else is accepted. */
function kam_blocks_allowed() {
	return array(
		// Only blocks that blocks.php registers. The home-page ones (hero,
		// reviews, reg lookup) join this list when they are written.
		'kam_tiles', 'kam_buttons', 'kam_branch', 'kam_contact_form', 'kam_map',
	);
}

/** The page being addressed, by slug or by id. */
function kam_blocks_page( $ref ) {
	$ref = trim( (string) $ref );
	if ( '' === $ref ) {
		return null;
	}
	if ( ctype_digit( $ref ) ) {
		$p = get_post( (int) $ref );
		return ( $p && 'page' === $p->post_type ) ? $p : null;
	}
	return get_page_by_path( sanitize_title( $ref ) );
}

/** A shortcode string, pulled apart into its name and its fields. */
function kam_blocks_parse( $shortcode ) {
	$s = trim( (string) $shortcode );
	if ( ! preg_match( '/^\[([a-z0-9_]+)([^\]]*)\]/i', $s, $m ) ) {
		return null;
	}
	$atts = shortcode_parse_atts( trim( $m[2] ) );
	return array(
		'block'  => $m[1],
		'fields' => is_array( $atts ) ? $atts : array(),
		// Content between the tags, where a block takes a list.
		'lines'  => preg_match( '/\](.*)\[\/' . preg_quote( $m[1], '/' ) . '\]/s', $s, $c )
			? trim( $c[1] ) : '',
	);
}

/**
 * One attribute, quoted so the value survives.
 *
 * A value holding a double quote is written in single quotes, and only a
 * value holding both falls back to an entity -- which is what a reader
 * would see, so it is the last resort rather than the default.
 */
function kam_blocks_attr( $v ) {
	$v = (string) $v;
	if ( false === strpos( $v, '"' ) ) {
		return '"' . $v . '"';
	}
	if ( false === strpos( $v, "'" ) ) {
		return "'" . $v . "'";
	}
	return '"' . str_replace( '"', '&quot;', $v ) . '"';
}

/**
 * A square bracket ends a shortcode, so no field may hold one. There is no
 * escape for it: encoding it shows the reader the encoding. Refuse instead,
 * and say which field, rather than writing something that renders as junk.
 */
function kam_blocks_bad( $fields ) {
	foreach ( (array) $fields as $k => $v ) {
		// Not the body. A block's writing can hold other blocks -- the
		// sponsorship page has the gate figures in the middle of its prose.
		if ( 'lines' === $k ) {
			continue;
		}
		if ( is_string( $v ) && ( false !== strpos( $v, '[' ) || false !== strpos( $v, ']' ) ) ) {
			return 'Square brackets cannot go in a block field (' . $k . ').';
		}
	}
	return '';
}

/** The same thing, written back out. */
function kam_blocks_build( $block, $fields, $lines = '' ) {
	$out = '[' . $block;
	foreach ( (array) $fields as $k => $v ) {
		if ( ! preg_match( '/^[a-z0-9_]+$/i', (string) $k ) ) {
			continue;
		}
		$out .= ' ' . $k . '=' . kam_blocks_attr( $v );
	}
	$out .= ']';
	if ( '' !== trim( (string) $lines ) ) {
		$out .= "\n" . trim( (string) $lines ) . "\n[/" . $block . ']';
	}
	return $out;
}

/**
 * One widget, from a plain description of it.
 *
 * A page "in blocks" has to mean blocks a person can edit, which means
 * Elementor's own heading and text widgets wherever the content is just a
 * heading or a paragraph -- not one shortcode box holding a page of HTML.
 * That was the first attempt and Ben was right to reject it: the words
 * were reachable, but only as HTML in a single field.
 *
 *   { "widget": "heading",     "fields": { "title": "Trials", "tag": "h2" } }
 *   { "widget": "text-editor", "fields": { "editor": "<p>...</p>" } }
 *   { "block":  "eastwood_gate" }
 */
function kam_blocks_widget( $spec ) {
	$id = substr( md5( wp_json_encode( $spec ) . wp_rand() ), 0, 7 );

	if ( ! empty( $spec['block'] ) ) {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => 'shortcode',
			'settings'   => array(
				'shortcode' => kam_blocks_build( (string) $spec['block'],
					(array) ( $spec['fields'] ?? array() ), (string) ( $spec['lines'] ?? '' ) ),
			),
		);
	}

	$kind   = (string) ( $spec['widget'] ?? '' );
	$fields = (array) ( $spec['fields'] ?? array() );

	if ( 'heading' === $kind ) {
		$tag = strtolower( (string) ( $fields['tag'] ?? 'h2' ) );
		if ( ! in_array( $tag, array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), true ) ) {
			$tag = 'h2';
		}
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => 'heading',
			'settings'   => array(
				'title'       => (string) ( $fields['title'] ?? '' ),
				'header_size' => $tag,
				'css_classes' => (string) ( $fields['class'] ?? '' ),
			),
		);
	}

	if ( 'text-editor' === $kind ) {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => 'text-editor',
			'settings'   => array(
				'editor'      => (string) ( $fields['editor'] ?? '' ),
				'css_classes' => (string) ( $fields['class'] ?? '' ),
			),
		);
	}

	if ( 'html' === $kind ) {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => 'html',
			'settings'   => array( 'html' => (string) ( $fields['html'] ?? '' ) ),
		);
	}

	if ( 'image' === $kind ) {
		$url = esc_url_raw( (string) ( $fields['url'] ?? '' ) );
		$aid = (int) ( $fields['id'] ?? 0 );
		if ( ! $aid && $url ) {
			$aid = (int) attachment_url_to_postid( $url );
		}
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => 'image',
			'settings'   => array(
				'image'       => array( 'url' => $url, 'id' => $aid, 'alt' => (string) ( $fields['alt'] ?? '' ) ),
				'image_size'  => 'full',
				'align'       => (string) ( $fields['align'] ?? 'center' ),
				'link_to'     => ! empty( $fields['link'] ) ? 'custom' : 'none',
				'link'        => array( 'url' => (string) ( $fields['link'] ?? '' ) ),
				'css_classes' => (string) ( $fields['class'] ?? '' ),
			),
		);
	}

	if ( 'button' === $kind ) {
		return array(
			'id'         => $id,
			'elType'     => 'widget',
			'widgetType' => 'button',
			'settings'   => array(
				'text'        => (string) ( $fields['text'] ?? '' ),
				'link'        => array( 'url' => (string) ( $fields['link'] ?? '' ), 'is_external' => '', 'nofollow' => '' ),
				'align'       => (string) ( $fields['align'] ?? 'left' ),
				'size'        => (string) ( $fields['size'] ?? 'sm' ),
				'css_classes' => (string) ( $fields['class'] ?? '' ),
			),
		);
	}

	return null;
}

/**
 * A row to hold widgets, drawing nothing of its own unless asked.
 *
 * Elementor writes a container's padding as its own per-element rule, which
 * beats a rule written against a class -- so a wrapper that needs padding
 * has to ask for it here rather than rely on the stylesheet. The written
 * pages need 64px under the last section, which is what .ew-prose used to
 * give them before an Elementor container was in the way.
 */
function kam_blocks_row( $widgets, $class = 'kam-blockrow', $pad = array(), $extra = array() ) {
	$pad = array_merge( array( 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0' ), $pad );

	/*
	 * kam-blockrow is on every row, whatever else it carries. It is what
	 * takes off the twenty pixels of gap Elementor puts between widgets --
	 * which, on a page split into one widget per section, added twenty
	 * pixels seven times over and made every written page taller than it
	 * was. The row's own class is added alongside it, not instead of it.
	 */
	$class = trim( $class );
	if ( 'kam-blockrow' !== $class && '' !== $class ) {
		$class = 'kam-blockrow ' . $class;
	}

	$settings = array_merge( array(
		'content_width' => 'full',
		'css_classes'   => $class,
		'padding'       => array( 'unit' => 'px', 'top' => $pad['top'], 'right' => $pad['right'],
			'bottom' => $pad['bottom'], 'left' => $pad['left'], 'isLinked' => false ),
	), $extra );

	return array(
		'id'       => substr( md5( 'row' . wp_rand() ), 0, 7 ),
		'elType'   => 'container',
		'settings' => $settings,
		'elements' => array_values( array_filter( $widgets ) ),
		'isInner'  => false,
	);
}

/**
 * A row of columns. The outer container lays its children out in a line
 * and lets them wrap; each child is a container of the given width
 * (half, by default) that goes full width on a phone. KAM's pages are
 * built on Bootstrap's col-md-6 pairs, and this is that pair in
 * Elementor's own terms, so the widgets in each column stay editable
 * and the columns stay columns in the editor.
 *
 * $columns is a list of array( 'class' => ..., 'widgets' => array( built
 * widgets ), 'width' => 50 ).
 */
function kam_blocks_columns( $columns, $class = 'kam-blockrow', $pad = array(), $extra = array() ) {
	$children = array();
	foreach ( $columns as $col ) {
		$width = isset( $col['width'] ) ? (int) $col['width'] : 50;
		$width = max( 10, min( 100, $width ) );
		$child = kam_blocks_row( (array) ( $col['widgets'] ?? array() ),
			(string) ( $col['class'] ?? 'kam-blockrow' ),
			(array) ( $col['padding'] ?? array() ),
			array(
				'width'        => array( 'unit' => '%', 'size' => $width, 'sizes' => array() ),
				// Bootstrap's col-lg-* stacks on a tablet too; col-md-* only on a phone.
				'width_tablet' => array( 'unit' => '%', 'size' => isset( $col['tablet'] ) ? max( 10, min( 100, (int) $col['tablet'] ) ) : $width, 'sizes' => array() ),
				'width_mobile' => array( 'unit' => '%', 'size' => 100, 'sizes' => array() ),
			) );
		$child['isInner'] = true;
		$children[] = $child;
	}
	return kam_blocks_row( $children, $class, $pad, array_merge( array(
		'flex_direction' => 'row',
		'flex_wrap'      => 'wrap',
		'flex_gap'       => array( 'column' => '0', 'row' => '0', 'isLinked' => true, 'unit' => 'px', 'size' => 0 ),
	), $extra ) );
}

/** Walk the Elementor tree and hand every widget to a callback. */
function kam_blocks_walk( &$nodes, $fn ) {
	foreach ( $nodes as &$node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( isset( $node['elType'] ) && 'widget' === $node['elType'] ) {
			$fn( $node );
		}
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			kam_blocks_walk( $node['elements'], $fn );
		}
	}
	unset( $node );
}

/**
 * Take one widget out of the tree, and any container it leaves empty.
 *
 * Setting a widget's text to nothing leaves the widget there. That is fine
 * for a line of prose, which then renders as nothing at all, but not for a
 * widget that is wrong rather than empty -- a shortcode nobody registered
 * prints its own name on the page, and no field change makes that stop.
 */
/**
 * Put a new widget next to one that is already on the page.
 *
 * The channel could change a block and it could take one away, but it
 * could not add one, so the only way to put a new section on a page
 * was to lay the whole page out again -- which throws away everything
 * a person has done in the builder since. This walks to whichever row
 * holds $id and drops the widget in beside it.
 *
 * @param array  $nodes  The document, by reference.
 * @param string $id     The id to sit next to.
 * @param array  $widget The widget to put in.
 * @param string $where  'after' or 'before'.
 * @return bool
 */
function kam_blocks_insert( &$nodes, $id, $widget, $where = 'after' ) {
	foreach ( $nodes as $i => &$node ) {
		if ( ( $node['id'] ?? '' ) === $id ) {
			array_splice( $nodes, 'before' === $where ? $i : $i + 1, 0, array( $widget ) );
			return true;
		}
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			if ( kam_blocks_insert( $node['elements'], $id, $widget, $where ) ) {
				return true;
			}
		}
	}
	return false;
}

function kam_blocks_remove( &$nodes, $id ) {
	$hit = false;
	foreach ( $nodes as $i => $node ) {
		if ( ! is_array( $node ) ) {
			continue;
		}
		if ( isset( $node['id'] ) && (string) $node['id'] === (string) $id ) {
			unset( $nodes[ $i ] );
			$hit = true;
			continue;
		}
		if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
			if ( kam_blocks_remove( $nodes[ $i ]['elements'], $id ) ) {
				$hit = true;
				// A container with nothing left in it still draws its own
				// padding, so it goes too.
				if ( ! $nodes[ $i ]['elements'] && 'widget' !== ( $node['elType'] ?? '' ) ) {
					unset( $nodes[ $i ] );
				}
			}
		}
	}
	$nodes = array_values( $nodes );
	return $hit;
}

function kam_blocks_read( $post ) {
	$raw = get_post_meta( $post->ID, '_elementor_data', true );
	$doc = is_string( $raw ) ? json_decode( $raw, true ) : $raw;
	return is_array( $doc ) ? $doc : array();
}

function kam_blocks_write( $post, $doc ) {
	update_post_meta( $post->ID, '_elementor_data', wp_slash( wp_json_encode( $doc ) ) );
	update_post_meta( $post->ID, '_elementor_edit_mode', 'builder' );
	// Elementor caches the CSS it generated for a page. Without this the
	// markup changes and the old stylesheet keeps being served.
	if ( class_exists( '\Elementor\Plugin' ) ) {
		\Elementor\Plugin::$instance->files_manager->clear_cache();
	}
	clean_post_cache( $post->ID );
}

add_action( 'rest_api_init', function () {

	register_rest_route( 'kam/v1', '/blocks', array(
		array(
			'methods'             => 'GET',
			'permission_callback' => function () { return current_user_can( 'edit_pages' ); },
			'callback'            => function ( $req ) {
				$post = kam_blocks_page( $req->get_param( 'page' ) );
				if ( ! $post ) {
					return new WP_Error( 'kam_no_page', 'No page by that name.', array( 'status' => 404 ) );
				}

				$out = array();
				$doc = kam_blocks_read( $post );
				kam_blocks_walk( $doc, function ( $node ) use ( &$out ) {
					$type = isset( $node['widgetType'] ) ? $node['widgetType'] : '';
					$row  = array(
						'id'     => isset( $node['id'] ) ? $node['id'] : '',
						'widget' => $type,
					);
					if ( 'shortcode' === $type && ! empty( $node['settings']['shortcode'] ) ) {
						$parsed = kam_blocks_parse( $node['settings']['shortcode'] );
						if ( $parsed ) {
							$row['block']  = $parsed['block'];
							$row['fields'] = $parsed['fields'];
							$row['lines']  = $parsed['lines'];
						}
					} elseif ( 'heading' === $type ) {
						$row['fields'] = array( 'title' => $node['settings']['title'] ?? '' );
					} elseif ( 'text-editor' === $type || 'html' === $type ) {
						$key = 'html' === $type ? 'html' : 'editor';
						$row['fields'] = array( $key => $node['settings'][ $key ] ?? '' );
					} elseif ( 'image' === $type ) {
						$row['fields'] = array( 'url' => $node['settings']['image']['url'] ?? '', 'alt' => $node['settings']['image']['alt'] ?? '', 'link' => $node['settings']['link']['url'] ?? '' );
					} elseif ( 'button' === $type ) {
						$row['fields'] = array( 'text' => $node['settings']['text'] ?? '', 'link' => $node['settings']['link']['url'] ?? '' );
					}
					$out[] = $row;
				} );

				return array(
					'page'     => $post->post_name,
					'id'       => $post->ID,
					'builder'  => 'builder' === get_post_meta( $post->ID, '_elementor_edit_mode', true ),
					'content'  => trim( (string) $post->post_content ),
					'blocks'   => $out,
				);
			},
		),
		array(
			'methods'             => 'POST',
			'permission_callback' => function () { return current_user_can( 'edit_pages' ); },
			'callback'            => function ( $req ) {
				$post = kam_blocks_page( $req->get_param( 'page' ) );
				$id   = (string) $req->get_param( 'id' );
				$set  = (array) $req->get_param( 'fields' );
				if ( ! $post ) {
					return new WP_Error( 'kam_no_page', 'No page by that name.', array( 'status' => 404 ) );
				}
				if ( '' === $id ) {
					return new WP_Error( 'kam_args', 'Give it an id.', array( 'status' => 400 ) );
				}

				if ( $req->get_param( 'remove' ) ) {
					$doc = kam_blocks_read( $post );
					if ( ! kam_blocks_remove( $doc, $id ) ) {
						return new WP_Error( 'kam_no_block', 'No block with that id on that page.', array( 'status' => 404 ) );
					}
					kam_blocks_write( $post, $doc );
					return array( 'ok' => true, 'page' => $post->post_name, 'id' => $id, 'removed' => true );
				}

				if ( ! $set ) {
					return new WP_Error( 'kam_args', 'Give it some fields, or remove: true.', array( 'status' => 400 ) );
				}
				$bad = kam_blocks_bad( $set );
				if ( $bad ) {
					return new WP_Error( 'kam_field', $bad, array( 'status' => 400 ) );
				}

				$doc   = kam_blocks_read( $post );
				$hit   = false;
				$after = array();

				kam_blocks_walk( $doc, function ( &$node ) use ( $id, $set, &$hit, &$after ) {
					if ( ( $node['id'] ?? '' ) !== $id ) {
						return;
					}
					$type = $node['widgetType'] ?? '';

					if ( 'shortcode' === $type ) {
						$parsed = kam_blocks_parse( $node['settings']['shortcode'] ?? '' );
						if ( ! $parsed ) { return; }
						$fields = array_merge( $parsed['fields'], $set );
						$lines  = array_key_exists( 'lines', $set ) ? $set['lines'] : $parsed['lines'];
						unset( $fields['lines'] );
						$node['settings']['shortcode'] = kam_blocks_build( $parsed['block'], $fields, $lines );
						$after = $fields;
					} else {
						foreach ( $set as $k => $v ) {
							if ( in_array( $k, array( 'title', 'editor', 'html', 'text' ), true ) ) {
								$node['settings'][ $k ] = $v;
								$after[ $k ] = $v;
							} elseif ( 'link' === $k ) {
								$node['settings']['link'] = array( 'url' => (string) $v );
								if ( 'image' === $type ) { $node['settings']['link_to'] = '' !== (string) $v ? 'custom' : 'none'; }
								$after[ $k ] = $v;
							} elseif ( 'url' === $k && 'image' === $type ) {
								$node['settings']['image']['url'] = (string) $v;
								$node['settings']['image']['id']  = (int) attachment_url_to_postid( (string) $v );
								$after[ $k ] = $v;
							} elseif ( 'alt' === $k && 'image' === $type ) {
								$node['settings']['image']['alt'] = (string) $v;
								$after[ $k ] = $v;
							}
						}
					}
					$hit = true;
				} );

				if ( ! $hit ) {
					return new WP_Error( 'kam_no_block', 'No block with that id on that page.', array( 'status' => 404 ) );
				}

				kam_blocks_write( $post, $doc );
				return array( 'ok' => true, 'page' => $post->post_name, 'id' => $id, 'fields' => $after );
			},
		),
	) );

	/*
	 * Add one widget to a page that already exists, without touching
	 * anything else on it. page-blocks below lays a whole page out
	 * again, which is right for a page being built and wrong for a page
	 * somebody is already working on.
	 */
	register_rest_route( 'kam/v1', '/block', array(
		'methods'             => 'POST',
		'permission_callback' => function () { return current_user_can( 'edit_pages' ); },
		'callback'            => function ( $req ) {
			$post = kam_blocks_page( $req->get_param( 'page' ) );
			if ( ! $post ) {
				return new WP_Error( 'kam_no_page', 'No page by that name.', array( 'status' => 404 ) );
			}

			$after  = (string) $req->get_param( 'after' );
			$before = (string) $req->get_param( 'before' );
			if ( '' === $after && '' === $before ) {
				return new WP_Error( 'kam_args',
					'Say which block it goes after, or before.', array( 'status' => 400 ) );
			}

			$spec = array(
				'widget' => (string) $req->get_param( 'widget' ),
				'block'  => (string) $req->get_param( 'block' ),
				'fields' => (array) $req->get_param( 'fields' ),
				'lines'  => (string) $req->get_param( 'lines' ),
			);

			if ( '' !== $spec['block'] ) {
				if ( ! in_array( $spec['block'], kam_blocks_allowed(), true ) ) {
					return new WP_Error( 'kam_block',
						'Not a block this channel writes: ' . $spec['block'],
						array( 'status' => 400 ) );
				}
				$bad = kam_blocks_bad( $spec['fields'] );
				if ( $bad ) {
					return new WP_Error( 'kam_field', $bad, array( 'status' => 400 ) );
				}
			} else {
				unset( $spec['block'] );
			}

			$widget = kam_blocks_widget( $spec );
			if ( null === $widget ) {
				return new WP_Error( 'kam_widget',
					'Not a widget this channel writes: ' . $spec['widget'],
					array( 'status' => 400 ) );
			}

			$doc = kam_blocks_read( $post );
			$ok  = kam_blocks_insert(
				$doc,
				'' !== $after ? $after : $before,
				$widget,
				'' !== $after ? 'after' : 'before'
			);
			if ( ! $ok ) {
				return new WP_Error( 'kam_no_block',
					'No block with that id on that page.', array( 'status' => 404 ) );
			}

			kam_blocks_write( $post, $doc );
			return array(
				'ok'   => true,
				'page' => $post->post_name,
				'id'   => $widget['id'],
			);
		},
	) );

	register_rest_route( 'kam/v1', '/page-blocks', array(
		'methods'             => 'POST',
		// Laying a page out again throws away whatever was there, so this one
		// asks for more than the others.
		'permission_callback' => function () { return current_user_can( 'edit_pages' ); },
		'callback'            => function ( $req ) {
			$post = kam_blocks_page( $req->get_param( 'page' ) );
			if ( ! $post ) {
				return new WP_Error( 'kam_no_page', 'No page by that name.', array( 'status' => 404 ) );
			}
			if ( ! $req->get_param( 'replace' ) ) {
				return new WP_Error( 'kam_replace', 'This discards the page. Pass replace: true.', array( 'status' => 400 ) );
			}

			$blocks = (array) $req->get_param( 'blocks' );
			if ( ! $blocks ) {
				return new WP_Error( 'kam_args', 'Give it some blocks.', array( 'status' => 400 ) );
			}

			$allowed = kam_blocks_allowed();
			$doc     = array();

			/*
			 * Two shapes. A flat list of blocks gives one row each, which is
			 * what the pages built so far use. A list of rows -- each with its
			 * own class and its own widgets -- lets a page put a heading and
			 * its paragraphs side by side inside one wrapper, which is what
			 * the written pages need: the wrapper carries the column, the
			 * widgets inside it are what a person edits.
			 */
			$rows = ( isset( $blocks[0]['widgets'] ) || isset( $blocks[0]['columns'] ) ) ? $blocks : array_map(
				function ( $b ) { return array( 'widgets' => array( $b ) ); },
				$blocks
			);

			/*
			 * One list of widget specs, checked and built, or a WP_Error
			 * naming the first thing wrong with it.
			 */
			$build = function ( $specs ) use ( $allowed ) {
				$built = array();
				foreach ( (array) $specs as $spec ) {
					if ( ! empty( $spec['block'] ) ) {
						if ( ! in_array( (string) $spec['block'], $allowed, true ) ) {
							return new WP_Error( 'kam_block',
								'Not a block this channel writes: ' . $spec['block'],
								array( 'status' => 400 ) );
						}
						$bad = kam_blocks_bad( (array) ( $spec['fields'] ?? array() ) );
						if ( $bad ) {
							return new WP_Error( 'kam_field', $bad, array( 'status' => 400 ) );
						}
					}
					$w = kam_blocks_widget( $spec );
					if ( null === $w ) {
						return new WP_Error( 'kam_widget',
							'Not a widget this channel writes: '
							. (string) ( $spec['widget'] ?? '?' ),
							array( 'status' => 400 ) );
					}
					$built[] = $w;
				}
				return $built;
			};
			$padding = function ( $row ) {
				$pad = array();
				foreach ( array( 'top', 'right', 'bottom', 'left' ) as $side ) {
					if ( isset( $row['padding'][ $side ] ) ) {
						$pad[ $side ] = (string) (int) $row['padding'][ $side ];
					}
				}
				return $pad;
			};

			foreach ( $rows as $row ) {
				$extra = array();
				if ( ! empty( $row['id'] ) ) {
					$extra['_element_id'] = sanitize_html_class( (string) $row['id'] );
				}

				// A row of columns: each column has its own class and widgets.
				if ( ! empty( $row['columns'] ) && is_array( $row['columns'] ) ) {
					$cols = array();
					foreach ( $row['columns'] as $col ) {
						$built = $build( $col['widgets'] ?? array() );
						if ( is_wp_error( $built ) ) {
							return $built;
						}
						$cols[] = array(
							'class'   => (string) ( $col['class'] ?? 'kam-blockrow' ),
							'widgets' => $built,
							'width'   => isset( $col['width'] ) ? (int) $col['width'] : 50,
							'tablet'  => isset( $col['tablet'] ) ? (int) $col['tablet'] : ( isset( $col['width'] ) ? (int) $col['width'] : 50 ),
							'padding' => $padding( $col ),
						);
					}
					if ( $cols ) {
						/*
						 * With a wrap, the columns sit inside an outer band: the
						 * band carries the full-width background (wrap), the
						 * columns row carries the centred width (class).
						 */
						if ( ! empty( $row['wrap'] ) ) {
							$inner = kam_blocks_columns( $cols,
								(string) ( $row['class'] ?? 'kam-blockrow' ), $padding( $row ) );
							$inner['isInner'] = true;
							$doc[] = kam_blocks_row( array( $inner ), (string) $row['wrap'],
								$padding( array( 'padding' => $row['wrap_padding'] ?? array() ) ), $extra );
						} else {
							$doc[] = kam_blocks_columns( $cols,
								(string) ( $row['class'] ?? 'kam-blockrow' ), $padding( $row ), $extra );
						}
					}
					continue;
				}

				$built = $build( $row['widgets'] ?? array() );
				if ( is_wp_error( $built ) ) {
					return $built;
				}
				if ( ! $built ) {
					continue;
				}
				$doc[] = kam_blocks_row( $built,
					(string) ( $row['class'] ?? 'kam-blockrow' ), $padding( $row ), $extra );
			}

			if ( ! $doc ) {
				return new WP_Error( 'kam_args', 'Nothing to lay out.', array( 'status' => 400 ) );
			}

			// The page stops carrying its own copy of the words. One source.
			wp_update_post( array( 'ID' => $post->ID, 'post_content' => '' ) );
			kam_blocks_write( $post, $doc );

			// The page template is left exactly as it was. Elementor renders
			// through the_content, which the current template already calls,
			// so switching it would change the header, the footer or the page
			// gutter for no gain -- and this has to match a capture.
			if ( $req->get_param( 'template' ) ) {
				update_post_meta( $post->ID, '_wp_page_template',
					sanitize_text_field( (string) $req->get_param( 'template' ) ) );
			}

			return array( 'ok' => true, 'page' => $post->post_name, 'blocks' => count( $doc ) );
		},
	) );
} );
