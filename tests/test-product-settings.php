<?php
// Lightweight unit harness for WooCommerce product and variation fields.
define( 'ABSPATH', __DIR__ );
$GLOBALS['field_values'] = array();
$GLOBALS['meta'] = array(
	321 => array( '_kitmage_lde_course_id' => '41' ),
	654 => array( '_kitmage_lde_course_id' => '42' ),
);

function add_action() {}
function __( $text ) { return $text; }
function esc_html__( $text ) { return $text; }
function absint( $value ) { return abs( (int) $value ); }
function get_the_ID() { return 123; }
function get_posts() { return array(); }
function get_post_meta( $id, $key ) { return isset( $GLOBALS['meta'][ $id ][ $key ] ) ? $GLOBALS['meta'][ $id ][ $key ] : ''; }
function woocommerce_wp_select( $args ) { $GLOBALS['field_values'][ $args['id'] ] = $args['value']; }
function woocommerce_wp_text_input( $args ) { $GLOBALS['field_values'][ $args['id'] ] = $args['value']; }

require_once dirname( __DIR__ ) . '/includes/class-product-settings.php';

use KitMage\LearnDashEntitlements\Product_Settings;

final class Fake_Variation_Product {
	public function get_id() { return 654; }
}

function expect_value( $label, $expected, $actual ) {
	if ( $expected !== $actual ) {
		fwrite( STDERR, "FAIL: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" );
		exit( 1 );
	}
	echo "PASS: {$label}\n";
}

$settings = new Product_Settings();

// WooCommerce's variation AJAX callback supplies a WP_Post-shaped object.
ob_start();
$settings->variation_fields( 0, array(), (object) array( 'ID' => 321 ) );
ob_end_clean();
expect_value( 'WP_Post variation uses its ID without a fatal method call', '41', $GLOBALS['field_values']['variable_kitmage_lde_0__kitmage_lde_course_id'] );

// Keep compatibility with callers that supply a product object.
ob_start();
$settings->variation_fields( 1, array(), new Fake_Variation_Product() );
ob_end_clean();
expect_value( 'product variation object uses get_id', '42', $GLOBALS['field_values']['variable_kitmage_lde_1__kitmage_lde_course_id'] );
