<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Redemption_Service {
	private $repository;
	private $learndash;
	public function __construct( Repository $repository, LearnDash $learndash ) { $this->repository = $repository; $this->learndash = $learndash; }

	public function redeem( $token, $user_id ) {
		global $wpdb;
		$wpdb->query( 'START TRANSACTION' );
		$grant = $this->repository->find_by_token( $token, true );
		if ( ! $grant ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'invalid', __( 'This enrollment link is invalid.', 'aspen-learndash-entitlements' ) ); }
		if ( $this->repository->find_redemption( $grant['id'], $user_id ) ) { $wpdb->query( 'COMMIT' ); return array( 'status' => 'duplicate', 'grant' => $grant ); }
		$state = Repository::state( $grant );
		if ( 'active' !== $state ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( $state, $this->state_message( $state ) ); }
		if ( ! $this->learndash->valid_course( (int) $grant['course_id'] ) ) { $wpdb->query( 'ROLLBACK' ); return new \WP_Error( 'course', __( 'This course is not currently available.', 'aspen-learndash-entitlements' ) ); }
		if ( $this->learndash->has_access( $user_id, (int) $grant['course_id'] ) ) { $wpdb->query( 'COMMIT' ); return array( 'status' => 'already_enrolled', 'grant' => $grant ); }
		$user = get_userdata( $user_id );
		$name = trim( get_user_meta( $user_id, 'first_name', true ) . ' ' . get_user_meta( $user_id, 'last_name', true ) );
		$name = $name ?: $user->display_name;
		$inserted = $wpdb->insert( Database::redemptions_table(), array( 'grant_id' => $grant['id'], 'user_id' => $user_id, 'redeemer_name' => $name, 'redeemed_at' => current_time( 'mysql', true ) ) );
		$updated = $wpdb->query( $wpdb->prepare( 'UPDATE ' . Database::grants_table() . ' SET redeemed_count = redeemed_count + 1, updated_at = UTC_TIMESTAMP() WHERE id = %d AND redeemed_count < total_count', $grant['id'] ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		if ( ! $inserted || 1 !== $updated || ! $this->learndash->enroll( $user_id, (int) $grant['course_id'] ) ) {
			$wpdb->query( 'ROLLBACK' );
			return new \WP_Error( 'enrollment_failed', __( 'Enrollment could not be completed. Please try again.', 'aspen-learndash-entitlements' ) );
		}
		$wpdb->query( 'COMMIT' );
		$grant['redeemed_count']++;
		return array( 'status' => 'success', 'grant' => $grant );
	}

	private function state_message( $state ) {
		$messages = array( 'expired' => __( 'This enrollment link has expired.', 'aspen-learndash-entitlements' ), 'exhausted' => __( 'All enrollments have been redeemed.', 'aspen-learndash-entitlements' ), 'revoked' => __( 'This enrollment link is unavailable.', 'aspen-learndash-entitlements' ) );
		return isset( $messages[ $state ] ) ? $messages[ $state ] : __( 'This enrollment link is unavailable.', 'aspen-learndash-entitlements' );
	}
}
