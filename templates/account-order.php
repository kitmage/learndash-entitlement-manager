<?php defined( 'ABSPATH' ) || exit; ?>
<h2><?php echo esc_html( sprintf( __( 'Training Entitlements — Order #%s', 'kitmage-learndash-entitlements' ), $order->get_order_number() ) ); ?></h2>
<p><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'training-entitlements' ) ); ?>"><?php esc_html_e( 'View all training entitlements', 'kitmage-learndash-entitlements' ); ?></a></p>
<?php if ( ! $grants ) : ?><p><?php echo esc_html( $message[1] ); ?></p><?php endif; ?>
<?php foreach ( $grants as $grant ) : $state = KitMage\LearnDashEntitlements\Repository::state( $grant ); $remaining = max( 0, (int) $grant['total_count'] - (int) $grant['redeemed_count'] ); ?>
<section class="kitmage-lde-grant" style="margin:1.5em 0;padding:1em;border:1px solid #ddd">
<h3><?php echo esc_html( get_the_title( $grant['course_id'] ) ); ?></h3>
<p><strong><?php esc_html_e( 'Status:', 'kitmage-learndash-entitlements' ); ?></strong> <?php echo esc_html( ucfirst( $state ) ); ?><br><strong><?php esc_html_e( 'Entitlements:', 'kitmage-learndash-entitlements' ); ?></strong> <?php echo esc_html( $grant['total_count'] ); ?><br><strong><?php esc_html_e( 'Remaining:', 'kitmage-learndash-entitlements' ); ?></strong> <?php echo esc_html( $remaining ); ?><br><strong><?php esc_html_e( 'Expires:', 'kitmage-learndash-entitlements' ); ?></strong> <?php echo $grant['expires_at'] ? esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $grant['expires_at'] . ' UTC' ) ) ) : esc_html__( 'Does not expire', 'kitmage-learndash-entitlements' ); ?></p>
<?php if ( 'active' === $state ) : $link = KitMage\LearnDashEntitlements\Enrollment_Controller::url( $grant['token'] ); ?>
<p><?php esc_html_e( 'Share this enrollment link with your attendees. Each attendee signs in to claim one enrollment. If you are attending, you can use the same link yourself.', 'kitmage-learndash-entitlements' ); ?></p>
<div class="kitmage-lde-enrollment-link">
	<label><?php esc_html_e( 'Enrollment Link', 'kitmage-learndash-entitlements' ); ?><input style="width:100%" readonly value="<?php echo esc_attr( $link ); ?>" onclick="this.select()"></label>
	<button type="button" class="button kitmage-lde-copy" hidden data-copied="<?php echo esc_attr__( 'Enrollment link copied.', 'kitmage-learndash-entitlements' ); ?>" data-failed="<?php echo esc_attr__( 'Select the enrollment link and copy it manually.', 'kitmage-learndash-entitlements' ); ?>"><?php esc_html_e( 'Copy enrollment link', 'kitmage-learndash-entitlements' ); ?></button>
	<span class="kitmage-lde-copy-status" role="status" aria-live="polite"></span>
</div>
<?php elseif ( 'exhausted' === $state ) : ?><p><?php esc_html_e( 'All enrollments have been redeemed.', 'kitmage-learndash-entitlements' ); ?></p><?php endif; ?>
<h4><?php esc_html_e( 'Enrolled users', 'kitmage-learndash-entitlements' ); ?></h4><ul><?php foreach ( $this->repository()->redemptions( $grant['id'] ) as $redemption ) : ?><li><?php echo esc_html( $redemption['redeemer_name'] ); ?></li><?php endforeach; ?></ul>
</section><?php endforeach; ?>
