<?php
// Lightweight unit harness: no WordPress, LearnDash, or FluentCRM installation required.
define( 'ABSPATH', __DIR__ );
$GLOBALS['meta'] = array();
function absint( $value ) { return abs( (int) $value ); }
function get_post_meta( $id, $key ) { return isset( $GLOBALS['meta'][ $id ][ $key ] ) ? $GLOBALS['meta'][ $id ][ $key ] : ''; }
function update_post_meta( $id, $key, $value ) { $GLOBALS['meta'][ $id ][ $key ] = $value; }
function get_post_type( $id ) { return 100 === (int) $id ? 'sfwd-courses' : 'sfwd-lessons'; }
function learndash_get_post_type_slug() { return 'sfwd-courses'; }
function learndash_get_course_id( $id ) { return 100; }
function sanitize_text_field( $value ) { return (string) $value; }
function add_filter() {}

require_once dirname( __DIR__ ) . '/includes/class-course-requirement-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-fluentcrm-adapter.php';
require_once dirname( __DIR__ ) . '/includes/class-fluentcrm-access-gate.php';

use Aspen\LearnDashEntitlements\Course_Requirement_Repository;
use Aspen\LearnDashEntitlements\FluentCRM_Adapter;
use Aspen\LearnDashEntitlements\FluentCRM_Access_Gate;

final class Fake_Tags_API { public function all() { return array( (object) array( 'id' => 4, 'title' => 'Four' ), (object) array( 'id' => 17, 'title' => 'Seventeen' ), (object) array( 'id' => 29, 'title' => 'Twenty Nine' ) ); } }
final class Fake_Contact { private $ids; public $calls = 0; public function __construct( $ids ) { $this->ids = $ids; } public function hasAnyTagId( $ids ) { $this->calls++; return (bool) array_intersect( $ids, $this->ids ); } }
final class Fake_Contacts_API { public $calls = 0; private $contact; public function __construct( $contact ) { $this->contact = $contact; } public function getContactByUserRef() { $this->calls++; return $this->contact; } }

function expect_value( $label, $expected, $actual ) { if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); } echo "PASS: {$label}\n"; }
function gate_for( $ids, $contact_ids, $enabled = true, $match = 'all', &$contacts_api = null ) {
	$repository = new Course_Requirement_Repository();
	$repository->save( 100, $enabled, $ids, $match );
	$contact = null === $contact_ids ? null : new Fake_Contact( $contact_ids );
	$contacts_api = new Fake_Contacts_API( $contact );
	$factory = function( $resource ) use ( $contacts_api ) { return 'tags' === $resource ? new Fake_Tags_API() : $contacts_api; };
	return new FluentCRM_Access_Gate( $repository, new FluentCRM_Adapter( $factory ) );
}

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
expect_value( 'direct lesson uses owning course rule', false, gate_for( array( 4 ), array() )->filter_step_access( true, 1, 200, 0 ) );
$gate = gate_for( array( 4 ), array( 4 ), true, 'all', $api );
$gate->filter_course_access( true, 100, 1 ); $gate->filter_step_access( true, 1, 200, 100 );
expect_value( 'same-request decision memoizes contact lookup', 1, $api->calls );
expect_value( 'invalid match defaults to ALL', 'all', Course_Requirement_Repository::normalize_match( 'invalid' ) );
expect_value( 'tag IDs normalize and deduplicate', array( 4, 17 ), Course_Requirement_Repository::normalize_tag_ids( array( '4', -17, 4, 0, 'bad' ) ) );
