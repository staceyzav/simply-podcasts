<?php
/**
 * Plugin Name: Simply Podcasts
 * Plugin URI:  https://simplydesign.com/simply-podcasts
 * Description: Podcast CPT with RSS sync from any feed. Season filters, full-description popup, and inline audio player via [simply_podcasts] shortcode.
 * Author:      Simply Design
 * Author URI:  https://simplydesign.com
 * Version:     1.0.0
 * License:     GPL-2.0-or-later
 * Text Domain: simply-podcasts
 * Requires at least: 5.4
 * Requires PHP: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'SP_VERSION', '1.0.0' );
define( 'SP_PATH', plugin_dir_path( __FILE__ ) );
define( 'SP_URL',  plugin_dir_url( __FILE__ ) );

require_once SP_PATH . 'includes/class-github-updater.php';
new Simply_GitHub_Updater( 'plugin', plugin_basename( __FILE__ ), 'staceyzav/simply-podcasts', SP_VERSION );


// =============================================================================
// Enqueue
// =============================================================================

add_action( 'wp_enqueue_scripts', function() {
	wp_enqueue_style(  'simply-podcasts', SP_URL . 'assets/css/simply-podcasts.css', array(), SP_VERSION );
	wp_enqueue_script( 'simply-podcasts', SP_URL . 'assets/js/simply-podcasts.js',  array(), SP_VERSION, true );
} );


// =============================================================================
// Settings — Settings → Simply Podcasts
// =============================================================================

add_action( 'admin_menu', function() {
	add_options_page( 'Simply Podcasts', 'Simply Podcasts', 'manage_options', 'simply-podcasts', 'sp_settings_page' );
} );

function sp_settings_page() {
	if ( isset( $_POST['sp_save_settings'] ) && check_admin_referer( 'sp_settings' ) ) {
		update_option( 'sp_podcast_feed_url', esc_url_raw( $_POST['sp_podcast_feed_url'] ?? '' ) );
		echo '<div class="notice notice-success is-dismissible"><p>Settings saved.</p></div>';
	}
	$feed_url = get_option( 'sp_podcast_feed_url', '' );
	?>
	<div class="wrap">
		<h1>Simply Podcasts</h1>
		<form method="post">
			<?php wp_nonce_field( 'sp_settings' ); ?>
			<table class="form-table">
				<tr>
					<th><label for="sp_podcast_feed_url">RSS Feed URL</label></th>
					<td>
						<input type="url" id="sp_podcast_feed_url" name="sp_podcast_feed_url"
							value="<?php echo esc_attr( $feed_url ); ?>"
							class="regular-text" placeholder="https://rss.buzzsprout.com/..." />
						<p class="description">Paste your Buzzsprout (or any podcast) RSS feed URL.</p>
					</td>
				</tr>
			</table>
			<?php submit_button( 'Save Settings', 'primary', 'sp_save_settings' ); ?>
		</form>
	</div>
	<?php
}


// =============================================================================
// CPT — simply_podcast
// =============================================================================

add_action( 'init', function() {
	register_post_type( 'simply_podcast', array(
		'labels' => array(
			'name'               => 'Podcast Episodes',
			'singular_name'      => 'Episode',
			'add_new_item'       => 'Add New Episode',
			'edit_item'          => 'Edit Episode',
			'new_item'           => 'New Episode',
			'view_item'          => 'View Episode',
			'search_items'       => 'Search Episodes',
			'not_found'          => 'No episodes found',
			'not_found_in_trash' => 'No episodes in trash',
			'menu_name'          => 'Podcast',
		),
		'public'        => false,
		'show_ui'       => true,
		'show_in_menu'  => true,
		'show_in_rest'  => true,
		'supports'      => array( 'title', 'editor', 'thumbnail' ),
		'menu_icon'     => 'dashicons-microphone',
		'menu_position' => 26,
	) );
} );

add_filter( 'manage_simply_podcast_posts_columns', function( $cols ) {
	return array(
		'cb'       => $cols['cb'],
		'title'    => 'Title',
		'episode'  => 'Episode',
		'season'   => 'Season',
		'duration' => 'Duration',
		'date'     => 'Published',
	);
} );

add_action( 'manage_simply_podcast_posts_custom_column', function( $col, $post_id ) {
	if ( $col === 'episode' ) echo esc_html( get_post_meta( $post_id, '_podcast_episode', true ) ?: '—' );
	if ( $col === 'season'  ) echo esc_html( get_post_meta( $post_id, '_podcast_season',  true ) ?: '—' );
	if ( $col === 'duration' ) {
		$secs = intval( get_post_meta( $post_id, '_podcast_duration', true ) );
		echo $secs ? esc_html( sprintf( '%d:%02d', floor( $secs / 60 ), $secs % 60 ) ) : '—';
	}
}, 10, 2 );


// =============================================================================
// Meta box
// =============================================================================

add_action( 'add_meta_boxes', function() {
	add_meta_box( 'sp_podcast_details', 'Podcast Details', 'sp_podcast_meta_box', 'simply_podcast', 'normal', 'high' );
} );

function sp_podcast_meta_box( $post ) {
	wp_nonce_field( 'sp_podcast_meta', 'sp_podcast_meta_nonce' );
	$art_url   = get_post_meta( $post->ID, '_podcast_art_url',   true );
	$audio_url = get_post_meta( $post->ID, '_podcast_audio_url', true );
	$episode   = get_post_meta( $post->ID, '_podcast_episode',   true );
	$season    = get_post_meta( $post->ID, '_podcast_season',    true );
	?>
	<table class="form-table" style="margin:0">
		<tr>
			<th style="width:140px;padding:8px 0"><label for="sp_art_url">Artwork URL</label></th>
			<td style="padding:8px 0">
				<input type="url" id="sp_art_url" name="sp_art_url"
					value="<?php echo esc_attr( $art_url ); ?>"
					style="width:100%;max-width:600px" />
				<?php if ( $art_url ) : ?>
				<br><img src="<?php echo esc_url( $art_url ); ?>" style="margin-top:8px;height:80px;width:80px;object-fit:cover;border-radius:4px">
				<?php endif; ?>
			</td>
		</tr>
		<tr>
			<th style="padding:8px 0">Audio URL</th>
			<td style="padding:8px 0"><code style="word-break:break-all"><?php echo esc_html( $audio_url ); ?></code></td>
		</tr>
		<tr>
			<th style="padding:8px 0">Season / Episode</th>
			<td style="padding:8px 0"><?php echo esc_html( "S{$season} · Ep {$episode}" ); ?></td>
		</tr>
	</table>
	<?php
}

add_action( 'save_post_simply_podcast', function( $post_id ) {
	if ( ! isset( $_POST['sp_podcast_meta_nonce'] ) ) return;
	if ( ! wp_verify_nonce( $_POST['sp_podcast_meta_nonce'], 'sp_podcast_meta' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( isset( $_POST['sp_art_url'] ) ) {
		update_post_meta( $post_id, '_podcast_art_url', esc_url_raw( $_POST['sp_art_url'] ) );
	}
} );


// =============================================================================
// RSS Sync
// =============================================================================

function sp_podcast_sync() {
	$feed_url = get_option( 'sp_podcast_feed_url', '' );
	if ( ! $feed_url ) return;

	$response = wp_remote_get( $feed_url, array( 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) return;

	$body = wp_remote_retrieve_body( $response );
	if ( empty( $body ) ) return;

	$xml = simplexml_load_string( $body, 'SimpleXMLElement', LIBXML_NOCDATA );
	if ( ! $xml ) return;

	$itunes_ns  = 'http://www.itunes.com/dtds/podcast-1.0.dtd';
	$content_ns = 'http://purl.org/rss/1.0/modules/content/';
	$imported   = 0;

	$xml->registerXPathNamespace( 'itunes',  $itunes_ns );
	$xml->registerXPathNamespace( 'content', $content_ns );

	$show_art_nodes = $xml->channel->xpath( 'itunes:image' );
	$show_art_url   = $show_art_nodes ? (string) $show_art_nodes[0]['href'] : '';

	foreach ( $xml->channel->item as $item ) {
		$item->registerXPathNamespace( 'itunes',  $itunes_ns );
		$item->registerXPathNamespace( 'content', $content_ns );

		$guid = trim( (string) $item->guid );
		if ( ! $guid ) continue;

		$existing = get_posts( array(
			'post_type'      => 'simply_podcast',
			'post_status'    => array( 'publish', 'draft', 'pending', 'private' ),
			'posts_per_page' => 1,
			'meta_key'       => '_podcast_guid',
			'meta_value'     => $guid,
			'fields'         => 'ids',
		) );
		if ( $existing ) continue;

		$itunes    = $item->children( $itunes_ns );
		$content   = $item->children( $content_ns );

		$title     = trim( (string) $item->title );
		$body      = trim( (string) $content->encoded ) ?: trim( (string) $item->description );
		$pub_date  = trim( (string) $item->pubDate );
		$audio_url = (string) $item->enclosure['url'];
		$duration  = (string) $itunes->duration;
		$ep_num    = (string) $itunes->episode;
		$season    = (string) $itunes->season;
		$ep_art    = $item->xpath( 'itunes:image' );
		$art_url   = $ep_art ? (string) $ep_art[0]['href'] : $show_art_url;

		$post_id = wp_insert_post( array(
			'post_type'    => 'simply_podcast',
			'post_title'   => $title,
			'post_content' => $body,
			'post_status'  => 'publish',
			'post_date'    => $pub_date ? date( 'Y-m-d H:i:s', strtotime( $pub_date ) ) : current_time( 'mysql' ),
		) );

		if ( is_wp_error( $post_id ) ) continue;

		update_post_meta( $post_id, '_podcast_guid',      $guid );
		update_post_meta( $post_id, '_podcast_audio_url', $audio_url );
		update_post_meta( $post_id, '_podcast_duration',  $duration );
		update_post_meta( $post_id, '_podcast_episode',   $ep_num );
		update_post_meta( $post_id, '_podcast_season',    $season );
		if ( $art_url ) update_post_meta( $post_id, '_podcast_art_url', $art_url );

		$imported++;
	}

	update_option( 'sp_podcast_last_sync',  current_time( 'mysql' ) );
	update_option( 'sp_podcast_last_count', $imported );
}


// =============================================================================
// Cron
// =============================================================================

add_action( 'init', function() {
	if ( ! wp_next_scheduled( 'sp_podcast_cron' ) ) {
		wp_schedule_event( time(), 'daily', 'sp_podcast_cron' );
	}
} );

add_action( 'sp_podcast_cron', 'sp_podcast_sync' );

register_deactivation_hook( __FILE__, function() {
	wp_clear_scheduled_hook( 'sp_podcast_cron' );
} );


// =============================================================================
// Admin — sync button + notice
// =============================================================================

add_action( 'admin_init', function() {
	if (
		isset( $_GET['sp_sync_podcasts'], $_GET['_wpnonce'] ) &&
		wp_verify_nonce( $_GET['_wpnonce'], 'sp_sync_podcasts' ) &&
		current_user_can( 'manage_options' )
	) {
		sp_podcast_sync();
		wp_redirect( admin_url( 'edit.php?post_type=simply_podcast&synced=1' ) );
		exit;
	}
} );

add_action( 'admin_notices', function() {
	$screen = get_current_screen();
	if ( ! $screen || $screen->post_type !== 'simply_podcast' ) return;

	$feed_url   = get_option( 'sp_podcast_feed_url', '' );
	$sync_url   = wp_nonce_url( admin_url( 'edit.php?post_type=simply_podcast&sp_sync_podcasts=1' ), 'sp_sync_podcasts' );
	$last_sync  = get_option( 'sp_podcast_last_sync', '' );
	$last_count = get_option( 'sp_podcast_last_count', 0 );

	if ( ! $feed_url ) {
		echo '<div class="notice notice-warning"><p>Simply Podcasts: <a href="' . esc_url( admin_url( 'options-general.php?page=simply-podcasts' ) ) . '">Set your RSS feed URL</a> to enable sync.</p></div>';
		return;
	}

	if ( isset( $_GET['synced'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Podcast sync complete — <strong>' . intval( $last_count ) . '</strong> new episode(s) imported.</p></div>';
	}

	echo '<div class="notice notice-info"><p>';
	echo '<a href="' . esc_url( $sync_url ) . '" class="button">↻ Sync Podcasts Now</a>';
	if ( $last_sync ) echo '&nbsp; Last synced: ' . esc_html( $last_sync );
	echo '</p></div>';
} );


// =============================================================================
// Shortcode — [simply_podcasts]
// =============================================================================

add_shortcode( 'simply_podcasts', 'sp_podcasts_shortcode' );

function sp_podcasts_shortcode( $atts ) {
	$atts = shortcode_atts( array(
		'limit'   => 10,
		'order'   => 'DESC',
		'season'  => '',
		'columns' => 3,
		'filters' => '0',
	), $atts );

	$show_filters = filter_var( $atts['filters'], FILTER_VALIDATE_BOOLEAN );

	$query_args = array(
		'post_type'      => 'simply_podcast',
		'post_status'    => 'publish',
		'posts_per_page' => $show_filters ? -1 : intval( $atts['limit'] ),
		'orderby'        => 'date',
		'order'          => $atts['order'] === 'ASC' ? 'ASC' : 'DESC',
	);

	if ( ! $show_filters && ! empty( $atts['season'] ) ) {
		$query_args['meta_query'] = array( array(
			'key'   => '_podcast_season',
			'value' => sanitize_text_field( $atts['season'] ),
		) );
	}

	$episodes = get_posts( $query_args );
	if ( empty( $episodes ) ) return '';

	$cols = max( 1, min( 4, intval( $atts['columns'] ) ) );

	$seasons = array();
	foreach ( $episodes as $ep ) {
		$s = get_post_meta( $ep->ID, '_podcast_season', true );
		if ( $s !== '' && $s !== false && ! in_array( $s, $seasons, true ) ) {
			$seasons[] = $s;
		}
	}
	sort( $seasons, SORT_NUMERIC );

	ob_start();
	?>
	<div class="sp-podcasts-wrap">

	<?php if ( $show_filters && count( $seasons ) >= 1 ) : ?>
	<div class="sp-podcast-filters">
		<button class="sp-podcast-filter is-active" data-season="">All Episodes</button>
		<?php foreach ( $seasons as $s ) : ?>
		<button class="sp-podcast-filter" data-season="<?php echo esc_attr( $s ); ?>">Season <?php echo esc_html( $s ); ?></button>
		<?php endforeach; ?>
	</div>
	<?php endif; ?>

	<div class="sp-podcasts sp-podcasts--cols-<?php echo $cols; ?>"
		style="--sp-cols:<?php echo $cols; ?>">
		<?php foreach ( $episodes as $ep ) :
			$audio_url = get_post_meta( $ep->ID, '_podcast_audio_url', true );
			$duration  = intval( get_post_meta( $ep->ID, '_podcast_duration', true ) );
			$ep_num    = get_post_meta( $ep->ID, '_podcast_episode', true );
			$season    = get_post_meta( $ep->ID, '_podcast_season', true );
			$thumb     = get_post_meta( $ep->ID, '_podcast_art_url', true ) ?: get_the_post_thumbnail_url( $ep->ID, 'medium' );
			$dur_str   = $duration ? sprintf( '%d:%02d', floor( $duration / 60 ), $duration % 60 ) : '';
			$meta_str  = '';
			if ( $season && $ep_num )    $meta_str = "S{$season} · Ep {$ep_num}";
			elseif ( $ep_num )           $meta_str = "Episode {$ep_num}";
			$popup_data = base64_encode( wp_json_encode( array(
				'title' => $ep->post_title,
				'meta'  => $meta_str,
				'date'  => get_the_date( 'F j, Y', $ep ),
				'desc'  => wpautop( wp_kses_post( $ep->post_content ) ),
				'art'   => $thumb ?: '',
				'audio' => $audio_url ?: '',
			) ) );
		?>
		<div class="sp-podcast-card" data-season="<?php echo esc_attr( $season ); ?>"
			data-podcast="<?php echo esc_attr( $popup_data ); ?>">

			<?php if ( $thumb ) : ?>
			<div class="sp-podcast-card__art">
				<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $ep->post_title ); ?>">
			</div>
			<?php endif; ?>

			<div class="sp-podcast-card__body">
				<div class="sp-podcast-card__meta">
					<?php if ( $meta_str ) : ?>
					<span class="sp-podcast-card__ep"><?php echo esc_html( $meta_str ); ?></span>
					<?php endif; ?>
					<?php if ( $dur_str ) : ?>
					<span class="sp-podcast-card__dur"><?php echo esc_html( $dur_str ); ?></span>
					<?php endif; ?>
				</div>

				<h3 class="sp-podcast-card__title"><?php echo esc_html( $ep->post_title ); ?></h3>

				<p class="sp-podcast-card__date"><?php echo esc_html( get_the_date( 'F j, Y', $ep ) ); ?></p>

				<?php if ( $ep->post_content ) : ?>
				<p class="sp-podcast-card__desc"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $ep->post_content ), 24 ) ); ?></p>
				<?php endif; ?>

				<?php if ( $audio_url ) : ?>
				<audio class="sp-podcast-card__player" controls preload="none">
					<source src="<?php echo esc_url( $audio_url ); ?>" type="audio/mpeg">
				</audio>
				<?php endif; ?>

				<?php if ( $show_filters ) : ?>
				<button class="sp-podcast-card__more">Full Description</button>
				<?php endif; ?>
			</div>

		</div>
		<?php endforeach; ?>
	</div><!-- .sp-podcasts -->

	<?php if ( $show_filters ) : ?>
	<div class="sp-podcast-overlay" aria-hidden="true"></div>
	<div class="sp-podcast-popup" role="dialog" aria-modal="true" aria-hidden="true">
		<button class="sp-podcast-popup__close" aria-label="Close">&#x2715;</button>
		<div class="sp-podcast-popup__inner">
			<div class="sp-podcast-popup__art"></div>
			<div class="sp-podcast-popup__body">
				<p class="sp-podcast-popup__meta"></p>
				<h3 class="sp-podcast-popup__title"></h3>
				<p class="sp-podcast-popup__date"></p>
				<audio class="sp-podcast-popup__player" controls preload="none"></audio>
				<div class="sp-podcast-popup__desc"></div>
			</div>
		</div>
	</div>
	<?php endif; ?>

	</div><!-- .sp-podcasts-wrap -->
	<?php
	return ob_get_clean();
}
