<?php defined( 'ABSPATH' ) || exit; ?>
<section class="kitmage-lde-next-steps" style="margin:1.5em 0;padding:1.5em;border:1px solid #ddd">
	<p><strong><?php esc_html_e( 'What’s next?', 'kitmage-learndash-entitlements' ); ?></strong></p>
	<h2><?php echo esc_html( $message[0] ); ?></h2>
	<?php if ( $account_order ) : ?>
		<p><?php echo esc_html( $message[1] ); ?></p>
		<p><a class="button" href="<?php echo esc_url( KitMage\LearnDashEntitlements\Account_Controller::order_url( $order->get_id() ) ); ?>"><?php esc_html_e( 'Manage training entitlements', 'kitmage-learndash-entitlements' ); ?></a></p>
		<p><?php esc_html_e( 'You can return anytime from My Account → Training Entitlements. Sign in with the account used for this purchase.', 'kitmage-learndash-entitlements' ); ?></p>
	<?php else : ?>
		<p><?php esc_html_e( 'This training purchase is not linked to an account. Contact us with your order number for help accessing your training entitlements.', 'kitmage-learndash-entitlements' ); ?></p>
	<?php endif; ?>
</section>
