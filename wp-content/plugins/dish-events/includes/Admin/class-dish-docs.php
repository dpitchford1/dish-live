<?php
/**
 * Dish Events — Documentation Viewer
 *
 * Renders Markdown files from the plugin's docs/ directory in a clean two-column
 * admin page. Three sections: Content Team, Usage (shortcodes/settings), Developer.
 *
 * URL pattern: admin.php?page=dish-events-docs&section=content-team&doc=01-class-lifecycle
 *
 * Markdown rendering: borrows Parsedown from the active theme's lib/ folder if
 * present (Basecamp ships it); falls back to a simple built-in converter.
 *
 * @package Dish\Events\Admin
 */

declare( strict_types=1 );

namespace Dish\Events\Admin;

/**
 * Class DishDocs
 */
final class DishDocs {

	const MENU_SLUG = 'dish-events-docs';
	const DOCS_DIR  = 'docs';

	/**
	 * Section definitions: slug => label.
	 *
	 * @var array<string, string>
	 */
	const SECTIONS = [
		'content-team' => 'Content Team',
		'usage'        => 'Usage',
		'developer'    => 'Developer',
	];

	// -------------------------------------------------------------------------
	// Boot
	// -------------------------------------------------------------------------

	public static function init(): void {
		add_action( 'admin_menu',            [ __CLASS__, 'register_menu' ] );
		add_action( 'admin_enqueue_scripts', [ __CLASS__, 'maybe_inject_styles' ] );
		add_filter( 'parent_file',           [ __CLASS__, 'fix_parent_file' ] );
		add_filter( 'submenu_file',          [ __CLASS__, 'fix_submenu_file' ] );
	}

	/**
	 * Tell WordPress which top-level menu to keep open when on the docs page.
	 *
	 * Without this filter, WP sometimes fails to expand the correct CPT parent
	 * when the active page is a custom submenu (not a CPT list/edit screen).
	 *
	 * @param string $parent_file
	 * @return string
	 */
	public static function fix_parent_file( ?string $parent_file ): ?string {
		if ( ( $_GET['page'] ?? '' ) === self::MENU_SLUG ) { // phpcs:ignore WordPress.Security.NonceVerification
			return 'edit.php?post_type=dish_class';
		}
		return $parent_file;
	}

	/**
	 * Tell WordPress which submenu item to highlight when on the docs page.
	 *
	 * @param string $submenu_file
	 * @return string
	 */
	public static function fix_submenu_file( ?string $submenu_file ): ?string {
		if ( ( $_GET['page'] ?? '' ) === self::MENU_SLUG ) { // phpcs:ignore WordPress.Security.NonceVerification
			return self::MENU_SLUG;
		}
		return $submenu_file;
	}

	// -------------------------------------------------------------------------
	// Admin menu
	// -------------------------------------------------------------------------

	public static function register_menu(): void {
		add_submenu_page(
			'edit.php?post_type=dish_class',
			__( 'Documentation', 'dish-events' ),
			__( 'Documentation', 'dish-events' ),
			'manage_options',
			self::MENU_SLUG,
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function maybe_inject_styles( string $hook ): void {
		if ( strpos( $hook, self::MENU_SLUG ) === false ) {
			return;
		}
		add_action( 'admin_head', [ __CLASS__, 'output_styles' ] );
	}

	// -------------------------------------------------------------------------
	// Docs directory helpers
	// -------------------------------------------------------------------------

	private static function docs_base(): string {
		return DISH_EVENTS_PATH . self::DOCS_DIR;
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
	 * "01-class-lifecycle" → "Class Lifecycle"
	 */
	private static function file_title( string $path ): string {
		$name = pathinfo( $path, PATHINFO_FILENAME );
		$name = (string) preg_replace( '/^\d+-/', '', $name );
		return ucwords( str_replace( '-', ' ', $name ) );
	}

	// -------------------------------------------------------------------------
	// Page renderer
	// -------------------------------------------------------------------------

	public static function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
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
		<div class="wrap dish-docs-wrap">

			<nav class="dish-docs-tabs" aria-label="<?php esc_attr_e( 'Documentation sections', 'dish-events' ); ?>">
				<?php foreach ( self::SECTIONS as $slug => $label ) : ?>
				<a
					href="<?php echo esc_url( $tab_urls[ $slug ] ); ?>"
					class="dish-docs-tab <?php echo $slug === $section ? 'is-active' : ''; ?>"
				>
					<?php echo esc_html( $label ); ?>
				</a>
				<?php endforeach; ?>
			</nav>

			<div class="dish-docs-layout">

				<aside class="dish-docs-sidebar">
					<ul class="dish-docs-file-list">
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
								class="dish-docs-file-link <?php echo $is_active ? 'is-active' : ''; ?>"
								<?php echo $is_active ? 'aria-current="page"' : ''; ?>
							>
								<?php echo esc_html( $title ); ?>
							</a>
						</li>
						<?php endforeach; ?>
						<?php if ( empty( $files ) ) : ?>
						<li class="dish-docs-empty">
							<?php esc_html_e( 'No documents in this section yet.', 'dish-events' ); ?>
						</li>
						<?php endif; ?>
					</ul>
				</aside>

				<main class="dish-docs-content" id="dish-docs-main">
					<?php if ( $page_title ) : ?>
					<h1 class="dish-docs-page-title"><?php echo esc_html( $page_title ); ?></h1>
					<?php endif; ?>

					<?php if ( $parsed_html ) : ?>
					<div class="dish-docs-body">
						<?php echo wp_kses_post( $parsed_html ); ?>
					</div>
					<?php else : ?>
					<p class="dish-docs-placeholder">
						<?php esc_html_e( 'Select a document from the sidebar.', 'dish-events' ); ?>
					</p>
					<?php endif; ?>
				</main>

			</div><!-- .dish-docs-layout -->

		</div><!-- .dish-docs-wrap -->
		<?php
	}

	// -------------------------------------------------------------------------
	// Markdown parser
	// -------------------------------------------------------------------------

	/**
	 * Convert Markdown to safe HTML.
	 *
	 * Uses Parsedown from the active theme if available (Basecamp ships it).
	 * Falls back to a simple built-in converter covering headings, bold, italic,
	 * inline code, fenced code blocks, tables, unordered/ordered lists,
	 * blockquotes, horizontal rules, and paragraphs.
	 *
	 * Output is sanitized by wp_kses_post() in the renderer.
	 */
	private static function parse_markdown( string $text ): string {
		if ( $text === '' ) {
			return '';
		}

		$parsedown_path = get_template_directory() . '/inc/admin/lib/Parsedown.php';
		if ( file_exists( $parsedown_path ) ) {
			if ( ! class_exists( 'Parsedown' ) ) {
				require_once $parsedown_path;
			}
			$pd = new \Parsedown();
			$pd->setSafeMode( true );
			$pd->setBreaksEnabled( false );
			return $pd->text( $text );
		}

		// ── Minimal built-in converter ────────────────────────────────────────
		return self::simple_markdown( $text );
	}

	/**
	 * Very small Markdown subset: headings, bold, italic, inline code, fenced
	 * code blocks, tables, lists, blockquotes, hr, paragraphs.
	 */
	private static function simple_markdown( string $text ): string {
		$lines  = explode( "\n", str_replace( "\r\n", "\n", $text ) );
		$html   = '';
		$i      = 0;
		$count  = count( $lines );

		while ( $i < $count ) {
			$line = $lines[ $i ];

			// Fenced code block.
			if ( preg_match( '/^```(\w*)$/', $line, $m ) ) {
				$lang = esc_attr( $m[1] );
				$code = '';
				$i++;
				while ( $i < $count && $lines[ $i ] !== '```' ) {
					$code .= esc_html( $lines[ $i ] ) . "\n";
					$i++;
				}
				$html .= "<pre><code class=\"language-{$lang}\">{$code}</code></pre>\n";
				$i++;
				continue;
			}

			// Horizontal rule.
			if ( preg_match( '/^[-*_]{3,}\s*$/', $line ) ) {
				$html .= "<hr>\n";
				$i++;
				continue;
			}

			// Headings.
			if ( preg_match( '/^(#{1,4})\s+(.+)$/', $line, $m ) ) {
				$level = strlen( $m[1] );
				$html .= "<h{$level}>" . self::inline_md( $m[2] ) . "</h{$level}>\n";
				$i++;
				continue;
			}

			// Blockquote.
			if ( str_starts_with( $line, '> ' ) ) {
				$bq = '';
				while ( $i < $count && str_starts_with( $lines[ $i ], '> ' ) ) {
					$bq .= self::inline_md( substr( $lines[ $i ], 2 ) ) . ' ';
					$i++;
				}
				$html .= '<blockquote><p>' . trim( $bq ) . "</p></blockquote>\n";
				continue;
			}

			// Unordered list.
			if ( preg_match( '/^[-*+]\s+(.+)$/', $line, $m ) ) {
				$html .= "<ul>\n";
				while ( $i < $count && preg_match( '/^[-*+]\s+(.+)$/', $lines[ $i ], $m ) ) {
					$html .= '<li>' . self::inline_md( $m[1] ) . "</li>\n";
					$i++;
				}
				$html .= "</ul>\n";
				continue;
			}

			// Ordered list.
			if ( preg_match( '/^\d+\.\s+(.+)$/', $line, $m ) ) {
				$html .= "<ol>\n";
				while ( $i < $count && preg_match( '/^\d+\.\s+(.+)$/', $lines[ $i ], $m ) ) {
					$html .= '<li>' . self::inline_md( $m[1] ) . "</li>\n";
					$i++;
				}
				$html .= "</ol>\n";
				continue;
			}

			// Table (line contains | and next non-empty line is a separator).
			if ( str_contains( $line, '|' ) && isset( $lines[ $i + 1 ] ) && preg_match( '/^\|?[-| :]+\|/', $lines[ $i + 1 ] ) ) {
				$headers = array_map( 'trim', explode( '|', trim( $line, '| ' ) ) );
				$i += 2; // skip header + separator rows.
				$html .= "<table>\n<thead><tr>";
				foreach ( $headers as $h ) {
					$html .= '<th>' . self::inline_md( $h ) . '</th>';
				}
				$html .= "</tr></thead>\n<tbody>\n";
				while ( $i < $count && str_contains( $lines[ $i ], '|' ) && trim( $lines[ $i ] ) !== '' ) {
					$cells = array_map( 'trim', explode( '|', trim( $lines[ $i ], '| ' ) ) );
					$html .= '<tr>';
					foreach ( $cells as $c ) {
						$html .= '<td>' . self::inline_md( $c ) . '</td>';
					}
					$html .= "</tr>\n";
					$i++;
				}
				$html .= "</tbody></table>\n";
				continue;
			}

			// Blank line.
			if ( trim( $line ) === '' ) {
				$i++;
				continue;
			}

			// Paragraph.
			$para = '';
			while ( $i < $count && trim( $lines[ $i ] ) !== '' && ! preg_match( '/^(#{1,4}|[-*+]|\d+\.|>|```|---|\*\*\*)/', $lines[ $i ] ) ) {
				$para .= $lines[ $i ] . ' ';
				$i++;
			}
			if ( trim( $para ) !== '' ) {
				$html .= '<p>' . self::inline_md( trim( $para ) ) . "</p>\n";
			}
		}

		return $html;
	}

	/** Apply inline Markdown: bold, italic, inline code, links. */
	private static function inline_md( string $text ): string {
		// Inline code (before bold/italic so backticks aren't processed further).
		$text = preg_replace( '/`([^`]+)`/', '<code>$1</code>', $text ) ?? $text;
		// Bold.
		$text = preg_replace( '/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text ) ?? $text;
		// Italic.
		$text = preg_replace( '/\*(.+?)\*/', '<em>$1</em>', $text ) ?? $text;
		// Links.
		$text = preg_replace( '/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2">$1</a>', $text ) ?? $text;
		return $text;
	}

	// -------------------------------------------------------------------------
	// Styles
	// -------------------------------------------------------------------------

	public static function output_styles(): void {
		?>
		<style>
		/* ── Dish Events Docs Viewer ─────────────────────────────────────────── */
		.dish-docs-wrap { margin: 0; padding: 0; }

		.dish-docs-tabs {
			display: flex;
			border-bottom: 1px solid #ddd;
			margin: 0 0 0 -20px;
			padding: 16px 20px 0;
			background: #fff;
		}
		.dish-docs-tab {
			padding: 9px 22px;
			font-size: 13px;
			font-weight: 600;
			text-decoration: none;
			color: #646970;
			border-bottom: 3px solid transparent;
		}
		.dish-docs-tab:hover   { color: #1d2327; }
		.dish-docs-tab.is-active { color: #1d2327; border-bottom-color: #2271b1; }

		.dish-docs-layout {
			display: grid;
			grid-template-columns: 220px 1fr;
			min-height: calc(100vh - 100px);
			background: #fff;
			border: 1px solid #ddd;
			border-top: none;
			margin-left: -20px;
		}

		.dish-docs-sidebar { border-right: 1px solid #ddd; background: #f9f9f9; padding: 8px 0; }
		.dish-docs-file-list { list-style: none; margin: 0; padding: 0; }
		.dish-docs-file-link {
			display: block;
			padding: 9px 16px;
			font-size: 13px;
			text-decoration: none;
			color: #3c434a;
			border-left: 3px solid transparent;
		}
		.dish-docs-file-link:hover  { background: #f0f0f0; color: #1d2327; }
		.dish-docs-file-link.is-active {
			background: #f0f4f9;
			color: #2271b1;
			border-left-color: #2271b1;
			font-weight: 600;
		}
		.dish-docs-empty { padding: 12px 16px; font-size: 13px; color: #8c8f94; }

		.dish-docs-content { padding: 32px 48px; max-width: 920px; }
		.dish-docs-page-title {
			font-size: 22px;
			font-weight: 700;
			margin: 0 0 24px;
			padding-bottom: 14px;
			border-bottom: 1px solid #eee;
			color: #1d2327;
		}

		.dish-docs-body h1, .dish-docs-body h2,
		.dish-docs-body h3, .dish-docs-body h4 { margin: 1.8em 0 0.5em; font-weight: 700; color: #1d2327; }
		.dish-docs-body h1 { font-size: 20px; margin-top: 0; }
		.dish-docs-body h2 { font-size: 17px; border-bottom: 1px solid #f0f0f0; padding-bottom: 6px; }
		.dish-docs-body h3 { font-size: 15px; }
		.dish-docs-body h4 { font-size: 12px; text-transform: uppercase; letter-spacing: 0.06em; color: #646970; }
		.dish-docs-body p  { margin: 0 0 1em; line-height: 1.75; color: #3c434a; font-size: 14px; }
		.dish-docs-body ul, .dish-docs-body ol { margin: 0 0 1em 1.4em; padding: 0; }
		.dish-docs-body li { margin-bottom: 0.35em; font-size: 14px; color: #3c434a; line-height: 1.65; }
		.dish-docs-body hr { border: none; border-top: 1px solid #eee; margin: 2em 0; }
		.dish-docs-body a  { color: #2271b1; text-underline-offset: 2px; }
		.dish-docs-body a:hover { color: #135e96; }

		.dish-docs-body blockquote {
			margin: 1em 0;
			padding: 10px 16px;
			border-left: 4px solid #2271b1;
			background: #f0f4f9;
			border-radius: 0 4px 4px 0;
		}
		.dish-docs-body blockquote p { margin: 0; color: #2271b1; }

		.dish-docs-body code {
			font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace;
			font-size: 12px;
			background: #f6f7f7;
			padding: 1px 5px;
			border-radius: 3px;
			color: #c7254e;
		}
		.dish-docs-body pre {
			background: #1d2327;
			color: #e0e0e0;
			padding: 18px 22px;
			border-radius: 4px;
			overflow-x: auto;
			margin: 0 0 1.4em;
			line-height: 1.65;
		}
		.dish-docs-body pre code { background: none; color: inherit; padding: 0; border-radius: 0; font-size: 12px; }

		.dish-docs-body table { border-collapse: collapse; width: 100%; margin-bottom: 1.4em; font-size: 13px; }
		.dish-docs-body th { background: #f6f7f7; font-weight: 600; text-align: left; padding: 8px 12px; border: 1px solid #ddd; }
		.dish-docs-body td { padding: 7px 12px; border: 1px solid #ddd; color: #3c434a; }
		.dish-docs-body tr:nth-child(even) td { background: #fafafa; }
		</style>
		<?php
	}
}
