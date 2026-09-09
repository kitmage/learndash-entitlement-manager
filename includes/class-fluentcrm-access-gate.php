<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

/** Composes LearnDash's existing positive result with a read-only FluentCRM gate. */
class FluentCRM_Access_Gate {
	private $requirements;
	private $fluentcrm;
	private $decisions = array();
	private $suspended = false;

	public function __construct( Course_Requirement_Repository $requirements, FluentCRM_Adapter $fluentcrm ) { $this->requirements = $requirements; $this->fluentcrm = $fluentcrm; }

	public function hooks() {
		add_filter( 'sfwd_lms_has_access', array( $this, 'filter_course_access' ), 20, 3 );
		add_filter( 'learndash_can_user_read_step', array( $this, 'filter_step_access' ), 20, 4 );
	}

	public function filter_course_access( $has_access, $post_id, $user_id ) {
		if ( ! $has_access || $this->suspended ) { return $has_access; }
		$course_id = $this->course_id( $post_id );
		return $course_id ? $this->meets_requirement( $user_id, $course_id ) : $has_access;
	}

	public function filter_step_access( $can_read, $user_id, $step_id, $course_id = 0 ) {
		if ( ! $can_read || $this->suspended ) { return $can_read; }
		$course_id = absint( $course_id ) ?: $this->course_id( $step_id );
		return $course_id ? $this->meets_requirement( $user_id, $course_id ) : $can_read;
	}

	public function meets_requirement( $user_id, $course_id ) {
		$key = absint( $user_id ) . ':' . absint( $course_id );
		if ( array_key_exists( $key, $this->decisions ) ) { return $this->decisions[ $key ]; }
		$rule = $this->requirements->get( $course_id );
		if ( ! $rule['enabled'] ) { return $this->decisions[ $key ] = true; }
		// Enabled-but-empty, missing API, missing/deleted tag, or missing contact all fail closed.
		if ( ! $rule['tag_ids'] || ! $this->fluentcrm->available() ) { return $this->decisions[ $key ] = false; }
		$known_ids = array_keys( $this->fluentcrm->tags() );
		if ( array_diff( $rule['tag_ids'], $known_ids ) ) { return $this->decisions[ $key ] = false; }
		return $this->decisions[ $key ] = $this->fluentcrm->contact_matches( $this->fluentcrm->contact( $user_id ), $rule['tag_ids'], $rule['match'] );
	}

	public function without_gate( $callback ) {
		$previous = $this->suspended; $this->suspended = true;
		try { return call_user_func( $callback ); } finally { $this->suspended = $previous; }
	}

	private function course_id( $post_id ) {
		$post_id = absint( $post_id );
		$course_type = function_exists( 'learndash_get_post_type_slug' ) ? learndash_get_post_type_slug( 'course' ) : 'sfwd-courses';
		if ( $course_type === get_post_type( $post_id ) ) { return $post_id; }
		return function_exists( 'learndash_get_course_id' ) ? absint( learndash_get_course_id( $post_id ) ) : 0;
	}
}
