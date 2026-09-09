<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Enrollment_Controller {
	private $repository;
	private $redemption;
	public function __construct( Repository $repository, Redemption_Service $redemption ) { $this->repository = $repository; $this->redemption = $redemption; }
	public function hooks() {
		add_action( 'init', array( $this, 'rewrite' ) );
		add_filter( 'query_vars', function( $vars ) { $vars[] = 'aspen_lde_token'; return $vars; } );
		add_action( 'template_redirect', array( $this, 'dispatch' ) );
	}
	public function rewrite() { add_rewrite_rule( '^training-enroll/([A-Za-z0-9_-]{43,})/?$', 'index.php?aspen_lde_token=$matches[1]', 'top' ); }
	public static function url( $token ) { return home_url( user_trailingslashit( 'training-enroll/' . rawurlencode( $token ) ) ); }

	public function dispatch() {
		$token = get_query_var( 'aspen_lde_token' );
		if ( ! $token ) { return; }
		$token = sanitize_text_field( $token );
		$grant = $this->repository->find_by_token( $token );
		if ( ! $grant ) { $this->render( __( 'Invalid enrollment link', 'aspen-learndash-entitlements' ), __( 'This enrollment link is invalid.', 'aspen-learndash-entitlements' ), 404 ); }
		$state = Repository::state( $grant );
		if ( 'active' !== $state ) { $this->render( __( 'Enrollment unavailable', 'aspen-learndash-entitlements' ), $this->state_message( $state ), 410 ); }
		if ( ! is_user_logged_in() ) {
			$return = self::url( $token );
			$url = function_exists( 'wc_get_page_permalink' ) ? add_query_arg( 'redirect', $return, wc_get_page_permalink( 'myaccount' ) ) : wp_login_url( $return );
			wp_safe_redirect( $url ); exit;
		}
		if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
			if ( ! isset( $_POST['aspen_lde_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aspen_lde_nonce'] ) ), 'aspen_lde_redeem_' . $grant['id'] ) ) {
				$this->render( __( 'Security check failed', 'aspen-learndash-entitlements' ), __( 'Please return to the enrollment link and try again.', 'aspen-learndash-entitlements' ), 403 );
			}
			$result = $this->redemption->redeem( $token, get_current_user_id() );
			if ( is_wp_error( $result ) ) { $this->render( __( 'Enrollment unavailable', 'aspen-learndash-entitlements' ), $result->get_error_message(), 409 ); }
			// The destination is trusted administrator configuration snapshotted server-side,
			// never browser input; external training portals are intentionally supported.
			wp_redirect( $this->destination( $result['grant'] ) ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
			exit;
		}
		$course = get_the_title( $grant['course_id'] );
		ob_start();
		include ASPEN_LDE_PATH . 'templates/enrollment.php';
		$this->render( sprintf( __( 'Enroll in %s?', 'aspen-learndash-entitlements' ), $course ), ob_get_clean(), 200, true );
	}

	private function destination( $grant ) {
		$url = ! empty( $grant['redirect_url'] ) ? esc_url_raw( $grant['redirect_url'] ) : get_permalink( $grant['course_id'] );
		return $url ?: ( get_permalink( $grant['course_id'] ) ?: home_url( '/' ) );
	}
	private function state_message( $state ) {
		return array_key_exists( $state, array( 'expired' => 1, 'exhausted' => 1 ) ) ? ( 'expired' === $state ? __( 'This enrollment link has expired.', 'aspen-learndash-entitlements' ) : __( 'All enrollments have been redeemed.', 'aspen-learndash-entitlements' ) ) : __( 'This enrollment link is unavailable.', 'aspen-learndash-entitlements' );
	}
	private function render( $title, $content, $code = 200, $html = false ) {
		status_header( $code ); nocache_headers();
		get_header(); echo '<main class="aspen-lde-enrollment" style="max-width:760px;margin:3rem auto;padding:0 1rem"><h1>' . esc_html( $title ) . '</h1>' . ( $html ? $content : '<p>' . esc_html( $content ) . '</p>' ) . '</main>'; get_footer(); exit;
	}
}
