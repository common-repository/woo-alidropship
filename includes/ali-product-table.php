<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Ali_Product_Table' ) ) {
	class Ali_Product_Table {

		public static function create_table() {
			if ( ! function_exists( 'dbDelta' ) ) {
				require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			}

			global $wpdb;
			$max_index_length = 191;
			$collate          = $wpdb->has_cap( 'collation' ) ? $wpdb->get_charset_collate() : '';

			$query = "create table if not exists {$wpdb->prefix}ald_posts
				(
				    ID                    bigint unsigned auto_increment		        primary key,
				    post_author           bigint unsigned default 0                     not null,
				    post_date             datetime        default '0000-00-00 00:00:00' not null,
				    post_date_gmt         datetime        default '0000-00-00 00:00:00' not null,
				    post_content          longtext                                      not null,
				    post_title            text                                          not null,
				    post_excerpt          text                                          not null,
				    post_status           varchar(20)     default 'publish'             not null,
				    comment_status        varchar(20)     default 'open'                not null,
				    ping_status           varchar(20)     default 'open'                not null,
				    post_password         varchar(255)    default ''                    not null,
				    post_name             varchar(200)    default ''                    not null,
				    to_ping               text                                          not null,
				    pinged                text                                          not null,
				    post_modified         datetime        default '0000-00-00 00:00:00' not null,
				    post_modified_gmt     datetime        default '0000-00-00 00:00:00' not null,
				    post_content_filtered longtext                                      not null,
				    post_parent           bigint unsigned default 0                     not null,
				    guid                  varchar(255)    default ''                    not null,
				    menu_order            int             default 0                     not null,
				    post_type             varchar(20)     default 'post'                not null,
				    post_mime_type        varchar(100)    default ''                    not null,
				    comment_count         bigint          default 0                     not null,
					KEY post_name (post_name($max_index_length)),
					KEY type_status_date (post_type,post_status,post_date,ID),
					KEY post_parent (post_parent),
					KEY post_author (post_author)
				) {$collate}";

			$query_meta = "create table if not exists {$wpdb->prefix}ald_postmeta
				(
				    meta_id bigint(20) unsigned NOT NULL auto_increment,
					ald_post_id bigint(20) unsigned NOT NULL default '0',
					meta_key varchar(255) default NULL,
					meta_value longtext,
					PRIMARY KEY  (meta_id),
					KEY ald_post_id (ald_post_id),
					KEY meta_key (meta_key($max_index_length))
				) {$collate}";

			$wpdb->query( $query );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->query( $query_meta );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

		}

		public static function _add_post_meta( $post_id, $meta_key, $meta_value, $unique = false ) {
			return add_metadata( 'ald_post', $post_id, $meta_key, $meta_value, $unique );
		}

		public static function _get_post_meta( $post_id, $key = '', $single = false ) {
			return get_metadata( 'ald_post', $post_id, $key, $single );
		}

		public static function _delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
			return delete_metadata( 'ald_post', $post_id, $meta_key, $meta_value );
		}

		public static function _update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
			return update_metadata( 'ald_post', $post_id, $meta_key, $meta_value, $prev_value );
		}

		public static function get_post_meta( $post_id, $key = '', $single = false ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table()
				? self::_get_post_meta( $post_id, $key, $single )
				: get_post_meta( $post_id, $key, $single );
		}

		public static function update_post_meta( $post_id, $meta_key, $meta_value, $prev_value = '' ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table()
				? self::_update_post_meta( $post_id, $meta_key, $meta_value, $prev_value )
				: update_post_meta( $post_id, $meta_key, $meta_value, $prev_value );
		}

		public static function delete_post_meta( $post_id, $meta_key, $meta_value = '' ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table()
				? self::_delete_post_meta( $post_id, $meta_key, $meta_value )
				: delete_post_meta( $post_id, $meta_key, $meta_value );
		}

		public static function _get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
			if ( ! $post ) {
				return null;
			}

			if ( $post instanceof ALD_Post ) {
				$_post = $post;
			} elseif ( is_object( $post ) ) {
				if ( empty( $post->filter ) ) {
					$_post = sanitize_post( $post, 'raw' );
					$_post = new WP_Post( $_post );
				} elseif ( 'raw' === $post->filter ) {
					$_post = new ALD_Post( $post );
				} else {
					$_post = ALD_Post::get_instance( $post->ID );
				}
			} else {
				$_post = ALD_Post::get_instance( $post );
			}

			if ( ! $_post ) {
				return null;
			}

			$_post = $_post->filter( $filter );

			if ( ARRAY_A === $output ) {
				return $_post->to_array();
			} elseif ( ARRAY_N === $output ) {
				return array_values( $_post->to_array() );
			}

			return $_post;
		}

		public static function get_post( $post = null, $output = OBJECT, $filter = 'raw' ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ? self::_get_post( $post, $output, $filter ) : get_post( $post, $output, $filter );
		}

		public static function insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
			global $wpdb;

			// Capture original pre-sanitized array for passing into filters.
			$unsanitized_postarr = $postarr;

			$user_id = get_current_user_id();

			$defaults = array(
				'post_author'           => $user_id,
				'post_content'          => '',
				'post_content_filtered' => '',
				'post_title'            => '',
				'post_excerpt'          => '',
				'post_status'           => 'draft',
				'post_type'             => 'post',
				'comment_status'        => '',
				'ping_status'           => '',
				'post_password'         => '',
				'to_ping'               => '',
				'pinged'                => '',
				'post_parent'           => 0,
				'menu_order'            => 0,
				'guid'                  => '',
				'import_id'             => 0,
				'context'               => '',
				'post_date'             => '',
				'post_date_gmt'         => '',
			);

			$postarr = wp_parse_args( $postarr, $defaults );

			unset( $postarr['filter'] );

			$postarr = sanitize_post( $postarr, 'db' );

			// Are we updating or creating?
			$post_id = 0;
			$update  = false;
			$guid    = $postarr['guid'];

			if ( ! empty( $postarr['ID'] ) ) {
				$update = true;

				// Get the post ID and GUID.
				$post_id     = $postarr['ID'];
				$post_before = self::_get_post( $post_id );

				if ( is_null( $post_before ) ) {
					if ( $wp_error ) {
						return new WP_Error( 'invalid_post', __( 'Invalid post ID.' ) );
					}

					return 0;
				}

				$guid            = self::get_post_field( 'guid', $post_id );
				$previous_status = self::get_post_field( 'post_status', $post_id );
			} else {
				$previous_status = 'new';
				$post_before     = null;
			}

			$post_type = empty( $postarr['post_type'] ) ? 'post' : $postarr['post_type'];

			$post_title   = $postarr['post_title'];
			$post_content = $postarr['post_content'];
			$post_excerpt = $postarr['post_excerpt'];

			if ( isset( $postarr['post_name'] ) ) {
				$post_name = $postarr['post_name'];
			} elseif ( $update ) {
				// For an update, don't modify the post_name if it wasn't supplied as an argument.
				$post_name = $post_before->post_name;
			}

			$maybe_empty = 'attachment' !== $post_type && ! $post_content && ! $post_title && ! $post_excerpt;

			if ( apply_filters( 'wp_insert_post_empty_content', $maybe_empty, $postarr ) ) {
				return $wp_error ? new WP_Error( 'empty_content', __( 'Content, title, and excerpt are empty.' ) ) : 0;
			}

			$post_status = empty( $postarr['post_status'] ) ? 'draft' : $postarr['post_status'];

			if ( ! empty( $postarr['post_category'] ) ) {
				// Filter out empty terms.
				$post_category = array_filter( $postarr['post_category'] );
			} elseif ( $update && ! isset( $postarr['post_category'] ) ) {
				$post_category = $post_before->post_category;
			}

			// Make sure we set a valid category.
			if ( empty( $post_category ) || 0 === count( $post_category ) || ! is_array( $post_category ) ) {
				// 'post' requires at least one category.
				if ( 'post' === $post_type && 'auto-draft' !== $post_status ) {
					$post_category = array( get_option( 'default_category' ) );
				} else {
					$post_category = array();
				}
			}

			if ( 'pending' === $post_status ) { //wait
				$post_type_object = get_post_type_object( $post_type );

				if ( ! $update && $post_type_object && ! current_user_can( $post_type_object->cap->publish_posts ) ) {
					$post_name = '';
				} elseif ( $update && ! current_user_can( 'publish_post', $post_id ) ) {
					$post_name = '';
				}
			}

			/*
			 * Create a valid post name. Drafts and pending posts are allowed to have
			 * an empty post name.
			 */
			if ( empty( $post_name ) ) {
				if ( ! in_array( $post_status, array( 'draft', 'pending', 'auto-draft' ), true ) ) {
					$post_name = sanitize_title( $post_title );
				} else {
					$post_name = '';
				}
			} else {
				// On updates, we need to check to see if it's using the old, fixed sanitization context.
				$check_name = sanitize_title( $post_name, '', 'old-save' );

				if ( $update && strtolower( urlencode( $post_name ) ) == $check_name && self::get_post_field( 'post_name', $post_id ) == $check_name ) {
					$post_name = $check_name;
				} else { // New post, or slug has changed.
					$post_name = sanitize_title( $post_name );
				}
			}
			/*
			 * Resolve the post date from any provided post date or post date GMT strings;
			 * if none are provided, the date will be set to now.
			 */

			$post_date = wp_resolve_post_date( $postarr['post_date'], $postarr['post_date_gmt'] );

			if ( ! $post_date ) {
				if ( $wp_error ) {
					return new WP_Error( 'invalid_date', __( 'Invalid date.' ) );
				} else {
					return 0;
				}
			}

			if ( empty( $postarr['post_date_gmt'] ) || '0000-00-00 00:00:00' === $postarr['post_date_gmt'] ) {
				if ( ! in_array( $post_status, get_post_stati( array( 'date_floating' => true ) ), true ) ) {
					$post_date_gmt = get_gmt_from_date( $post_date );
				} else {
					$post_date_gmt = '0000-00-00 00:00:00';
				}
			} else {
				$post_date_gmt = $postarr['post_date_gmt'];
			}

			if ( $update || '0000-00-00 00:00:00' === $post_date ) {
				$post_modified     = current_time( 'mysql' );
				$post_modified_gmt = current_time( 'mysql', 1 );
			} else {
				$post_modified     = $post_date;
				$post_modified_gmt = $post_date_gmt;
			}

			if ( 'attachment' !== $post_type ) {
				$now = gmdate( 'Y-m-d H:i:s' );

				if ( 'publish' === $post_status ) {
					if ( strtotime( $post_date_gmt ) - strtotime( $now ) >= MINUTE_IN_SECONDS ) {
						$post_status = 'future';
					}
				} elseif ( 'future' === $post_status ) {
					if ( strtotime( $post_date_gmt ) - strtotime( $now ) < MINUTE_IN_SECONDS ) {
						$post_status = 'publish';
					}
				}
			}

			// Comment status.
			if ( empty( $postarr['comment_status'] ) ) {
				if ( $update ) {
					$comment_status = 'closed';
				} else {
					$comment_status = get_default_comment_status( $post_type );
				}
			} else {
				$comment_status = $postarr['comment_status'];
			}

			// These variables are needed by compact() later.
			$post_content_filtered = $postarr['post_content_filtered'];
			$post_author           = isset( $postarr['post_author'] ) ? $postarr['post_author'] : $user_id;
			$ping_status           = empty( $postarr['ping_status'] ) ? get_default_comment_status( $post_type, 'pingback' ) : $postarr['ping_status'];
			$to_ping               = isset( $postarr['to_ping'] ) ? sanitize_trackback_urls( $postarr['to_ping'] ) : '';
			$pinged                = isset( $postarr['pinged'] ) ? $postarr['pinged'] : '';
			$import_id             = isset( $postarr['import_id'] ) ? $postarr['import_id'] : 0;
			$menu_order            = $postarr['menu_order'] ? (int) $postarr['menu_order'] : 0;

			$post_password = isset( $postarr['post_password'] ) ? $postarr['post_password'] : '';
			if ( 'private' === $post_status ) {
				$post_password = '';
			}

			$post_parent = isset( $postarr['post_parent'] ) ? (int) $postarr['post_parent'] : 0;

			$new_postarr = array_merge(
				array( 'ID' => $post_id ),
				compact( array_diff( array_keys( $defaults ), array( 'context', 'filter' ) ) )
			);

			$post_parent = apply_filters( 'wp_insert_post_parent', $post_parent, $post_id, $new_postarr, $postarr );

			if ( 'trash' === $previous_status && 'trash' !== $post_status ) {
				$desired_post_slug = self::_get_post_meta( $post_id, '_wp_desired_post_slug', true );

				if ( $desired_post_slug ) {
					self::_delete_post_meta( $post_id, '_wp_desired_post_slug' );
					$post_name = $desired_post_slug;
				}
			}

			// If a trashed post has the desired slug, change it and let this post have it.
			if ( 'trash' !== $post_status && $post_name ) {
				/**
				 * Filters whether or not to add a `__trashed` suffix to trashed posts that match the name of the updated post.
				 *
				 * @param bool $add_trashed_suffix Whether to attempt to add the suffix.
				 * @param string $post_name The name of the post being updated.
				 * @param int $post_id Post ID.
				 *
				 * @since 5.4.0
				 *
				 */
				$add_trashed_suffix = apply_filters( 'add_trashed_suffix_to_trashed_posts', true, $post_name, $post_id );

				if ( $add_trashed_suffix ) {
//				wp_add_trashed_suffix_to_post_name_for_trashed_posts( $post_name, $post_id );
				}
			}

			// When trashing an existing post, change its slug to allow non-trashed posts to use it.
			if ( 'trash' === $post_status && 'trash' !== $previous_status && 'new' !== $previous_status ) {
//			$post_name = wp_add_trashed_suffix_to_post_name_for_post( $post_id );
			}

//		$post_name = wp_unique_post_slug( $post_name, $post_id, $post_status, $post_type, $post_parent );

			// Don't unslash.
			$post_mime_type = isset( $postarr['post_mime_type'] ) ? $postarr['post_mime_type'] : '';

			// Expected_slashed (everything!).
			$data = compact(
				'post_author',
				'post_date',
				'post_date_gmt',
				'post_content',
				'post_content_filtered',
				'post_title',
				'post_excerpt',
				'post_status',
				'post_type',
				'comment_status',
				'ping_status',
				'post_password',
				'post_name',
				'to_ping',
				'pinged',
				'post_modified',
				'post_modified_gmt',
				'post_parent',
				'menu_order',
				'post_mime_type',
				'guid'
			);

			$emoji_fields = array( 'post_title', 'post_content', 'post_excerpt' );

//		foreach ( $emoji_fields as $emoji_field ) {
//			if ( isset( $data[ $emoji_field ] ) ) {
//				$charset = $wpdb->get_col_charset( $wpdb->posts, $emoji_field );
//
//				if ( 'utf8' === $charset ) {
//					$data[ $emoji_field ] = wp_encode_emoji( $data[ $emoji_field ] );
//				}
//			}
//		}


			$data  = wp_unslash( $data );
			$where = array( 'ID' => $post_id );

			if ( $update ) {
				/**
				 * Fires immediately before an existing post is updated in the database.
				 *
				 * @param int $post_id Post ID.
				 * @param array $data Array of unslashed post data.
				 *
				 * @since 2.5.0
				 *
				 */
//			do_action( 'pre_post_update', $post_id, $data );

				if ( false === $wpdb->update( $wpdb->prefix . 'ald_posts', $data, $where ) ) {// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					return $wp_error ? new WP_Error( 'ald_db_update_error', __( 'Could not update post in the database.' ), $wpdb->last_error ) : 0;
				}
			} else {
				// If there is a suggested ID, use it if not already present.
				if ( ! empty( $import_id ) ) {
					$import_id = (int) $import_id;

					if ( ! $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM $wpdb->ald_posts WHERE ID = %d", $import_id ) ) ) {// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
						$data['ID'] = $import_id;
					}
				}

				if ( false === $wpdb->insert( $wpdb->prefix . 'ald_posts', $data ) ) {// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
					return $wp_error ? new WP_Error( 'ald_db_update_error', __( 'Could not insert post into the database.' ), $wpdb->last_error ) : 0;
				}

				$post_id = (int) $wpdb->insert_id;

				// Use the newly generated $post_id.
				$where = array( 'ID' => $post_id );
			}

			if ( empty( $data['post_name'] ) && ! in_array( $data['post_status'], array( 'draft', 'pending', 'auto-draft' ), true ) ) {
//			$data['post_name'] = wp_unique_post_slug( sanitize_title( $data['post_title'], $post_id ), $post_id, $data['post_status'], $post_type, $post_parent );
				$data['post_name'] = sanitize_title( $data['post_title'], $post_id );

				$wpdb->update( $wpdb->prefix . 'ald_posts', array( 'post_name' => $data['post_name'] ), $where );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
//			clean_post_cache( $post_id );
			}

//		if ( is_object_in_taxonomy( $post_type, 'category' ) ) {
//			wp_set_post_categories( $post_id, $post_category );
//		}

//		if ( isset( $postarr['tags_input'] ) && is_object_in_taxonomy( $post_type, 'post_tag' ) ) {
//			wp_set_post_tags( $post_id, $postarr['tags_input'] );
//		}

			// Add default term for all associated custom taxonomies.
//		if ( 'auto-draft' !== $post_status ) {
//			foreach ( get_object_taxonomies( $post_type, 'object' ) as $taxonomy => $tax_object ) {
//
//				if ( ! empty( $tax_object->default_term ) ) {
//
//					// Filter out empty terms.
//					if ( isset( $postarr['tax_input'][ $taxonomy ] ) && is_array( $postarr['tax_input'][ $taxonomy ] ) ) {
//						$postarr['tax_input'][ $taxonomy ] = array_filter( $postarr['tax_input'][ $taxonomy ] );
//					}
//
//					// Passed custom taxonomy list overwrites the existing list if not empty.
//					$terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'ids' ) );
//					if ( ! empty( $terms ) && empty( $postarr['tax_input'][ $taxonomy ] ) ) {
//						$postarr['tax_input'][ $taxonomy ] = $terms;
//					}
//
//					if ( empty( $postarr['tax_input'][ $taxonomy ] ) ) {
//						$default_term_id = get_option( 'default_term_' . $taxonomy );
//						if ( ! empty( $default_term_id ) ) {
//							$postarr['tax_input'][ $taxonomy ] = array( (int) $default_term_id );
//						}
//					}
//				}
//			}
//		}

			// New-style support for all custom taxonomies.
//		if ( ! empty( $postarr['tax_input'] ) ) {
//			foreach ( $postarr['tax_input'] as $taxonomy => $tags ) {
//				$taxonomy_obj = get_taxonomy( $taxonomy );
//
//				if ( ! $taxonomy_obj ) {
//					/* translators: %s: Taxonomy name. */
//					_doing_it_wrong( __FUNCTION__, sprintf( __( 'Invalid taxonomy: %s.' ), $taxonomy ), '4.4.0' );
//					continue;
//				}
//
//				// array = hierarchical, string = non-hierarchical.
//				if ( is_array( $tags ) ) {
//					$tags = array_filter( $tags );
//				}
//
//				if ( current_user_can( $taxonomy_obj->cap->assign_terms ) ) {
//					wp_set_post_terms( $post_id, $tags, $taxonomy );
//				}
//			}
//		}

			if ( ! empty( $postarr['meta_input'] ) ) {
				foreach ( $postarr['meta_input'] as $field => $value ) {
					self::_update_post_meta( $post_id, $field, $value );
				}
			}


			// Set or remove featured image.
//		if ( isset( $postarr['_thumbnail_id'] ) ) {
//			$thumbnail_support = current_theme_supports( 'post-thumbnails', $post_type ) && post_type_supports( $post_type, 'thumbnail' ) || 'revision' === $post_type;
//
//			if ( ! $thumbnail_support && 'attachment' === $post_type && $post_mime_type ) {
//				if ( wp_attachment_is( 'audio', $post_id ) ) {
//					$thumbnail_support = post_type_supports( 'attachment:audio', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:audio' );
//				} elseif ( wp_attachment_is( 'video', $post_id ) ) {
//					$thumbnail_support = post_type_supports( 'attachment:video', 'thumbnail' ) || current_theme_supports( 'post-thumbnails', 'attachment:video' );
//				}
//			}
//
//			if ( $thumbnail_support ) {
//				$thumbnail_id = (int) $postarr['_thumbnail_id'];
//				if ( - 1 === $thumbnail_id ) {
//					delete_post_thumbnail( $post_id );
//				} else {
//					set_post_thumbnail( $post_id, $thumbnail_id );
//				}
//			}
//		}

//		clean_post_cache( $post_id );

			$post = self::_get_post( $post_id );

			if ( ! empty( $postarr['page_template'] ) ) {
				$post->page_template = $postarr['page_template'];
				$page_templates      = wp_get_theme()->get_page_templates( $post );

				if ( 'default' !== $postarr['page_template'] && ! isset( $page_templates[ $postarr['page_template'] ] ) ) {
					if ( $wp_error ) {
						return new WP_Error( 'invalid_page_template', __( 'Invalid page template.' ) );
					}

					self::_update_post_meta( $post_id, '_wp_page_template', 'default' );
				} else {
					self::_update_post_meta( $post_id, '_wp_page_template', $postarr['page_template'] );
				}
			}

			return $post_id;
		}

		public static function wp_insert_post( $postarr, $wp_error = false, $fire_after_hooks = true ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ? self::insert_post( $postarr, $wp_error, $fire_after_hooks ) : wp_insert_post( $postarr, $wp_error, $fire_after_hooks );
		}

		public static function wp_update_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table()
				? self::update_post( $postarr, $wp_error, $fire_after_hooks )
				: wp_update_post( $postarr, $wp_error, $fire_after_hooks );
		}

		public static function wp_delete_post( $postid = 0, $force_delete = false ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table()
				? self::delete_post( $postid, $force_delete )
				: wp_delete_post( $postid, $force_delete );
		}

		public static function update_post( $postarr = array(), $wp_error = false, $fire_after_hooks = true ) {
			if ( is_object( $postarr ) ) {
				// Non-escaped post was passed.
				$postarr = get_object_vars( $postarr );
				$postarr = wp_slash( $postarr );
			}

			// First, get all of the original fields.
			$post = self::get_post( $postarr['ID'], ARRAY_A );

			if ( is_null( $post ) ) {
				if ( $wp_error ) {
					return new WP_Error( 'invalid_post', __( 'Invalid post ID.' ) );
				}

				return 0;
			}

			// Escape data pulled from DB.
			$post = wp_slash( $post );

			// Drafts shouldn't be assigned a date unless explicitly done so by the user.
			if ( isset( $post['post_status'] )
			     && in_array( $post['post_status'], array( 'draft', 'pending', 'auto-draft' ), true )
			     && empty( $postarr['edit_date'] ) && ( '0000-00-00 00:00:00' === $post['post_date_gmt'] )
			) {
				$clear_date = true;
			} else {
				$clear_date = false;
			}

			// Merge old and new fields with new fields overwriting old ones.
			$postarr = array_merge( $post, $postarr );
			if ( $clear_date ) {
				$postarr['post_date']     = current_time( 'mysql' );
				$postarr['post_date_gmt'] = '';
			}

			return self::insert_post( $postarr, $wp_error, $fire_after_hooks );
		}

		public static function delete_post( $postid = 0, $force_delete = false ) {
			global $wpdb;

			$post = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$wpdb->ald_posts} WHERE ID = %d", $postid ) );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! $post ) {
				return $post;
			}
			$post = self::_get_post( $post );

//		if ( ! $force_delete && 'trash' !== get_post_status( $postid ) && EMPTY_TRASH_DAYS ) {
//			return self::trash_post( $postid );
//		}

			$check = apply_filters( 'viwad_pre_delete_post', null, $post, $force_delete );
			if ( null !== $check ) {
				return $check;
			}
//		do_action( 'before_delete_post', $postid, $post );

			self::_delete_post_meta( $postid, '_wp_trash_meta_status' );
			self::_delete_post_meta( $postid, '_wp_trash_meta_time' );

			$parent_data  = array( 'post_parent' => $post->post_parent );
			$parent_where = array( 'post_parent' => $postid );

			if ( is_post_type_hierarchical( $post->post_type ) ) {
				// Point children of this page to its parent, also clean the cache of affected children.
				$children_query = $wpdb->prepare( "SELECT * FROM {$wpdb->ald_posts} WHERE post_parent = %d AND post_type = %s", $postid, $post->post_type );
				$children       = $wpdb->get_results( $children_query );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				if ( $children ) {
					$wpdb->update( $wpdb->ald_posts, $parent_data, $parent_where + array( 'post_type' => $post->post_type ) );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				}
			}

			// Point all attachments to this post up one level.
			$wpdb->update( $wpdb->ald_posts, $parent_data, $parent_where );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			$post_meta_ids = $wpdb->get_col( $wpdb->prepare( "SELECT meta_id FROM {$wpdb->ald_postmeta} WHERE ald_post_id = %d ", $postid ) );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			foreach ( $post_meta_ids as $mid ) {
				delete_metadata_by_mid( 'ald_post', $mid );
			}

//		do_action( 'delete_post', $postid, $post );

			$result = $wpdb->delete( $wpdb->ald_posts, array( 'ID' => $postid ) );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

			if ( ! $result ) {
				return false;
			}

//		do_action( 'deleted_post', $postid, $post );

//		clean_post_cache( $post );

			if ( is_post_type_hierarchical( $post->post_type ) && $children ) {
				foreach ( $children as $child ) {
//				clean_post_cache( $child );
				}
			}

			wp_clear_scheduled_hook( 'publish_future_post', array( $postid ) );

//		do_action( 'after_delete_post', $postid, $post );

			return $post;
		}

		public static function wp_trash_post( $post_id = 0 ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ? self::trash_post( $post_id ) : wp_trash_post( $post_id );
		}

		public static function trash_post( $post_id = 0 ) {
			if ( ! EMPTY_TRASH_DAYS ) {
				return self::delete_post( $post_id, true );
			}

			$post = self::_get_post( $post_id );

			if ( ! $post ) {
				return $post;
			}

			if ( 'trash' === $post->post_status ) {
				return false;
			}

			$check = apply_filters( 'pre_trash_post', null, $post );

			if ( null !== $check ) {
				return $check;
			}

			self::_add_post_meta( $post_id, '_wp_trash_meta_status', $post->post_status );
			self::_add_post_meta( $post_id, '_wp_trash_meta_time', time() );

			$post_updated = self::update_post(
				array(
					'ID'          => $post_id,
					'post_status' => 'trash',
				)
			);

			if ( ! $post_updated ) {
				return false;
			}

//		do_action( 'trashed_post', $post_id );

			return $post;
		}

		public static function get_post_field( $field, $post = null, $context = 'display' ) {
			$post = self::_get_post( $post );

			if ( ! $post ) {
				return '';
			}

			if ( ! isset( $post->$field ) ) {
				return '';
			}

			return sanitize_post_field( $field, $post->$field, $post->ID, $context );
		}

		public static function wp_count_posts( $type = 'post', $perm = '' ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table()
				? self::count_posts( $type, $perm )
				: wp_count_posts( $type, $perm );
		}

		public static function count_posts( $type = 'post', $perm = '' ) {
			global $wpdb;

//		if ( ! post_type_exists( $type ) ) {
//			return new stdClass();
//		}

			$cache_key = _count_posts_cache_key( $type, $perm );

			$counts = wp_cache_get( $cache_key, 'ald_counts' );

			if ( false !== $counts ) {
				// We may have cached this before every status was registered.
				foreach ( get_post_stati() as $status ) {
					if ( ! isset( $counts->{$status} ) ) {
						$counts->{$status} = 0;
					}
				}

				/** This filter is documented in wp-includes/post.php */
				return apply_filters( 'wp_count_posts', $counts, $type, $perm );
			}

			$query = "SELECT post_status, COUNT( * ) AS num_posts FROM {$wpdb->ald_posts} WHERE post_type = %s";

			if ( 'readable' === $perm && is_user_logged_in() ) {
				if ( ! current_user_can( 'read_private_posts' ) ) {
					$query .= $wpdb->prepare(
						" AND (post_status != 'private' OR ( post_author = %d AND post_status = 'private' ))",
						get_current_user_id()
					);
				}
			}

			$query .= ' GROUP BY post_status';

			$results = (array) $wpdb->get_results( $wpdb->prepare( $query, $type ), ARRAY_A );// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared , WordPress.DB.DirectDatabaseQuery.DirectQuery
			$counts  = array_fill_keys( get_post_stati(), 0 );

			foreach ( $results as $row ) {
				$counts[ $row['post_status'] ] = $row['num_posts'];
			}

			$counts = (object) $counts;
			wp_cache_set( $cache_key, $counts, 'ald_counts' );

			/**
			 * Filters the post counts by status for the current post type.
			 *
			 * @param stdClass $counts An object containing the current post_type's post
			 *                         counts by status.
			 * @param string $type Post type.
			 * @param string $perm The permission to determine if the posts are 'readable'
			 *                         by the current user.
			 *
			 * @since 3.7.0
			 *
			 */
			return apply_filters( 'wp_count_posts', $counts, $type, $perm );
		}

		public static function get_post_status( $post = null ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table()
				? self::_get_post_status( $post )
				: get_post_status( $post );
		}

		public static function _get_post_status( $post = null ) {
			$post = self::_get_post( $post );

			if ( ! is_object( $post ) ) {
				return false;
			}

			$post_status = $post->post_status;

			return apply_filters( 'ald_get_post_status', $post_status, $post );
		}

		public static function wp_publish_post( $post ) {
			VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ? self::publish_post( $post ) : wp_publish_post( $post );
		}

		public static function publish_post( $post ) {
			global $wpdb;

			$post = self::_get_post( $post );

			if ( ! $post ) {
				return;
			}

			if ( 'publish' === $post->post_status ) {
				return;
			}

//		$post_before = self::_get_post( $post->ID );


			$wpdb->update( $wpdb->posts, array( 'post_status' => 'publish' ), array( 'ID' => $post->ID ) );// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

//		clean_post_cache( $post->ID );

//		$old_status        = $post->post_status;
//		$post->post_status = 'publish';
//		wp_transition_post_status( 'publish', $old_status, $post );
//
//		/** This action is documented in wp-includes/post.php */
//		do_action( "edit_post_{$post->post_type}", $post->ID, $post );
//
//		/** This action is documented in wp-includes/post.php */
//		do_action( 'edit_post', $post->ID, $post );
//
//		/** This action is documented in wp-includes/post.php */
//		do_action( "save_post_{$post->post_type}", $post->ID, $post, true );
//
//		/** This action is documented in wp-includes/post.php */
//		do_action( 'save_post', $post->ID, $post, true );
//
//		/** This action is documented in wp-includes/post.php */
//		do_action( 'wp_insert_post', $post->ID, $post, true );
//
//		wp_after_insert_post( $post, true, $post_before );
		}

		public static function wp_query( $args ) {
			return VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ? new Ali_Product_Query( $args ) : new WP_Query( $args );
		}

		public static function get_posts( $args = null ) {
			if ( VI_WOO_ALIDROPSHIP_DATA::is_ald_table() ) {
				$defaults = array(
					'numberposts'      => 5,
					'category'         => 0,
					'orderby'          => 'date',
					'order'            => 'DESC',
					'include'          => array(),
					'exclude'          => array(),// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_exclude
					'meta_key'         => '',// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
					'meta_value'       => '',// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
					'post_type'        => 'post',
					'suppress_filters' => true,// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.SuppressFilters_suppress_filters
				);

				$parsed_args = wp_parse_args( $args, $defaults );
				if ( empty( $parsed_args['post_status'] ) ) {
					$parsed_args['post_status'] = ( 'attachment' === $parsed_args['post_type'] ) ? 'inherit' : 'publish';
				}
				if ( ! empty( $parsed_args['numberposts'] ) && empty( $parsed_args['posts_per_page'] ) ) {
					$parsed_args['posts_per_page'] = $parsed_args['numberposts'];
				}
				if ( ! empty( $parsed_args['category'] ) ) {
					$parsed_args['cat'] = $parsed_args['category'];
				}
				if ( ! empty( $parsed_args['include'] ) ) {
					$incposts                      = wp_parse_id_list( $parsed_args['include'] );
					$parsed_args['posts_per_page'] = count( $incposts );  // Only the number of posts included.
					$parsed_args['post__in']       = $incposts;
				} elseif ( ! empty( $parsed_args['exclude'] ) ) {
					$parsed_args['post__not_in'] = wp_parse_id_list( $parsed_args['exclude'] );// phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
				}

				$parsed_args['ignore_sticky_posts'] = true;
				$parsed_args['no_found_rows']       = true;

				$get_posts = new Ali_Product_Query();

				return $get_posts->query( $parsed_args );
			} else {
				return get_posts( $args );
			}
		}

	}
}
