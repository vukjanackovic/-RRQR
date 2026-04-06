<?php
/**
 * Settings form page template.
 *
 * @package RRQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<?php if ( isset( $_GET['settings-updated'] ) && 'true' === sanitize_key( $_GET['settings-updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Settings saved.', 'rrqr' ); ?></p>
		</div>
	<?php endif; ?>

	<form method="post">
		<?php
		wp_nonce_field( 'rrqr_save_settings', 'rrqr_settings_nonce' );
		do_settings_sections( 'rrqr-settings' );
		submit_button();
		?>
	</form>
</div>
