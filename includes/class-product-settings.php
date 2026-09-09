<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Product_Settings {
	const COURSE = '_aspen_lde_course_id';
	const COUNT = '_aspen_lde_count';
	const DAYS = '_aspen_lde_days';
	const REDIRECT = '_aspen_lde_redirect';

	public function hooks() {
		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'product_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product' ) );
		add_action( 'woocommerce_product_after_variable_attributes', array( $this, 'variation_fields' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'snapshot' ), 10, 4 );
	}

	private function courses() {
		$post_type = function_exists( 'learndash_get_post_type_slug' ) ? learndash_get_post_type_slug( 'course' ) : 'sfwd-courses';
		$options = array( '' => __( '— No entitlement —', 'aspen-learndash-entitlements' ) );
		foreach ( get_posts( array( 'post_type' => $post_type, 'post_status' => array( 'draft', 'publish' ), 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) ) as $course ) {
			$options[ $course->ID ] = $course->post_title . ' (' . get_post_status_object( $course->post_status )->label . ')';
		}
		return $options;
	}

	private function render( $prefix = '', $values = null ) {
		$id = $values ? $values->get_id() : get_the_ID();
		woocommerce_wp_select( array( 'id' => $prefix . self::COURSE, 'name' => $prefix . self::COURSE, 'label' => __( 'LearnDash course', 'aspen-learndash-entitlements' ), 'options' => $this->courses(), 'value' => get_post_meta( $id, self::COURSE, true ) ) );
		woocommerce_wp_text_input( array( 'id' => $prefix . self::COUNT, 'name' => $prefix . self::COUNT, 'label' => __( 'Entitlements per unit', 'aspen-learndash-entitlements' ), 'type' => 'number', 'custom_attributes' => array( 'min' => '0', 'step' => '1' ), 'value' => get_post_meta( $id, self::COUNT, true ) ) );
		woocommerce_wp_text_input( array( 'id' => $prefix . self::DAYS, 'name' => $prefix . self::DAYS, 'label' => __( 'Entitlement validity days', 'aspen-learndash-entitlements' ), 'type' => 'number', 'custom_attributes' => array( 'min' => '0', 'step' => '1' ), 'value' => get_post_meta( $id, self::DAYS, true ) ) );
		woocommerce_wp_text_input( array( 'id' => $prefix . self::REDIRECT, 'name' => $prefix . self::REDIRECT, 'label' => __( 'Redemption redirect URL', 'aspen-learndash-entitlements' ), 'type' => 'url', 'value' => get_post_meta( $id, self::REDIRECT, true ) ) );
	}

	public function product_fields() { echo '<div class="options_group"><h4 style="padding-left:12px">' . esc_html__( 'Training Entitlements', 'aspen-learndash-entitlements' ) . '</h4>'; $this->render(); echo '</div>'; }
	public function variation_fields( $loop, $data, $variation ) { echo '<div class="form-row form-row-full"><strong>' . esc_html__( 'Training Entitlements', 'aspen-learndash-entitlements' ) . '</strong></div>'; $this->render( 'variable_aspen_lde_' . $loop . '_', $variation ); }

	private function save( $id, $prefix = '' ) {
		$fields = array( self::COURSE, self::COUNT, self::DAYS, self::REDIRECT );
		foreach ( $fields as $field ) {
			$key = $prefix . $field;
			if ( ! isset( $_POST[ $key ] ) ) { continue; }
			$value = wp_unslash( $_POST[ $key ] );
			$value = self::REDIRECT === $field ? esc_url_raw( $value ) : (string) max( 0, absint( $value ) );
			update_post_meta( $id, $field, $value );
		}
	}
	public function save_product( $id ) { $this->save( $id ); }
	public function save_variation( $id, $loop ) { $this->save( $id, 'variable_aspen_lde_' . $loop . '_' ); }

	public function snapshot( $item, $cart_key, $values, $order ) {
		$product = $values['data'];
		foreach ( array( self::COURSE, self::COUNT, self::DAYS, self::REDIRECT ) as $key ) {
			$item->add_meta_data( $key, $product->get_meta( $key, true ), true );
		}
	}
}
