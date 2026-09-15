<?php
// Lightweight unit harness for LearnDash course-editor tab placement.
define( 'ABSPATH', __DIR__ );
function add_action() {}
function add_filter( $hook, $callback ) { $GLOBALS['filters'][ $hook ] = $callback; }
function learndash_get_post_type_slug() { return 'sfwd-courses'; }
function __( $text ) { return $text; }
function esc_url_raw( $value ) { return filter_var( $value, FILTER_VALIDATE_URL ) ? $value : ''; }
function add_meta_box( $id, $title, $callback, $screen, $context, $priority ) {
	$GLOBALS['meta_box'] = compact( 'id', 'title', 'callback', 'screen', 'context', 'priority' );
}

require_once dirname( __DIR__ ) . '/includes/class-course-requirement-repository.php';
require_once dirname( __DIR__ ) . '/includes/class-fluentcrm-adapter.php';
require_once dirname( __DIR__ ) . '/includes/class-course-access-settings.php';

use Aspen\LearnDashEntitlements\Course_Access_Settings;
use Aspen\LearnDashEntitlements\Course_Requirement_Repository;
use Aspen\LearnDashEntitlements\FluentCRM_Adapter;

function expect_value( $label, $expected, $actual ) {
	if ( $expected !== $actual ) { fwrite( STDERR, "FAIL: {$label}\n" ); exit( 1 ); }
	echo "PASS: {$label}\n";
}

$settings = new Course_Access_Settings( new Course_Requirement_Repository(), new FluentCRM_Adapter() );
$settings->hooks();
$filter = $GLOBALS['filters']['learndash_header_tab_menu'];
$settings->add_meta_box();
expect_value( 'meta box uses the main editor column', 'normal', $GLOBALS['meta_box']['context'] );
expect_value( 'meta box is registered for courses', 'sfwd-courses', $GLOBALS['meta_box']['screen'] );

$tabs = array(
	array( 'id' => 'post-body-content', 'metaboxes' => array() ),
	array( 'id' => 'sfwd-courses-settings', 'metaboxes' => array( 'learndash-course-access-settings' ) ),
);
$tabs = call_user_func( $filter, $tabs );
expect_value( 'meta box is absent from the content tab', array(), $tabs[0]['metaboxes'] );
expect_value( 'meta box is appended to the settings tab', array( 'learndash-course-access-settings', 'aspen-lde-fluentcrm-access' ), $tabs[1]['metaboxes'] );

$tabs = call_user_func( $filter, $tabs );
expect_value( 'tab registration is idempotent', 1, count( array_keys( $tabs[1]['metaboxes'], 'aspen-lde-fluentcrm-access', true ) ) );

$keyed_tabs = array( 'sfwd-courses-settings' => array( 'metaboxes' => array() ) );
$keyed_tabs = call_user_func( $filter, $keyed_tabs );
expect_value( 'keyed LearnDash tab data is supported', array( 'aspen-lde-fluentcrm-access' ), $keyed_tabs['sfwd-courses-settings']['metaboxes'] );
