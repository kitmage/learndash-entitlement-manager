<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Plugin {
	private static $instance;
	public static function instance() { if ( ! self::$instance ) { self::$instance = new self(); } return self::$instance; }
	private function __construct() {
		add_action( 'before_woocommerce_init', function() { if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) { \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', ASPEN_LDE_FILE, true ); } } );
		Database::migrate();
		if ( ! $this->dependencies_ready() ) { add_action( 'admin_notices', array( $this, 'dependency_notice' ) ); return; }
		$repository = new Repository();
		( new Product_Settings() )->hooks();
		( new Order_Service( $repository ) )->hooks();
		$redemption = new Redemption_Service( $repository, new LearnDash() );
		( new Enrollment_Controller( $repository, $redemption ) )->hooks();
		( new Account_Controller( $repository ) )->hooks();
	}
	private function dependencies_ready() { return class_exists( 'WooCommerce' ) && function_exists( 'ld_update_course_access' ) && function_exists( 'sfwd_lms_has_access' ); }
	public function dependency_notice() { if ( current_user_can( 'activate_plugins' ) ) { echo '<div class="notice notice-error"><p>' . esc_html__( 'Aspen LearnDash Training Entitlement Manager requires active WooCommerce and LearnDash plugins.', 'aspen-learndash-entitlements' ) . '</p></div>'; } }
	public static function deactivate() { flush_rewrite_rules(); }
}
