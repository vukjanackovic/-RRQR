<?php
/**
 * Game detail page template.
 *
 * @package RRQR
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php echo esc_html( $team_label ); ?> — <?php esc_html_e( 'Generate Reaction', 'rrqr' ); ?></h1>

	<p>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=rrqr' ) ); ?>" class="button">&larr; <?php esc_html_e( 'Back to Games', 'rrqr' ); ?></a>
	</p>

	<?php if ( empty( $players ) ) : ?>
		<div class="notice notice-warning">
			<p><?php printf( esc_html__( 'No %s players found for this game.', 'rrqr' ), esc_html( $team_label ) ); ?></p>
		</div>
	<?php else : ?>
	<?php
		$away_id        = $game['awayTeam']['teamId'] ?? '';
		$away_team_name = $game['awayTeam']['teamName'] ?? '';
		$away_name      = ( $game['awayTeam']['teamCity'] ?? '' ) . ' ' . $away_team_name;
		$away_score     = $game['awayTeam']['score'] ?? 0;
		$home_id        = $game['homeTeam']['teamId'] ?? '';
		$home_team_name = $game['homeTeam']['teamName'] ?? '';
		$home_name      = ( $game['homeTeam']['teamCity'] ?? '' ) . ' ' . $home_team_name;
		$home_score     = $game['homeTeam']['score'] ?? 0;
	?>
	<div id="rrqr-game-data" style="display:none;"
		data-away-id="<?php echo esc_attr( $away_id ); ?>"
		data-away-name="<?php echo esc_attr( $away_name ); ?>"
		data-away-team="<?php echo esc_attr( $away_team_name ); ?>"
		data-away-score="<?php echo esc_attr( $away_score ); ?>"
		data-home-id="<?php echo esc_attr( $home_id ); ?>"
		data-home-name="<?php echo esc_attr( $home_name ); ?>"
		data-home-team="<?php echo esc_attr( $home_team_name ); ?>"
		data-home-score="<?php echo esc_attr( $home_score ); ?>"
	></div>
	<div class="rrqr-game-columns">
		<div class="rrqr-game-column-left">
		<div class="rrqr-player-list">
			<?php foreach ( $players as $player ) :
				if ( empty( $player['played'] ) || 1 !== (int) $player['played'] ) {
					continue;
				}
				$player_id  = $player['personId'] ?? '';
				$first_name = $player['firstName'] ?? '';
				$last_name  = $player['familyName'] ?? '';
				$initial    = $first_name ? mb_substr( $first_name, 0, 1 ) . '.' : '';
				$display    = trim( $initial . ' ' . $last_name );

				$statline = RRQR_Admin::format_statline( $player['statistics'] ?? array() );
				$headshot_url = sprintf( RRQR_Admin::HEADSHOT_URL, $player_id );
			?>
				<div class="rrqr-player-row"
					data-headshot="<?php echo esc_attr( $headshot_url ); ?>"
					data-name="<?php echo esc_attr( $display ); ?>"
					data-statline="<?php echo esc_attr( $statline ); ?>"
				>
					<div class="rrqr-player-headshot">
						<img
							src="<?php echo esc_url( $headshot_url ); ?>"
							alt="<?php echo esc_attr( $display ); ?>"
							width="130"
							height="95"
						/>
						<div class="rrqr-player-name"><?php echo esc_html( $display ); ?></div>
					</div>
					<div class="rrqr-player-textarea">
						<textarea name="rrqr_reaction[<?php echo esc_attr( $player_id ); ?>]" rows="4" placeholder="<?php esc_attr_e( 'Write reaction…', 'rrqr' ); ?>"></textarea>
						<div class="rrqr-player-stats"><?php echo esc_html( $statline ); ?></div>
					</div>
					<div class="rrqr-player-grade">
						<select name="rrqr_grade[<?php echo esc_attr( $player_id ); ?>]">
							<?php foreach ( $grades as $grade ) : ?>
								<option value="<?php echo esc_attr( $grade ); ?>"><?php echo esc_html( $grade ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
				</div>
			<?php endforeach; ?>
		</div>

		<?php if ( ! empty( $coach_name ) ) : ?>
		<h3><?php esc_html_e( 'Coach', 'rrqr' ); ?></h3>
		<div class="rrqr-player-row"
			data-headshot="<?php echo esc_attr( $coach_headshot_url ); ?>"
			data-name="<?php echo esc_attr( $coach_name ); ?>"
			data-statline="<?php esc_attr_e( 'Coach', 'rrqr' ); ?>"
		>
			<div class="rrqr-player-headshot">
				<?php if ( ! empty( $coach_headshot_url ) ) : ?>
				<img
					src="<?php echo esc_url( $coach_headshot_url ); ?>"
					alt="<?php echo esc_attr( $coach_name ); ?>"
					style="max-width:130px;height:auto;"
				/>
				<?php endif; ?>
				<div class="rrqr-player-name"><?php echo esc_html( $coach_name ); ?></div>
			</div>
			<div class="rrqr-player-textarea">
				<textarea name="rrqr_reaction_coach" rows="4" placeholder="<?php esc_attr_e( 'Write reaction…', 'rrqr' ); ?>"></textarea>
				<div class="rrqr-player-stats"><?php esc_html_e( 'Coach', 'rrqr' ); ?></div>
			</div>
			<div class="rrqr-player-grade">
				<select name="rrqr_grade_coach">
					<?php foreach ( $grades as $grade ) : ?>
						<option value="<?php echo esc_attr( $grade ); ?>"><?php echo esc_html( $grade ); ?></option>
					<?php endforeach; ?>
				</select>
			</div>
		</div>
		<?php endif; ?>

		<h3><?php esc_html_e( 'Things we saw', 'rrqr' ); ?></h3>
		<div class="rrqr-things-we-saw">
			<?php for ( $i = 1; $i <= 5; $i++ ) : ?>
			<div class="rrqr-thing-row">
				<textarea name="rrqr_thing[<?php echo $i; ?>]" rows="3" placeholder="<?php echo esc_attr( sprintf( __( 'Thing %d…', 'rrqr' ), $i ) ); ?>"></textarea>
			</div>
			<?php endfor; ?>
		</div>

		</div>
		<div class="rrqr-game-column-right">
			<button type="button" class="button button-primary rrqr-generate-btn" id="rrqr-generate"><?php esc_html_e( 'Generate', 'rrqr' ); ?></button>
			<textarea id="rrqr-output" class="rrqr-output-textarea" rows="20" readonly></textarea>
			<div class="rrqr-action-buttons">
				<button type="button" class="button" id="rrqr-copy"><?php esc_html_e( 'Copy to Clipboard', 'rrqr' ); ?></button>
			</div>
			<div id="rrqr-preview" class="rrqr-preview"></div>
		</div>
	</div>
	<?php endif; ?>
</div>
