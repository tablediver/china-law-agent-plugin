<?php
/**
 * Plugin Name:  China Law Agent
 * Description:  Bindet den Railway-gehosteten China Law KI-Assistenten via [china_law_agent] Shortcode ein.
 * Version:      1.0.0
 * Author:       Ebner Stolz
 * License:      GPL-2.0-or-later
 */

defined( 'ABSPATH' ) || exit;

define( 'CLA_OPTION_KEY', 'china_law_agent_settings' );
define( 'CLA_CACHE_KEY',  'china_law_agent_embed' );

// ── Einstellungen ────────────────────────────────────────────────────────────

function cla_defaults(): array {
	return [
		'railway_url' => 'https://lawchina-assistant-production.up.railway.app',
		'cache_ttl'   => 60,
	];
}

function cla_settings(): array {
	return wp_parse_args( get_option( CLA_OPTION_KEY, [] ), cla_defaults() );
}

// ── Admin-Seite ──────────────────────────────────────────────────────────────

add_action( 'admin_menu', function () {
	add_options_page(
		'China Law Agent',
		'China Law Agent',
		'manage_options',
		'china-law-agent',
		'cla_settings_page'
	);
} );

add_action( 'admin_init', function () {
	if ( isset( $_GET['cla_clear'] ) && current_user_can( 'manage_options' ) ) {
		delete_transient( CLA_CACHE_KEY );
		wp_safe_redirect( admin_url( 'options-general.php?page=china-law-agent&cleared=1' ) );
		exit;
	}
} );

function cla_settings_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;

	if ( isset( $_POST['cla_save'] ) ) {
		check_admin_referer( 'cla_nonce' );
		update_option( CLA_OPTION_KEY, [
			'railway_url' => esc_url_raw( trim( $_POST['railway_url'] ?? '' ) ),
			'cache_ttl'   => max( 1, intval( $_POST['cache_ttl'] ?? 60 ) ),
		] );
		delete_transient( CLA_CACHE_KEY );
		echo '<div class="notice notice-success is-dismissible"><p>Gespeichert &amp; Cache geleert.</p></div>';
	}

	if ( isset( $_GET['cleared'] ) ) {
		echo '<div class="notice notice-success is-dismissible"><p>Cache geleert.</p></div>';
	}

	$s = cla_settings();
	?>
	<div class="wrap">
		<h1>China Law Agent</h1>
		<form method="post">
			<?php wp_nonce_field( 'cla_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="railway_url">Railway-URL</label></th>
					<td>
						<input type="url" id="railway_url" name="railway_url"
						       value="<?= esc_attr( $s['railway_url'] ) ?>" class="regular-text" required>
						<p class="description">URL des Railway-Deployments, ohne abschließenden Slash.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="cache_ttl">Cache-Dauer (Minuten)</label></th>
					<td>
						<input type="number" id="cache_ttl" name="cache_ttl"
						       value="<?= esc_attr( $s['cache_ttl'] ) ?>" min="1" class="small-text">
						<p class="description">Wie lange das Embed-HTML zwischengespeichert wird.</p>
					</td>
				</tr>
			</table>
			<p class="submit">
				<button type="submit" name="cla_save" class="button button-primary">Speichern</button>
				&nbsp;
				<a href="<?= esc_url( admin_url( 'options-general.php?page=china-law-agent&cla_clear=1' ) ) ?>"
				   class="button">Cache manuell leeren</a>
			</p>
		</form>
	</div>
	<?php
}

// ── Shortcode [china_law_agent] ──────────────────────────────────────────────

add_shortcode( 'china_law_agent', 'cla_shortcode' );

function cla_shortcode(): string {
	$s   = cla_settings();
	$url = rtrim( $s['railway_url'], '/' ) . '/embed';

	$html = get_transient( CLA_CACHE_KEY );

	if ( $html === false ) {
		$response = wp_remote_get( $url, [ 'timeout' => 15 ] );

		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return '<div style="padding:2rem;color:#c00;">China Law Agent konnte nicht geladen werden.</div>';
		}

		$html = wp_remote_retrieve_body( $response );
		set_transient( CLA_CACHE_KEY, $html, $s['cache_ttl'] * 60 );
	}

	return $html;
}

// ── Weißer Header auf Seiten mit dem Shortcode ───────────────────────────────

add_action( 'wp_head', function () {
	global $post;
	if ( ! is_a( $post, 'WP_Post' ) || ! has_shortcode( $post->post_content, 'china_law_agent' ) ) {
		return;
	}
	?>
	<style id="cla-header-css">
		/* Weißer Header – identisch zur Homepage-Logik des Themes */
		body:has(.china-law-agent-embed) {
			--color-menu: var(--color-white);
			display: grid;
			grid-template-rows: min-content min-content min-content;
			grid-template-columns: 100dvw;
			grid-template-areas: 'header' 'main' 'footer';
			width: 100dvw;
		}
		/* Nav-Links weiß (explizite Überschreibung wie im Theme-Desktop-Media-Query) */
		body:has(.china-law-agent-embed) .site-navigation .menu {
			--color-menu: var(--color-white);
		}
		body:has(.china-law-agent-embed) .site-navigation .menu .sub-menu {
			--color-menu: #63666a;
		}
		body:has(.china-law-agent-embed) .site-header:has(#menu-nav:checked) .site-title,
		body:has(.china-law-agent-embed) .site-header:has(#menu-nav:checked) .site-navigation .toggle-label[for='menu-off'] {
			--color-menu: var(--color-logo-text);
		}
		body:has(.china-law-agent-embed) .top-navigation .menu-item.menu-item-um-login {
			background-color: transparent;
			color: var(--color-white);
		}
		/* Header überlagert Widget (wie Homepage-Slider) */
		body:has(.china-law-agent-embed) .site-header {
			grid-area: header;
			z-index: 1;
		}
		/* Widget beginnt hinter dem Header – kein Abstand oben, Seitenpadding bleibt für Grid-Math */
		body:has(.china-law-agent-embed) .site-main {
			grid-area: header / header / main / main;
			padding-top: 0;
		}
		/* Kein Abstand vor dem Widget */
		body:has(.china-law-agent-embed) .entry-content {
			margin-top: 0;
		}
		/* Widget bricht aus dem Grid-Container aus → volle Browserbreite.
		   .entry und .entry-content bleiben unangetastet, damit nachfolgender
		   Content (z. B. China Law News) weiterhin korrekt im 12-Spalten-Grid liegt. */
		body:has(.china-law-agent-embed) .china-law-agent-embed {
			width: calc(100% + 2 * var(--space-outer));
			margin-left: calc(-1 * var(--space-outer));
		}
	</style>
	<?php
} );
