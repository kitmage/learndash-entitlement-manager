<?php
// Exercise the real controller and redemption service with WordPress/LearnDash fixtures.
define( 'ABSPATH', __DIR__ );
define( 'ARRAY_A', 'ARRAY_A' );
define( 'KITMAGE_LDE_PATH', dirname( __DIR__ ) . '/' );
$GLOBALS['options'] = array( 'kitmage_lde_enrollment_page_id' => 90 );
$GLOBALS['pages'] = array( 90 => (object) array( 'ID' => 90, 'post_type' => 'page', 'post_status' => 'publish', 'post_password' => '' ) );
$GLOBALS['token'] = str_repeat( 'a', 43 );
$GLOBALS['query_token'] = $GLOBALS['token'];
$GLOBALS['page_id'] = 90;
$GLOBALS['singular'] = true;
$GLOBALS['logged_in'] = true;
$GLOBALS['status'] = 200;
$GLOBALS['nocache'] = 0;
$GLOBALS['header_calls'] = 0;
$GLOBALS['hooks'] = array();
$GLOBALS['shortcodes'] = array();
$GLOBALS['access'] = false;
$GLOBALS['course_valid'] = true;
$GLOBALS['settings_errors'] = array();
$_SERVER['REQUEST_METHOD'] = 'GET';
$_POST = array();
$_GET = array();
function add_action( $hook, $callback, $priority = 10 ) { $GLOBALS['hooks'][$hook] = array( $callback, $priority ); }
function add_filter( $hook, $callback, $priority = 10 ) { add_action( $hook, $callback, $priority ); }
function add_shortcode( $name, $callback ) { $GLOBALS['shortcodes'][$name] = $callback; }
function __( $text ) { return $text; }
function esc_html( $text ) { return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $text ) { return esc_html( $text ); }
function esc_html__( $text ) { return esc_html( $text ); }
function esc_html_e( $text ) { echo esc_html( $text ); }
function absint( $value ) { return abs( (int) $value ); }
function get_option( $key, $default = false ) { return isset( $GLOBALS['options'][$key] ) ? $GLOBALS['options'][$key] : $default; }
function get_post( $id ) { return isset( $GLOBALS['pages'][$id] ) ? $GLOBALS['pages'][$id] : null; }
function get_permalink( $id ) { return 90 === $id ? ( isset( $GLOBALS['page_permalink'] ) ? $GLOBALS['page_permalink'] : 'https://example.test/training-enrollment/' ) : 'https://example.test/course/'; }
function home_url( $path ) { return 'https://example.test/' . ltrim( $path, '/' ); }
function user_trailingslashit( $path ) { return $path . '/'; }
function add_query_arg( $key, $value, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . urlencode( $key ) . '=' . urlencode( $value ); }
function get_query_var( $key, $default = '' ) { return 'kitmage_lde_token' === $key ? $GLOBALS['query_token'] : $default; }
function is_page( $id ) { return $GLOBALS['singular'] && $GLOBALS['page_id'] === $id; }
function is_singular() { return $GLOBALS['singular']; }
function is_admin() { return false; }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function get_current_user_id() { return $GLOBALS['logged_in'] ? 7 : 0; }
function get_the_title() { return 'Course <script>unsafe</script>'; }
function wc_get_page_permalink() { return 'https://example.test/account/'; }
function status_header( $code ) { $GLOBALS['status'] = $code; }
function nocache_headers() { $GLOBALS['nocache']++; }
function get_header() { $GLOBALS['header_calls']++; throw new Legacy_Render(); }
function get_footer() { $GLOBALS['header_calls']++; }
function wp_unslash( $value ) { return stripslashes( $value ); }
function sanitize_text_field( $value ) { return strip_tags( $value ); }
function wp_verify_nonce( $nonce, $action ) { return 'nonce-' . $action === $nonce; }
function wp_nonce_field( $action, $name ) { echo '<input type="hidden" name="' . esc_html( $name ) . '" value="nonce-' . esc_html( $action ) . '">'; }
function wp_safe_redirect( $url, $code = 302 ) { throw new Enrollment_Redirect( $url, $code, true ); }
function wp_redirect( $url, $code = 302 ) { throw new Enrollment_Redirect( $url, $code, false ); }
function is_wp_error( $value ) { return $value instanceof WP_Error; }
function esc_url_raw( $url ) { return filter_var( $url, FILTER_VALIDATE_URL ) ? $url : ''; }
function get_post_type() { return $GLOBALS['course_valid'] ? 'sfwd-courses' : 'post'; }
function get_post_status() { return 'publish'; }
function sfwd_lms_has_access() { return $GLOBALS['access']; }
function ld_update_course_access() { $GLOBALS['access'] = true; }
function get_userdata() { return (object) array( 'display_name' => 'Attendee' ); }
function get_user_meta() { return ''; }
function current_time() { return gmdate( 'Y-m-d H:i:s' ); }
function add_settings_error( $option, $code, $message ) { $GLOBALS['settings_errors'][] = $code; }
function add_options_page( $title, $label, $capability, $slug, $callback ) { $GLOBALS['settings_menu'] = array( $capability, $slug ); }
function register_setting( $group, $option, $args ) { $GLOBALS['setting'] = array( $option, $args ); }
function add_settings_section() {}
function add_settings_field() {}

final class WP_Error {
	private $message;
	public function __construct( $code, $message ) { $this->message = $message; }
	public function get_error_message() { return $this->message; }
}
final class Legacy_Render extends RuntimeException {}
final class Enrollment_Redirect extends RuntimeException {
	public $url;
	public $status;
	public $safe;
	public function __construct( $url, $status, $safe ) { $this->url = $url; $this->status = $status; $this->safe = $safe; }
}
final class Enrollment_DB {
	public $prefix = 'test_';
	public $redemptions = array();
	public $transactions = 0;
	private $snapshot;
	public function prepare( $query, ...$args ) { return array( $query, $args ); }
	public function get_row( $prepared ) {
		if ( false !== strpos( $prepared[0], 'redemptions' ) ) { return isset( $this->redemptions[$prepared[1][1]] ) ? $this->redemptions[$prepared[1][1]] : null; }
		return $GLOBALS['grant'] && $GLOBALS['grant']['token'] === $prepared[1][0] ? $GLOBALS['grant'] : null;
	}
	public function query( $query ) {
		if ( 'START TRANSACTION' === $query ) { $this->transactions++; $this->snapshot = array( $GLOBALS['grant'], $this->redemptions ); return 1; }
		if ( 'ROLLBACK' === $query ) { list( $GLOBALS['grant'], $this->redemptions ) = $this->snapshot; return 1; }
		if ( 'COMMIT' === $query ) { return 1; }
		if ( $GLOBALS['grant']['redeemed_count'] >= $GLOBALS['grant']['total_count'] ) { return 0; }
		$GLOBALS['grant']['redeemed_count']++;
		return 1;
	}
	public function insert( $table, $data ) { $this->redemptions[$data['user_id']] = $data; return 1; }
}
$GLOBALS['wpdb'] = new Enrollment_DB();
function reset_grant() {
	$GLOBALS['grant'] = array( 'id' => 4, 'token' => $GLOBALS['token'], 'course_id' => 41, 'status' => 'active', 'total_count' => 3, 'redeemed_count' => 0, 'expires_at' => null, 'redirect_url' => '' );
	$GLOBALS['wpdb']->redemptions = array();
	$GLOBALS['access'] = false;
	$GLOBALS['course_valid'] = true;
	$_POST = array();
	$_SERVER['REQUEST_METHOD'] = 'GET';
}
reset_grant();

require_once KITMAGE_LDE_PATH . 'includes/class-database.php';
require_once KITMAGE_LDE_PATH . 'includes/class-repository.php';
require_once KITMAGE_LDE_PATH . 'includes/class-learndash.php';
require_once KITMAGE_LDE_PATH . 'includes/class-redemption-service.php';
require_once KITMAGE_LDE_PATH . 'includes/class-enrollment-controller.php';
require_once KITMAGE_LDE_PATH . 'includes/class-enrollment-settings.php';
use KitMage\LearnDashEntitlements\Repository;
use KitMage\LearnDashEntitlements\LearnDash;
use KitMage\LearnDashEntitlements\Redemption_Service;
use KitMage\LearnDashEntitlements\Enrollment_Controller;
use KitMage\LearnDashEntitlements\Enrollment_Settings;
function controller() { $repository = new Repository(); return new Enrollment_Controller( $repository, new Redemption_Service( $repository, new LearnDash() ) ); }
function expect( $label, $condition ) { if ( ! $condition ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); } echo "PASS: {$label}\n"; }
function contains( $html, $text ) { return false !== strpos( $html, $text ); }
function redirected( $controller ) { try { $controller->dispatch(); } catch ( Enrollment_Redirect $redirect ) { return $redirect; } return null; }

$controller = controller();
$controller->hooks();
expect( 'shortcode registered and submissions run before standard template redirects', isset( $GLOBALS['shortcodes']['training-enrollment'] ) && 1 === $GLOBALS['hooks']['template_redirect'][1] );
$controller->dispatch();
$html = $controller->shortcode();
expect( 'normal page rendering continues without manually loading header or footer', 0 === $GLOBALS['header_calls'] && 200 === $GLOBALS['status'] );
expect( 'shortcode returns theme-styleable markup without forced spacing or document elements', contains( $html, 'class="kitmage-lde-enrollment"' ) && ! contains( $html, '<main' ) && ! contains( $html, 'style=' ) );
expect( 'course title is escaped and form posts to the chosen page with a nonce', contains( $html, '&lt;script&gt;unsafe&lt;/script&gt;' ) && contains( $html, '/training-enrollment/?kitmage_lde_token=' ) && contains( $html, 'name="kitmage_lde_nonce"' ) );
expect( 'page rendering and repeated shortcode evaluation do not redeem', 0 === $GLOBALS['wpdb']->transactions && 0 === $GLOBALS['grant']['redeemed_count'] );
$controller->shortcode();
expect( 'Elementor repeated rendering leaves capacity unchanged', 0 === $GLOBALS['wpdb']->transactions );
expect( 'enrollment responses opt out of caching', defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE && $GLOBALS['nocache'] > 0 );
$robots = $controller->robots( array( 'index' => true ) );
expect( 'enrollment pages opt out of indexing', isset( $robots['noindex'] ) && ! isset( $robots['index'] ) );

$GLOBALS['query_token'] = '';
$controller = controller();
$controller->dispatch();
expect( 'editor and direct-page views without a token show useful neutral guidance', contains( $controller->shortcode(), 'Open the enrollment link supplied' ) && ! contains( $controller->shortcode(), '<form' ) );
$GLOBALS['query_token'] = 'invalid<script>';
$controller = controller();
$controller->dispatch();
expect( 'invalid links render an error within the chosen page', 404 === $GLOBALS['status'] && contains( $controller->shortcode(), 'This enrollment link is invalid.' ) && 0 === $GLOBALS['header_calls'] );
$GLOBALS['query_token'] = $GLOBALS['token'];
foreach ( array( 'expired', 'exhausted', 'revoked' ) as $state ) {
	reset_grant();
	if ( 'expired' === $state ) { $GLOBALS['grant']['expires_at'] = gmdate( 'Y-m-d H:i:s' ); }
	if ( 'exhausted' === $state ) { $GLOBALS['grant']['redeemed_count'] = 3; }
	if ( 'revoked' === $state ) { $GLOBALS['grant']['status'] = 'revoked'; }
	$controller = controller(); $controller->dispatch();
	expect( $state . ' link uses page content without a confirmation form', 410 === $GLOBALS['status'] && contains( $controller->shortcode(), 'Enrollment unavailable' ) && ! contains( $controller->shortcode(), '<form' ) );
}
reset_grant();
$GLOBALS['logged_in'] = false;
$controller = controller(); $controller->dispatch();
$html = $controller->shortcode();
expect( 'signed-out visitors see an in-page sign-in/create-account action', contains( $html, 'Sign in / Create account' ) && contains( $html, 'kitmage_lde_login_token=' ) && ! contains( $html, '<form' ) );
$_GET[Enrollment_Controller::LOGIN_TOKEN] = $GLOBALS['token'];
expect( 'WooCommerce login and registration return to the enrollment page', Enrollment_Controller::url( $GLOBALS['token'] ) === $controller->login_redirect( 'default' ) && isset( $GLOBALS['hooks']['woocommerce_registration_redirect'] ) );
$_GET[Enrollment_Controller::LOGIN_TOKEN] = 'https://evil.example/';
expect( 'login return cannot be an arbitrary external URL', 'default' === $controller->login_redirect( 'default' ) );
$_GET[Enrollment_Controller::LOGIN_TOKEN] = array( $GLOBALS['token'] );
expect( 'malformed login return is ignored', 'default' === $controller->login_redirect( 'default' ) );
$_GET = array();
$GLOBALS['logged_in'] = true;

$_SERVER['REQUEST_METHOD'] = 'POST';
$before = $GLOBALS['wpdb']->transactions;
$controller = controller(); $controller->dispatch();
expect( 'POST without a nonce renders security feedback on the page without redeeming', 403 === $GLOBALS['status'] && contains( $controller->shortcode(), 'Security check failed' ) && $before === $GLOBALS['wpdb']->transactions );
$_POST['kitmage_lde_nonce'] = array( 'nonce-kitmage_lde_redeem_4' );
$controller = controller(); $controller->dispatch();
expect( 'malformed nonce is rejected safely', 403 === $GLOBALS['status'] && $before === $GLOBALS['wpdb']->transactions );
$_POST['kitmage_lde_nonce'] = 'nonce-kitmage_lde_redeem_4';
$GLOBALS['course_valid'] = false;
$controller = controller(); $controller->dispatch();
expect( 'redemption service errors stay within the page and preserve capacity', 409 === $GLOBALS['status'] && contains( $controller->shortcode(), 'This course is not currently available.' ) && 0 === $GLOBALS['grant']['redeemed_count'] );
$GLOBALS['course_valid'] = true;
$redirect = redirected( controller() );
expect( 'valid POST redeems once and redirects to the course', $redirect && ! $redirect->safe && 'https://example.test/course/' === $redirect->url && 1 === $GLOBALS['grant']['redeemed_count'] );
$redirect = redirected( controller() );
expect( 'repeated POST keeps existing idempotent redemption behavior', $redirect && 1 === $GLOBALS['grant']['redeemed_count'] );
reset_grant();
$GLOBALS['grant']['redirect_url'] = 'https://training.example/next';
$_SERVER['REQUEST_METHOD'] = 'POST'; $_POST['kitmage_lde_nonce'] = 'nonce-kitmage_lde_redeem_4';
$redirect = redirected( controller() );
expect( 'successful enrollment retains the purchased redirect destination', $redirect && 'https://training.example/next' === $redirect->url );

reset_grant();
$GLOBALS['singular'] = false; $GLOBALS['page_id'] = 0;
$redirect = redirected( controller() );
expect( 'existing bearer links route to the actual WordPress page', $redirect && $redirect->safe && 302 === $redirect->status && Enrollment_Controller::url( $GLOBALS['token'] ) === $redirect->url );
$_SERVER['REQUEST_METHOD'] = 'POST';
$before = $GLOBALS['wpdb']->transactions;
$redirect = redirected( controller() );
expect( 'in-flight legacy forms preserve POST data when changing enrollment pages', $redirect && 307 === $redirect->status && $before === $GLOBALS['wpdb']->transactions );
$GLOBALS['options'][Enrollment_Controller::PAGE_OPTION] = 0;
expect( 'unconfigured sites keep existing enrollment URLs', 'https://example.test/training-enroll/' . $GLOBALS['token'] . '/' === Enrollment_Controller::url( $GLOBALS['token'] ) );
$_SERVER['REQUEST_METHOD'] = 'GET';
try { controller()->dispatch(); } catch ( Legacy_Render $ignored ) {}
expect( 'unconfigured sites retain the built-in renderer', 1 === $GLOBALS['header_calls'] );
$GLOBALS['singular'] = true; $GLOBALS['page_id'] = 91;
$before = $GLOBALS['header_calls']; controller()->dispatch();
expect( 'shortcodes on ordinary pages do not hijack their WordPress template', $before === $GLOBALS['header_calls'] );
expect( 'unconfigured shortcode can still display a challenge with a compatible action', contains( controller()->shortcode(), '/training-enroll/' ) );

$settings = new Enrollment_Settings();
$settings->menu(); $settings->register();
expect( 'page selection uses the standard administrator-only Settings API', array( 'manage_options', 'kitmage-training-enrollment' ) === $GLOBALS['settings_menu'] && Enrollment_Controller::PAGE_OPTION === $GLOBALS['setting'][0] );
expect( 'published public page is accepted', 90 === $settings->sanitize_page( '90' ) );
$GLOBALS['options'][Enrollment_Controller::PAGE_OPTION] = 90;
foreach ( array( 'draft', 'private', 'trash' ) as $status ) {
	$GLOBALS['pages'][90]->post_status = $status;
	expect( $status . ' page falls back to working legacy URLs', 0 === Enrollment_Controller::page_id() && contains( Enrollment_Controller::url( $GLOBALS['token'] ), '/training-enroll/' ) );
}
$GLOBALS['pages'][90]->post_status = 'publish'; $GLOBALS['pages'][90]->post_password = 'protected';
expect( 'password-protected pages are not usable enrollment destinations', 0 === Enrollment_Controller::page_id() );
$GLOBALS['pages'][90]->post_password = '';
foreach ( array( 999, array( 90 ), 'not-a-number', -90 ) as $invalid ) {
	$before = count( $GLOBALS['settings_errors'] );
	expect( 'invalid setting preserves prior selection and reports an error', 90 === $settings->sanitize_page( $invalid ) && count( $GLOBALS['settings_errors'] ) === $before + 1 );
}
expect( 'page selection can be disabled explicitly', 0 === $settings->sanitize_page( '0' ) );
$GLOBALS['options'][Enrollment_Controller::PAGE_OPTION] = 0; $GLOBALS['query_token'] = '';
expect( 'ordinary site pages retain their robots policy', array( 'index' => true ) === controller()->robots( array( 'index' => true ) ) );
$GLOBALS['options'][Enrollment_Controller::PAGE_OPTION] = 90;
$GLOBALS['page_permalink'] = 'https://example.test/?page_id=90';
expect( 'plain permalink sites retain the page query when adding an enrollment token', contains( Enrollment_Controller::url( $GLOBALS['token'] ), '?page_id=90&kitmage_lde_token=' ) );
