<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * Dashboard. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://joshlevinson.me/
 * @since             3.0.0
 * @package           Woocommerce_Product_Faqs
 *
 * @wordpress-plugin
 * Plugin Name:       WooCommerce Product FAQs
 * Plugin URI:        http://joshlevinson.me/
 * Description:       WooCommerce Product FAQs enables your WooCommerce powered site to utilize a FAQ system, allowing both users and site owners to add questions to products.
 * Version:           3.0.3
 * Author:            Josh Levinson
 * Author URI:        http://joshlevinson.me/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       woocommerce-product-faqs
 * Domain Path:       /languages
 */

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
*/

// If this file is called directly, abort
if ( ! defined( 'WPINC' ) ) {
	die;
}

//versioning constants
define( 'WOOFAQS_VERSION', '3.0.2' );
define( 'WOOFAQS_MINIMUM_WP_VERSION', '3.5' );
define( 'WOOFAQS_MINIMUM_WC_VERSION', '2.0' );

//paths and URLs for this plugin
define( 'WOOFAQS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WOOFAQS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOOFAQS_PLUGIN_FILE', plugin_basename( __FILE__ ) );

//commonly used strings
define( 'WOOFAQS_POST_TYPE', 'woo_faq' );
define( 'WOOFAQS_OPTIONS_PREFIX', 'woocommerce_faqs' );

//include functionality shared between front and admin
include __DIR__ . '/shared/shared.php';

//if WooCommerce isn't at least v2.0.0, abort.
add_action( 'woocommerce_loaded', function () {

	if ( ! version_compare( WC_VERSION, '2.0.0', '>' ) ) {
		return;
	}

	//include public facing functionality
	include __DIR__ . '/public/public.php';
	//include admin-only functionality
	include __DIR__ . '/admin/admin.php';

	//bootstrap the shared functionality
	Woo_Faqs\CoreShared\hooks();

	//bootstrap the public functionality
	Woo_Faqs\CorePublic\hooks();

	//if in the admin, bootstrap the admin functionality
	if ( is_admin() ) {
		Woo_Faqs\CoreAdmin\hooks();
	}

} );

//register the "shared" activation hook
register_activation_hook( __FILE__, 'Woo_Faqs\CoreShared\activate' );
