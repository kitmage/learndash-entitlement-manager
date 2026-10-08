<?php defined( 'ABSPATH' ) || exit; ?>
<section class="kitmage-lde-dashboard" style="margin:1.5em 0;padding:1em;border:1px solid #ddd">
	<h2><?php esc_html_e( 'Training Entitlements', 'kitmage-learndash-entitlements' ); ?></h2>
	<p><?php echo esc_html( sprintf( _n( 'You have %s training entitlement available.', 'You have %s training entitlements available.', $remaining, 'kitmage-learndash-entitlements' ), number_format_i18n( $remaining ) ) ); ?></p>
	<p><a class="button" href="<?php echo esc_url( wc_get_account_endpoint_url( 'training-entitlements' ) ); ?>"><?php esc_html_e( 'Manage training entitlements', 'kitmage-learndash-entitlements' ); ?></a></p>
</section>
