<?php

declare(strict_types=1);
/**
 * Dish Docs — Admin Documentation Viewer
 *
 * Reads Markdown files from the theme's Docs/ directory and renders them in a
 * clean two-column admin page. Accessible by any logged-in user.
 *
 * URL pattern: admin.php?page=dish-docs&section=developer&doc=01-architecture
 *
 * @package basecamp
 */

namespace Basecamp\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/lib/Parsedown.php';

final class Docs {

	const MENU_SLUG = 'dish-docs';
	const DOCS_DIR  = 'Docs';

	/**
	 * Section definitions: slug => label.
	 *
	 * @var array<string, string>
	 */
	const SECTIONS = [
        'content-team' => 'Content Team',
		'developer'    => 'Developer',
        'planning'    => 'Planning',
        'plugin-audit'    => 'Plugin Audit',
	];

	// =========================================================================
	// Boot
	// =========================================================================

	public static function init(): void {
		add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'maybe_inject_styles' ] );
	}

	// =========================================================================
	// Admin menu
	// =========================================================================

	public static function register_menu(): void {
		add_menu_page(
			__( 'Dish Docs', 'basecamp' ),
			__( 'Dish Docs', 'basecamp' ),
			'read',
			self::MENU_SLUG,
			[ __CLASS__, 'render_page' ],
			'dashicons-book-alt',
			3
		);
	}

	/**
	 * Inject scoped styles only on the docs page (avoids an extra HTTP request).
	 */
	public static function maybe_inject_styles( string $hook ): void {
		if ( 'toplevel_page_' . self::MENU_SLUG !== $hook ) {
			return;
		}
		add_action( 'admin_head', [ __CLASS__, 'output_styles' ] );
	}

	// =========================================================================
	// Docs directory helpers
	// =========================================================================

	private static function docs_base(): string {
		return get_template_directory() . '/' . self::DOCS_DIR;
	}

	/**
	 * Return sorted array of .md file paths for a given section.
	 *
	 * @return string[]
	 */
	private static function section_files( string $section ): array {
		$files = glob( self::docs_base() . '/' . $section . '/*.md' );
		if ( ! $files ) {
			return [];
		}
		sort( $files );
		return $files;
	}

	/**
	 * Convert a filename to a human-readable title.
	 * "02-code-style" → "Code Style"
	 */
	private static function file_title( string $path ): string {
		$name = pathinfo( $path, PATHINFO_FILENAME );
		$name = (string) preg_replace( '/^\d+-/', '', $name );
		return ucwords( str_replace( '-', ' ', $name ) );
	}

	// =========================================================================
	// Page renderer
	// =========================================================================

	public static function render_page(): void {
		if ( ! current_user_can( 'read' ) ) {
			return;
		}

		$section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'content-team';
		if ( ! array_key_exists( $section, self::SECTIONS ) ) {
			$section = 'content-team';
		}

		$file_slug   = isset( $_GET['doc'] ) ? sanitize_file_name( $_GET['doc'] ) : '';
		$docs_base   = self::docs_base();
		$files       = self::section_files( $section );
		$content_raw = '';
		$page_title  = '';
		$active_slug = '';

		// Load requested file with path-traversal protection.
		if ( $file_slug ) {
			$requested = $docs_base . '/' . $section . '/' . $file_slug . '.md';
			$real      = realpath( $requested );
			$base_real = realpath( $docs_base );

			if (
				$real &&
				$base_real &&
				str_starts_with( $real, $base_real ) &&
				is_file( $real )
			) {
				$content_raw = (string) file_get_contents( $real );
				$page_title  = self::file_title( $real );
				$active_slug = $file_slug;
			}
		}

		// Fall back to first file in the section.
		if ( $content_raw === '' && ! empty( $files ) ) {
			$first       = $files[0];
			$content_raw = (string) file_get_contents( $first );
			$page_title  = self::file_title( $first );
			$active_slug = pathinfo( $first, PATHINFO_FILENAME );
		}

		$parsed_html = self::parse_markdown( $content_raw );

		// Build tab URLs.
		$tab_urls = [];
		foreach ( array_keys( self::SECTIONS ) as $slug ) {
			$tab_urls[ $slug ] = admin_url( 'admin.php?page=' . self::MENU_SLUG . '&section=' . $slug );
		}
		?>
		<div class="wrap basecamp-docs-wrap">

			<nav class="basecamp-docs-tabs" aria-label="<?php esc_attr_e( 'Documentation sections', 'basecamp' ); ?>">
				<?php foreach ( self::SECTIONS as $slug => $label ) : ?>
				<a
					href="<?php echo esc_url( $tab_urls[ $slug ] ); ?>"
					class="basecamp-docs-tab <?php echo $slug === $section ? 'is-active' : ''; ?>"
				>
					<?php echo esc_html( $label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>

			<div class="basecamp-docs-layout">

				<aside class="basecamp-docs-sidebar">
					<ul class="basecamp-docs-file-list">
						<?php foreach ( $files as $file_path ) :
							$slug      = pathinfo( $file_path, PATHINFO_FILENAME );
							$title     = self::file_title( $file_path );
							$url       = admin_url(
								'admin.php?page=' . self::MENU_SLUG .
								'&section=' . $section .
								'&doc=' . rawurlencode( $slug )
							);
							$is_active = ( $slug === $active_slug );
						?>
						<li>
							<a
								href="<?php echo esc_url( $url ); ?>"
								class="basecamp-docs-file-link <?php echo $is_active ? 'is-active' : ''; ?>"
								<?php echo $is_active ? 'aria-current="page"' : ''; ?>
							>
								<?php echo esc_html( $title ); ?>
							</a>
						</li>
						<?php endforeach; ?>
						<?php if ( empty( $files ) ) : ?>
						<li class="basecamp-docs-empty">
							<?php esc_html_e( 'No documents found in this section.', 'basecamp' ); ?>
						</li>
						<?php endif; ?>
					</ul>
				</aside>

				<main class="basecamp-docs-content" id="basecamp-docs-main">
					<?php if ( $page_title ) : ?>
					<h1 class="basecamp-docs-page-title"><?php echo esc_html( $page_title ); ?></h1>
					<?php endif; ?>

					<?php if ( $parsed_html ) : ?>
					<div class="basecamp-docs-body">
						<?php echo wp_kses_post( $parsed_html ); ?>
					</div>
					<?php else : ?>
					<p class="basecamp-docs-placeholder">
						<?php esc_html_e( 'Select a document from the sidebar.', 'basecamp' ); ?>
					</p>
					<?php endif; ?>
				</main>

			</div><!-- .basecamp-docs-layout -->

		</div><!-- .basecamp-docs-wrap -->
		<?php
	}

	// =========================================================================
	// Markdown parser
	// =========================================================================

	/**
	 * Convert Markdown to safe HTML via Parsedown.
	 *
	 * Output is subsequently sanitized by wp_kses_post() in the renderer,
	 * so Parsedown runs in safe mode as an additional layer.
	 *
	 * @param  string $text Raw Markdown.
	 * @return string       HTML.
	 */
	private static function parse_markdown( string $text ): string {
		$parsedown = new \Parsedown();
		$parsedown->setSafeMode( true );
		$parsedown->setBreaksEnabled( false );
		return $parsedown->text( $text );
	}

	// =========================================================================
	// Styles
	// =========================================================================

	public static function output_styles(): void {
		?>
		<style>
		/* ── Basecamp Docs Viewer ────────────────────────────────────────────────── */
		.basecamp-docs-wrap { margin: 0; padding: 0; }

		/* Section tabs */
		.basecamp-docs-tabs {
			display: flex;
			border-bottom: 1px solid #ddd;
			margin: 0 0 0 -20px;
			padding: 16px 20px 0;
			background: #fff;
		}
		.basecamp-docs-tab {
			padding: 9px 22px;
			font-size: 13px;
			font-weight: 600;
			text-decoration: none;
			color: #646970;
			border-bottom: 3px solid transparent;
			transition: color 0.15s, border-color 0.15s;
		}
		.basecamp-docs-tab:hover  { color: #1d2327; }
		.basecamp-docs-tab.is-active { color: #1d2327; border-bottom-color: #2271b1; }

		/* Two-column layout */
		.basecamp-docs-layout {
			display: grid;
			grid-template-columns: 210px 1fr;
			min-height: calc(100vh - 100px);
			background: #fff;
			border: 1px solid #ddd;
			border-top: none;
			margin-left: -20px;
		}

		/* Sidebar */
		.basecamp-docs-sidebar { border-right: 1px solid #ddd; background: #f9f9f9; padding: 8px 0; }
		.basecamp-docs-file-list { list-style: none; margin: 0; padding: 0; }
		.basecamp-docs-file-link {
			display: block;
			padding: 9px 16px;
			font-size: 13px;
			text-decoration: none;
			color: #3c434a;
			border-left: 3px solid transparent;
			transition: background 0.1s, border-color 0.1s;
		}
		.basecamp-docs-file-link:hover { background: #f0f0f0; color: #1d2327; }
		.basecamp-docs-file-link.is-active {
			background: #f0f4f9;
			color: #2271b1;
			border-left-color: #2271b1;
			font-weight: 600;
		}
		.basecamp-docs-empty { padding: 12px 16px; font-size: 13px; color: #8c8f94; }

		/* Content */
		.basecamp-docs-content { padding: 32px 48px; max-width: 900px; }
		.basecamp-docs-page-title {
			font-size: 22px;
			font-weight: 700;
			margin: 0 0 24px;
			padding-bottom: 14px;
			border-bottom: 1px solid #eee;
			color: #1d2327;
		}

		/* Typography */
		.basecamp-docs-body h1,
		.basecamp-docs-body h2,
		.basecamp-docs-body h3,
		.basecamp-docs-body h4 { margin: 1.8em 0 0.5em; font-weight: 700; color: #1d2327; }
		.basecamp-docs-body h1 { font-size: 20px; margin-top: 0; }
		.basecamp-docs-body h2 { font-size: 17px; border-bottom: 1px solid #f0f0f0; padding-bottom: 6px; }
		.basecamp-docs-body h3 { font-size: 15px; }
		.basecamp-docs-body h4 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #646970; }
		.basecamp-docs-body p  { margin: 0 0 1em; line-height: 1.75; color: #3c434a; font-size: 14px; }
		.basecamp-docs-body ul,
		.basecamp-docs-body ol { margin: 0 0 1em 1.4em; padding: 0; }
		.basecamp-docs-body li { margin-bottom: 0.35em; font-size: 14px; color: #3c434a; line-height: 1.65; }
		.basecamp-docs-body hr { border: none; border-top: 1px solid #eee; margin: 2em 0; }
		.basecamp-docs-body a  { color: #2271b1; text-underline-offset: 2px; }
		.basecamp-docs-body a:hover { color: #135e96; }

		/* Blockquote */
		.basecamp-docs-body blockquote {
			margin: 1em 0;
			padding: 10px 16px;
			border-left: 4px solid #2271b1;
			background: #f0f4f9;
			border-radius: 0 4px 4px 0;
		}
		.basecamp-docs-body blockquote p { margin: 0; color: #2271b1; }

		/* Inline code */
		.basecamp-docs-body code {
			font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
			font-size: 12px;
			background: #f6f7f7;
			padding: 1px 5px;
			border-radius: 3px;
			color: #c7254e;
		}

		/* Code blocks */
		.basecamp-docs-body pre {
			background: #1d2327;
			color: #e0e0e0;
			padding: 18px 22px;
			border-radius: 4px;
			overflow-x: auto;
			margin: 0 0 1.4em;
			line-height: 1.65;
		}
		.basecamp-docs-body pre code {
			background: none;
			color: inherit;
			padding: 0;
			border-radius: 0;
			font-size: 12px;
		}

		/* Tables */
		.basecamp-docs-body table { border-collapse: collapse; width: 100%; margin-bottom: 1.4em; font-size: 13px; }
		.basecamp-docs-body th { background: #f6f7f7; font-weight: 600; text-align: left; padding: 8px 12px; border: 1px solid #ddd; }
		.basecamp-docs-body td { padding: 7px 12px; border: 1px solid #ddd; color: #3c434a; }
		.basecamp-docs-body tr:nth-child(even) td { background: #fafafa; }
		</style>
		<?php
	}
}

Docs::init();
