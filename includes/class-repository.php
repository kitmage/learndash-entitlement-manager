<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Repository {
	public function find_by_token( $token, $for_update = false ) {
		global $wpdb;
		$table = Database::grants_table();
		$lock = $for_update ? ' FOR UPDATE' : '';
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE token = %s{$lock}", $token ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function find_redemption( $grant_id, $user_id ) {
		global $wpdb;
		$table = Database::redemptions_table();
		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE grant_id = %d AND user_id = %d", $grant_id, $user_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function create_grant( array $data ) {
		global $wpdb;
		// Duplicate order-item and token keys make retries harmless at the storage boundary.
		return false !== $wpdb->insert( Database::grants_table(), $data );
	}

	public function grants_for_order( $order_id ) {
		global $wpdb;
		$table = Database::grants_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC", $order_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function customer_orders( $customer_id ) {
		global $wpdb;
		$table = Database::grants_table();
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT order_id FROM {$table} WHERE customer_id = %d ORDER BY issued_at DESC", $customer_id ) ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function redemptions( $grant_id ) {
		global $wpdb;
		$table = Database::redemptions_table();
		return $wpdb->get_results( $wpdb->prepare( "SELECT user_id, redeemer_name, redeemed_at FROM {$table} WHERE grant_id = %d ORDER BY redeemed_at ASC", $grant_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	}

	public function invalidate_order( $order_id ) {
		global $wpdb;
		return $wpdb->query( $wpdb->prepare( 'UPDATE ' . Database::grants_table() . " SET status = 'revoked', updated_at = UTC_TIMESTAMP() WHERE order_id = %d AND status = 'active'", $order_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	public static function state( array $grant ) {
		if ( 'active' !== $grant['status'] ) {
			return 'revoked';
		}
		if ( ! empty( $grant['expires_at'] ) && strtotime( $grant['expires_at'] . ' UTC' ) <= time() ) {
			return 'expired';
		}
		if ( (int) $grant['redeemed_count'] >= (int) $grant['total_count'] ) {
			return 'exhausted';
		}
		return 'active';
	}
}
