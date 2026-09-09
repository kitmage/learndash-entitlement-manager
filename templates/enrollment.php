<?php defined( 'ABSPATH' ) || exit; ?>
<p><?php esc_html_e( 'You are about to enroll your account in this training course.', 'aspen-learndash-entitlements' ); ?></p>
<form method="post" action="<?php echo esc_url( Aspen\LearnDashEntitlements\Enrollment_Controller::url( $token ) ); ?>">
	<?php wp_nonce_field( 'aspen_lde_redeem_' . $grant['id'], 'aspen_lde_nonce' ); ?>
	<button type="submit" class="button alt"><?php esc_html_e( 'Confirm Enrollment', 'aspen-learndash-entitlements' ); ?></button>
	<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php esc_html_e( 'Not Now', 'aspen-learndash-entitlements' ); ?></a>
</form>
