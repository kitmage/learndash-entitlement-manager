<?php
namespace KitMage\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Enrollment_Settings {
	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}
	public function menu() {
		add_options_page( __( 'Training Enrollment', 'kitmage-learndash-entitlements' ), __( 'Training Enrollment', 'kitmage-learndash-entitlements' ), 'manage_options', 'kitmage-training-enrollment', array( $this, 'page' ) );
	}
	public function register() {
		register_setting( 'kitmage_lde_enrollment', Enrollment_Controller::PAGE_OPTION, array( 'type' => 'integer', 'default' => 0, 'sanitize_callback' => array( $this, 'sanitize_page' ) ) );
		add_settings_section( 'kitmage_lde_enrollment', __( 'Enrollment page', 'kitmage-learndash-entitlements' ), '__return_false', 'kitmage-training-enrollment' );
		add_settings_field( Enrollment_Controller::PAGE_OPTION, __( 'Training enrollment page', 'kitmage-learndash-entitlements' ), array( $this, 'field' ), 'kitmage-training-enrollment', 'kitmage_lde_enrollment', array( 'label_for' => Enrollment_Controller::PAGE_OPTION ) );
	}
	public function sanitize_page( $value ) {
		$numeric = is_scalar( $value ) && 1 === preg_match( '/\A[0-9]+\z/', (string) $value );
		$id = $numeric ? absint( $value ) : 0;
		if ( 0 === $id && $numeric ) { return 0; }
		$page = $id ? get_post( $id ) : null;
		if ( $page && 'page' === $page->post_type && 'publish' === $page->post_status && ! $page->post_password ) { return $id; }
		add_settings_error( Enrollment_Controller::PAGE_OPTION, 'invalid_enrollment_page', __( 'Select a published, publicly accessible WordPress page.', 'kitmage-learndash-entitlements' ) );
		return absint( get_option( Enrollment_Controller::PAGE_OPTION, 0 ) );
	}
	public function field() {
		wp_dropdown_pages( array( 'name' => Enrollment_Controller::PAGE_OPTION, 'id' => Enrollment_Controller::PAGE_OPTION, 'selected' => absint( get_option( Enrollment_Controller::PAGE_OPTION, 0 ) ), 'post_status' => 'publish', 'show_option_none' => __( 'Use the built-in enrollment screen', 'kitmage-learndash-entitlements' ), 'option_none_value' => '0' ) );
		echo '<p class="description">' . esc_html__( 'Add [training-enrollment] to the selected page using the WordPress editor or an Elementor Shortcode widget. Existing enrollment links will open this page automatically.', 'kitmage-learndash-entitlements' ) . '</p>';
	}
	public function page() {
		if ( ! current_user_can( 'manage_options' ) ) { return; }
		echo '<div class="wrap"><h1>' . esc_html__( 'Training Enrollment', 'kitmage-learndash-entitlements' ) . '</h1>';
		settings_errors();
		echo '<form action="options.php" method="post">';
		settings_fields( 'kitmage_lde_enrollment' );
		do_settings_sections( 'kitmage-training-enrollment' );
		submit_button();
		echo '</form></div>';
	}
}
