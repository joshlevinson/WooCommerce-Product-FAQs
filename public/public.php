<?php

namespace Woo_Faqs\CorePublic;

/**
 * Bootstraps shared functionality
 *
 * Common pattern that takes care of adding filters and actions
 * Utilizing a time-saving namespace abstraction
 *
 * @since 3.0.0
 */
function hooks() {
	$n = function ( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	// Load public-facing style sheet and JavaScript.
	add_action( 'wp_enqueue_scripts', $n( 'enqueue_styles' ) );
	add_action( 'wp_enqueue_scripts', $n( 'enqueue_scripts' ), 1000 );

	//action for notifying asker about posted answer
	add_action( 'wp_insert_comment', $n( 'answer_posted' ), 99, 2 );

	//filter the redirect to take us back to the product after an admin has replied to a FAQ
	add_filter( 'comment_post_redirect', $n( 'redirect_comment_form' ), 10, 2 );

	//filter woo's tabs to add FAQs
	add_filter( 'woocommerce_product_tabs', $n( 'faq_tab' ) );

	//woocommerce tab titles and priorities
	add_filter( 'woocommerce_product_tabs', $n( 'filter_faq_tab' ) );

}

/**
 * Register and enqueue public-facing style sheet.
 *
 * @since    3.0.0
 */
function enqueue_styles() {
	if ( is_product() ) {
		wp_enqueue_style( 'woocommerce-faqs-plugin-styles', WOOFAQS_PLUGIN_URL . '/public/assets/css/public.css' );
	}
}

/**
 * Register and enqueues public-facing JavaScript files.
 *
 * @since    3.0.0
 */
function enqueue_scripts() {

	if ( is_product() ) {

		wp_enqueue_script(
			'woocommerce-faqs-plugin-script',
			WOOFAQS_PLUGIN_URL . '/public/assets/js/public.js',
			array( 'jquery' ),
			WOOFAQS_VERSION,
			true
		);

		$localize = array(
			'ajaxurl' => admin_url( 'admin-ajax.php' ),
			'spinner' => admin_url( 'images/wpspin_light.gif' ),
		);

		//if we are view/previewing a faq, localize that so it is available to our javascript

		if ( isset( $_GET['faq-view'] ) || isset( $_GET['faq-preview'] ) ) {

			$faq_to_highlight = ( isset( $_GET['faq-view'] ) ? absint( $_GET['faq-view'] ) : absint( $_GET['faq-preview'] ) );

			$localize['faq_highlight'] = $faq_to_highlight;

			//and localize the color with a filter, so it can be changed either by user or maybe later as a settings option

			$localize['faq_highlight_color'] = esc_attr( apply_filters( WOOFAQS_OPTIONS_PREFIX . '_front_faq_highlight_color', '#9ED1D6' ) );

		}

		wp_localize_script( 'woocommerce-faqs-plugin-script', WOOFAQS_OPTIONS_PREFIX . '_data', $localize );

	}

}

/**
 * Notifies the visitor/customer that an
 * answer has been posted to their question
 *
 * @since    3.0.0
 */
function answer_posted( $comment_id, $comment_object ) {

	$post_id = (int) $comment_object->comment_post_ID;

	if ( WOOFAQS_POST_TYPE === get_post_type( $post_id ) ) {
		$product_id = get_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_product', true );

		$post_data = array(
			'post_id'          => $post_id,
			'question_title'   => esc_html( get_the_title( $post_id ) ),
			'product_title'    => esc_html( get_the_title( $product_id ) ),
			'product_id'       => esc_html( absint( $product_id ) ),
			'faq_author_email' => sanitize_email( get_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_author_email', true ) ),
		);

		send_notifications( 'asker', $post_data );
	}

}

/**
 * Redirect the answerer back to the product page
 * after they interact with the FAQ section
 *
 * @since    1.0.0
 */
function redirect_comment_form( $location, $comment ) {

	$faq = $comment->comment_post_ID;

	if ( $product = get_post_meta( $faq, '_' . WOOFAQS_POST_TYPE . '_product', true ) ) {
		$location = esc_url( get_permalink( $product ) ) . '#tab-faqs';
	} else if ( isset( $_SERVER['HTTP_REFERER'] ) && $_SERVER['HTTP_REFERER'] ) {
		$location = esc_url( $_SERVER['HTTP_REFERER'] );
	}

	return $location;

}

/**
 * Adds our FAQs tab to Woo's product tabs
 *
 * @since    3.0.0
 */
function faq_tab( $tabs ) {

	$tabs['faqs'] = array(

		'title'    => __( 'FAQs', 'woocommerce-faqs' ),
		'priority' => 100,
		'callback' => 'Woo_Faqs\CorePublic\faq_tab_content'

	);

	return $tabs;

}

/**
 * Displays the content for the FAQs tab
 * the form and the faq loop
 *
 * @since    3.0.0
 */
function faq_tab_content() {

	//handle the submission if it posted
	if ( isset( $_POST['submit_faq'] ) && $_POST['submit_faq'] ) {
		$result = handle_submission();
	}

	//the faqs loop
	include( __DIR__ . '/templates/loop-faqs.php' );

	$disable_ask = get_option( WOOFAQS_OPTIONS_PREFIX . '_disable_ask', false );
	if ( ! $disable_ask || $disable_ask === 'no' ) {
		//the faq form
		include( __DIR__ . '/templates/faq-form.php' );
	}

}

/**
 * Handle the submission of a FAQ
 *
 * @since    3.0.0
 */
function handle_submission() {

	//this $post variable is for the PRODUCT
	global $post;

	//create errors and result arrays
	$errors = array();
	$result = array();

	//put post data into an array
	if ( isset( $_POST['faq_author_name'] ) ) {
		$input['faq_author_name'] = sanitize_text_field( $_POST['faq_author_name'] );
	}
	if ( isset( $_POST['faq_author_email'] ) ) {
		$input['faq_author_email'] = sanitize_email( $_POST['faq_author_email'] );
	}
	if ( isset( $_POST['faq_content'] ) ) {
		$input['faq_content'] = esc_textarea( stripslashes( $_POST['faq_content'] ) );
	}

	//very simple validation for content, name, and email
	//TODO - make this validation more stringent
	if ( empty( $input['faq_content'] ) ) {
		$errors['faq_content'] = __( 'Please enter a question!', 'woocommerce-faqs' );
	}

	if ( empty( $input['faq_author_name'] ) ) {
		$errors['faq_author_name'] = __( 'Please enter your name!', 'woocommerce-faqs' );
	}

	if ( empty( $input['faq_author_email'] ) || ( ! empty( $input['faq_author_email'] ) && ! is_email( $input['faq_author_email'] ) ) ) {
		$errors['faq_author_email'] = __( 'Please enter a valid email!', 'woocommerce-faqs' );
	}

	$result = handle_antispam();

	//if antispam returned a error type result, asker failed antispam check
	if ( $result['type'] == 'error' ) {
		$errors[] = $result['message'];
	}

	//passed all checks
	if ( empty( $errors ) ) {
		$post_info = array(
			'post_title'     => __( 'Question for ', 'woocommerce-faqs' ) . $post->post_title,
			'post_content'   => wp_strip_all_tags( $input['faq_content'] ),
			'post_type'      => WOOFAQS_POST_TYPE,
			'post_status'    => 'pending',
			'comment_status' => 'open'
		);

		//create the post
		$post_id = wp_insert_post( $post_info );

		//add post meta
		update_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_product', $post->ID );
		update_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_author_name', $input['faq_author_name'] );
		update_post_meta( $post_id, '_' . WOOFAQS_POST_TYPE . '_author_email', $input['faq_author_email'] );

		//data for elsewhere (like the notifications)
		$input['product_title']     = $post->post_title;
		$input['question_title']    = $post_info['post_title'];
		$input['question_content']  = $post_info['post_content'];
		$input['post_id']           = absint( $post_id );
		$input['product_id']        = absint( $post->ID );
		$input['product_author_id'] = absint( $post->post_author );

		//result for the form (success)
		$result['type']    = 'success';
		$result['message'] = __( 'FAQ Successfully Posted. Your question will be reviewed and answered soon!', 'woocommerce-faqs' );

		//send the notification to the answerer
		send_notifications( 'answerer', $input );

	} else {
		//result for the form (error)
		$result['type']   = 'error';
		$result['errors'] = $errors;
	}

	return $result;

}

/**
 * Handler for building and sending out
 * asker and answerer emails
 *
 * @since    3.0.0
 */
function send_notifications( $to_whom = false, $post_data = null ) {

	//required info for both emails
	//todo - make this actually used by the function to force requirements
	//especially when filter addition is complete, so this function will fail w/o required data
	$answerer_email_required = apply_filters(
		WOOFAQS_OPTIONS_PREFIX . '_answerer_email_required',
		array(
			'question_title',
			'faq_author_name',
			'product_title',
			'question_content',
		),
		$post_data
	);
	$asker_email_required    = apply_filters(
		WOOFAQS_OPTIONS_PREFIX . '_asker_email_required',
		array(
			'question_title',
			'product_title',
			'post_id',
		),
		$post_data
	);

	//who to send the emails to
	if ( isset( $post_data['product_author_id'] ) ) {
		$author = $post_data['product_author_id'];
	} else if ( isset( $post_data['product_id'] ) ) {
		$author = get_post_field( 'post_author', $post_data['product_id'] );
	}

	$product_link = get_permalink( $post_data['product_id'] );

	$answerer_email = get_answerer_email( $author );
	$from_name      = get_from_name( $post_data );
	$asker_email    = apply_filters( WOOFAQS_OPTIONS_PREFIX . '_asker_email', $post_data['faq_author_email'], $post_data );
	$to             = '';
	$subject        = '';
	$message        = '';
	$headers        = array();
	$success        = false;

	//we need to know who to send to!
	if ( $to_whom ) {

		//filter wp mail to html
		add_filter( 'wp_mail_content_type', __NAMESPACE__ . '\set_html_content_type' );

		$from = '';
		$from .= 'From: ' . $from_name;

		$from .= ' <';
		$from .= $answerer_email;
		$from .= '>';
		$headers[] = $from;

		switch ( $to_whom ) {
			case 'answerer':
				$headers[] = 'Reply-To: ' . $post_data['faq_author_name'] . ' <' . $post_data['faq_author_email'] . '>';
				$to        = $answerer_email;

				$subject = sprintf( __( 'New %1$s', 'woocommerce-faqs' ), $post_data['question_title'] );

				$subject = apply_filters( WOOFAQS_OPTIONS_PREFIX . '_answerer_email_subject', $subject, $post_data );

				$message = '<p>' .
				           sprintf( __( '%1$s asked the following question about %2$s', 'woocommerce-faqs' ),
					           $post_data['faq_author_name'],
					           '<a href="' . esc_url( $product_link ) . '">' . $post_data['product_title']
				           );
				$message .= ':</a></p>';

				$message .= '<p>"' . $post_data['question_content'] . '"</p>';

				$message .= '<p>' . sprintf( __( 'The question can be administered <a href="%1$s">here</a>.', 'woocommerce-faqs' ),
							admin_url( '/edit.php?post_type=' ) . WOOFAQS_POST_TYPE . '&highlight=' .
							absint( $post_data['post_id'] )
							) . '</p>';

				$message .= '<p>' . __( 'If the question asker left a valid email, you can reply directly to them
				from this email. Note this will not post the reply on your website. ', 'woocommerce-faqs' ) . '</p>';

				//allow the final message to be filtered
				$message = apply_filters( WOOFAQS_OPTIONS_PREFIX . '_answerer_email_message', $message, $post_data );

				break;
			case 'asker':
				$to      = $asker_email;

				$subject = sprintf( __( 'Response to %1$s', 'woocommerce-faqs' ), $post_data['question_title'] );

				//allow the subject to be filtered
				$subject = apply_filters( WOOFAQS_OPTIONS_PREFIX . '_asker_email_subject', $subject, $post_data );

				$message = '<p>' . sprintf( __( 'A reply to your question about %1$s has been posted!', 'woocommerce-faqs' ),
										$post_data['product_title']
									) . '</p>';

				$message .= '<p>' . sprintf( __( 'View the answer <a href="%1$s">here</a>.', 'woocommerce-faqs' ),
										add_query_arg( 'faq-view', $post_data['post_id'] . '#tab-faqs', $product_link )
									) . '</p>';

				//allow the final message to be filtered
				$message = apply_filters( WOOFAQS_OPTIONS_PREFIX . '_asker_email_message', $message, $post_data );
				break;
		}
		if ( ! empty( $to ) ) {
			$success = wp_mail( $to, $subject, $message, $headers );
		}
		remove_filter( 'wp_mail_content_type', __NAMESPACE__ . '\set_html_content_type' );
	}

	//we may want to check on this later
	return $success;

}

/**
 * Filters the answerer's email alias in case it was set in the Dashboard.
 *
 * @since    3.0.0
 *
 * @param string $post_data Data used in case devs want for filter
 *
 * @return string The answerer's "from" email alias
 */
function get_from_name( $post_data ) {
	$from_name = get_option( WOOFAQS_OPTIONS_PREFIX . '_from_name' );
	if ( ! $from_name ) {
		$from_name = get_bloginfo( 'name' );
	}

	return esc_html( apply_filters( WOOFAQS_OPTIONS_PREFIX . '_from_name', $from_name, $post_data ) );
}

/**
 * Filters the answerer email in case it was set in the Dashboard.
 *
 * @since    3.0.0
 *
 * @param string $email The email before this filter
 *
 * @return string The email after this filter
 */
function get_answerer_email( $author ) {

	$email = get_option( WOOFAQS_OPTIONS_PREFIX . '_answerer_email' );

	if ( $email ) {
		return $email;
	}

	$email = get_the_author_meta( 'user_email', $author );

	if ( $email ) {
		return $email;
	}

	return sanitize_email( get_option( 'admin_email' ) );

}

/**
 * Filter WP emails to be HTML (for the ones we send)
 *
 * @since   3.0.0
 */
function set_html_content_type() {

	return 'text/html';

}

/**
 * Handle the form's antispam functionality
 *
 * Currently uses a simple honeypot to combat spam
 *
 * @since    3.0.0
 */
function handle_antispam() {

	//we need the result array one way or the other
	$result = array();

	//if primary_email is set/not empty, the honeypot has been triggered
	if ( isset( $_POST['primary_email'] ) && $_POST['primary_email'] != '' ) {
		$result['type']    = 'error';
		$result['message'] = __( 'You\'ve triggered our anti-spam filter. If you have a form-filling application/extension, please disable it temporarily.', 'woocommerce-faqs' );
	} else {
		$result['type'] = 'success';
	}

	return $result;

}

/**
 * Returns 'error' for the form inputs
 * whose input resulted in an error
 * or just an empty string
 *
 * @since    3.0.0
 *
 * @param $result array The results we're checking
 * @param $key string the index of $result we want to check
 *
 * @return string Either 'error' or nothing
 */
function should_display_error( $result, $key ) {

	if ( isset( $result['errors'] ) && is_array( $result['errors'] ) ) {

		if ( array_key_exists( $key, $result['errors'] ) ) {

			return 'error';

		}

	}

	return '';

}

/**
 * Our comment (answer) loop
 *
 * @since    3.0.0
 */
function comment_callback( $comment, $args, $depth ) {

	$GLOBALS['comment'] = $comment;

	$comment_count = (int) $comment->comment_count;

	?>

	<div <?php comment_class(); ?> id="li-comment-<?php comment_ID() ?>" class="clearfix">

		<div id="comment-<?php comment_ID(); ?>" class="comment-body clearfix">

			<div class="wrapper">

				<div class="extra-wrap">

					<span><?php _e( 'A: ', 'woocommerce-faqs' ); ?><?php echo esc_html( get_comment_text() ); ?></span>

				</div>

				<div class="comment-author">

					<?php echo get_avatar( $comment->comment_author_email, 65 ); ?>

					<?php printf( '<span class="author">— %1$s</span>', esc_url( get_comment_author_link() ) ); ?>

				</div>

			</div>

			<div class="wrapper">

				<div class="reply">

				</div>

				<div class="comment-meta commentmetadata"><?php printf( '%1$s', get_comment_date( 'F j, Y' ) ); ?></div>

			</div>

		</div>

	</div>

<?php

}

/**
 * Filters the tab title if the setting is set in the Dashboard.
 *
 * @since    3.0.9
 *
 * @param    string $title the title before this filter
 *
 * @return    string    $title    the title after this filter
 */
function filter_faq_tab( $tabs ) {

	$user_title    = get_option( WOOFAQS_OPTIONS_PREFIX . '_tab_title' );
	$user_priority = get_option( WOOFAQS_OPTIONS_PREFIX . '_tab_priority' );
	if ( $user_title && isset( $tabs['faqs'] ) ) {
		$tabs['faqs']['title'] = sanitize_text_field( $user_title );
	}
	if ( $user_priority && isset( $tabs['faqs'] ) ) {
		$tabs['faqs']['priority'] = intval( $user_priority );
	}

	return $tabs;

}