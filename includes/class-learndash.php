<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class LearnDash {
	private $access_gate;
	public function __construct( FluentCRM_Access_Gate $access_gate = null ) { $this->access_gate = $access_gate; }
	public function valid_course( $course_id ) {
		$post_type = function_exists( 'learndash_get_post_type_slug' ) ? learndash_get_post_type_slug( 'course' ) : 'sfwd-courses';
		return $course_id > 0 && $post_type === get_post_type( $course_id ) && in_array( get_post_status( $course_id ), array( 'draft', 'publish' ), true );
	}
	public function has_access( $user_id, $course_id ) {
		$check = function() use ( $user_id, $course_id ) { return function_exists( 'sfwd_lms_has_access' ) && (bool) sfwd_lms_has_access( $course_id, $user_id ); };
		// Entitlement accounting needs the underlying enrollment state, not the
		// learner-facing FluentCRM authorization result layered on top of it.
		return $this->access_gate ? $this->access_gate->without_gate( $check ) : $check();
	}
	public function enroll( $user_id, $course_id ) {
		if ( ! function_exists( 'ld_update_course_access' ) ) { return false; }
		$operation = function() use ( $user_id, $course_id ) {
			ld_update_course_access( $user_id, $course_id, false );
			return $this->has_access( $user_id, $course_id );
		};
		return $operation();
	}
}
