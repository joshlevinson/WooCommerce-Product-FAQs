<?php

namespace Woo_Faqs\CoreAdmin;


/**
 * Bootstraps admin functionality
 *
 * Common pattern that takes care of adding filters and actions
 * Utilizing a time-saving namespace abstraction
 *
 * @since 3.0.0
 *
 * @param $function string The function to bootstrap
 */
function hooks() {
	$n = function ( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	//woocommerce settings "API"
	add_filter( 'woocommerce_settings_tabs_array', $n( 'woocommerce_settings_tabs_array' ), 100 );
	add_action( 'woocommerce_settings_faqs', $n( 'display_settings' ) );
	add_action( 'woocommerce_settings_save_faqs', $n( 'update_settings' ) );

	add_action( 'admin_enqueue_scripts', $n( 'admin_enqueue_scripts' ) );

	//custom view/preview/approve links
	add_filter( 'post_row_actions', $n( 'post_row_actions' ), 10, 2 );

	//custom post table columns
	add_filter( 'manage_edit-' . WOOFAQS_POST_TYPE . '_columns', $n( 'set_custom_edit_columns' ) );

	//custom post table columns content
	add_action( 'manage_' . WOOFAQS_POST_TYPE . '_posts_custom_column', $n( 'custom_column' ), 1, 2 );

	//meta boxes
	add_action( 'add_meta_boxes', $n( 'meta_boxes' ) );

	//save meta
	add_action( 'save_post', $n( 'save_meta' ) );

	//filter for meta boxes' text
	add_filter( 'gettext', $n( 'filter_gettext' ), 10, 3 );

	//add ajax for approving faqs quickly from the post row
	add_action( 'wp_ajax_approve_woo_faq', $n( 'approve_woo_faq' ) );

	//filter the links so they show up on products instead of a single woo_faq-type permalink
	add_filter( 'preview_post_link', $n( 'faq_link_filter' ), 10, 2 );
	add_filter( 'post_type_link', $n( 'faq_link_filter' ), 1, 3 );
}

/**
 * Register this plugin in the WC Settings screen
 *
 * @since    3.0.0
 */
function woocommerce_settings_tabs_array( $tabs ) {
	$tabs['faqs'] = __( 'FAQs', 'woocommerce-faqs' );

	return $tabs;
}

/**
 * Display this plugin's admin settings
 *
 * Hooks into WC's settings api
 * which allows for building, saving, and retrieval of options
 *
 * @since 3.0.0
 */
function display_settings() {
	\WC_Admin_Settings::output_fields( get_settings() );
}

/**
 * Get the settings array
 *
 * Hooks into WC's settings api
 * which allows for building, saving, and retrieval of options
 *
 * @since 3.0.0
 *
 * @return array This plugin's WC Settings
 */
function get_settings() {
	return array(
		array(
			'title' => __( 'General Settings', 'woocommerce-faqs' ),
			'type'  => 'title',
			'id'    => WOOFAQS_OPTIONS_PREFIX . '_general',
		),
		array(
			'title'   => __( 'Expand FAQ content by default', 'woocommerce-faqs' ),
			'id'      => WOOFAQS_OPTIONS_PREFIX . '_expand_faqs',
			'type'    => 'checkbox',
			'default' => 'no',
			'desc'    => __( 'If this is checked, all FAQs will expand when the tab is visible to show the question and answer.',
				'woocommerce-faqs' )
		),
		array(
			'title'   => __( 'Disable asking functionality', 'woocommerce-faqs' ),
			'id'      => WOOFAQS_OPTIONS_PREFIX . '_disable_ask',
			'type'    => 'checkbox',
			'default' => 'no',
			'desc'    => __( 'If this is checked, asking/answering can only be done by someone with priveleges to edit the product.',
				'woocommerce-faqs' )
		),
		array(
			'title' => __( 'FAQ notification email address', 'woocommerce-faqs' ),
			'id'    => WOOFAQS_OPTIONS_PREFIX . '_answerer_email',
			'type'  => 'text',
			'desc'  => __( 'Default (left blank), new FAQ email is sent to the product author.',
				'woocommerce-faqs' )
		),
		array(
			'title' => __( 'FAQ notification from name', 'woocommerce-faqs' ),
			'id'    => WOOFAQS_OPTIONS_PREFIX . '_from_name',
			'type'  => 'text',
			'desc'  => __( 'Default\'s to WordPress default from name.',
				'woocommerce-faqs' )
		),
		array(
			'type' => 'sectionend',
			'id'   => WOOFAQS_OPTIONS_PREFIX . '_general'
		),
		array(
			'title' => __( 'Tab Settings', 'woocommerce-faqs' ),
			'type'  => 'title',
			'id'    => WOOFAQS_OPTIONS_PREFIX . '_tab_settings'
		),
		array(
			'title' => __( 'Tab Title', 'woocommerce-faqs' ),
			'id'    => WOOFAQS_OPTIONS_PREFIX . '_tab_title',
			'type'  => 'text'
		),
		array(
			'title' => __( 'Tab Priority', 'woocommerce-faqs' ),
			'id'    => WOOFAQS_OPTIONS_PREFIX . '_tab_priority',
			'type'  => 'text'
		),
		array(
			'type' => 'sectionend',
			'id'   => WOOFAQS_OPTIONS_PREFIX . '_tab_settings'
		),
	);
}

/**
 * Update this plugin's WC Settings
 *
 * Hooks into WC's settings api
 * which allows for building, saving, and retrieval of options
 *
 * @since 3.0.0
 */
function update_settings() {
	\WC_Admin_Settings::save_fields( get_settings() );
}

/**
 * Enqueue plugin's admin scripts
 *
 * Enqueues scripts on edit.php?post_type=woo_faq
 * Also localizes the highlighted FAQ (if present)
 * so it can be focused on
 *
 * @since 3.0.0
 */
function admin_enqueue_scripts() {
	$screen = get_current_screen();

	//we need to load this script on the edit page for our post type
	if (
		is_object( $screen )
		&& property_exists( $screen, 'id' )
		&& $screen->id == 'edit-' . WOOFAQS_POST_TYPE
	) {
		wp_enqueue_script(
			'woocommerce-faqs-admin-script',
			WOOFAQS_PLUGIN_URL . '/admin/assets/js/admin.js',
			array( 'jquery' ),
			WOOFAQS_VERSION
		);

		$localize = array();

		//if we are administering a faq, localize that so it is available to the javascript
		if ( isset( $_GET['highlight'] ) ) {

			$localize['highlight'] = absint( $_GET['highlight'] );
			//and localize the color with a filter, so it can be changed either by user or maybe later as a settings option
			$localize['highlight_color'] = apply_filters(
				WOOFAQS_OPTIONS_PREFIX . '_admin_faq_highlight_color',
				'#9ED1D6'
			);

		}

		wp_localize_script( 'woocommerce-faqs-admin-script', WOOFAQS_OPTIONS_PREFIX . '_data', $localize );

	}
}

/**
 * Changes up the action row for custom behavior
 *
 * Instead of using the typical edit, preview, publish links
 * This filters so they behave according to this plugin's
 * data/preview model.
 *
 * @since    3.0.0
 *
 * @param $actions array The incoming array of actions to filter
 * @param $post object The post object (the post being viewed)
 *
 * @return array The "maybe" filtered array of post actions
 */
function post_row_actions( $actions, $post ) {

	//check for our post type

	if ( WOOFAQS_POST_TYPE === $post->post_type ) {

		$post_type_object = get_post_type_object( $post->post_type );

		$post_type_label = $post_type_object->labels->singular_name;

		if ( $post->post_status == 'draft' || $post->post_status == 'pending' ) {

			$actions['publish'] = "<a href='#' class='submitpublish' data-id='" .
			                      $post->ID . "' title='" . esc_attr( __( 'Approve this ', 'woocommerce-faqs' ) ) .
			                      $post_type_label . "' data-nonce='" .
			                      wp_create_nonce( 'publish-post_' . $post->ID ) . "'>" .
			                      __( 'Approve', 'woocommerce-faqs' ) . "</a>";

		} else {
			$actions['view'] = "<a title='" . esc_attr( __( 'View this ', 'woocommerce-faqs' ) ) .
			                   $post_type_label . "' href='" . faq_link_filter( '', $post ) . "'>" .
			                   __( 'View', 'woocommerce-faqs' ) . "</a>";
		}

	}

	return $actions;

}

/**
 * Returns the convoluted permalink to the
 * possible products this faq can lie on.
 *
 * @param string $post_link The link to the current post
 * @param \WP_Post $post The current post
 *
 * @return string The product link
 */
function faq_link_filter( $post_link = '', $post ) {

	//if we don't have a post object, bail
	if ( ! $post ) {
		return $post_link;
	}

	//if this isn't the right post type, bail
	if ( ! property_exists( $post, 'post_type' ) || WOOFAQS_POST_TYPE !== $post->post_type ) {

		return $post_link;

	}

	//get the product this faq is associated with
	$product = get_post_meta( $post->ID, '_' . WOOFAQS_POST_TYPE . '_product', true );

	$category = (int) get_post_meta( $post->ID, '_' . WOOFAQS_POST_TYPE . '_categories', true );

	//by default, we are "viewing" the faq; we preview unpublished faqs
	$view = $post->post_status == 'publish' ? 'view' : 'preview';

	//if there's nothing in the post meta, we haven't assigned yet and don't have a valid url.
	if ( ! $product && ! $category ) {
		return '#unassigned-faq';
	}

	//if the faq is for all products, just use the product archive (like /shop)
	if ( $product == 'all' ) {
		return get_post_type_archive_link( 'product' );
	}

	if ( $category ) {
		return get_term_link( $category, 'product_cat' );
	}

	//if we're here, we should have a valid product ID
	$product = get_permalink( $product );


	if( get_option('permalink_structure') ) {
		$product = add_query_arg( 'faq-' . $view, absint( $post->ID ) . '#tab-faqs', $product );
	} else {
		$product .= '&faq-' . $view . '=' . absint( $post->ID ) . '#tab-faqs';
	}

	//so return the link of that product, with the highlighted faq query string and tab hash
	return $product;

}

/**
 * Ajax handler for quickly approving faqs
 *
 * Allows an admin to quickly approve a FAQ
 * By clicking the appropriate link
 * from the post row actions
 *
 * @since    3.0.0
 */
function approve_woo_faq() {

	//the posted post id
	$post_id = absint( $_POST['post_id'] );

	//initialize our results array
	$result = array();

	//todo: move this cap to product author
	if ( ! current_user_can( 'publish_post', $post_id ) ) {

		wp_send_json_error( array(
			'message' => __( 'Current user does not have permissions over this post', 'woocommerce-faqs' ),
		) );

	}

	//verify the posted nonce
	if ( ! wp_verify_nonce( $_POST['nonce'], 'publish-post_' . $post_id ) ) {

		wp_send_json_error( array(
			'message' => __( 'Cheatin&#8217; uh?' ),
		) );

	}

	//if we got this far, publish the post and generate the success
	wp_publish_post( $post_id );

	$result['message'] = __( 'Approved...reloading now.', 'woocommerce-faqs' );

	$result['redirect'] = admin_url( 'edit.php?post_type=' . WOOFAQS_POST_TYPE );

	wp_send_json_success( $result );

}

/**
 * Return columns for the post table of this post type
 *
 * @since     3.0.0
 *
 * @return    array    Array of the columns
 */
function set_custom_edit_columns() {

	$columns = array(

		'cb'          => '<input type="checkbox" />',
		'title'       => __( 'Question', 'woocommerce-faqs' ),
		'asker'       => __( 'Asker', 'woocommerce-faqs' ),
		'asker_email' => __( 'Asker Email', 'woocommerce-faqs' ),
		'comments'    => __( 'Answers', 'woocommerce-faqs' ),
		'date'        => __( 'Date Asked', 'woocommerce-faqs' )

	);

	return $columns;
}

/**
 * Echo the columns' content
 *
 * @since     3.0.0
 *
 * @param $column string The current column to echo data for
 * @param $post_id int The ID of the current post.
 *
 * @return    null
 */
function custom_column( $column, $post_id ) {
	switch ( $column ) {

		case 'asker' :

			echo esc_html( get_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_author_name', true ) );

			break;

		case 'asker_email' :

			echo sanitize_email( get_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_author_email', true ) );

			break;

	}

}

/**
 * Add meta boxes for this post type
 *
 * @since     3.0.0
 *
 * @return    null
 */
function meta_boxes() {

	add_meta_box(
		WOOFAQS_POST_TYPE . '_product',
		__( 'FAQ Details', 'woocommerce-faqs' ),
		__NAMESPACE__ . '\metabox',
		WOOFAQS_POST_TYPE,
		'normal',
		'high'
	);

	remove_meta_box( 'commentstatusdiv', WOOFAQS_POST_TYPE, 'normal' );

}

/**
 * Meta box content
 * @since     3.0.0
 * @return    null
 */
function metabox( $post ) {

	//get current value

	$current_product = get_post_meta( $post->ID, '_' . WOOFAQS_POST_TYPE . '_product', true );

	//get current value

	$category = get_post_meta( $post->ID, '_' . WOOFAQS_POST_TYPE . '_categories', true );

	$author_name = get_post_meta( $post->ID, '_' . WOOFAQS_POST_TYPE . '_author_name', true );

	$author_email = get_post_meta( $post->ID, '_' . WOOFAQS_POST_TYPE . '_author_email', true );

	//nonce

	wp_nonce_field( plugin_basename( __FILE__ ), WOOFAQS_POST_TYPE . 'meta_nonce' );

	//get all products
	//todo: refactor to use paginated or searchable select instead of -1
	$args = array(
		'post_type'              => 'product',
		'numberposts'            => - 1,
		'update_post_meta_cache' => false,
		'update_post_term_cache' => false,
	);

	$products = new \WP_Query( $args );

	if ( $products ) {
		//Product relationship label
		echo '<p><label for="_' . WOOFAQS_POST_TYPE . '_product">';
		_e( 'Product this question is shown on.', 'woocommerce-faqs' );
		echo '</label></p>';

		//Product relationship select
		echo '<p><select name="' . '_' . WOOFAQS_POST_TYPE . '_product">';
		echo '<option ' . selected( $current_product, '0', false ) . ' value="0">' . __( 'No product selection (use category only)', 'woocommerce-faqs' ) . '</option>';
		echo '<option ' . selected( $current_product, 'all', false ) . ' value="' . 'all' . '">' . __( 'All products', 'woocommerce-faqs' ) . '</option>';
		foreach ( $products->posts as $product ) {
			echo '<option ' . selected( $current_product, $product->ID, false ) . ' value="' . absint( $product->ID ) . '">' . esc_html( $product->post_title ) . '</option>';
		}
		echo '</select></p>';
	} //otherwise, just say there are no products
	else {
		echo '<p>';
		_e( 'No Products Found', 'woocommerce-faqs' );
		echo '</p>';
	}

	//Product relationship label

	echo '<p><label for="_' . '_' . WOOFAQS_POST_TYPE . '_categories">';
	_e( 'Categories this question is shown on.', 'woocommerce-faqs' );
	echo '<br />';
	_e( 'If changed from default, this will display on any products in specified categories in addition to the product selection chosen above.', 'woocommerce-faqs' );
	echo '</label></p>';

	$args = array(
		'orderby'          => 'NAME',
		'show_option_all'  => 'All categories',
		'show_option_none' => 'Default - no category filters',
		'order'            => 'ASC',
		'show_count'       => 0,
		'hide_empty'       => 1,
		'echo'             => 1,
		'selected'         => $category !== false && $category !== '' ? $category : - 1,
		'hierarchical'     => 0,
		'name'             => '_' . WOOFAQS_POST_TYPE . '_categories',
		'id'               => '_' . WOOFAQS_POST_TYPE . '_categories',
		'class'            => 'postform',
		'depth'            => 0,
		'tab_index'        => 0,
		'taxonomy'         => 'product_cat',
		'hide_if_empty'    => false,
		'walker'           => ''
	);
	wp_dropdown_categories( $args );
	echo '</p>';

	//question author info
	echo '<p>';
	_e( 'It is best to leave the fields below blank if you are adding a FAQ manually.', 'woocommerce-faqs' );
	echo '</p>';

	//author's name
	echo '<p><label for="_' . WOOFAQS_POST_TYPE . '_author_name">';
	_e( 'Author: ', 'woocommerce-faqs' );
	echo '</label>';
	echo '<input type="text" name="_' . WOOFAQS_POST_TYPE . '_author_name" value="' . esc_html( $author_name ) . '"/></p>';

	//author's email
	echo '<p><label for="_' . WOOFAQS_POST_TYPE . '_author_email">';
	_e( 'Author Email: ', 'woocommerce-faqs' );
	echo '</label>';
	echo '<input type="email" name="_' . WOOFAQS_POST_TYPE . '_author_email" value="' . sanitize_email( $author_email ) . '"/></p>';
}

/**
 * Save meta info
 *
 * @since     3.0.0
 *
 * @param $post_id int The ID of the post that's being saved.
 *
 * @return    null
 */
function save_meta( $post_id ) {

	// First we need to check if the current user is authorised to do this action.

	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	// Secondly we need to check if the user intended to change this value.

	if ( ! isset( $_POST[ WOOFAQS_POST_TYPE . 'meta_nonce' ] ) || ! wp_verify_nonce( $_POST[ WOOFAQS_POST_TYPE . 'meta_nonce' ], plugin_basename( __FILE__ ) ) ) {
		return;
	}

	$author_name  = sanitize_text_field( $_POST[ '_' . WOOFAQS_POST_TYPE . '_author_name' ] );
	$author_email = sanitize_email( $_POST[ '_' . WOOFAQS_POST_TYPE . '_author_email' ] );
	$product      = absint( $_POST[ '_' . WOOFAQS_POST_TYPE . '_product' ] );
	$category     = absint( $_POST[ '_' . WOOFAQS_POST_TYPE . '_categories' ] );

	if ( $author_name ) {
		update_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_author_name', $author_name );
	} else {
		delete_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_author_name' );
	}

	if ( $author_email ) {
		update_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_author_email', $author_email );
	} else {
		delete_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_author_email' );
	}

	if ( isset( $product ) ) {
		update_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_product', $product );
	} else {
		delete_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_product' );
	}

	if ( isset( $category ) && $category >= 0 ) {
		update_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_categories', $category );
	} else {
		delete_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_categories' );
	}
}

/**
 * Filter the comment text on the edit screen
 * to be more sensible
 *
 * @since     3.0.0
 *
 * @param $translated
 *
 * @return    object full translations object
 */
function filter_gettext( $translated, $original, $domain ) {
	//prevent filter looping
	remove_filter( 'gettext', __NAMESPACE__ . '\filter_gettext', 10, 3 );

	if (
		( isset( $_REQUEST['post_type'] ) && WOOFAQS_POST_TYPE === $_REQUEST['post_type'] )
		|| ( isset( $_REQUEST['post'] ) && WOOFAQS_POST_TYPE === get_post_type( $_REQUEST['post'] ) )
	) {
		$strings = array(
			__( 'Comments', 'woocommerce-faqs' )                => __( 'Answers', 'woocommerce-faqs' ),
			__( 'Add comment', 'woocommerce-faqs' )             => __( 'Add answer', 'woocommerce-faqs' ),
			__( 'Add Comment', 'woocommerce-faqs' )             => __( 'Add Answer', 'woocommerce-faqs' ),
			__( 'Add new Comment', 'woocommerce-faqs' )         => __( 'Add new Answer', 'woocommerce-faqs' ),
			__( 'No comments yet.', 'woocommerce-faqs' )        => __( 'No answers yet.', 'woocommerce-faqs' ),
			__( 'Show comments', 'woocommerce-faqs' )           => __( 'Show answers', 'woocommerce-faqs' ),
			__( 'No more comments found.', 'woocommerce-faqs' ) => __( 'No more answers found.', 'woocommerce-faqs' )
		);
		if ( isset( $strings[ $original ] ) ) {
			$translations = get_translations_for_domain( $domain );
			$translated   = $translations->translate( $strings[ $original ] );
		}
	}

	//reset filter
	add_filter( 'gettext', __NAMESPACE__ . '\filter_gettext', 10, 3 );

	return $translated;
}