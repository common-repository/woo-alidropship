<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Ali_Product_Query' ) ) {
	class Ali_Product_Query extends WP_Query {
		private $query_vars_hash = false;
		private $query_vars_changed = true;

		protected function parse_orderby( $orderby ) {
			global $wpdb;

			// Used to filter values.
			$allowed_keys = array(
				'post_name',
				'post_author',
				'post_date',
				'post_title',
				'post_modified',
				'post_parent',
				'post_type',
				'name',
				'author',
				'date',
				'title',
				'modified',
				'parent',
				'type',
				'ID',
				'menu_order',
				'comment_count',
				'rand',
				'post__in',
				'post_parent__in',
				'post_name__in',
			);

			$primary_meta_key   = '';
			$primary_meta_query = false;
			$meta_clauses       = $this->meta_query->get_clauses();
			if ( ! empty( $meta_clauses ) ) {
				$primary_meta_query = reset( $meta_clauses );

				if ( ! empty( $primary_meta_query['key'] ) ) {
					$primary_meta_key = $primary_meta_query['key'];
					$allowed_keys[]   = $primary_meta_key;
				}

				$allowed_keys[] = 'meta_value';
				$allowed_keys[] = 'meta_value_num';
				$allowed_keys   = array_merge( $allowed_keys, array_keys( $meta_clauses ) );
			}

			// If RAND() contains a seed value, sanitize and add to allowed keys.
			$rand_with_seed = false;
			if ( preg_match( '/RAND\(([0-9]+)\)/i', $orderby, $matches ) ) {
				$orderby        = sprintf( 'RAND(%s)', (int) $matches[1] );
				$allowed_keys[] = $orderby;
				$rand_with_seed = true;
			}

			if ( ! in_array( $orderby, $allowed_keys, true ) ) {
				return false;
			}

			$orderby_clause = '';

			switch ( $orderby ) {
				case 'post_name':
				case 'post_author':
				case 'post_date':
				case 'post_title':
				case 'post_modified':
				case 'post_parent':
				case 'post_type':
				case 'ID':
				case 'menu_order':
				case 'comment_count':
					$orderby_clause = "{$wpdb->ald_posts}.{$orderby}";
					break;
				case 'rand':
					$orderby_clause = 'RAND()';
					break;
				case $primary_meta_key:
				case 'meta_value':
					if ( ! empty( $primary_meta_query['type'] ) ) {
						$orderby_clause = "CAST({$primary_meta_query['alias']}.meta_value AS {$primary_meta_query['cast']})";
					} else {
						$orderby_clause = "{$primary_meta_query['alias']}.meta_value";
					}
					break;
				case 'meta_value_num':
					$orderby_clause = "{$primary_meta_query['alias']}.meta_value+0";
					break;
				case 'post__in':
					if ( ! empty( $this->query_vars['post__in'] ) ) {
						$orderby_clause = "FIELD({$wpdb->ald_posts}.ID," . implode( ',', array_map( 'absint', $this->query_vars['post__in'] ) ) . ')';
					}
					break;
				case 'post_parent__in':
					if ( ! empty( $this->query_vars['post_parent__in'] ) ) {
						$orderby_clause = "FIELD( {$wpdb->ald_posts}.post_parent," . implode( ', ', array_map( 'absint', $this->query_vars['post_parent__in'] ) ) . ' )';
					}
					break;
				case 'post_name__in':
					if ( ! empty( $this->query_vars['post_name__in'] ) ) {
						$post_name__in        = array_map( 'sanitize_title_for_query', $this->query_vars['post_name__in'] );
						$post_name__in_string = "'" . implode( "','", $post_name__in ) . "'";
						$orderby_clause       = "FIELD( {$wpdb->ald_posts}.post_name," . $post_name__in_string . ' )';
					}
					break;
				default:
					if ( array_key_exists( $orderby, $meta_clauses ) ) {
						$meta_clause    = $meta_clauses[ $orderby ];
						$orderby_clause = "CAST({$meta_clause['alias']}.meta_value AS {$meta_clause['cast']})";
					} elseif ( $rand_with_seed ) {
						$orderby_clause = $orderby;
					} else {
						// Default: order by post field.
						$orderby_clause = "{$wpdb->ald_posts}.post_" . sanitize_key( $orderby );
					}

					break;
			}

			return $orderby_clause;
		}

		private function set_found_posts( $q, $limits ) {
			global $wpdb;

			if ( $q['no_found_rows'] || ( is_array( $this->posts ) && ! $this->posts ) ) {
				return;
			}

			if ( ! empty( $limits ) ) {
				$found_posts_query = apply_filters_ref_array( 'found_posts_query', array( 'SELECT FOUND_ROWS()', &$this ) );

				$this->found_posts = (int) $wpdb->get_var( $found_posts_query );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			} else {
				if ( is_array( $this->posts ) ) {
					$this->found_posts = count( $this->posts );
				} else {
					if ( null === $this->posts ) {
						$this->found_posts = 0;
					} else {
						$this->found_posts = 1;
					}
				}
			}

			$this->found_posts = (int) apply_filters_ref_array( 'found_posts', array( $this->found_posts, &$this ) );

			if ( ! empty( $limits ) ) {
				$this->max_num_pages = ceil( $this->found_posts / $q['posts_per_page'] );
			}
		}

		public function get_posts() {
			global $wpdb;

			$this->parse_query();

			do_action_ref_array( 'pre_get_posts', array( &$this ) );

			// Shorthand.
			$q = &$this->query_vars;

			// Fill again in case 'pre_get_posts' unset some vars.
			$q = $this->fill_query_vars( $q );

			/**
			 * Filters whether an attachment query should include filenames or not.
			 *
			 * @param bool $allow_query_attachment_by_filename Whether or not to include filenames.
			 *
			 * @since 6.0.3
			 *
			 */
			$this->allow_query_attachment_by_filename = apply_filters( 'wp_allow_query_attachment_by_filename', false );
			remove_all_filters( 'wp_allow_query_attachment_by_filename' );

			// Parse meta query.
			$this->meta_query = new WP_Meta_Query();
			$this->meta_query->parse_query_vars( $q );

			// Set a flag if a 'pre_get_posts' hook changed the query vars.
			$hash = md5( serialize( $this->query_vars ) );
			if ( $hash != $this->query_vars_hash ) {
				$this->query_vars_changed = true;
				$this->query_vars_hash    = $hash;
			}
			unset( $hash );

			// First let's clear some variables.
			$distinct         = '';
			$whichauthor      = '';
			$whichmimetype    = '';
			$where            = '';
			$limits           = '';
			$join             = '';
			$search           = '';
			$groupby          = '';
			$post_status_join = false;
			$page             = 1;


			if ( ! isset( $q['ignore_sticky_posts'] ) ) {
				$q['ignore_sticky_posts'] = false;
			}

			if ( ! isset( $q['suppress_filters'] ) ) {
				$q['suppress_filters'] = false;
			}

			if ( ! isset( $q['cache_results'] ) ) {
				$q['cache_results'] = true;
			}

			if ( ! isset( $q['update_post_term_cache'] ) ) {
				$q['update_post_term_cache'] = true;
			}

			if ( ! isset( $q['update_menu_item_cache'] ) ) {
				$q['update_menu_item_cache'] = false;
			}

			if ( ! isset( $q['lazy_load_term_meta'] ) ) {
				$q['lazy_load_term_meta'] = $q['update_post_term_cache'];
			} elseif ( $q['lazy_load_term_meta'] ) { // Lazy loading term meta only works if term caches are primed.
				$q['update_post_term_cache'] = true;
			}

			if ( ! isset( $q['update_post_meta_cache'] ) ) {
				$q['update_post_meta_cache'] = true;
			}

			if ( ! isset( $q['post_type'] ) ) {
				if ( $this->is_search ) {
					$q['post_type'] = 'any';
				} else {
					$q['post_type'] = '';
				}
			}
			$post_type = $q['post_type'];
			if ( empty( $q['posts_per_page'] ) ) {
				$q['posts_per_page'] = get_option( 'posts_per_page' );
			}
			if ( isset( $q['showposts'] ) && $q['showposts'] ) {
				$q['showposts']      = (int) $q['showposts'];
				$q['posts_per_page'] = $q['showposts'];
			}
			if ( ( isset( $q['posts_per_archive_page'] ) && 0 != $q['posts_per_archive_page'] ) && ( $this->is_archive || $this->is_search ) ) {
				$q['posts_per_page'] = $q['posts_per_archive_page'];
			}
			if ( ! isset( $q['nopaging'] ) ) {
				if ( - 1 == $q['posts_per_page'] ) {
					$q['nopaging'] = true;
				} else {
					$q['nopaging'] = false;
				}
			}

			if ( $this->is_feed ) {
				// This overrides 'posts_per_page'.
				if ( ! empty( $q['posts_per_rss'] ) ) {
					$q['posts_per_page'] = $q['posts_per_rss'];
				} else {
					$q['posts_per_page'] = get_option( 'posts_per_rss' );
				}
				$q['nopaging'] = false;
			}
			$q['posts_per_page'] = (int) $q['posts_per_page'];
			if ( $q['posts_per_page'] < - 1 ) {
				$q['posts_per_page'] = abs( $q['posts_per_page'] );
			} elseif ( 0 == $q['posts_per_page'] ) {
				$q['posts_per_page'] = 1;
			}

			if ( ! isset( $q['comments_per_page'] ) || 0 == $q['comments_per_page'] ) {
				$q['comments_per_page'] = get_option( 'comments_per_page' );
			}

			if ( $this->is_home && ( empty( $this->query ) || 'true' === $q['preview'] ) && ( 'page' === get_option( 'show_on_front' ) ) && get_option( 'page_on_front' ) ) {
				$this->is_page = true;
				$this->is_home = false;
				$q['page_id']  = get_option( 'page_on_front' );
			}

			if ( isset( $q['page'] ) ) {
				$q['page'] = trim( $q['page'], '/' );
				$q['page'] = absint( $q['page'] );
			}

			// If true, forcibly turns off SQL_CALC_FOUND_ROWS even when limits are present.
			if ( isset( $q['no_found_rows'] ) ) {
				$q['no_found_rows'] = (bool) $q['no_found_rows'];
			} else {
				$q['no_found_rows'] = false;
			}

			switch ( $q['fields'] ) {
				case 'ids':
					$fields = "{$wpdb->ald_posts}.ID";
					break;
				case 'id=>parent':
					$fields = "{$wpdb->ald_posts}.ID, {$wpdb->ald_posts}.post_parent";
					break;
				default:
					$fields = "{$wpdb->ald_posts}.*";
			}

			if ( '' !== $q['menu_order'] ) {
				$where .= " AND {$wpdb->ald_posts}.menu_order = " . $q['menu_order'];
			}
			// The "m" parameter is meant for months but accepts datetimes of varying specificity.
			if ( $q['m'] ) {
				$where .= " AND YEAR({$wpdb->ald_posts}.post_date)=" . substr( $q['m'], 0, 4 );
				if ( strlen( $q['m'] ) > 5 ) {
					$where .= " AND MONTH({$wpdb->ald_posts}.post_date)=" . substr( $q['m'], 4, 2 );
				}
				if ( strlen( $q['m'] ) > 7 ) {
					$where .= " AND DAYOFMONTH({$wpdb->ald_posts}.post_date)=" . substr( $q['m'], 6, 2 );
				}
				if ( strlen( $q['m'] ) > 9 ) {
					$where .= " AND HOUR({$wpdb->ald_posts}.post_date)=" . substr( $q['m'], 8, 2 );
				}
				if ( strlen( $q['m'] ) > 11 ) {
					$where .= " AND MINUTE({$wpdb->ald_posts}.post_date)=" . substr( $q['m'], 10, 2 );
				}
				if ( strlen( $q['m'] ) > 13 ) {
					$where .= " AND SECOND({$wpdb->ald_posts}.post_date)=" . substr( $q['m'], 12, 2 );
				}
			}

			// Handle the other individual date parameters.
			$date_parameters = array();

			if ( '' !== $q['hour'] ) {
				$date_parameters['hour'] = $q['hour'];
			}

			if ( '' !== $q['minute'] ) {
				$date_parameters['minute'] = $q['minute'];
			}

			if ( '' !== $q['second'] ) {
				$date_parameters['second'] = $q['second'];
			}

			if ( $q['year'] ) {
				$date_parameters['year'] = $q['year'];
			}

			if ( $q['monthnum'] ) {
				$date_parameters['monthnum'] = $q['monthnum'];
			}

			if ( $q['w'] ) {
				$date_parameters['week'] = $q['w'];
			}

			if ( $q['day'] ) {
				$date_parameters['day'] = $q['day'];
			}

			if ( $date_parameters ) {
				$date_query = new WP_Date_Query( array( $date_parameters ) );
				$where      .= $date_query->get_sql();
			}
			unset( $date_parameters, $date_query );

			// Handle complex date queries.
			if ( ! empty( $q['date_query'] ) ) {
				$this->date_query = new WP_Date_Query( $q['date_query'] );
				$where            .= $this->date_query->get_sql();
			}

			// If we've got a post_type AND it's not "any" post_type.
			if ( ! empty( $q['post_type'] ) && 'any' !== $q['post_type'] ) {
				foreach ( (array) $q['post_type'] as $_post_type ) {
					$ptype_obj = get_post_type_object( $_post_type );
					if ( ! $ptype_obj || ! $ptype_obj->query_var || empty( $q[ $ptype_obj->query_var ] ) ) {
						continue;
					}

					if ( ! $ptype_obj->hierarchical ) {
						// Non-hierarchical post types can directly use 'name'.
						$q['name'] = $q[ $ptype_obj->query_var ];
					} else {
						// Hierarchical post types will operate through 'pagename'.
						$q['pagename'] = $q[ $ptype_obj->query_var ];
						$q['name']     = '';
					}

					// Only one request for a slug is possible, this is why name & pagename are overwritten above.
					break;
				} // End foreach.
				unset( $ptype_obj );
			}

			if ( '' !== $q['title'] ) {
				$where .= $wpdb->prepare( " AND {$wpdb->ald_posts}.post_title = %s", stripslashes( $q['title'] ) );
			}

			// Parameters related to 'post_name'.
			if ( '' !== $q['name'] ) {
				$q['name'] = sanitize_title_for_query( $q['name'] );
				$where     .= " AND {$wpdb->ald_posts}.post_name = '" . $q['name'] . "'";
			} elseif ( '' !== $q['pagename'] ) {
				if ( isset( $this->queried_object_id ) ) {
					$reqpage = $this->queried_object_id;
				} else {
					if ( 'page' !== $q['post_type'] ) {
						foreach ( (array) $q['post_type'] as $_post_type ) {
							$ptype_obj = get_post_type_object( $_post_type );
							if ( ! $ptype_obj || ! $ptype_obj->hierarchical ) {
								continue;
							}

							$reqpage = get_page_by_path( $q['pagename'], OBJECT, $_post_type );
							if ( $reqpage ) {
								break;
							}
						}
						unset( $ptype_obj );
					} else {
						$reqpage = get_page_by_path( $q['pagename'] );
					}
					if ( ! empty( $reqpage ) ) {
						$reqpage = $reqpage->ID;
					} else {
						$reqpage = 0;
					}
				}

				$page_for_posts = get_option( 'page_for_posts' );
				if ( ( 'page' !== get_option( 'show_on_front' ) ) || empty( $page_for_posts ) || ( $reqpage != $page_for_posts ) ) {
					$q['pagename'] = sanitize_title_for_query( wp_basename( $q['pagename'] ) );
					$q['name']     = $q['pagename'];
					$where         .= " AND ({$wpdb->ald_posts}.ID = '$reqpage')";
					$reqpage_obj   = get_post( $reqpage );
					if ( is_object( $reqpage_obj ) && 'attachment' === $reqpage_obj->post_type ) {
						$this->is_attachment = true;
						$post_type           = 'attachment';
						$q['post_type']      = 'attachment';
						$this->is_page       = true;
						$q['attachment_id']  = $reqpage;
					}
				}
			} elseif ( '' !== $q['attachment'] ) {
				$q['attachment'] = sanitize_title_for_query( wp_basename( $q['attachment'] ) );
				$q['name']       = $q['attachment'];
				$where           .= " AND {$wpdb->ald_posts}.post_name = '" . $q['attachment'] . "'";
			} elseif ( is_array( $q['post_name__in'] ) && ! empty( $q['post_name__in'] ) ) {
				$q['post_name__in'] = array_map( 'sanitize_title_for_query', $q['post_name__in'] );
				$post_name__in      = "'" . implode( "','", $q['post_name__in'] ) . "'";
				$where              .= " AND {$wpdb->ald_posts}.post_name IN ($post_name__in)";
			}

			// If an attachment is requested by number, let it supersede any post number.
			if ( $q['attachment_id'] ) {
				$q['p'] = absint( $q['attachment_id'] );
			}

			// If a post number is specified, load that post.
			if ( $q['p'] ) {
				$where .= " AND {$wpdb->ald_posts}.ID = " . $q['p'];
			} elseif ( $q['post__in'] ) {
				$post__in = implode( ',', array_map( 'absint', $q['post__in'] ) );
				$where    .= " AND {$wpdb->ald_posts}.ID IN ($post__in)";
			} elseif ( $q['post__not_in'] ) {
				$post__not_in = implode( ',', array_map( 'absint', $q['post__not_in'] ) );
				$where        .= " AND {$wpdb->ald_posts}.ID NOT IN ($post__not_in)";
			}

			if ( is_numeric( $q['post_parent'] ) ) {
				$where .= $wpdb->prepare( " AND {$wpdb->ald_posts}.post_parent = %d ", $q['post_parent'] );
			} elseif ( $q['post_parent__in'] ) {
				$post_parent__in = implode( ',', array_map( 'absint', $q['post_parent__in'] ) );
				$where           .= " AND {$wpdb->ald_posts}.post_parent IN ($post_parent__in)";
			} elseif ( $q['post_parent__not_in'] ) {
				$post_parent__not_in = implode( ',', array_map( 'absint', $q['post_parent__not_in'] ) );
				$where               .= " AND {$wpdb->ald_posts}.post_parent NOT IN ($post_parent__not_in)";
			}

			if ( $q['page_id'] ) {
				if ( ( 'page' !== get_option( 'show_on_front' ) ) || ( get_option( 'page_for_posts' ) != $q['page_id'] ) ) {
					$q['p'] = $q['page_id'];
					$where  = " AND {$wpdb->ald_posts}.ID = " . $q['page_id'];
				}
			}

			// If a search pattern is specified, load the posts that match.
			if ( strlen( $q['s'] ) ) {
				$search = $this->parse_search( $q );
			}

			if ( ! $q['suppress_filters'] ) {
				$search = apply_filters_ref_array( 'posts_search', array( $search, &$this ) );
			}

			// Taxonomies.
			if ( ! $this->is_singular ) {
				$this->parse_tax_query( $q );

				$clauses = $this->tax_query->get_sql( $wpdb->ald_posts, 'ID' );

				$join  .= $clauses['join'];
				$where .= $clauses['where'];
			}

			if ( $this->is_tax ) {
				if ( empty( $post_type ) ) {
					// Do a fully inclusive search for currently registered post types of queried taxonomies.
					$post_type  = array();
					$taxonomies = array_keys( $this->tax_query->queried_terms );
					foreach ( get_post_types( array( 'exclude_from_search' => false ) ) as $pt ) {
						$object_taxonomies = 'attachment' === $pt ? get_taxonomies_for_attachments() : get_object_taxonomies( $pt );
						if ( array_intersect( $taxonomies, $object_taxonomies ) ) {
							$post_type[] = $pt;
						}
					}
					if ( ! $post_type ) {
						$post_type = 'any';
					} elseif ( count( $post_type ) == 1 ) {
						$post_type = $post_type[0];
					}

					$post_status_join = true;
				} elseif ( in_array( 'attachment', (array) $post_type, true ) ) {
					$post_status_join = true;
				}
			}

			/*
			 * Ensure that 'taxonomy', 'term', 'term_id', 'cat', and
			 * 'category_name' vars are set for backward compatibility.
			 */
			if ( ! empty( $this->tax_query->queried_terms ) ) {

				/*
				 * Set 'taxonomy', 'term', and 'term_id' to the
				 * first taxonomy other than 'post_tag' or 'category'.
				 */
				if ( ! isset( $q['taxonomy'] ) ) {
					foreach ( $this->tax_query->queried_terms as $queried_taxonomy => $queried_items ) {
						if ( empty( $queried_items['terms'][0] ) ) {
							continue;
						}

						if ( ! in_array( $queried_taxonomy, array( 'category', 'post_tag' ), true ) ) {
							$q['taxonomy'] = $queried_taxonomy;

							if ( 'slug' === $queried_items['field'] ) {
								$q['term'] = $queried_items['terms'][0];
							} else {
								$q['term_id'] = $queried_items['terms'][0];
							}

							// Take the first one we find.
							break;
						}
					}
				}

				// 'cat', 'category_name', 'tag_id'.
				foreach ( $this->tax_query->queried_terms as $queried_taxonomy => $queried_items ) {
					if ( empty( $queried_items['terms'][0] ) ) {
						continue;
					}

					if ( 'category' === $queried_taxonomy ) {
						$the_cat = get_term_by( $queried_items['field'], $queried_items['terms'][0], 'category' );
						if ( $the_cat ) {
							$this->set( 'cat', $the_cat->term_id );
							$this->set( 'category_name', $the_cat->slug );
						}
						unset( $the_cat );
					}

					if ( 'post_tag' === $queried_taxonomy ) {
						$the_tag = get_term_by( $queried_items['field'], $queried_items['terms'][0], 'post_tag' );
						if ( $the_tag ) {
							$this->set( 'tag_id', $the_tag->term_id );
						}
						unset( $the_tag );
					}
				}
			}

			if ( ! empty( $this->tax_query->queries ) || ! empty( $this->meta_query->queries ) || ! empty( $this->allow_query_attachment_by_filename ) ) {
				$groupby = "{$wpdb->ald_posts}.ID";
			}

			// Author/user stuff.

			if ( ! empty( $q['author'] ) && '0' != $q['author'] ) {
				$q['author'] = addslashes_gpc( '' . urldecode( $q['author'] ) );
				$authors     = array_unique( array_map( 'intval', preg_split( '/[,\s]+/', $q['author'] ) ) );
				foreach ( $authors as $author ) {
					$key         = $author > 0 ? 'author__in' : 'author__not_in';
					$q[ $key ][] = abs( $author );
				}
				$q['author'] = implode( ',', $authors );
			}

			if ( ! empty( $q['author__not_in'] ) ) {
				$author__not_in = implode( ',', array_map( 'absint', array_unique( (array) $q['author__not_in'] ) ) );
				$where          .= " AND {$wpdb->ald_posts}.post_author NOT IN ($author__not_in) ";
			} elseif ( ! empty( $q['author__in'] ) ) {
				$author__in = implode( ',', array_map( 'absint', array_unique( (array) $q['author__in'] ) ) );
				$where      .= " AND {$wpdb->ald_posts}.post_author IN ($author__in) ";
			}

			// Author stuff for nice URLs.

			if ( '' !== $q['author_name'] ) {
				if ( strpos( $q['author_name'], '/' ) !== false ) {
					$q['author_name'] = explode( '/', $q['author_name'] );
					if ( $q['author_name'][ count( $q['author_name'] ) - 1 ] ) {
						$q['author_name'] = $q['author_name'][ count( $q['author_name'] ) - 1 ]; // No trailing slash.
					} else {
						$q['author_name'] = $q['author_name'][ count( $q['author_name'] ) - 2 ]; // There was a trailing slash.
					}
				}
				$q['author_name'] = sanitize_title_for_query( $q['author_name'] );
				$q['author']      = get_user_by( 'slug', $q['author_name'] );
				if ( $q['author'] ) {
					$q['author'] = $q['author']->ID;
				}
				$whichauthor .= " AND ({$wpdb->ald_posts}.post_author = " . absint( $q['author'] ) . ')';
			}

			// Matching by comment count.
			if ( isset( $q['comment_count'] ) ) {
				// Numeric comment count is converted to array format.
				if ( is_numeric( $q['comment_count'] ) ) {
					$q['comment_count'] = array(
						'value' => (int) $q['comment_count'],
					);
				}

				if ( isset( $q['comment_count']['value'] ) ) {
					$q['comment_count'] = array_merge(
						array(
							'compare' => '=',
						),
						$q['comment_count']
					);

					// Fallback for invalid compare operators is '='.
					$compare_operators = array( '=', '!=', '>', '>=', '<', '<=' );
					if ( ! in_array( $q['comment_count']['compare'], $compare_operators, true ) ) {
						$q['comment_count']['compare'] = '=';
					}

					$where .= $wpdb->prepare( " AND {$wpdb->ald_posts}.comment_count {$q['comment_count']['compare']} %d", $q['comment_count']['value'] );// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				}
			}

			// MIME-Type stuff for attachment browsing.

			if ( isset( $q['post_mime_type'] ) && '' !== $q['post_mime_type'] ) {
				$whichmimetype = wp_post_mime_type_where( $q['post_mime_type'], $wpdb->ald_posts );
			}
			$where .= $search . $whichauthor . $whichmimetype;

			if ( ! empty( $this->allow_query_attachment_by_filename ) ) {
				$join .= " LEFT JOIN {$wpdb->ald_postmeta} AS sq1 ON ( {$wpdb->ald_posts}.ID = sq1.post_id AND sq1.meta_key = '_wp_attached_file' )";
			}

			if ( ! empty( $this->meta_query->queries ) ) {
				$clauses = $this->meta_query->get_sql( 'ald_post', $wpdb->ald_posts, 'ID', $this );
				$join    .= $clauses['join'];
				$where   .= $clauses['where'];
			}

			$rand = ( isset( $q['orderby'] ) && 'rand' === $q['orderby'] );
			if ( ! isset( $q['order'] ) ) {
				$q['order'] = $rand ? '' : 'DESC';
			} else {
				$q['order'] = $rand ? '' : $this->parse_order( $q['order'] );
			}

			// These values of orderby should ignore the 'order' parameter.
			$force_asc = array( 'post__in', 'post_name__in', 'post_parent__in' );
			if ( isset( $q['orderby'] ) && in_array( $q['orderby'], $force_asc, true ) ) {
				$q['order'] = '';
			}

			// Order by.
			if ( empty( $q['orderby'] ) ) {
				/*
				 * Boolean false or empty array blanks out ORDER BY,
				 * while leaving the value unset or otherwise empty sets the default.
				 */
				if ( isset( $q['orderby'] ) && ( is_array( $q['orderby'] ) || false === $q['orderby'] ) ) {
					$orderby = '';
				} else {
					$orderby = "{$wpdb->ald_posts}.post_date " . $q['order'];
				}
			} elseif ( 'none' === $q['orderby'] ) {
				$orderby = '';
			} else {
				$orderby_array = array();
				if ( is_array( $q['orderby'] ) ) {
					foreach ( $q['orderby'] as $_orderby => $order ) {
						$orderby = addslashes_gpc( urldecode( $_orderby ) );
						$parsed  = $this->parse_orderby( $orderby );

						if ( ! $parsed ) {
							continue;
						}

						$orderby_array[] = $parsed . ' ' . $this->parse_order( $order );
					}
					$orderby = implode( ', ', $orderby_array );

				} else {
					$q['orderby'] = urldecode( $q['orderby'] );
					$q['orderby'] = addslashes_gpc( $q['orderby'] );

					foreach ( explode( ' ', $q['orderby'] ) as $i => $orderby ) {
						$parsed = $this->parse_orderby( $orderby );
						// Only allow certain values for safety.
						if ( ! $parsed ) {
							continue;
						}

						$orderby_array[] = $parsed;
					}
					$orderby = implode( ' ' . $q['order'] . ', ', $orderby_array );

					if ( empty( $orderby ) ) {
						$orderby = "{$wpdb->ald_posts}.post_date " . $q['order'];
					} elseif ( ! empty( $q['order'] ) ) {
						$orderby .= " {$q['order']}";
					}
				}

			}

			// Order search results by relevance only when another "orderby" is not specified in the query.
			if ( ! empty( $q['s'] ) ) {
				$search_orderby = '';
				if ( ! empty( $q['search_orderby_title'] ) && ( empty( $q['orderby'] ) && ! $this->is_feed ) || ( isset( $q['orderby'] ) && 'relevance' === $q['orderby'] ) ) {
					$search_orderby = $this->parse_search_order( $q );
				}

				if ( ! $q['suppress_filters'] ) {
					$search_orderby = apply_filters( 'posts_search_orderby', $search_orderby, $this );
				}

				if ( $search_orderby ) {
					$orderby = $orderby ? $search_orderby . ', ' . $orderby : $search_orderby;
				}
			}

			if ( is_array( $post_type ) && count( $post_type ) > 1 ) {
				$post_type_cap = 'multiple_post_type';
			} else {
				if ( is_array( $post_type ) ) {
					$post_type = reset( $post_type );
				}
				$post_type_object = get_post_type_object( $post_type );
				if ( empty( $post_type_object ) ) {
					$post_type_cap = $post_type;
				}
			}

			if ( isset( $q['post_password'] ) ) {
				$where .= $wpdb->prepare( " AND {$wpdb->ald_posts}.post_password = %s", $q['post_password'] );
				if ( empty( $q['perm'] ) ) {
					$q['perm'] = 'readable';
				}
			} elseif ( isset( $q['has_password'] ) ) {
				$where .= sprintf( " AND {$wpdb->ald_posts}.post_password %s ''", $q['has_password'] ? '!=' : '=' );
			}

			if ( ! empty( $q['comment_status'] ) ) {
				$where .= $wpdb->prepare( " AND {$wpdb->ald_posts}.comment_status = %s ", $q['comment_status'] );
			}

			if ( ! empty( $q['ping_status'] ) ) {
				$where .= $wpdb->prepare( " AND {$wpdb->ald_posts}.ping_status = %s ", $q['ping_status'] );
			}

			$skip_post_status = false;
			if ( 'any' === $post_type ) {
				$in_search_post_types = get_post_types( array( 'exclude_from_search' => false ) );
				if ( empty( $in_search_post_types ) ) {
					$post_type_where  = ' AND 1=0 ';
					$skip_post_status = true;
				} else {
					$post_type_where = " AND {$wpdb->ald_posts}.post_type IN ('" . implode( "', '", array_map( 'esc_sql', $in_search_post_types ) ) . "')";
				}
			} elseif ( ! empty( $post_type ) && is_array( $post_type ) ) {
				$post_type_where = " AND {$wpdb->ald_posts}.post_type IN ('" . implode( "', '", esc_sql( $post_type ) ) . "')";
			} elseif ( ! empty( $post_type ) ) {
				$post_type_where  = $wpdb->prepare( " AND {$wpdb->ald_posts}.post_type = %s", $post_type );
				$post_type_object = get_post_type_object( $post_type );
			} elseif ( $this->is_attachment ) {
				$post_type_where  = " AND {$wpdb->ald_posts}.post_type = 'attachment'";
				$post_type_object = get_post_type_object( 'attachment' );
			} elseif ( $this->is_page ) {
				$post_type_where  = " AND {$wpdb->ald_posts}.post_type = 'page'";
				$post_type_object = get_post_type_object( 'page' );
			} else {
				$post_type_where  = " AND {$wpdb->ald_posts}.post_type = 'post'";
				$post_type_object = get_post_type_object( 'post' );
			}

			$edit_cap = 'edit_post';
			$read_cap = 'read_post';

			if ( ! empty( $post_type_object ) ) {
				$edit_others_cap  = $post_type_object->cap->edit_others_posts;
				$read_private_cap = $post_type_object->cap->read_private_posts;
			} else {
				$edit_others_cap  = 'edit_others_' . $post_type_cap . 's';
				$read_private_cap = 'read_private_' . $post_type_cap . 's';
			}

			$user_id = get_current_user_id();

			$q_status = array();
			if ( $skip_post_status ) {
				$where .= $post_type_where;
			} elseif ( ! empty( $q['post_status'] ) ) {

				$where .= $post_type_where;

				$statuswheres = array();
				$q_status     = $q['post_status'];
				if ( ! is_array( $q_status ) ) {
					$q_status = explode( ',', $q_status );
				}
				$r_status = array();
				$p_status = array();
				$e_status = array();
				if ( in_array( 'any', $q_status, true ) ) {
					foreach ( get_post_stati( array( 'exclude_from_search' => true ) ) as $status ) {
						if ( ! in_array( $status, $q_status, true ) ) {
							$e_status[] = "{$wpdb->ald_posts}.post_status <> '$status'";
						}
					}
				} else {
					foreach ( get_post_stati() as $status ) {
						if ( in_array( $status, $q_status, true ) ) {
							if ( 'private' === $status ) {
								$p_status[] = "{$wpdb->ald_posts}.post_status = '$status'";
							} else {
								$r_status[] = "{$wpdb->ald_posts}.post_status = '$status'";
							}
						}
					}
				}

				if ( empty( $q['perm'] ) || 'readable' !== $q['perm'] ) {
					$r_status = array_merge( $r_status, $p_status );
					unset( $p_status );
				}

				if ( ! empty( $e_status ) ) {
					$statuswheres[] = '(' . implode( ' AND ', $e_status ) . ')';
				}
				if ( ! empty( $r_status ) ) {
					if ( ! empty( $q['perm'] ) && 'editable' === $q['perm'] && ! current_user_can( $edit_others_cap ) ) {
						$statuswheres[] = "({$wpdb->ald_posts}.post_author = $user_id " . 'AND (' . implode( ' OR ', $r_status ) . '))';
					} else {
						$statuswheres[] = '(' . implode( ' OR ', $r_status ) . ')';
					}
				}
				if ( ! empty( $p_status ) ) {
					if ( ! empty( $q['perm'] ) && 'readable' === $q['perm'] && ! current_user_can( $read_private_cap ) ) {
						$statuswheres[] = "({$wpdb->ald_posts}.post_author = $user_id " . 'AND (' . implode( ' OR ', $p_status ) . '))';
					} else {
						$statuswheres[] = '(' . implode( ' OR ', $p_status ) . ')';
					}
				}
				if ( $post_status_join ) {
					$join .= " LEFT JOIN {$wpdb->ald_posts} AS p2 ON ({$wpdb->ald_posts}.post_parent = p2.ID) ";
					foreach ( $statuswheres as $index => $statuswhere ) {
						$statuswheres[ $index ] = "($statuswhere OR ({$wpdb->ald_posts}.post_status = 'inherit' AND " . str_replace( $wpdb->ald_posts, 'p2', $statuswhere ) . '))';
					}
				}
				$where_status = implode( ' OR ', $statuswheres );
				if ( ! empty( $where_status ) ) {
					$where .= " AND ($where_status)";
				}
			} elseif ( ! $this->is_singular ) {
				if ( 'any' === $post_type ) {
					$queried_post_types = get_post_types( array( 'exclude_from_search' => false ) );
				} elseif ( is_array( $post_type ) ) {
					$queried_post_types = $post_type;
				} elseif ( ! empty( $post_type ) ) {
					$queried_post_types = array( $post_type );
				} else {
					$queried_post_types = array( 'post' );
				}

				if ( ! empty( $queried_post_types ) ) {

					$status_type_clauses = array();

					foreach ( $queried_post_types as $queried_post_type ) {

						$queried_post_type_object = get_post_type_object( $queried_post_type );

						$type_where = '(' . $wpdb->prepare( "{$wpdb->ald_posts}.post_type = %s AND (", $queried_post_type );

						// Public statuses.
						$public_statuses = get_post_stati( array( 'public' => true ) );
						$status_clauses  = array();
						foreach ( $public_statuses as $public_status ) {
							$status_clauses[] = "{$wpdb->ald_posts}.post_status = '$public_status'";
						}
						$type_where .= implode( ' OR ', $status_clauses );

						// Add protected states that should show in the admin all list.
						if ( $this->is_admin ) {
							$admin_all_statuses = get_post_stati(
								array(
									'protected'              => true,
									'show_in_admin_all_list' => true,
								)
							);
							foreach ( $admin_all_statuses as $admin_all_status ) {
								$type_where .= " OR {$wpdb->ald_posts}.post_status = '$admin_all_status'";
							}
						}

						// Add private states that are visible to current user.
						if ( is_user_logged_in() && $queried_post_type_object instanceof WP_Post_Type ) {
							$read_private_cap = $queried_post_type_object->cap->read_private_posts;
							$private_statuses = get_post_stati( array( 'private' => true ) );
							foreach ( $private_statuses as $private_status ) {
								$type_where .= current_user_can( $read_private_cap ) ? " \nOR {$wpdb->ald_posts}.post_status = '$private_status'" : " \nOR ({$wpdb->ald_posts}.post_author = $user_id AND {$wpdb->ald_posts}.post_status = '$private_status')";
							}
						}

						$type_where .= '))';

						$status_type_clauses[] = $type_where;
					}

					if ( ! empty( $status_type_clauses ) ) {
						$where .= ' AND (' . implode( ' OR ', $status_type_clauses ) . ')';
					}
				} else {
					$where .= ' AND 1=0 ';
				}
			} else {
				$where .= $post_type_where;
			}

			if ( ! $q['suppress_filters'] ) {
				$where = apply_filters_ref_array( 'posts_where', array( $where, &$this ) );
				$join  = apply_filters_ref_array( 'posts_join', array( $join, &$this ) );
			}

			// Paging.
			if ( empty( $q['nopaging'] ) && ! $this->is_singular ) {
				$page = absint( $q['paged'] );
				if ( ! $page ) {
					$page = 1;
				}

				// If 'offset' is provided, it takes precedence over 'paged'.
				if ( isset( $q['offset'] ) && is_numeric( $q['offset'] ) ) {
					$q['offset'] = absint( $q['offset'] );
					$pgstrt      = $q['offset'] . ', ';
				} else {
					$pgstrt = absint( ( $page - 1 ) * $q['posts_per_page'] ) . ', ';
				}
				$limits = 'LIMIT ' . $pgstrt . $q['posts_per_page'];
			}

			$pieces = array( 'where', 'groupby', 'join', 'orderby', 'distinct', 'fields', 'limits' );

			/*
			 * Apply post-paging filters on where and join. Only plugins that
			 * manipulate paging queries should use these hooks.
			 */
			if ( ! $q['suppress_filters'] ) {
				$where    = apply_filters_ref_array( 'posts_where_paged', array( $where, &$this ) );
				$groupby  = apply_filters_ref_array( 'posts_groupby', array( $groupby, &$this ) );
				$join     = apply_filters_ref_array( 'posts_join_paged', array( $join, &$this ) );
				$orderby  = apply_filters_ref_array( 'posts_orderby', array( $orderby, &$this ) );
				$distinct = apply_filters_ref_array( 'posts_distinct', array( $distinct, &$this ) );
				$limits   = apply_filters_ref_array( 'post_limits', array( $limits, &$this ) );
				$fields   = apply_filters_ref_array( 'posts_fields', array( $fields, &$this ) );
				$clauses  = (array) apply_filters_ref_array( 'posts_clauses', array( compact( $pieces ), &$this ) );

				$where    = isset( $clauses['where'] ) ? $clauses['where'] : '';
				$groupby  = isset( $clauses['groupby'] ) ? $clauses['groupby'] : '';
				$join     = isset( $clauses['join'] ) ? $clauses['join'] : '';
				$orderby  = isset( $clauses['orderby'] ) ? $clauses['orderby'] : '';
				$distinct = isset( $clauses['distinct'] ) ? $clauses['distinct'] : '';
				$fields   = isset( $clauses['fields'] ) ? $clauses['fields'] : '';
				$limits   = isset( $clauses['limits'] ) ? $clauses['limits'] : '';
			}
//		do_action( 'posts_selection', $where . $groupby . $orderby . $limits . $join );
			if ( ! $q['suppress_filters'] ) {
				$where    = apply_filters_ref_array( 'posts_where_request', array( $where, &$this ) );
				$groupby  = apply_filters_ref_array( 'posts_groupby_request', array( $groupby, &$this ) );
				$join     = apply_filters_ref_array( 'posts_join_request', array( $join, &$this ) );
				$orderby  = apply_filters_ref_array( 'posts_orderby_request', array( $orderby, &$this ) );
				$distinct = apply_filters_ref_array( 'posts_distinct_request', array( $distinct, &$this ) );
				$fields   = apply_filters_ref_array( 'posts_fields_request', array( $fields, &$this ) );
				$limits   = apply_filters_ref_array( 'post_limits_request', array( $limits, &$this ) );
				$clauses  = (array) apply_filters_ref_array( 'posts_clauses_request', array( compact( $pieces ), &$this ) );

				$where    = isset( $clauses['where'] ) ? $clauses['where'] : '';
				$groupby  = isset( $clauses['groupby'] ) ? $clauses['groupby'] : '';
				$join     = isset( $clauses['join'] ) ? $clauses['join'] : '';
				$orderby  = isset( $clauses['orderby'] ) ? $clauses['orderby'] : '';
				$distinct = isset( $clauses['distinct'] ) ? $clauses['distinct'] : '';
				$fields   = isset( $clauses['fields'] ) ? $clauses['fields'] : '';
				$limits   = isset( $clauses['limits'] ) ? $clauses['limits'] : '';
			}

			if ( ! empty( $groupby ) ) {
				$groupby = 'GROUP BY ' . $groupby;
			}
			if ( ! empty( $orderby ) ) {
				$orderby = 'ORDER BY ' . $orderby;
			}

			$found_rows = '';
			if ( ! $q['no_found_rows'] && ! empty( $limits ) ) {
				$found_rows = 'SQL_CALC_FOUND_ROWS';
			}

			$old_request = "
			SELECT $found_rows $distinct $fields
			FROM {$wpdb->ald_posts} $join
			WHERE 1=1 $where
			$groupby
			$orderby
			$limits
		";

			$this->request = $old_request;

			if ( ! $q['suppress_filters'] ) {
				$this->request = apply_filters_ref_array( 'posts_request', array( $this->request, &$this ) );
			}

			$this->posts = apply_filters_ref_array( 'posts_pre_query', array( null, &$this ) );

			$id_query_is_cacheable = ! str_contains( strtoupper( $orderby ), ' RAND(' );

			$cacheable_field_values = array(
				"{$wpdb->ald_posts}.*",
				"{$wpdb->ald_posts}.ID, {$wpdb->ald_posts}.post_parent",
				"{$wpdb->ald_posts}.ID",
			);

			if ( ! in_array( $fields, $cacheable_field_values, true ) ) {
				$id_query_is_cacheable = false;
			}

			if ( $q['cache_results'] && $id_query_is_cacheable ) {
				$new_request = str_replace( $fields, "{$wpdb->ald_posts}.*", $this->request );
				$cache_key   = $this->generate_cache_key( $q, $new_request );

				$cache_found = false;
				if ( null === $this->posts ) {
					$cached_results = wp_cache_get( $cache_key, 'ald_posts', false, $cache_found );

					if ( $cached_results ) {
						if ( 'ids' === $q['fields'] ) {
							/** @var int[] */
							$this->posts = array_map( 'intval', $cached_results['posts'] );
						} else {
							_prime_post_caches( $cached_results['posts'], $q['update_post_term_cache'], $q['update_post_meta_cache'] );
							/** @var WP_Post[] */
							$this->posts = array_map( 'get_post', $cached_results['posts'] );
						}

						$this->post_count    = count( $this->posts );
						$this->found_posts   = $cached_results['found_posts'];
						$this->max_num_pages = $cached_results['max_num_pages'];

						if ( 'ids' === $q['fields'] ) {
							return $this->posts;
						} elseif ( 'id=>parent' === $q['fields'] ) {
							/** @var int[] */
							$post_parents = array();

							foreach ( $this->posts as $key => $post ) {
								$obj              = new stdClass();
								$obj->ID          = (int) $post->ID;
								$obj->post_parent = (int) $post->post_parent;

								$this->posts[ $key ] = $obj;

								$post_parents[ $obj->ID ] = $obj->post_parent;
							}

							return $post_parents;
						}
					}
				}
			}

			if ( 'ids' === $q['fields'] ) {
				if ( null === $this->posts ) {
					$this->posts = $wpdb->get_col( $this->request );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery
				}

				/** @var int[] */
				$this->posts      = array_map( 'intval', $this->posts );
				$this->post_count = count( $this->posts );

				$this->set_found_posts( $q, $limits );

				if ( $q['cache_results'] && $id_query_is_cacheable ) {
					$cache_value = array(
						'posts'         => $this->posts,
						'found_posts'   => $this->found_posts,
						'max_num_pages' => $this->max_num_pages,
					);

					wp_cache_set( $cache_key, $cache_value, 'ald_posts' );
				}

				return $this->posts;
			}

			if ( 'id=>parent' === $q['fields'] ) {
				if ( null === $this->posts ) {
					$this->posts = $wpdb->get_results( $this->request );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
				}

				$this->post_count = count( $this->posts );
				$this->set_found_posts( $q, $limits );

				/** @var int[] */
				$post_parents = array();
				$post_ids     = array();

				foreach ( $this->posts as $key => $post ) {
					$this->posts[ $key ]->ID          = (int) $post->ID;
					$this->posts[ $key ]->post_parent = (int) $post->post_parent;

					$post_parents[ (int) $post->ID ] = (int) $post->post_parent;
					$post_ids[]                      = (int) $post->ID;
				}

				if ( $q['cache_results'] && $id_query_is_cacheable ) {
					$cache_value = array(
						'posts'         => $post_ids,
						'found_posts'   => $this->found_posts,
						'max_num_pages' => $this->max_num_pages,
					);

					wp_cache_set( $cache_key, $cache_value, 'posts' );
				}

				return $post_parents;
			}

			if ( null === $this->posts ) {
				$split_the_query = ( $old_request == $this->request && "{$wpdb->ald_posts}.*" === $fields && ! empty( $limits ) && $q['posts_per_page'] < 500 );
				$split_the_query = apply_filters( 'split_the_query', $split_the_query, $this );

				if ( $split_the_query ) {
					// First get the IDs and then fill in the objects.

					$this->request = "
					SELECT $found_rows $distinct {$wpdb->ald_posts}.ID
					FROM {$wpdb->ald_posts} $join
					WHERE 1=1 $where
					$groupby
					$orderby
					$limits
				";
					$this->request = apply_filters( 'posts_request_ids', $this->request, $this );

					$post_ids = $wpdb->get_col( $this->request );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery

					if ( $post_ids ) {
						$this->posts = $post_ids;
						$this->set_found_posts( $q, $limits );
						_prime_post_caches( $post_ids, $q['update_post_term_cache'], $q['update_post_meta_cache'] );
					} else {
						$this->posts = array();
					}
				} else {
					$this->posts = $wpdb->get_results( $this->request );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
					$this->set_found_posts( $q, $limits );
				}
			}

			// Convert to WP_Post objects.
			if ( $this->posts ) {
				/** @var WP_Post[] */
				$this->posts = array_map( 'get_post', $this->posts );
			}

			if ( $q['cache_results'] && $id_query_is_cacheable && ! $cache_found ) {
				$post_ids = wp_list_pluck( $this->posts, 'ID' );

				$cache_value = array(
					'posts'         => $post_ids,
					'found_posts'   => $this->found_posts,
					'max_num_pages' => $this->max_num_pages,
				);

				wp_cache_set( $cache_key, $cache_value, 'posts' );
			}

			if ( ! $q['suppress_filters'] ) {
				$this->posts = apply_filters_ref_array( 'posts_results', array( $this->posts, &$this ) );
			}

			if ( ! empty( $this->posts ) && $this->is_comment_feed && $this->is_singular ) {
				/** This filter is documented in wp-includes/query.php */
				$cjoin = apply_filters_ref_array( 'comment_feed_join', array( '', &$this ) );

				/** This filter is documented in wp-includes/query.php */
				$cwhere = apply_filters_ref_array( 'comment_feed_where', array( "WHERE comment_post_ID = '{$this->posts[0]->ID}' AND comment_approved = '1'", &$this ) );

				/** This filter is documented in wp-includes/query.php */
				$cgroupby = apply_filters_ref_array( 'comment_feed_groupby', array( '', &$this ) );
				$cgroupby = ( ! empty( $cgroupby ) ) ? 'GROUP BY ' . $cgroupby : '';

				/** This filter is documented in wp-includes/query.php */
				$corderby = apply_filters_ref_array( 'comment_feed_orderby', array( 'comment_date_gmt DESC', &$this ) );
				$corderby = ( ! empty( $corderby ) ) ? 'ORDER BY ' . $corderby : '';

				/** This filter is documented in wp-includes/query.php */
				$climits = apply_filters_ref_array( 'comment_feed_limits', array( 'LIMIT ' . get_option( 'posts_per_rss' ), &$this ) );

				$comments_request = "SELECT {$wpdb->comments}.comment_ID FROM {$wpdb->comments} $cjoin $cwhere $cgroupby $corderby $climits";

				$comment_key          = md5( $comments_request );
				$comment_last_changed = wp_cache_get_last_changed( 'comment' );

				$comment_cache_key = "comment_feed:$comment_key:$comment_last_changed";
				$comment_ids       = wp_cache_get( $comment_cache_key, 'comment' );
				if ( false === $comment_ids ) {
					$comment_ids = $wpdb->get_col( $comments_request );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery
					wp_cache_add( $comment_cache_key, $comment_ids, 'comment' );
				}
				_prime_comment_caches( $comment_ids, false );

				// Convert to WP_Comment.
				/** @var WP_Comment[] */
				$this->comments      = array_map( 'get_comment', $comment_ids );
				$this->comment_count = count( $this->comments );
			}

			// Check post status to determine if post should be displayed.
			if ( ! empty( $this->posts ) && ( $this->is_single || $this->is_page ) ) {
				$status = get_post_status( $this->posts[0] );

				if ( 'attachment' === $this->posts[0]->post_type && 0 === (int) $this->posts[0]->post_parent ) {
					$this->is_page       = false;
					$this->is_single     = true;
					$this->is_attachment = true;
				}

				// If the post_status was specifically requested, let it pass through.
				if ( ! in_array( $status, $q_status, true ) ) {
					$post_status_obj = get_post_status_object( $status );

					if ( $post_status_obj && ! $post_status_obj->public ) {
						if ( ! is_user_logged_in() ) {
							// User must be logged in to view unpublished posts.
							$this->posts = array();
						} else {
							if ( $post_status_obj->protected ) {
								// User must have edit permissions on the draft to preview.
								if ( ! current_user_can( $edit_cap, $this->posts[0]->ID ) ) {
									$this->posts = array();
								} else {
									$this->is_preview = true;
									if ( 'future' !== $status ) {
										$this->posts[0]->post_date = current_time( 'mysql' );
									}
								}
							} elseif ( $post_status_obj->private ) {
								if ( ! current_user_can( $read_cap, $this->posts[0]->ID ) ) {
									$this->posts = array();
								}
							} else {
								$this->posts = array();
							}
						}
					} elseif ( ! $post_status_obj ) {
						// Post status is not registered, assume it's not public.
						if ( ! current_user_can( $edit_cap, $this->posts[0]->ID ) ) {
							$this->posts = array();
						}
					}
				}

				if ( $this->is_preview && $this->posts && current_user_can( $edit_cap, $this->posts[0]->ID ) ) {
					$this->posts[0] = get_post( apply_filters_ref_array( 'the_preview', array( $this->posts[0], &$this ) ) );
				}
			}

			// Put sticky posts at the top of the posts array.
			$sticky_posts = get_option( 'sticky_posts' );
			if ( $this->is_home && $page <= 1 && is_array( $sticky_posts ) && ! empty( $sticky_posts ) && ! $q['ignore_sticky_posts'] ) {
				$num_posts     = count( $this->posts );
				$sticky_offset = 0;
				// Loop over posts and relocate stickies to the front.
				for ( $i = 0; $i < $num_posts; $i ++ ) {
					if ( in_array( $this->posts[ $i ]->ID, $sticky_posts, true ) ) {
						$sticky_post = $this->posts[ $i ];
						// Remove sticky from current position.
						array_splice( $this->posts, $i, 1 );
						// Move to front, after other stickies.
						array_splice( $this->posts, $sticky_offset, 0, array( $sticky_post ) );
						// Increment the sticky offset. The next sticky will be placed at this offset.
						$sticky_offset ++;
						// Remove post from sticky posts array.
						$offset = array_search( $sticky_post->ID, $sticky_posts, true );
						unset( $sticky_posts[ $offset ] );
					}
				}

				// If any posts have been excluded specifically, Ignore those that are sticky.
				if ( ! empty( $sticky_posts ) && ! empty( $q['post__not_in'] ) ) {
					$sticky_posts = array_diff( $sticky_posts, $q['post__not_in'] );
				}

				// Fetch sticky posts that weren't in the query results.
				if ( ! empty( $sticky_posts ) ) {
					$stickies = get_posts(
						array(
							'post__in'               => $sticky_posts,
							'post_type'              => $post_type,
							'post_status'            => 'publish',
							'posts_per_page'         => count( $sticky_posts ),
							'suppress_filters'       => $q['suppress_filters'],
							'cache_results'          => $q['cache_results'],
							'update_post_meta_cache' => $q['update_post_meta_cache'],
							'update_post_term_cache' => $q['update_post_term_cache'],
							'lazy_load_term_meta'    => $q['lazy_load_term_meta'],
						)
					);

					foreach ( $stickies as $sticky_post ) {
						array_splice( $this->posts, $sticky_offset, 0, array( $sticky_post ) );
						$sticky_offset ++;
					}
				}
			}

			if ( ! $q['suppress_filters'] ) {
				$this->posts = apply_filters_ref_array( 'the_posts', array( $this->posts, &$this ) );
			}

			// Ensure that any posts added/modified via one of the filters above are
			// of the type WP_Post and are filtered.
			if ( $this->posts ) {
				$this->post_count = count( $this->posts );

				/** @var WP_Post[] */
				$this->posts = array_map( 'get_post', $this->posts );

				if ( $q['cache_results'] ) {
					update_post_caches( $this->posts, $post_type, $q['update_post_term_cache'], $q['update_post_meta_cache'] );
				}

				/** @var WP_Post */
				$this->post = reset( $this->posts );
			} else {
				$this->post_count = 0;
				$this->posts      = array();
			}

			if ( ! empty( $this->posts ) && $q['update_menu_item_cache'] ) {
				update_menu_item_cache( $this->posts );
			}

			if ( $q['lazy_load_term_meta'] ) {
				wp_queue_posts_for_term_meta_lazyload( $this->posts );
			}

			return $this->posts;
		}

	}
}
