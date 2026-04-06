<?php
/**
 * Admin settings page template.
 *
 * @package RRQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1>
		<?php echo esc_html( get_admin_page_title() ); ?>
		<button type="button" class="page-title-action" id="rrqr-start-generating">
			<?php esc_html_e( 'Refresh', 'rrqr' ); ?>
		</button>
	</h1>
	<div id="rrqr-results"></div>
</div>
