<?php
// Dependency-free regression harness for checkout and account presentation.
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'KITMAGE_LDE_PATH', dirname( __DIR__ ) . '/' );
define( 'KITMAGE_LDE_FILE', KITMAGE_LDE_PATH . 'kitmage-lms-entitlement-manager.php' );
define( 'KITMAGE_LDE_VERSION', 'test' );
$GLOBALS['user_id'] = 7;
$GLOBALS['orders'] = array();
$GLOBALS['grants'] = array();
$GLOBALS['hooks'] = array();
$GLOBALS['scripts'] = array();
function add_action( $hook, $callback, $priority = 10, $arguments = 1 ) { $GLOBALS['hooks'][$hook] = array( $callback, $priority, $arguments ); }
function add_filter( $hook, $callback, $priority = 10, $arguments = 1 ) { add_action( $hook, $callback, $priority, $arguments ); }
function __( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_attr( $text ) { return esc_html( $text ); }
function esc_url( $text ) { return esc_html( $text ); }
function esc_html__( $text ) { return esc_html( $text ); }
function esc_attr__( $text ) { return esc_attr( $text ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function _n( $one, $many, $number ) { return 1 === $number ? $one : $many; }
function number_format_i18n( $number ) { return number_format( $number ); }
function absint( $value ) { return abs( (int) $value ); }
function get_current_user_id() { return $GLOBALS['user_id']; }
function wc_get_order( $id ) { return isset( $GLOBALS['orders'][$id] ) ? $GLOBALS['orders'][$id] : false; }
function wc_get_account_endpoint_url( $endpoint ) { return 'https://example.test/account/' . $endpoint . '/'; }
function add_query_arg( $key, $value, $url ) { return $url . '?' . urlencode( $key ) . '=' . urlencode( $value ); }
function wp_unslash( $value ) { return stripslashes( $value ); }
function wc_clean( $value ) { return trim( $value ); }
function wc_print_notice( $message ) { echo esc_html( $message ); }
function plugins_url( $path ) { return 'https://example.test/plugins/entitlements/' . $path; }
function wp_enqueue_script( $handle, $url, $deps, $version, $footer ) { $GLOBALS['scripts'][$handle] = array( $url, $footer ); }
function get_the_title() { return 'Example course'; }
function home_url( $path ) { return 'https://example.test/' . ltrim( $path, '/' ); }
function user_trailingslashit( $path ) { return $path . '/'; }
function get_option() { return 'Y-m-d'; }
function wp_date( $format, $time ) { return gmdate( $format, $time ); }
function wc_format_datetime() { return '2026-01-01'; }

final class Navigation_DB {
	public $prefix = 'test_';
	public function prepare( $query, ...$args ) { return array( $query, $args ); }
	public function get_results( $prepared ) {
		if ( false !== strpos( $prepared[0], 'redemptions' ) ) { return array(); }
		return array_values( array_filter( $GLOBALS['grants'], function( $grant ) use ( $prepared ) { return $grant['order_id'] === $prepared[1][0]; } ) );
	}
	public function get_col( $prepared ) {
		$orders = array();
		foreach ( $GLOBALS['grants'] as $grant ) { if ( $grant['customer_id'] === $prepared[1][0] ) { $orders[] = $grant['order_id']; } }
		return array_values( array_unique( $orders ) );
	}
}
$GLOBALS['wpdb'] = new Navigation_DB();

final class Navigation_Item {
	private $course;
	private $count;
	private $quantity;
	public function __construct( $course = 41, $count = 3, $quantity = 1 ) { $this->course = $course; $this->count = $count; $this->quantity = $quantity; }
	public function get_meta( $key ) { return '_kitmage_lde_course_id' === $key ? $this->course : $this->count; }
	public function get_quantity() { return $this->quantity; }
}
final class Navigation_Order {
	private $id;
	private $customer;
	private $status;
	private $items;
	public function __construct( $id, $customer = 7, $status = 'processing', $items = null ) { $this->id = $id; $this->customer = $customer; $this->status = $status; $this->items = null === $items ? array( new Navigation_Item() ) : $items; }
	public function get_id() { return $this->id; }
	public function get_order_number() { return 'ORDER-' . $this->id; }
	public function get_customer_id() { return $this->customer; }
	public function get_order_key() { return 'key-' . $this->id; }
	public function get_items() { return $this->items; }
	public function get_date_created() { return null; }
	public function is_paid() { return in_array( $this->status, array( 'processing', 'completed' ), true ); }
	public function has_status( $statuses ) { return in_array( $this->status, (array) $statuses, true ); }
}

require_once KITMAGE_LDE_PATH . 'includes/class-database.php';
require_once KITMAGE_LDE_PATH . 'includes/class-repository.php';
require_once KITMAGE_LDE_PATH . 'includes/class-product-settings.php';
require_once KITMAGE_LDE_PATH . 'includes/class-enrollment-controller.php';
require_once KITMAGE_LDE_PATH . 'includes/class-account-controller.php';
use KitMage\LearnDashEntitlements\Account_Controller;
use KitMage\LearnDashEntitlements\Repository;
function controller() { return new Account_Controller( new Repository() ); }
function output( $callback ) { ob_start(); $callback(); return ob_get_clean(); }
function expect( $label, $condition ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); } echo "PASS: {$label}\n"; }
function contains( $html, $text ) { return false !== strpos( $html, $text ); }
function confirm( $id ) { $controller = controller(); return output( function() use ( $controller, $id ) { $controller->confirmation( $id ); } ); }
function grant( $order_id, $values = array() ) {
	return array_merge( array( 'id' => $order_id, 'order_id' => $order_id, 'customer_id' => 7, 'status' => 'active', 'total_count' => 3, 'redeemed_count' => 1, 'expires_at' => null, 'course_id' => 41, 'token' => 'secret-' . $order_id ), $values );
}
$GLOBALS['orders'][10] = new Navigation_Order( 10 );
$GLOBALS['grants'][] = grant( 10 );
$GLOBALS['orders'][11] = new Navigation_Order( 11, 7, 'processing', array() );
$GLOBALS['orders'][12] = new Navigation_Order( 12, 7, 'on-hold' );
$GLOBALS['orders'][13] = new Navigation_Order( 13 );
foreach ( array( 14 => 'cancelled', 15 => 'failed', 16 => 'refunded' ) as $id => $status ) { $GLOBALS['orders'][$id] = new Navigation_Order( $id, 7, $status ); }
$GLOBALS['orders'][17] = new Navigation_Order( 17, 7, 'processing', array( new Navigation_Item( 41, 0 ) ) );
$GLOBALS['orders'][18] = new Navigation_Order( 18, 7, 'processing', array( new Navigation_Item( 41, 3, 0 ) ) );
$GLOBALS['orders'][19] = new Navigation_Order( 19, 8 );
$GLOBALS['orders'][20] = new Navigation_Order( 20, 0 );
$GLOBALS['orders'][21] = new Navigation_Order( 21, 7, 'completed' );
$GLOBALS['grants'][] = grant( 21 );
$GLOBALS['orders'][22] = new Navigation_Order( 22, 7, 'completed', array() );
$GLOBALS['grants'][] = grant( 22 );

$controller = controller();
$controller->hooks();
expect( 'classic and block confirmation hooks are registered', isset( $GLOBALS['hooks']['woocommerce_before_thankyou'], $GLOBALS['hooks']['woocommerce_thankyou'] ) );
expect( 'order action filter receives the order', 2 === $GLOBALS['hooks']['woocommerce_my_account_my_orders_actions'][2] );
$html = output( function() use ( $controller ) { $controller->confirmation( 10 ); $controller->confirmation( 10 ); } );
expect( 'confirmation renders one ready card with a direct order link', 1 === substr_count( $html, 'kitmage-lde-next-steps' ) && contains( $html, 'Your training entitlements are ready' ) && contains( $html, '/account/training-entitlements/?entitlement-order=10' ) );
expect( 'confirmation does not expose bearer enrollment tokens', ! contains( $html, 'secret-' ) );
expect( 'regular, disabled and zero-quantity orders have no card', '' === confirm( 11 ) && '' === confirm( 17 ) && '' === confirm( 18 ) );
expect( 'pending payment is distinguished from ready entitlements', contains( confirm( 12 ), 'once payment is confirmed' ) && ! contains( confirm( 12 ), 'are ready' ) );
expect( 'paid orders awaiting grants have a preparation message', contains( confirm( 13 ), 'being prepared' ) );
foreach ( array( 14, 15, 16 ) as $id ) { expect( 'unsuccessful order ' . $id . ' is not advertised as ready', contains( confirm( $id ), 'unavailable' ) && ! contains( confirm( $id ), 'are ready' ) ); }
expect( 'renewal and historic purchased grants get direct management links', contains( confirm( 21 ), 'entitlement-order=21' ) && contains( confirm( 22 ), 'entitlement-order=22' ) );
expect( 'unowned or nonexistent order cannot render a card', '' === confirm( 19 ) && '' === confirm( 999 ) );
$GLOBALS['user_id'] = 0;
expect( 'signed-out confirmation requires an order key', '' === confirm( 10 ) );
$_GET['key'] = 'wrong';
expect( 'invalid order key is rejected', '' === confirm( 10 ) );
$_GET['key'] = array( 'key-10' );
expect( 'malformed order key is rejected without a type error', '' === confirm( 10 ) );
$_GET['key'] = 'key-10';
expect( 'signed-out purchaser with a key sees sign-in guidance', contains( confirm( 10 ), 'Sign in with the account used for this purchase.' ) );
$_GET['key'] = 'key-20';
$html = confirm( 20 );
expect( 'guest order gets contact guidance without an inaccessible button', contains( $html, 'not linked to an account' ) && ! contains( $html, 'Manage training entitlements' ) );
$_GET = array( 'entitlement-order' => 20 );
expect( 'guest cannot access grant details directly', '' === output( function() { controller()->endpoint(); } ) );
$GLOBALS['user_id'] = 7;
$_GET = array();
$actions = array( 'view' => array( 'url' => 'existing', 'name' => 'View' ) );
$changed = controller()->order_actions( $actions, $GLOBALS['orders'][10] );
expect( 'account order actions preserve existing actions and link to the purchase', $changed['view'] === $actions['view'] && contains( $changed['training-entitlements']['url'], 'entitlement-order=10' ) );
expect( 'account actions leave ordinary and unowned orders alone', $actions === controller()->order_actions( $actions, $GLOBALS['orders'][11] ) && $actions === controller()->order_actions( $actions, $GLOBALS['orders'][19] ) );
expect( 'account order details show the same management cue', contains( output( function() { controller()->view_order( 10 ); } ), 'Manage training entitlements' ) );
expect( 'account order details reject another customer', '' === output( function() { controller()->view_order( 19 ); } ) );
$_GET = array( 'entitlement-order' => 10 );
expect( 'login returns the purchaser to the order-specific entitlement screen', contains( controller()->login_redirect( 'default', (object) array( 'ID' => 7 ) ), 'entitlement-order=10' ) );
expect( 'login does not redirect another customer to unowned entitlements', 'default' === controller()->login_redirect( 'default', (object) array( 'ID' => 8 ) ) );
$_GET = array();
expect( 'ordinary logins retain their existing redirect', 'default' === controller()->login_redirect( 'default', (object) array( 'ID' => 7 ) ) );

// Count only usable capacity, including exact expiry and stale ownership cases.
$GLOBALS['grants'][] = grant( 10, array( 'id' => 101, 'expires_at' => gmdate( 'Y-m-d H:i:s' ) ) );
$GLOBALS['grants'][] = grant( 10, array( 'id' => 102, 'redeemed_count' => 3 ) );
$GLOBALS['grants'][] = grant( 10, array( 'id' => 103, 'status' => 'revoked' ) );
$GLOBALS['grants'][] = grant( 19 );
$GLOBALS['grants'][] = grant( 999 );
$GLOBALS['grants'][] = grant( 14 );
$html = output( function() { controller()->dashboard(); } );
expect( 'dashboard counts only usable entitlements on owned paid existing orders', contains( $html, 'You have 6 training entitlements available.' ) );
$_GET = array( 'entitlement-order' => 19 );
expect( 'direct order-specific access rejects another customer', contains( output( function() { controller()->endpoint(); } ), 'That order is not available.' ) );
$_GET = array( 'entitlement-order' => 12 );
expect( 'pending order details explain why no enrollment links exist', contains( output( function() { controller()->endpoint(); } ), 'once payment is confirmed' ) );
$_GET = array( 'entitlement-order' => 13 );
expect( 'paid empty order details show preparation guidance', contains( output( function() { controller()->endpoint(); } ), 'Payment has been confirmed' ) );
$_GET = array( 'entitlement-order' => 11 );
expect( 'ordinary order details do not promise future entitlements', contains( output( function() { controller()->endpoint(); } ), 'does not include training entitlements' ) );
$_GET = array( 'entitlement-order' => 10 );
$html = output( function() { controller()->endpoint(); } );
expect( 'only usable grants display copy controls and bearer links', 1 === substr_count( $html, 'class="button kitmage-lde-copy"' ) && contains( $html, 'secret-10' ) );
expect( 'grant details explain attendee redemption and provide accessible feedback', contains( $html, 'Each attendee signs in to claim one enrollment.' ) && contains( $html, 'role="status"' ) && contains( $html, 'readonly' ) );
expect( 'copy script is enqueued in the footer on order-specific details', isset( $GLOBALS['scripts']['kitmage-lde-account'] ) && $GLOBALS['scripts']['kitmage-lde-account'][1] );
$GLOBALS['grants'] = array( grant( 10, array( 'redeemed_count' => 3 ) ) );
expect( 'exhausted purchases do not claim readiness', ! contains( confirm( 10 ), 'are ready' ) );
expect( 'dashboard hides itself when no usable entitlements remain', '' === output( function() { controller()->dashboard(); } ) );
$GLOBALS['grants'] = array( grant( 10, array( 'total_count' => 2, 'redeemed_count' => 1 ) ) );
expect( 'dashboard uses the singular label for one entitlement', contains( output( function() { controller()->dashboard(); } ), 'You have 1 training entitlement available.' ) );
