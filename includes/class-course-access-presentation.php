<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

/** Replaces LearnDash's closed-course CTA for enrolled users denied by the tag gate. */
final class Course_Access_Presentation {
	private $requirements;
	private $access_gate;
	private $learndash;

	public function __construct( Course_Requirement_Repository $requirements, FluentCRM_Access_Gate $access_gate, LearnDash $learndash ) {
		$this->requirements = $requirements;
		$this->access_gate = $access_gate;
		$this->learndash = $learndash;
	}

	public function hooks() {
		add_filter( 'learndash_payment_closed_button', array( $this, 'filter_closed_button' ), 20, 2 );
	}

	public function filter_closed_button( $button, $payment_params = array() ) {
		$course_id = $this->course_id( $payment_params );
		$user_id = get_current_user_id();
		if ( ! $course_id || ! $user_id || ! $this->should_replace( $course_id, $user_id ) ) { return $button; }

		$url = $this->requirements->get( $course_id )['next_url'];
		return '<a class="btn-join button button-primary button-large wp-element-button ld--ignore-inline-css learndash-button-closed" id="btn-join" href="' . esc_url( $url ) . '">' . esc_html__( 'Next', 'aspen-learndash-entitlements' ) . '</a>';
	}

	public function should_replace( $course_id, $user_id ) {
		$rule = $this->requirements->get( $course_id );
		if ( ! $rule['enabled'] || ! $rule['next_url'] ) { return false; }
		if ( ! $this->learndash->has_access( $user_id, $course_id ) ) { return false; }
		return ! $this->access_gate->meets_requirement( $user_id, $course_id );
	}

	private function course_id( $payment_params ) {
		$post = is_array( $payment_params ) && isset( $payment_params['post'] ) ? $payment_params['post'] : null;
		if ( is_object( $post ) && isset( $post->ID ) ) { return absint( $post->ID ); }
		if ( is_numeric( $post ) ) { return absint( $post ); }
		if ( is_array( $payment_params ) && isset( $payment_params['course_id'] ) ) { return absint( $payment_params['course_id'] ); }
		return absint( get_the_ID() );
	}
}
