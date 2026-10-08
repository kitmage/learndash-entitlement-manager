<?php defined( 'ABSPATH' ) || exit; ?>
<p class="kitmage-lde-enrollment-description"><?php esc_html_e( 'You are about to enroll your account in this training course.', 'kitmage-learndash-entitlements' ); ?></p>
<form class="kitmage-lde-enrollment-form" method="post" action="<?php echo esc_url( KitMage\LearnDashEntitlements\Enrollment_Controller::url( $token ) ); ?>">
	<?php wp_nonce_field( 'kitmage_lde_redeem_' . $grant['id'], 'kitmage_lde_nonce' ); ?>
	<button type="submit" class="button alt kitmage-lde-confirm"><?php esc_html_e( 'Confirm Enrollment', 'kitmage-learndash-entitlements' ); ?></button>
	<a class="button kitmage-lde-not-now" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Not Now', 'kitmage-learndash-entitlements' ); ?></a>
</form>
