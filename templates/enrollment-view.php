<?php defined( 'ABSPATH' ) || exit; ?>
<div class="<?php echo $show_title ? 'kitmage-lde-enrollment' : 'kitmage-lde-enrollment-content'; ?>">
	<?php if ( $show_title ) : ?><h2 class="kitmage-lde-enrollment-title"><?php echo esc_html( $view['title'] ); ?></h2><?php endif; ?>
	<?php if ( $view['message'] ) : ?><p class="kitmage-lde-enrollment-message"><?php echo esc_html( $view['message'] ); ?></p><?php endif; ?>
	<?php if ( $view['login_url'] ) : ?>
		<p><a class="button kitmage-lde-sign-in" href="<?php echo esc_url( $view['login_url'] ); ?>"><?php esc_html_e( 'Sign in / Create account', 'kitmage-learndash-entitlements' ); ?></a></p>
	<?php elseif ( $view['form'] ) : ?>
		<?php include KITMAGE_LDE_PATH . 'templates/enrollment.php'; ?>
	<?php endif; ?>
</div>
