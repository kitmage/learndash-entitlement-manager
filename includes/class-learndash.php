<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class LearnDash {
	public function valid_course( $course_id ) {
		$post_type = function_exists( 'learndash_get_post_type_slug' ) ? learndash_get_post_type_slug( 'course' ) : 'sfwd-courses';
		return $course_id > 0 && $post_type === get_post_type( $course_id ) && in_array( get_post_status( $course_id ), array( 'draft', 'publish' ), true );
	}
	public function has_access( $user_id, $course_id ) {
		return function_exists( 'sfwd_lms_has_access' ) && (bool) sfwd_lms_has_access( $course_id, $user_id );
	}
	public function enroll( $user_id, $course_id ) {
		if ( ! function_exists( 'ld_update_course_access' ) ) { return false; }
		ld_update_course_access( $user_id, $course_id, false );
		return $this->has_access( $user_id, $course_id );
	}
}
