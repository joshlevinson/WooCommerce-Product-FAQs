<?php

$html = '';

//the title of the section
$html .= '<h3 class="faq-title">' . __( 'Have a Question? Submit it here!', 'woocommerce-faqs' ) . '</h3>';

global $current_user;
get_currentuserinfo();

$is_logged_in = is_user_logged_in();

$author_name  = $is_logged_in && $current_user->display_name ? $current_user->display_name : '';
$author_email = $is_logged_in && $current_user->user_email ? $current_user->user_email : '';
$faq_content  = '';

//handle the submission results
if ( isset( $result ) && $result ) {

	$result_message = '';

	$html .= '<div class="faq-result';

	//error messages
	if ( $result['type'] == 'error' ) {

		//error class for the results div
		$html .= ' woocommerce-error faq-error';

		//we need to repopulate the form if it generated an error
		$author_name = isset( $_POST['faq_author_name'] ) ? sanitize_text_field( $_POST['faq_author_name'] ) : '';

		$author_email = isset( $_POST['faq_author_email'] ) ? sanitize_email( $_POST['faq_author_email'] ) : '';

		$faq_content = isset( $_POST['faq_content'] ) ? esc_textarea( stripslashes( $_POST['faq_content'] ) ) : '';

		//error list
		$result_message .= '<ul class="faq-errors">';

		//if the result is an error, $result contains an array of errors in $result['errors']
		//each error message is setup like array('input_name'=>'error_message' )
		foreach ( $result['errors'] as $key => $error ) {

			//so add them as list items
			$result_message .= '<li class="single-faq-error">' . esc_html( $error ) . '</li>';

		}
		//close the error messages
		$result_message .= '</ul>';

	} //success message
	else {
		//success class
		$html .= ' woocommerce-message faq-success';

		//if it's a success, $result['message'] only holds the single string message
		$result_message = $result['message'];

	}

	//add the $result_message to the $html variable.
	//at this point, it doesn't matter if it is a success or an error,
	//as $result_message is just an html string
	$html .= '">' . esc_html( $result_message ) . '</div>';

} else {
	$result = '';
}

//create the form
$html .= '<form method="POST" action="#tab-faqs" class="faq-form">';

//and the inputs
if ( ! $is_logged_in ) {
	$html .= '<label for="faq_author_name">' .
	         esc_html_x( 'Your Name', 'woocommerce-faqs' ) .
	         ':</label> <abbr class="required" title="required">*</abbr><br />';
}

//each input goes through should_display_error, 
//which checks if the current input's name exists in the result variable.
//should_display_errors checks if $result['errors'] is an array, 
//so we don't need to worry about that here.
$html .= '<input class="' . esc_attr( Woo_Faqs\CorePublic\should_display_error( $result, 'faq_author_name' ) ) . '" id="faq-author-name-input"';
if ( $is_logged_in ) {
	$html .= ' readonly hidden';
}
$html .= ' value="' . esc_attr( $author_name ) .
         '" required="required" type="text" name="faq_author_name" placeholder="' .
         esc_attr_x( 'Your Name', 'woocommerce-faqs' ) .
         '" />';

if ( ! $is_logged_in ) {
	$html .= '<label for="faq_author_email">' .
	         esc_attr_x( 'Your Email', 'woocommerce-faqs' ) .
	         ':</label> <abbr class="required" title="required">*</abbr>';
}

$html .= '<input class="' . esc_attr( Woo_Faqs\CorePublic\should_display_error( $result, 'faq_author_email' ) ) . '" id="faq-author-email-input" ';
if ( $is_logged_in ) {
	$html .= ' readonly hidden';
}
$html .= ' value="' . esc_attr( $author_email ) .
         '" required="required" type="email" name="faq_author_email" placeholder="' .
         esc_attr_x( 'Your Email', 'woocommerce-faqs' ) . '" />';

$html .= '<p><label for="faq_content">'
         . esc_html_x( 'Your Question', 'woocommerce-faqs' )
         . ':</label> <abbr class="required" title="required">*</abbr>';

$html .= '<textarea class="' .
         esc_attr( Woo_Faqs\CorePublic\should_display_error( $result, 'faq_content' ) ) .
         '"  placeholder="' . esc_attr_x( 'Your Question', 'woocommerce-faqs' ) .
         '" required="required" id="faq-content-input" name="faq_content" />' .
         esc_textarea( $faq_content ) .
         '</textarea></p>';

//honeypot anti-spam
$html .= '<input type="text" name="primary_email" id="poohbear" />';

$html .= '<input type="submit" name="submit_faq" value="' . esc_attr_x( 'Submit', 'woocommerce-faqs' ) . '" />';

$html .= '</form>';

//output the html
echo $html;