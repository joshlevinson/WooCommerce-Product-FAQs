<?php

//$post is the product
global $post;


//these $args are for retrieving the faqs
//todo: think about making these flexible

//get the terms of this product
$terms = wp_get_post_terms( $post->ID, 'product_cat', array( 'fields' => 'ids' ) );

$args = array(
	'nopaging'       => true,
	//todo: nix the unlimited posts per page, though it's not likely their will be tons of faqs
	//todo: possibly consider pagination
	'posts_per_page' => - 1,
	'order'          => 'DESC',
	'post_type'      => WOOFAQS_POST_TYPE,
	'post_status'    => 'publish',
	'orderby'        => 'menu_order',
	//this is the true association between product and faq
	'meta_query'     => array(
		'relation' => 'OR',
		//exact match on the product ID
		array(
			'key'   => '_' . WOOFAQS_POST_TYPE . '_product',
			'value' => $post->ID,
		),
		//match FAQs that exist on all products
		array(
			'key'   => '_' . WOOFAQS_POST_TYPE . '_product',
			'value' => 'all',
		),
		array(
			'key'   => '_' . WOOFAQS_POST_TYPE . '_categories',
			'value' => '0',
		),
		array(
			'key'     => '_' . WOOFAQS_POST_TYPE . '_categories',
			'value'   => $terms,
			'compare' => 'IN'
		),
	),
);

$preview = false;

//if we are 'previewing' or viewing a faq
if ( isset( $_GET['faq-preview'] ) ) {

	//check its post status to see if it is indeed unpublished
	if ( get_post_status( $_GET['faq-preview'] ) != 'publish' ) {

		//if so, override our above args to only retrieve the
		//preview faq
		$args = array(
			'post_type'   => WOOFAQS_POST_TYPE,
			'post__in'    => array( absint( $_GET['faq-preview'] ) ),
			'post_status' => 'any'

		);

		//and set the $preview variable
		//to 'preview' for use later on
		$preview = 'preview';

	} else {

		/*
		* otherwise, just set the $preview variable
		* equal to the faq post id
		* this comes into play if an admin has visited the preview link
		* for a faq that has already been approved
		* or if we are just viewing a faq
		*/
		$preview = absint( $_GET['faq-preview'] );
	}
} else if ( isset( $_GET['faq-view'] ) ) {

	$preview = absint( $_GET['faq-view'] );
}

//keep the product for use in the inner loops
global $post;
$product = $post;

//create the query
$faqs = new WP_Query( $args );

//should we expand all faqs by default?
$expand = get_option( WOOFAQS_OPTIONS_PREFIX . '_expand_faqs', false );

//if the query retrieved some posts
if ( $faqs->have_posts() ) {

	//faqs wrapper
	echo '<div class="woo-faqs">';

	//counter for even/odd class
	$c = 0;

	//loop the faqs
	while ( $faqs->have_posts() ) : $faqs->the_post();

		//cache the faq ID
		$faq_id = get_the_ID();

		//get the 'comments' (answers)
		$args     = array( 'post_id' => $faq_id, 'order' => 'ASC' );
		$comments = get_comments( $args );

		//single faq wrapper
		echo '<div class="single-woo-faq';
		if ( $expand && 'no' !== $expand ) {
			echo ' show';
		}
		//for css targeting
		echo ' faq-' . $faq_id;
		//even/odd targeting
		if ( $c % 2 == 0 ) {
			echo ' even';
		} else {
			echo ' odd';
		}
		//css targeting of view links
		if ( $preview == 'preview' ) {
			echo ' preview';
		} else if ( $preview == $faq_id ) {
			echo ' view';
		}
		if ( $comments ) {
			echo ' answered';
		} else {
			echo ' unanswerered';
		}
		//close single faq wrapper beginning div
		echo '">';

		//the content is the question, which is the title
		echo '<span class="faq-question"><a title="' . __( 'Click to view the answer!', 'woocommerce-faqs' ) . '">'
		     . esc_html_x( 'Q:', 'woocommerce-faqs' ) .
		     ' ' . esc_html( get_the_content() );
		//show the faq's status to an admin
		if ( $preview == 'preview' ) {
			echo ' (' . esc_html_x( 'Pending Approval', 'woocommerce-faqs' ) . ') ';
		}
		//close the single faq title
		echo '</a></span>';

		//the 'content' of the faq is the answer(s)
		//and for the admin, also the reply form
		echo '<div class="faq-content">';

		//get the name of the asker
		echo '<div class="faq-author">';
		$author_name = get_post_meta( $faq_id, '_' . WOOFAQS_POST_TYPE . '_author_name', true );
		echo '<span class="asked-by-on">' . esc_html_x( '— Asked', 'woocommerce-faqs' ) . ' ';
		if ( $author_name ) {
			/* translators: Used like "Asked by John Doe" (if asker name is available) */
			echo esc_html_x( 'by', 'woocommerce-faqs' ) . ' </span>';
			echo '<span class="faq-author-name">' . esc_html( $author_name ) . '</span>';
		}

		/* translators: Used like "Asked by John Doe on 1/2/2016" (date) */
		echo ' ' . esc_html_x( 'on', 'woocommerce-faqs' );
		if ( ! $author_name ) {
			echo '</span>';
		}
		echo '<span class="faq-date">' . ' ' . get_the_date() . '</span>';
		echo '</div>';

		//list the answers, if any
		if ( $comments ) {
			wp_list_comments( array( 'callback' => 'Woo_Faqs\CorePublic\comment_callback' ), $comments );
		} else {
			echo '<p class="comment-list awaiting-response">' .
			     esc_html_x( 'This question has not been responded to yet.', 'woocommerce-faqs' ) .
			     '</p>';
		}

		//wrapper for answer form
		echo '<div class="faq-comment">';

		//todo: move this cap to the product author?
		$answer_caps = apply_filters( WOOFAQS_OPTIONS_PREFIX . '_answer_caps', 'edit_post' );
		//we don't want to allow answers on an unpublished
		//faq, or allow unauthorized users to answer
		if (
			is_user_logged_in()
			&& current_user_can( $answer_caps, $product->ID )
			&& get_post_status( $faq_id ) == 'publish'
		) {

			$args = array(
				'id_form'              => 'commentform',
				'id_submit'            => 'submit',
				'title_reply'          => __( 'Leave an Answer', 'woocommerce-faqs' ),
				'title_reply_to'       => __( 'Leave an Answer to %s', 'woocommerce-faqs' ),
				'cancel_reply_link'    => __( 'Cancel Answer', 'woocommerce-faqs' ),
				'label_submit'         => __( 'Post Answer', 'woocommerce-faqs' ),
				'comment_field'        => '<p class="comment-form-comment"><label for="comment">' . _x( 'Answer', 'noun', 'woocommerce-faqs' ) .
				                          '</label><textarea id="comment" name="comment" cols="45" rows="8" aria-required="true">' .
				                          '</textarea></p>',
				'must_log_in'          => '',
				'logged_in_as'         => '',
				'comment_notes_before' => '',
				'comment_notes_after'  => '',
				'fields'               => apply_filters( 'comment_form_default_fields', array() ),
			);
			comment_form( $args, $faq_id );
		} else if ( is_user_logged_in() && current_user_can( $answer_caps, $product->ID ) ) {
			echo '<form id="quick-approve-faq" action="" method="post">';
			echo '<input id="qaf_post_id" type="hidden" name="post_id" value="' . $faq_id . '" />';
			echo '<input id="qaf_nonce" type="hidden" name="nonce" value="' . wp_create_nonce( 'publish-post_' . $faq_id ) . '" />';
			echo '<input type="submit" name="approve_faq" value="' . esc_attr_x( 'Approve this FAQ', 'woocommerce-faqs' ) . '" />';
			echo '</form>';
		}

		//end wrapper for faq answer form
		echo '</div>';

		//end wrapper for faq 'content'
		echo '</div>';

		//end wrapper for single faq
		echo '</div>';

		//increase even/odd counter
		$c ++;

		//end the faq loop
	endwhile;

	// Reset Query
	wp_reset_postdata();

	//ending wrapper for faqs section
	echo '</div>';
}
