=== WooCommerce Show More Orders ===
Contributors: danbruce
Tags: woocommerce, orders, admin, pagination
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.0.0
License: MIT
License URI: https://opensource.org/licenses/MIT

Adds a "per page" selector to the WooCommerce Orders list so you can display more orders at once.

== Description ==

WooCommerce shows 20 orders per page by default. This plugin adds a dropdown next to the
order filters (WooCommerce > Orders) with larger page sizes: 20, 50, 100, 250, 500, 1,000 and 2,500.

* Works with both High-Performance Order Storage (HPOS) and legacy post-based order storage.
* The choice is saved per user and is shared with the core "Screen Options" setting.
* Allows values above the 999 limit of the Screen Options panel.

Very large page sizes load every order on one page, which can be slow on big stores.

== Customizing the choices ==

Use the `wcsmo_per_page_choices` filter:

`add_filter( 'wcsmo_per_page_choices', function () {
	return array( 25, 100, 400, 800 );
} );`

== Installation ==

1. Copy the `woocommerce-show-more-orders` folder into `wp-content/plugins/`.
2. Activate the plugin from the Plugins screen (WooCommerce must be active).
3. Open WooCommerce > Orders and choose a page size from the new dropdown.

== Changelog ==

= 1.0.0 =
* Initial release.
