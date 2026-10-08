<?php
namespace KitMage\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Enrollment_Controller {
	const PAGE_OPTION = 'kitmage_lde_enrollment_page_id';
	const LOGIN_TOKEN = 'kitmage_lde_login_token';
	private $repository;
	private $redemption;
	private $view;
	public function __construct( Repository $repository, Redemption_Service $redemption ) { $this->repository = $repository; $this->redemption = $redemption; }
	public function hooks() {
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', function( $vars ) { $vars[] = 'kitmage_lde_token'; return $vars; } );
		add_shortcode( 'training-enrollment', array( $this, 'shortcode' ) );
		// Submit and redirect before WordPress or Elementor sends page output.
		add_action( 'template_redirect', array( $this, 'dispatch' ), 1 );
		add_filter( 'woocommerce_login_redirect', array( $this, 'login_redirect' ), 20 );
		add_filter( 'woocommerce_registration_redirect', array( $this, 'login_redirect' ), 20 );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
	}
	public function rewrite() { add_rewrite_rule( '^training-enroll/([A-Za-z0-9_-]{43,})/?$', 'index.php?kitmage_lde_token=$matches[1]', 'top' ); }

	public static function page_id() {
		$id = absint( get_option( self::PAGE_OPTION, 0 ) );
		$page = $id ? get_post( $id ) : null;
		return $page && 'page' === $page->post_type && 'publish' === $page->post_status && ! $page->post_password ? $id : 0;
	}
	public static function url( $token ) {
		$page_id = self::page_id();
		if ( $page_id && get_permalink( $page_id ) ) { return add_query_arg( 'kitmage_lde_token', $token, get_permalink( $page_id ) ); }
		return home_url( user_trailingslashit( 'training-enroll/' . rawurlencode( $token ) ) );
	}
	private function token() {
		$token = get_query_var( 'kitmage_lde_token', '' );
		return is_string( $token ) ? $token : '';
	}
	private static function valid_token( $token ) { return is_string( $token ) && 1 === preg_match( '/\A[A-Za-z0-9_-]{43,}\z/', $token ); }

	public function dispatch() {
		if ( is_admin() ) { return; }
		$token = $this->token();
		$page_id = self::page_id();
		$on_page = $page_id && is_page( $page_id );
		// Ordinary WordPress pages keep their normal template even with a token.
		if ( is_singular() && ! $on_page ) { return; }
		if ( ! $token && ! $on_page ) { return; }
		$this->protect_response();
		if ( $page_id && ! $on_page ) {
			// Preserve old links and in-flight confirmation forms when switching pages.
			wp_safe_redirect( self::url( $token ), 'POST' === $_SERVER['REQUEST_METHOD'] ? 307 : 302 ); exit;
		}
		$this->view = $this->prepare_view( $token );
		if ( $this->view['form'] && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			$nonce = isset( $_POST['kitmage_lde_nonce'] ) && is_string( $_POST['kitmage_lde_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['kitmage_lde_nonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'kitmage_lde_redeem_' . $this->view['grant']['id'] ) ) {
				$this->view = $this->message_view( __( 'Security check failed', 'kitmage-learndash-entitlements' ), __( 'Please reopen your enrollment link and try again.', 'kitmage-learndash-entitlements' ), 403 );
			} else {
				$result = $this->redemption->redeem( $token, get_current_user_id() );
				if ( is_wp_error( $result ) ) {
					$this->view = $this->message_view( __( 'Enrollment unavailable', 'kitmage-learndash-entitlements' ), $result->get_error_message(), 409 );
				} else {
					// The redirect is trusted administrator configuration snapshotted at purchase.
					wp_redirect( $this->destination( $result['grant'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
					exit;
				}
			}
		}
		status_header( $this->view['code'] );
		if ( $on_page ) { return; } // Let the normal page loop and Elementor render.
		if ( $this->view['login_url'] ) { wp_safe_redirect( $this->view['login_url'] ); exit; }
		$this->render( $this->view['title'], $this->component( $this->view, false ), $this->view['code'], true );
	}

	public function shortcode() {
		$token = $this->token();
		if ( $token ) { $this->protect_response(); }
		// Rendering a shortcode (including an Elementor preview) never redeems a seat.
		return $this->component( $this->view ?: $this->prepare_view( $token ) );
	}
	private function message_view( $title, $message, $code = 200 ) {
		return array( 'title' => $title, 'message' => $message, 'code' => $code, 'form' => false, 'login_url' => '', 'token' => '', 'grant' => null );
	}
	private function prepare_view( $token ) {
		if ( ! $token ) { return $this->message_view( __( 'Training enrollment', 'kitmage-learndash-entitlements' ), __( 'Open the enrollment link supplied by your training organizer to select your course.', 'kitmage-learndash-entitlements' ) ); }
		$grant = self::valid_token( $token ) ? $this->repository->find_by_token( $token ) : null;
		if ( ! $grant ) { return $this->message_view( __( 'Invalid enrollment link', 'kitmage-learndash-entitlements' ), __( 'This enrollment link is invalid.', 'kitmage-learndash-entitlements' ), 404 ); }
		$state = Repository::state( $grant );
		if ( 'active' !== $state ) { return $this->message_view( __( 'Enrollment unavailable', 'kitmage-learndash-entitlements' ), $this->state_message( $state ), 410 ); }
		$view = $this->message_view( sprintf( __( 'Enroll in %s?', 'kitmage-learndash-entitlements' ), get_the_title( $grant['course_id'] ) ), '' );
		$view['token'] = $token;
		$view['grant'] = $grant;
		$view['form'] = is_user_logged_in();
		if ( ! $view['form'] ) {
			$view['message'] = __( 'Sign in or create an account to confirm your enrollment.', 'kitmage-learndash-entitlements' );
			$view['login_url'] = function_exists( 'wc_get_page_permalink' ) ? add_query_arg( self::LOGIN_TOKEN, $token, wc_get_page_permalink( 'myaccount' ) ) : wp_login_url( self::url( $token ) );
		}
		return $view;
	}
	private function component( $view, $show_title = true ) {
		$token = $view['token'];
		$grant = $view['grant'];
		ob_start();
		include KITMAGE_LDE_PATH . 'templates/enrollment-view.php';
		return ob_get_clean();
	}
	private function protect_response() {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) { define( 'DONOTCACHEPAGE', true ); }
		nocache_headers();
		if ( ! headers_sent() ) { header( 'Referrer-Policy: no-referrer' ); }
	}
	public function robots( $robots ) {
		$page_id = self::page_id();
		if ( $this->token() || ( $page_id && is_page( $page_id ) ) ) { unset( $robots['index'] ); $robots['noindex'] = true; }
		return $robots;
	}
	public function login_redirect( $redirect ) {
		$token = isset( $_GET[ self::LOGIN_TOKEN ] ) && is_string( $_GET[ self::LOGIN_TOKEN ] ) ? wp_unslash( $_GET[ self::LOGIN_TOKEN ] ) : '';
		return self::valid_token( $token ) ? self::url( $token ) : $redirect;
	}

	private function destination( $grant ) {
		$url = ! empty( $grant['redirect_url'] ) ? esc_url_raw( $grant['redirect_url'] ) : get_permalink( $grant['course_id'] );
		return $url ?: ( get_permalink( $grant['course_id'] ) ?: home_url( '/' ) );
	}
	private function state_message( $state ) {
		return array_key_exists( $state, array( 'expired' => 1, 'exhausted' => 1 ) ) ? ( 'expired' === $state ? __( 'This enrollment link has expired.', 'kitmage-learndash-entitlements' ) : __( 'All enrollments have been redeemed.', 'kitmage-learndash-entitlements' ) ) : __( 'This enrollment link is unavailable.', 'kitmage-learndash-entitlements' );
	}
	private function render( $title, $content, $code = 200, $html = false ) {
		status_header( $code ); nocache_headers();
		get_header(); echo '<main class="kitmage-lde-enrollment" style="max-width:760px;margin:3rem auto;padding:0 1rem"><h1>' . esc_html( $title ) . '</h1>' . ( $html ? $content : '<p>' . esc_html( $content ) . '</p>' ) . '</main>'; get_footer(); exit;
	}
}
