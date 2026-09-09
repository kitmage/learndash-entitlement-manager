<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

final class Database {
	const VERSION = '1.0.0';

	public static function grants_table() {
		global $wpdb;
		return $wpdb->prefix . 'aspen_lde_grants';
	}

	public static function redemptions_table() {
		global $wpdb;
		return $wpdb->prefix . 'aspen_lde_redemptions';
	}

	public static function activate() {
		self::migrate();
		add_rewrite_endpoint( 'training-entitlements', EP_ROOT | EP_PAGES );
		add_rewrite_rule( '^training-enroll/([A-Za-z0-9_-]{43,})/?$', 'index.php?aspen_lde_token=$matches[1]', 'top' );
		flush_rewrite_rules();
	}

	public static function migrate() {
		if ( self::VERSION === get_option( 'aspen_lde_db_version' ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$charset = $wpdb->get_charset_collate();
		$grants = self::grants_table();
		$redemptions = self::redemptions_table();
		dbDelta( "CREATE TABLE {$grants} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_id bigint(20) unsigned NOT NULL,
			order_item_id bigint(20) unsigned NOT NULL,
			subscription_id bigint(20) unsigned NULL,
			customer_id bigint(20) unsigned NOT NULL DEFAULT 0,
			product_id bigint(20) unsigned NOT NULL,
			variation_id bigint(20) unsigned NULL,
			course_id bigint(20) unsigned NOT NULL,
			total_count int(10) unsigned NOT NULL,
			redeemed_count int(10) unsigned NOT NULL DEFAULT 0,
			token varchar(86) NOT NULL,
			issued_at datetime NOT NULL,
			expires_at datetime NULL,
			redirect_url text NULL,
			status varchar(20) NOT NULL DEFAULT 'active',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_item (order_item_id),
			UNIQUE KEY token (token),
			KEY order_id (order_id),
			KEY customer_id (customer_id)
		) ENGINE=InnoDB {$charset};" );
		dbDelta( "CREATE TABLE {$redemptions} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			grant_id bigint(20) unsigned NOT NULL,
			user_id bigint(20) unsigned NOT NULL,
			redeemer_name varchar(191) NOT NULL,
			redeemed_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY grant_user (grant_id,user_id),
			KEY grant_id (grant_id)
		) ENGINE=InnoDB {$charset};" );
		update_option( 'aspen_lde_db_version', self::VERSION, false );
	}
}
