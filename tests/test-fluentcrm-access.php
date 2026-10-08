<?php
// Lightweight unit harness: no WordPress, LearnDash, or FluentCRM installation required.
define( 'ABSPATH', __DIR__ );
$GLOBALS['meta'] = array();
$GLOBALS['filters'] = array();
$GLOBALS['current_user_id'] = 1;
$GLOBALS['admin_request'] = false;
$GLOBALS['manage_options'] = false;
if ( in_array( '--rest', $argv, true ) ) { define( 'REST_REQUEST', true ); }
function get_current_user_id() { return $GLOBALS['current_user_id']; }
function is_admin() { return $GLOBALS['admin_request']; }
function current_user_can( $capability ) { return 'manage_options' === $capability && $GLOBALS['manage_options']; }
function absint( $value ) { return abs( (int) $value ); }
function get_post_meta( $id, $key ) { return isset( $GLOBALS['meta'][ $id ][ $key ] ) ? $GLOBALS['meta'][ $id ][ $key ] : ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function get_post_type( $id ) { $types = array( 100 => 'sfwd-courses', 110 => 'sfwd-courses', 200 => 'sfwd-lessons', 300 => 'sfwd-quiz', 400 => 'sfwd-topic' ); return isset( $types[$id] ) ? $types[$id] : ''; }
function learndash_get_post_type_slug() { return 'sfwd-courses'; }
function learndash_get_course_id( $id ) { return in_array( $id, array( 100, 200, 300, 400 ), true ) ? 100 : 0; }
function sanitize_text_field( $value ) { return (string) $value; }
function esc_url_raw( $value ) { return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : ''; }
function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { $GLOBALS['filters'][$hook][$priority][] = array( $callback, $accepted_args ); }
function apply_filters( $hook, $value, ...$args ) {
	if ( ! isset( $GLOBALS['filters'][$hook] ) ) { return $value; }
	ksort( $GLOBALS['filters'][$hook] );
	foreach ( $GLOBALS['filters'][$hook] as $callbacks ) {
		foreach ( $callbacks as $callback ) { $value = call_user_func_array( $callback[0], array_slice( array_merge( array( $value ), $args ), 0, $callback[1] ) ); }
	}
	return $value;
}

require_once dirname( __DIR__ ) . '/includes/class-course-requirement-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-fluentcrm-adapter.php';
require_once dirname( __DIR__ ) . '/includes/class-fluentcrm-access-gate.php';

use KitMage\LearnDashEntitlements\Course_Requirement_Repository;
use KitMage\LearnDashEntitlements\FluentCRM_Adapter;
use KitMage\LearnDashEntitlements\FluentCRM_Access_Gate;

final class Fake_Tags_API { public function all() { return array( (object) array( 'id' => 4, 'title' => 'Four' ), (object) array( 'id' => 17, 'title' => 'Seventeen' ), (object) array( 'id' => 29, 'title' => 'Twenty Nine' ) ); } }
final class Fake_Contact { private $ids; public $calls = 0; public function __construct( $ids ) { $this->ids = $ids; } public function hasAnyTagId( $ids ) { $this->calls++; return (bool) array_intersect( $ids, $this->ids ); } }
final class Fake_Contacts_API { public $calls = 0; public $user_ids = array(); private $contact; public function __construct( $contact ) { $this->contact = $contact; } public function getContactByUserRef( $user_id ) { $this->calls++; $this->user_ids[] = $user_id; return 1 === $user_id ? $this->contact : null; } }

function expect_value( $label, $expected, $actual ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); } echo "PASS: {$label}\n"; }
function gate_for( $ids, $contact_ids, $enabled = true, $match = 'all', &$contacts_api = null ) {
	$repository = new Course_Requirement_Repository();
	$repository->save( 100, $enabled, $ids, $match );
	$contact = null === $contact_ids ? null : new Fake_Contact( $contact_ids );
	$contacts_api = new Fake_Contacts_API( $contact );
	$factory = function( $resource ) use ( $contacts_api ) { return 'tags' === $resource ? new Fake_Tags_API() : $contacts_api; };
	return new FluentCRM_Access_Gate( $repository, new FluentCRM_Adapter( $factory ) );
}

// Use LearnDash's actual hook contract: (allowed, step ID, course ID), no user ID.
$GLOBALS['filters'] = array();
$gate = gate_for( array( 4 ), array( 4 ), true, 'all', $api ); $gate->hooks();
expect_value( 'tagged student can read a lesson through the real three-argument hook', true, apply_filters( 'learndash_can_user_read_step', true, 200, 100 ) );
expect_value( 'step hook looks up the current student rather than the step ID', array( 1 ), $api->user_ids );
expect_value( 'step hook registers exactly the documented arguments', 3, $GLOBALS['filters']['learndash_can_user_read_step'][20][0][1] );
expect_value( 'tagged student can read a quiz', true, apply_filters( 'learndash_can_user_read_step', true, 300, 100 ) );
expect_value( 'tagged student can read a topic', true, apply_filters( 'learndash_can_user_read_step', true, 400, 100 ) );
expect_value( 'tagged student retains direct step access without explicit course context', true, apply_filters( 'learndash_can_user_read_step', true, 200, 0 ) );
expect_value( 'underlying step denial remains denied even with matching tags', false, apply_filters( 'learndash_can_user_read_step', false, 200, 100 ) );
expect_value( 'course and step decisions agree for the same student', true, apply_filters( 'sfwd_lms_has_access', true, 100, 1 ) );
expect_value( 'unresolved step preserves LearnDash result', true, apply_filters( 'learndash_can_user_read_step', true, 999, 0 ) );
$GLOBALS['current_user_id'] = 2;
expect_value( 'another student cannot borrow the cached matching student decision', false, apply_filters( 'learndash_can_user_read_step', true, 200, 100 ) );
$GLOBALS['current_user_id'] = 0;
expect_value( 'logged-out step requests fail the enabled tag rule', false, apply_filters( 'learndash_can_user_read_step', true, 200, 100 ) );
$GLOBALS['current_user_id'] = 1;
$other_course = new Course_Requirement_Repository(); $other_course->save( 110, true, array( 17 ), 'all' );
expect_value( 'shared step honors the supplied course requirement', false, apply_filters( 'learndash_can_user_read_step', true, 200, 110 ) );


$api = null;
expect_value( 'ungated course preserves positive access', true, gate_for( array(), array(), false )->filter_course_access( true, 100, 1 ) );
expect_value( 'underlying denial is never elevated', false, gate_for( array( 4 ), array( 4 ) )->filter_course_access( false, 100, 1 ) );
expect_value( 'single matching tag passes', true, gate_for( array( 4 ), array( 4 ) )->filter_course_access( true, 100, 1 ) );
expect_value( 'single missing tag denies', false, gate_for( array( 4 ), array( 17 ) )->filter_course_access( true, 100, 1 ) );
expect_value( 'ALL passes with every tag', true, gate_for( array( 4, 17, 29 ), array( 4, 17, 29 ), true, 'all' )->filter_course_access( true, 100, 1 ) );
expect_value( 'ALL denies when one tag is absent', false, gate_for( array( 4, 17, 29 ), array( 4, 17 ), true, 'all' )->filter_course_access( true, 100, 1 ) );
expect_value( 'ANY passes with one tag', true, gate_for( array( 4, 17, 29 ), array( 17 ), true, 'any' )->filter_course_access( true, 100, 1 ) );
expect_value( 'ANY denies with no tags', false, gate_for( array( 4, 17, 29 ), array( 99 ), true, 'any' )->filter_course_access( true, 100, 1 ) );
expect_value( 'missing contact denies', false, gate_for( array( 4 ), null )->filter_course_access( true, 100, 1 ) );
expect_value( 'enabled empty configuration fails closed', false, gate_for( array(), array( 4 ) )->filter_course_access( true, 100, 1 ) );
$unavailable_repository = new Course_Requirement_Repository(); $unavailable_repository->save( 100, true, array( 4 ), 'all' );
expect_value( 'FluentCRM unavailable fails closed for gated course', false, ( new FluentCRM_Access_Gate( $unavailable_repository, new FluentCRM_Adapter() ) )->filter_course_access( true, 100, 1 ) );
expect_value( 'deleted configured tag fails closed', false, gate_for( array( 47 ), array( 47 ) )->filter_course_access( true, 100, 1 ) );
expect_value( 'direct lesson uses owning course rule', false, gate_for( array( 4 ), array() )->filter_step_access( true, 200, 0 ) );
$gate = gate_for( array( 4 ), array( 4 ), true, 'all', $api );
$gate->filter_course_access( true, 100, 1 ); $gate->filter_step_access( true, 200, 100 );
expect_value( 'same-request decision memoizes contact lookup', 1, $api->calls );
expect_value( 'invalid match defaults to ALL', 'all', Course_Requirement_Repository::normalize_match( 'invalid' ) );
expect_value( 'tag IDs normalize and deduplicate', array( 4, 17 ), Course_Requirement_Repository::normalize_tag_ids( array( '4', -17, 4, 0, 'bad' ) ) );


$GLOBALS['filters'] = array();
$gate = gate_for( array( 4 ), array(), true, 'all', $api ); $gate->hooks();
$GLOBALS['admin_request'] = true; $GLOBALS['manage_options'] = true;
expect_value( 'backend administrator keeps native course access without CRM tags', true, apply_filters( 'sfwd_lms_has_access', true, 100, 1 ) );
expect_value( 'backend administrator keeps native lesson and quiz builder visibility', true, apply_filters( 'learndash_can_user_read_step', true, 300, 100 ) );
expect_value( 'backend administrator does not elevate a native denial', false, apply_filters( 'learndash_can_user_read_step', false, 300, 100 ) );
expect_value( 'backend administrator also preserves native course denials', false, apply_filters( 'sfwd_lms_has_access', false, 100, 1 ) );
expect_value( 'backend bypass uses the acting administrator when inspecting another user', true, apply_filters( 'sfwd_lms_has_access', true, 100, 2 ) );
expect_value( 'backend requirement helper does not restrict administrator tools', true, $gate->meets_requirement( 1, 100 ) );
expect_value( 'backend administrator checks do not call FluentCRM', 0, $api->calls );
expect_value( 'backend administrator keeps access when FluentCRM is unavailable', true, ( new FluentCRM_Access_Gate( $unavailable_repository, new FluentCRM_Adapter() ) )->filter_course_access( true, 100, 1 ) );
$GLOBALS['manage_options'] = false;
expect_value( 'student AJAX through wp-admin still requires CRM tags', false, apply_filters( 'learndash_can_user_read_step', true, 200, 100 ) );
expect_value( 'student backend-context course checks still require CRM tags', false, apply_filters( 'sfwd_lms_has_access', true, 100, 1 ) );
$GLOBALS['admin_request'] = false; $GLOBALS['manage_options'] = true;
if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
	expect_value( 'authenticated administrator REST checks bypass the tag gate', true, apply_filters( 'learndash_can_user_read_step', true, 200, 100 ) );
	expect_value( 'administrator REST bypass does not elevate a native denial', false, apply_filters( 'sfwd_lms_has_access', false, 100, 1 ) );
} else {
	expect_value( 'administrator viewing the frontend still needs the CRM tags', false, apply_filters( 'learndash_can_user_read_step', true, 200, 100 ) );
}
$GLOBALS['manage_options'] = false;
expect_value( 'non-administrator requests still enforce tags after an admin bypass', false, apply_filters( 'learndash_can_user_read_step', true, 200, 100 ) );
$GLOBALS['admin_request'] = true; $GLOBALS['filters'] = array();
$gate = gate_for( array( 4 ), array( 4 ), true, 'all', $api ); $gate->hooks();
expect_value( 'tagged student AJAX retains access while still checking CRM', true, apply_filters( 'learndash_can_user_read_step', true, 200, 100 ) );
expect_value( 'student AJAX is evaluated against the student contact', array( 1 ), $api->user_ids );
