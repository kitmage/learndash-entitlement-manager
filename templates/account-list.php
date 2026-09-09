<?php defined( 'ABSPATH' ) || exit; ?>
<h2><?php esc_html_e( 'Training Entitlements', 'aspen-learndash-entitlements' ); ?></h2>
<?php if ( ! $orders ) : ?><p><?php esc_html_e( 'You do not have any training entitlements yet.', 'aspen-learndash-entitlements' ); ?></p><?php else : ?>
<table class="woocommerce-orders-table shop_table shop_table_responsive"><thead><tr><th><?php esc_html_e( 'Order', 'aspen-learndash-entitlements' ); ?></th><th><?php esc_html_e( 'Date', 'aspen-learndash-entitlements' ); ?></th></tr></thead><tbody>
<?php foreach ( $orders as $order ) : ?><tr><td><a href="<?php echo esc_url( add_query_arg( 'entitlement-order', $order->get_id(), wc_get_account_endpoint_url( 'training-entitlements' ) ) ); ?>">#<?php echo esc_html( $order->get_order_number() ); ?></a></td><td><?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?></td></tr><?php endforeach; ?>
</tbody></table><?php endif; ?>
