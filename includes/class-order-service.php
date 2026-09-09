<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Order_Service {
	private $repository;
	public function __construct( Repository $repository ) { $this->repository = $repository; }

	public function hooks() {
		add_action( 'woocommerce_payment_complete', array( $this, 'maybe_create' ) );
		add_action( 'woocommerce_order_status_processing', array( $this, 'maybe_create' ) );
		add_action( 'woocommerce_order_status_completed', array( $this, 'maybe_create' ) );
		add_action( 'woocommerce_order_status_cancelled', array( $this, 'invalidate' ) );
		add_action( 'woocommerce_order_status_refunded', array( $this, 'invalidate' ) );
		add_action( 'woocommerce_subscription_renewal_payment_complete', array( $this, 'renewal_paid' ), 10, 2 );
	}

	public function renewal_paid( $subscription, $renewal_order = null ) {
		if ( ! $renewal_order && is_object( $subscription ) && method_exists( $subscription, 'get_last_order' ) ) {
			$renewal_order = $subscription->get_last_order( 'all' );
		}
		$this->maybe_create( is_object( $renewal_order ) ? $renewal_order->get_id() : $renewal_order );
	}

	public function maybe_create( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || ! $order->is_paid() || ! $order->get_date_paid() ) { return; }
		$issued = gmdate( 'Y-m-d H:i:s', $order->get_date_paid()->getTimestamp() );
		$subscription_id = function_exists( 'wcs_get_subscriptions_for_order' ) ? $this->subscription_id( $order ) : null;
		foreach ( $order->get_items( 'line_item' ) as $item_id => $item ) {
			$course = absint( $item->get_meta( Product_Settings::COURSE, true ) );
			$per_unit = absint( $item->get_meta( Product_Settings::COUNT, true ) );
			$total = $per_unit * absint( $item->get_quantity() );
			if ( ! $course || ! $total ) { continue; }
			$days = absint( $item->get_meta( Product_Settings::DAYS, true ) );
			$product = $item->get_product();
			$this->repository->create_grant( array(
				'order_id' => $order->get_id(), 'order_item_id' => $item_id,
				'subscription_id' => $subscription_id, 'customer_id' => $order->get_customer_id(),
				'product_id' => $item->get_product_id(), 'variation_id' => $item->get_variation_id() ?: null,
				'course_id' => $course, 'total_count' => $total, 'redeemed_count' => 0,
				'token' => $this->token(), 'issued_at' => $issued,
				'expires_at' => $days ? gmdate( 'Y-m-d H:i:s', $order->get_date_paid()->getTimestamp() + DAY_IN_SECONDS * $days ) : null,
				'redirect_url' => esc_url_raw( $item->get_meta( Product_Settings::REDIRECT, true ) ),
				'status' => 'active', 'created_at' => current_time( 'mysql', true ), 'updated_at' => current_time( 'mysql', true ),
			) );
		}
	}

	private function subscription_id( $order ) {
		$subscriptions = wcs_get_subscriptions_for_order( $order, array( 'order_type' => 'any' ) );
		$subscription = reset( $subscriptions );
		return $subscription ? $subscription->get_id() : null;
	}

	private function token() { return rtrim( strtr( base64_encode( random_bytes( 32 ) ), '+/', '-_' ), '=' ); }
	public function invalidate( $order_id ) { $this->repository->invalidate_order( absint( $order_id ) ); }
}
