<?php
// Lightweight unit harness for the enrolled-but-denied LearnDash CTA.
define( 'ABSPATH', __DIR__ );
$GLOBALS['meta'] = array();
$GLOBALS['filters'] = array();
$GLOBALS['underlying_access'] = false;
$GLOBALS['current_user_id'] = 7;
$GLOBALS['current_course_id'] = 100;

function absint( $value ) { return abs( (int) $value ); }
function get_post_meta( $id, $key ) { return isset( $GLOBALS['meta'][ $id ][ $key ] ) ? $GLOBALS['meta'][ $id ][ $key ] : ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function get_post_type( $id ) { return 100 === (int) $id ? 'sfwd-courses' : ''; }
function learndash_get_post_type_slug() { return 'sfwd-courses'; }
function learndash_get_course_id( $id ) { return (int) $id; }
function get_current_user_id() { return $GLOBALS['current_user_id']; }
function get_the_ID() { return $GLOBALS['current_course_id']; }
function sanitize_text_field( $value ) { return (string) $value; }
function esc_url_raw( $value ) { return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : ''; }
function esc_url( $value ) { return htmlspecialchars( esc_url_raw( $value ), ENT_QUOTES, 'UTF-8' ); }
function esc_html__( $text ) {
	$text = isset( $GLOBALS['translated_next'] ) && 'Next' === $text ? $GLOBALS['translated_next'] : $text;
	return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
}
function add_filter( $hook, $callback ) { $GLOBALS['filters'][ $hook ][] = $callback; }
function sfwd_lms_has_access( $course_id, $user_id ) {
	$result = $GLOBALS['underlying_access'];
	foreach ( isset( $GLOBALS['filters']['sfwd_lms_has_access'] ) ? $GLOBALS['filters']['sfwd_lms_has_access'] : array() as $callback ) {
		$result = call_user_func( $callback, $result, $course_id, $user_id );
	}
	return $result;
}

require_once dirname( __DIR__ ) . '/includes/class-course-requirement-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-fluentcrm-adapter.php';
require_once dirname( __DIR__ ) . '/includes/class-fluentcrm-access-gate.php';
require_once dirname( __DIR__ ) . '/includes/class-learndash.php';
require_once dirname( __DIR__ ) . '/includes/class-course-access-presentation.php';

use Aspen\LearnDashEntitlements\Course_Access_Presentation;
use Aspen\LearnDashEntitlements\Course_Requirement_Repository;
use Aspen\LearnDashEntitlements\FluentCRM_Access_Gate;
use Aspen\LearnDashEntitlements\FluentCRM_Adapter;
use Aspen\LearnDashEntitlements\LearnDash;

final class Presentation_Fake_Tags_API {
	public function all() { return array( (object) array( 'id' => 4, 'title' => 'Four' ), (object) array( 'id' => 17, 'title' => 'Seventeen' ) ); }
}
final class Presentation_Fake_Contact {
	private $ids;
	public function __construct( $ids ) { $this->ids = $ids; }
	public function hasAnyTagId( $ids ) { return (bool) array_intersect( $ids, $this->ids ); }
}
final class Presentation_Fake_Contacts_API {
	private $contact;
	public function __construct( $ids ) { $this->contact = new Presentation_Fake_Contact( $ids ); }
	public function getContactByUserRef() { return $this->contact; }
}

function expect_value( $label, $expected, $actual ) {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: {$label}\nExpected: " . var_export( $expected, true ) . "\nActual: " . var_export( $actual, true ) . "\n" ); exit( 1 ); }
	echo "PASS: {$label}\n";
}

function presentation_for( $enabled, $required_ids, $contact_ids, $match, $next_url ) {
	$GLOBALS['filters'] = array();
	$repository = new Course_Requirement_Repository();
	$repository->save( 100, $enabled, $required_ids, $match, $next_url );
	$contacts = new Presentation_Fake_Contacts_API( $contact_ids );
	$adapter = new FluentCRM_Adapter( function( $resource ) use ( $contacts ) { return 'tags' === $resource ? new Presentation_Fake_Tags_API() : $contacts; } );
	$gate = new FluentCRM_Access_Gate( $repository, $adapter );
	$gate->hooks();
	$presentation = new Course_Access_Presentation( $repository, $gate, new LearnDash( $gate ) );
	$presentation->hooks();
	return $presentation;
}

$original = '<a href="/buy/">Take this Course</a>';
$url = 'https://example.com/training/next-step/?a=1&b=2';
$GLOBALS['underlying_access'] = true;
expect_value( 'unrestricted course keeps original button', $original, presentation_for( false, array(), array(), 'all', $url )->filter_closed_button( $original, array( 'post' => (object) array( 'ID' => 100 ) ) ) );
$GLOBALS['underlying_access'] = false;
expect_value( 'unenrolled user keeps original button', $original, presentation_for( true, array( 4 ), array(), 'all', $url )->filter_closed_button( $original, array( 'course_id' => 100 ) ) );
$GLOBALS['underlying_access'] = true;
expect_value( 'enrolled matching user keeps original button', $original, presentation_for( true, array( 4 ), array( 4 ), 'all', $url )->filter_closed_button( $original, array( 'course_id' => 100 ) ) );
$button = presentation_for( true, array( 4 ), array(), 'all', $url )->filter_closed_button( $original, array( 'course_id' => 100 ) );
expect_value( 'enrolled denied user receives Next button', true, false !== strpos( $button, '>Next</a>' ) );
expect_value( 'Next URL is escaped in output', true, false !== strpos( $button, 'href="https://example.com/training/next-step/?a=1&amp;b=2"' ) );
expect_value( 'blank Next URL keeps original button', $original, presentation_for( true, array( 4 ), array(), 'all', '' )->filter_closed_button( $original, array( 'course_id' => 100 ) ) );
expect_value( 'ALL matching still denies a partial match', true, presentation_for( true, array( 4, 17 ), array( 4 ), 'all', $url )->should_replace( 100, 7 ) );
expect_value( 'ANY matching still passes with one tag', false, presentation_for( true, array( 4, 17 ), array( 4 ), 'any', $url )->should_replace( 100, 7 ) );

$GLOBALS['meta'][100] = array();
$legacy_repository = new Course_Requirement_Repository();
expect_value( 'legacy course defaults to a blank Next URL', '', $legacy_repository->get( 100 )['next_url'] );
$legacy_repository->save( 100, true, array( 4 ), 'all', 'javascript:alert(1)' );
expect_value( 'unsafe Next URL is rejected on save', '', $legacy_repository->get( 100 )['next_url'] );
$legacy_repository->save( 100, true, array( 4 ), 'all', array( $url ) );
expect_value( 'non-scalar Next URL is rejected on save', '', $legacy_repository->get( 100 )['next_url'] );
$GLOBALS['translated_next'] = 'Next & forward';
$escaped_label = presentation_for( true, array( 4 ), array(), 'all', $url )->filter_closed_button( $original, array( 'course_id' => 100 ) );
expect_value( 'translated button label is escaped', true, false !== strpos( $escaped_label, '>Next &amp; forward</a>' ) );
