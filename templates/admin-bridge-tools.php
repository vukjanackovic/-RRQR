<?php
/**
 * Bridge tools admin page.
 *
 * @package RRQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$sync_results = null;
if ( isset( $_GET['rrqr_synced'] ) && '1' === sanitize_key( wp_unslash( $_GET['rrqr_synced'] ) ) ) {
	$sync_results = get_transient( 'rrqr_bridge_sync_notice_' . get_current_user_id() );
	delete_transient( 'rrqr_bridge_sync_notice_' . get_current_user_id() );
}
?>
<div class="wrap">
	<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

	<p><?php esc_html_e( 'Populates or inspects the local mirror under uploads/rrqr-bridge. “Try sync from this server” uses the same outbound IP as WordPress—if Cloudways is blocked, that button will fail and you should use the REST ingest or the CLI fetcher from another network.', 'rrqr' ); ?></p>
	<p class="description"><?php esc_html_e( 'Note: ca.global.nba.com often returns HTML (not JSON) for the legacy standing.json URL. Schedule from cdn.nba.com is reliable; theme standings may need a different data source.', 'rrqr' ); ?></p>

	<h2><?php esc_html_e( 'Cached files', 'rrqr' ); ?></h2>
	<table class="widefat striped" style="max-width:920px;">
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Item', 'rrqr' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Path', 'rrqr' ); ?></th>
				<th scope="col"><?php esc_html_e( 'Status', 'rrqr' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( RRQR_Bridge::get_monitored_definitions() as $def ) : ?>
				<?php
				$st = RRQR_Bridge::get_path_status( $def['path'] );
				?>
				<tr>
					<td><?php echo esc_html( $def['label'] ); ?></td>
					<td><code><?php echo esc_html( $def['path'] ); ?></code></td>
					<td>
						<?php if ( ! empty( $st['error'] ) ) : ?>
							<?php echo esc_html( $st['error'] ); ?>
						<?php elseif ( empty( $st['exists'] ) ) : ?>
							<?php esc_html_e( 'Missing', 'rrqr' ); ?>
						<?php else : ?>
							<?php
							echo esc_html(
								sprintf(
									/* translators: 1: file size, 2: time ago (e.g. "5 minutes") */
									__( '%1$s, updated %2$s ago', 'rrqr' ),
									size_format( $st['size'] ),
									human_time_diff( $st['mtime'], time() )
								)
							);
							?>
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>

	<h2 style="margin-top:2em;"><?php esc_html_e( 'Try sync from this server', 'rrqr' ); ?></h2>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="rrqr_bridge_server_sync" />
		<?php wp_nonce_field( 'rrqr_bridge_server_sync' ); ?>
		<?php
		submit_button(
			__( 'Download schedule + standings into bridge cache', 'rrqr' ),
			'secondary',
			'submit',
			false,
			array( 'id' => 'rrqr-bridge-server-sync' )
		);
		?>
	</form>

	<?php if ( is_array( $sync_results ) ) : ?>
		<div class="notice notice-info" style="margin:1em 0; padding:12px;">
			<p><strong><?php esc_html_e( 'Sync result', 'rrqr' ); ?></strong></p>
			<ul style="list-style:disc;margin-left:1.5em;">
				<?php foreach ( $sync_results as $row ) : ?>
					<li>
						<strong><?php echo esc_html( $row['label'] ?? $row['path'] ?? '' ); ?></strong>
						—
						<?php if ( ! empty( $row['ok'] ) ) : ?>
							<span style="color:#008a20;"><?php echo esc_html( $row['message'] ?? '' ); ?></span>
							<?php if ( isset( $row['bytes'] ) ) : ?>
								(<?php echo esc_html( size_format( (int) $row['bytes'] ) ); ?>)
							<?php endif; ?>
						<?php else : ?>
							<span style="color:#b32d2e;"><?php echo esc_html( $row['message'] ?? __( 'Failed.', 'rrqr' ) ); ?></span>
						<?php endif; ?>
					</li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>

	<h2 style="margin-top:2em;"><?php esc_html_e( 'Boxscores', 'rrqr' ); ?></h2>
	<p class="description">
		<?php esc_html_e( 'Per-game boxscores are not bulk-synced here. Use the CLI fetcher with --game=XXXXXXXXXX, REST ingest, or leave admin test mode off and load games normally when the CDN is reachable.', 'rrqr' ); ?>
	</p>
</div>
