<?php
namespace Aspen\LearnDashEntitlements;

defined( 'ABSPATH' ) || exit;

/** LearnDash course editor UI for FluentCRM access requirements. */
final class Course_Access_Settings {
	const META_BOX_ID = 'aspen-lde-fluentcrm-access';
	const COURSE_SETTINGS_TAB_ID = 'sfwd-courses-settings';

	private $requirements;
	private $fluentcrm;
	public function __construct( Course_Requirement_Repository $requirements, FluentCRM_Adapter $fluentcrm ) { $this->requirements = $requirements; $this->fluentcrm = $fluentcrm; }

	public function hooks() {
		add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
		add_filter( 'learndash_header_tab_menu', array( $this, 'add_to_course_settings_tab' ) );
		add_action( 'save_post', array( $this, 'save' ) );
		add_action( 'admin_notices', array( $this, 'configuration_notice' ) );
	}

	public function add_meta_box() {
		$post_type = function_exists( 'learndash_get_post_type_slug' ) ? learndash_get_post_type_slug( 'course' ) : 'sfwd-courses';
		add_meta_box( self::META_BOX_ID, __( 'FluentCRM Access Requirements', 'aspen-learndash-entitlements' ), array( $this, 'render' ), $post_type, 'normal', 'default' );
	}

	/** Place the meta box in LearnDash's Course Settings editor tab. */
	public function add_to_course_settings_tab( $tabs ) {
		if ( ! is_array( $tabs ) ) { return $tabs; }

		foreach ( $tabs as $key => &$tab ) {
			if ( ! is_array( $tab ) || ( self::COURSE_SETTINGS_TAB_ID !== $key && self::COURSE_SETTINGS_TAB_ID !== ( isset( $tab['id'] ) ? $tab['id'] : '' ) ) ) { continue; }
			if ( ! isset( $tab['metaboxes'] ) || ! is_array( $tab['metaboxes'] ) ) { $tab['metaboxes'] = array(); }
			if ( ! in_array( self::META_BOX_ID, $tab['metaboxes'], true ) ) { $tab['metaboxes'][] = self::META_BOX_ID; }
			break;
		}
		unset( $tab );

		return $tabs;
	}

	public function render( $post ) {
		$rule = $this->requirements->get( $post->ID );
		$tags = $this->fluentcrm->tags();
		wp_nonce_field( 'aspen_lde_save_fluentcrm_access', 'aspen_lde_fluentcrm_nonce' );
		?>
		<p><label><input type="checkbox" name="aspen_lde_fluentcrm_enabled" value="1" <?php checked( $rule['enabled'] ); ?>> <?php esc_html_e( 'Require FluentCRM tags for course access', 'aspen-learndash-entitlements' ); ?></label></p>
		<p><label for="aspen-lde-required-tags"><strong><?php esc_html_e( 'Required Tags', 'aspen-learndash-entitlements' ); ?></strong></label><br>
		<select id="aspen-lde-required-tags" name="aspen_lde_fluentcrm_tag_ids[]" multiple size="7" style="width:100%">
		<?php foreach ( $tags as $id => $title ) : ?><option value="<?php echo esc_attr( $id ); ?>" <?php selected( in_array( (int) $id, $rule['tag_ids'], true ) ); ?>><?php echo esc_html( $title ); ?></option><?php endforeach; ?>
		<?php foreach ( array_diff( $rule['tag_ids'], array_keys( $tags ) ) as $id ) : ?><option value="<?php echo esc_attr( $id ); ?>" selected><?php echo esc_html( sprintf( __( 'Missing tag — ID %d', 'aspen-learndash-entitlements' ), $id ) ); ?></option><?php endforeach; ?>
		</select></p>
		<?php if ( ! $this->fluentcrm->available() ) : ?><p class="notice notice-warning inline"><?php esc_html_e( 'FluentCRM is unavailable. An enabled requirement will deny course access.', 'aspen-learndash-entitlements' ); ?></p><?php elseif ( $rule['enabled'] && ! $rule['tag_ids'] ) : ?><p class="notice notice-warning inline"><?php esc_html_e( 'Select at least one tag. This course currently fails closed.', 'aspen-learndash-entitlements' ); ?></p><?php endif; ?>
		<fieldset><legend><strong><?php esc_html_e( 'Tag Matching', 'aspen-learndash-entitlements' ); ?></strong></legend>
		<label><input type="radio" name="aspen_lde_fluentcrm_match" value="all" <?php checked( 'all', $rule['match'] ); ?>> <?php esc_html_e( 'Require ALL selected tags', 'aspen-learndash-entitlements' ); ?></label><p class="description"><?php esc_html_e( 'The student must have every selected tag.', 'aspen-learndash-entitlements' ); ?></p>
		<label><input type="radio" name="aspen_lde_fluentcrm_match" value="any" <?php checked( 'any', $rule['match'] ); ?>> <?php esc_html_e( 'Require ANY selected tag', 'aspen-learndash-entitlements' ); ?></label><p class="description"><?php esc_html_e( 'The student must have at least one selected tag.', 'aspen-learndash-entitlements' ); ?></p>
		</fieldset>
		<?php
	}

	public function save( $post_id ) {
		if ( ! isset( $_POST['aspen_lde_fluentcrm_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['aspen_lde_fluentcrm_nonce'] ) ), 'aspen_lde_save_fluentcrm_access' ) ) { return; }
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) { return; }
		$post_type = function_exists( 'learndash_get_post_type_slug' ) ? learndash_get_post_type_slug( 'course' ) : 'sfwd-courses';
		if ( $post_type !== get_post_type( $post_id ) ) { return; }
		$tags = isset( $_POST['aspen_lde_fluentcrm_tag_ids'] ) ? (array) wp_unslash( $_POST['aspen_lde_fluentcrm_tag_ids'] ) : array();
		$match = isset( $_POST['aspen_lde_fluentcrm_match'] ) ? sanitize_key( wp_unslash( $_POST['aspen_lde_fluentcrm_match'] ) ) : 'all';
		$this->requirements->save( $post_id, isset( $_POST['aspen_lde_fluentcrm_enabled'] ), $tags, $match );
	}

	public function configuration_notice() {
		if ( $this->fluentcrm->available() ) { return; }
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : get_the_ID();
		if ( $post_id && current_user_can( 'edit_post', $post_id ) && $this->requirements->get( $post_id )['enabled'] ) { echo '<div class="notice notice-warning"><p>' . esc_html__( 'This course requires FluentCRM tags, but FluentCRM is unavailable. Learner access is denied until the dependency is restored or the requirement is disabled.', 'aspen-learndash-entitlements' ) . '</p></div>'; }
	}
}
