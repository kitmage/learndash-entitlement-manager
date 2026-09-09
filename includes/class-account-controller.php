<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Account_Controller {
	private $repository;
	public function __construct( Repository $repository ) { $this->repository = $repository; }
	public function hooks() {
		add_action( 'init', function() { add_rewrite_endpoint( 'training-entitlements', EP_ROOT | EP_PAGES ); } );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu' ) );
		add_action( 'woocommerce_account_training-entitlements_endpoint', array( $this, 'endpoint' ) );
	}
	public function menu( $items ) { $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null; unset( $items['customer-logout'] ); $items['training-entitlements'] = __( 'Training Entitlements', 'aspen-learndash-entitlements' ); if ( $logout ) { $items['customer-logout'] = $logout; } return $items; }
	public function endpoint() {
		$user_id = get_current_user_id();
		$order_id = isset( $_GET['entitlement-order'] ) ? absint( $_GET['entitlement-order'] ) : 0;
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || (int) $order->get_customer_id() !== $user_id ) { wc_print_notice( __( 'That order is not available.', 'aspen-learndash-entitlements' ), 'error' ); return; }
			$grants = $this->repository->grants_for_order( $order_id );
			include ASPEN_LDE_PATH . 'templates/account-order.php'; return;
		}
		$orders = array();
		foreach ( $this->repository->customer_orders( $user_id ) as $id ) { $order = wc_get_order( $id ); if ( $order && (int) $order->get_customer_id() === $user_id ) { $orders[] = $order; } }
		include ASPEN_LDE_PATH . 'templates/account-list.php';
	}
	public function repository() { return $this->repository; }
}
