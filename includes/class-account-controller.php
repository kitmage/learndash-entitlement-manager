<?php
namespace KitMage\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Account_Controller {
	private $repository;
	private $confirmation_orders = array();
	public function __construct( Repository $repository ) { $this->repository = $repository; }
	public function hooks() {
		add_action( 'init', function() { add_rewrite_endpoint( 'training-entitlements', EP_ROOT | EP_PAGES ); } );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu' ) );
		add_action( 'woocommerce_account_training-entitlements_endpoint', array( $this, 'endpoint' ) );
		add_action( 'woocommerce_before_thankyou', array( $this, 'confirmation' ), 5 );
		// Block-based confirmations expose the thankyou hook in Additional Information.
		add_action( 'woocommerce_thankyou', array( $this, 'confirmation' ), 5 );
		add_action( 'woocommerce_view_order', array( $this, 'view_order' ), 5 );
		add_filter( 'woocommerce_my_account_my_orders_actions', array( $this, 'order_actions' ), 10, 2 );
		add_action( 'woocommerce_account_dashboard', array( $this, 'dashboard' ) );
		add_filter( 'woocommerce_login_redirect', array( $this, 'login_redirect' ), 10, 2 );
	}
	public function menu( $items ) { $logout = isset( $items['customer-logout'] ) ? $items['customer-logout'] : null; unset( $items['customer-logout'] ); $items['training-entitlements'] = __( 'Training Entitlements', 'kitmage-learndash-entitlements' ); if ( $logout ) { $items['customer-logout'] = $logout; } return $items; }
	public function endpoint() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) { return; }
		$order_id = isset( $_GET['entitlement-order'] ) ? absint( $_GET['entitlement-order'] ) : 0;
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || (int) $order->get_customer_id() !== $user_id ) { wc_print_notice( __( 'That order is not available.', 'kitmage-learndash-entitlements' ), 'error' ); return; }
			$grants = $this->repository->grants_for_order( $order_id );
			$message = $this->order_message( $order, $grants );
			wp_enqueue_script( 'kitmage-lde-account', plugins_url( 'assets/account.js', KITMAGE_LDE_FILE ), array(), KITMAGE_LDE_VERSION, true );
			include KITMAGE_LDE_PATH . 'templates/account-order.php'; return;
		}
		$orders = array();
		foreach ( $this->repository->customer_orders( $user_id ) as $id ) { $order = wc_get_order( $id ); if ( $order && (int) $order->get_customer_id() === $user_id ) { $orders[] = $order; } }
		include KITMAGE_LDE_PATH . 'templates/account-list.php';
	}

	public static function order_url( $order_id ) {
		return add_query_arg( 'entitlement-order', absint( $order_id ), wc_get_account_endpoint_url( 'training-entitlements' ) );
	}

	private function owns_order( $order ) {
		$user_id = get_current_user_id();
		return $user_id && (int) $order->get_customer_id() === $user_id;
	}

	private function has_entitlements( $order, $grants ) {
		if ( $grants ) { return true; }
		// Use the purchased terms, even if the product was changed or deleted later.
		foreach ( $order->get_items( 'line_item' ) as $item ) {
			if ( absint( $item->get_meta( Product_Settings::COURSE, true ) ) && absint( $item->get_meta( Product_Settings::COUNT, true ) ) && $item->get_quantity() > 0 ) { return true; }
		}
		return false;
	}

	private function order_message( $order, $grants ) {
		if ( ! $this->has_entitlements( $order, $grants ) ) {
			return array( __( 'Training Entitlements', 'kitmage-learndash-entitlements' ), __( 'This order does not include training entitlements.', 'kitmage-learndash-entitlements' ) );
		}
		if ( $order->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ) {
			return array( __( 'Training entitlement status', 'kitmage-learndash-entitlements' ), __( 'This order is failed, cancelled, or refunded. Training entitlements from this order are unavailable.', 'kitmage-learndash-entitlements' ) );
		}
		if ( ! $order->is_paid() ) {
			return array( __( 'Your training entitlements are awaiting payment', 'kitmage-learndash-entitlements' ), __( 'Your training entitlements will be available once payment is confirmed.', 'kitmage-learndash-entitlements' ) );
		}
		foreach ( $grants as $grant ) {
			if ( 'active' === Repository::state( $grant ) ) {
				return array( __( 'Your training entitlements are ready', 'kitmage-learndash-entitlements' ), __( 'Find your enrollment links, share them with attendees, and track who has enrolled.', 'kitmage-learndash-entitlements' ) );
			}
		}
		if ( $grants ) {
			return array( __( 'Training entitlement status', 'kitmage-learndash-entitlements' ), __( 'View your training entitlements to check remaining enrollments, expiry dates, and enrolled attendees.', 'kitmage-learndash-entitlements' ) );
		}
		return array( __( 'Your training entitlements are being prepared', 'kitmage-learndash-entitlements' ), __( 'Payment has been confirmed, but your training entitlements are not available yet. Please check this page again shortly. If they remain unavailable, contact us with your order number.', 'kitmage-learndash-entitlements' ) );
	}

	public function confirmation( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order || isset( $this->confirmation_orders[ $order->get_id() ] ) ) { return; }
		// A logged-out purchaser can follow the confirmation URL with its order key.
		$key = isset( $_GET['key'] ) && is_string( $_GET['key'] ) ? wc_clean( wp_unslash( $_GET['key'] ) ) : '';
		if ( ! $this->owns_order( $order ) && ( ! $key || ! $order->get_order_key() || ! hash_equals( $order->get_order_key(), $key ) ) ) { return; }
		if ( $this->render_order_card( $order ) ) { $this->confirmation_orders[ $order->get_id() ] = true; }
	}

	public function view_order( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( $order && $this->owns_order( $order ) ) { $this->render_order_card( $order ); }
	}

	private function render_order_card( $order ) {
		$grants = $this->repository->grants_for_order( $order->get_id() );
		if ( ! $this->has_entitlements( $order, $grants ) ) { return false; }
		$message = $this->order_message( $order, $grants );
		$account_order = (bool) $order->get_customer_id();
		include KITMAGE_LDE_PATH . 'templates/order-next-steps.php';
		return true;
	}

	public function order_actions( $actions, $order ) {
		if ( $this->owns_order( $order ) && $this->has_entitlements( $order, $this->repository->grants_for_order( $order->get_id() ) ) ) {
			$actions['training-entitlements'] = array( 'url' => self::order_url( $order->get_id() ), 'name' => __( 'Manage training entitlements', 'kitmage-learndash-entitlements' ) );
		}
		return $actions;
	}

	public function login_redirect( $redirect, $user ) {
		$order_id = isset( $_GET['entitlement-order'] ) ? absint( $_GET['entitlement-order'] ) : 0;
		$order = $order_id ? wc_get_order( $order_id ) : false;
		if ( $order && $user->ID && (int) $order->get_customer_id() === (int) $user->ID ) { return self::order_url( $order_id ); }
		return $redirect;
	}

	public function dashboard() {
		$user_id = get_current_user_id();
		if ( ! $user_id ) { return; }
		$remaining = 0;
		foreach ( $this->repository->customer_orders( $user_id ) as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order || ! $this->owns_order( $order ) || ! $order->is_paid() || $order->has_status( array( 'failed', 'cancelled', 'refunded' ) ) ) { continue; }
			foreach ( $this->repository->grants_for_order( $order_id ) as $grant ) {
				if ( 'active' === Repository::state( $grant ) ) { $remaining += max( 0, (int) $grant['total_count'] - (int) $grant['redeemed_count'] ); }
			}
		}
		if ( ! $remaining ) { return; }
		include KITMAGE_LDE_PATH . 'templates/account-dashboard.php';
	}
	public function repository() { return $this->repository; }
}
