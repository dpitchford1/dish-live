<?php
/*
 * Template Name: Style Guide
 * Author: Kaneism
 * 
 * This page is for developers to test all of your styles.
 * Create a new WordPress page and use this page template. 
 * 
 * Everything is hardcoded in here to avoid crazy WP formatting
 * stuffs.
 * 
 *
*/
?>
<?php
use Dish\Events\Data\ChefRepository;
use Dish\Events\Data\ClassRepository;
use Dish\Events\Frontend\Frontend;
?>

<?php get_header(); ?>

<?php if ( have_posts() ) : the_post(); ?>

<?php /* ── Hero ─────────────────────────────────────────── */ ?>
<?php if ( has_post_thumbnail() ) : ?>
<div class="hero has--feature-content">
    <?php Basecamp_Frontend::picture( get_post_thumbnail_id(), [
        'landscape_size' => 'basecamp-img-xl',
        'loading'        => 'eager',
        'fetchpriority'  => 'high',
        'img_class'      => 'hero-img size-basecamp-img-xl',
    ] ); ?>
    <div class="hero-feature--content">
        <h1 class="hero-title"><?php the_title(); ?></h1>
        <?php if ( has_excerpt() ) : ?>
            <p class="hero-excerpt"><?php the_excerpt(); ?></p>
        <?php endif; ?>
        <div class="hero-buttons">
            <a href="/classes/calendar/" class="button button--primary"><?php esc_html_e( 'View Calendar', 'dish-events' ); ?></a>
            <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button button--primary"><?php esc_html_e( 'Class Formats', 'dish-events' ); ?></a>
        </div>
    </div>
</div>
<?php endif; ?>


<?php endif; ?><?php /* ── end while have_posts */ ?>

<div class="fluid has--aside">
<main id="main-content" class="main--content">

    <h1 class="page-title"><?php the_title(); ?></h1>

    <div class="sg--content-wrapper">

        <p class="intro">A living reference for all Dish Events components and CSS helpers. Each entry shows a live render alongside the code to produce it and all available options &mdash; no digging through docs required.</p>

        <hr />

        <!-- ── 1. Chef Cards ──────────────────────────────────────────────── -->
        <div class="sg--component content-region">
            <header class="sg--component__header">
                <h2 class="sg--component__title">Chef Cards Grid</h2>
                <p>Displays published chefs in a responsive card grid. Each card shows photo, name, role, excerpt, and a profile link. Rendered via <code>chefs/card.php</code>.</p>
            </header>

            <?php $chefs = ChefRepository::query( [ 'exclude_team' => true ] ); ?>
            <?php if ( ! empty( $chefs ) ) : ?>
            <div class="sg--component__demo">
                <section class="dish-home-section dish-home-chefs">
                    <h2 class="dish-home-section__heading"><?php esc_html_e( 'Meet the Chefs', 'dish-events' ); ?></h2>
                    <div class="grid-general grid--4col">
                        <?php foreach ( $chefs as $chef ) : ?>
                            <?php include Frontend::locate( 'chefs/card.php' ); ?>
                        <?php endforeach; ?>
                    </div>
                    <p class="dish-home-section__more">
                        <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_chef' ) ); ?>" class="button">
                            <?php esc_html_e( 'Meet All Chefs', 'dish-events' ); ?>
                        </a>
                    </p>
                </section>
            </div>
            <?php endif; ?>

            <details class="sg--component__code">
                <summary>Code &amp; Options</summary>
                <pre><code><?php echo htmlspecialchars( <<<'CODE'
use Dish\Events\Data\ChefRepository;
use Dish\Events\Frontend\Frontend;

$chefs = ChefRepository::query( [
    'exclude_team' => true,       // bool   — exclude team members (chefs only)
    // 'team_only'  => true,      // bool   — show team members only
    // 'limit'      => 4,         // int    — max results; default -1 (all)
    // 'orderby'    => 'title',   // string — default 'title'
    // 'order'      => 'ASC',     // string — 'ASC' | 'DESC'
    // 'status'     => 'publish', // string — default 'publish'
] );

if ( ! empty( $chefs ) ) : ?>
<div class="grid-general grid--4col">
    <?php foreach ( $chefs as $chef ) : ?>
        <?php include Frontend::locate( 'chefs/card.php' ); ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
CODE
                ); ?></code></pre>
            </details>
        </div>

        <hr />

        <!-- ── 2. Format Cards ────────────────────────────────────────────── -->
        <div class="sg--component content-region">
            <header class="sg--component__header">
                <h2 class="sg--component__title">Format Cards Grid</h2>
                <p>Displays class formats in a card grid. Each card shows the format image, title, excerpt, and a link to the format page. Rendered via <code>formats/card.php</code>.</p>
            </header>

            <?php
            $formats = get_posts( [
                'post_type'      => 'dish_format',
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'orderby'        => 'menu_order',
                'order'          => 'ASC',
            ] );
            ?>
            <?php if ( ! empty( $formats ) ) : ?>
            <div class="sg--component__demo">
                <section class="dish-home-section dish-home-formats">
                    <h2 class="dish-home-section__heading"><?php esc_html_e( 'Browse by Format', 'dish-events' ); ?></h2>
                    <div class="grid-general grid--3col">
                        <?php foreach ( $formats as $format ) : ?>
                            <?php include Frontend::locate( 'formats/card.php' ); ?>
                        <?php endforeach; ?>
                    </div>
                    <p class="dish-home-section__more">
                        <a href="<?php echo esc_url( get_post_type_archive_link( 'dish_format' ) ); ?>" class="button">
                            <?php esc_html_e( 'View All Formats', 'dish-events' ); ?>
                        </a>
                    </p>
                </section>
            </div>
            <?php endif; ?>

            <details class="sg--component__code">
                <summary>Code &amp; Options</summary>
                <pre><code><?php echo htmlspecialchars( <<<'CODE'
use Dish\Events\Frontend\Frontend;

$formats = get_posts( [
    'post_type'      => 'dish_format',
    'post_status'    => 'publish',
    'posts_per_page' => -1,            // int — cap results, or -1 for all
    'orderby'        => 'menu_order',  // drag-drop order set in WP admin
    'order'          => 'ASC',
] );

if ( ! empty( $formats ) ) : ?>
<div class="grid-general grid--3col">
    <?php foreach ( $formats as $format ) : ?>
        <?php include Frontend::locate( 'formats/card.php' ); ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
CODE
                ); ?></code></pre>
            </details>
        </div>

        <hr />

        <!-- ── 3. Upcoming Classes ───────────────────────────────────────── -->
        <div class="sg--component content-region">
            <header class="sg--component__header">
                <h2 class="sg--component__title">Upcoming Classes Grid</h2>
                <p>Displays upcoming class instances ordered by start date. Each card shows date, title, price, spots remaining, and a booking link. Rendered via <code>classes/card.php</code>.</p>
            </header>

            <?php $upcoming = ClassRepository::get_upcoming( 6 ); ?>
            <?php if ( ! empty( $upcoming ) ) : ?>
            <div class="sg--component__demo">
                <section class="dish-home-section dish-home-upcoming">
                    <h2 class="dish-home-section__heading"><?php esc_html_e( 'Upcoming Classes', 'dish-events' ); ?></h2>
                    <div class="grid-general grid--3col">
                        <?php foreach ( $upcoming as $class ) : ?>
                            <?php include Frontend::locate( 'classes/card.php' ); ?>
                        <?php endforeach; ?>
                    </div>
                </section>
            </div>
            <?php else : ?>
            <p class="sg--component__empty"><em>No upcoming classes found.</em></p>
            <?php endif; ?>

            <details class="sg--component__code">
                <summary>Code &amp; Options</summary>
                <pre><code><?php echo htmlspecialchars( <<<'CODE'
use Dish\Events\Data\ClassRepository;
use Dish\Events\Frontend\Frontend;

// Simple: next N upcoming public classes ordered by date.
$upcoming = ClassRepository::get_upcoming( 6 ); // int $limit, default 10

// Full query — all args optional:
$upcoming = ClassRepository::query( [
    'limit'        => 6,                        // int    — max results; default -1 (all)
    'start_after'  => time(),                   // int    — UTC timestamp lower bound
    'start_before' => strtotime( '+30 days' ),  // int    — UTC timestamp upper bound
    'template_id'  => 42,                       // int    — single template only
    'template_ids' => [42, 43],                 // int[]  — multiple templates
    'is_private'   => false,                    // bool   — true = private/corporate only
    'order'        => 'ASC',                    // string — 'ASC' | 'DESC'
    'status'       => 'publish',                // string — default 'publish'
    'offset'       => 0,                        // int    — for pagination
] );

if ( ! empty( $upcoming ) ) : ?>
<div class="grid-general grid--3col">
    <?php foreach ( $upcoming as $class ) : ?>
        <?php include Frontend::locate( 'classes/card.php' ); ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>
CODE
                ); ?></code></pre>
            </details>
        </div>

        <hr />

        <!-- ── 4. Shortcodes ─────────────────────────────────────────────── -->
        <div class="sg--component content-region">
            <header class="sg--component__header">
                <h2 class="sg--component__title">Shortcodes</h2>
                <p>Drop these into any WP page content area, or call <code>do_shortcode()</code> from a template. Each renders its full archive grid with no arguments required.</p>
            </header>

            <details class="sg--component__code" open>
                <summary>All available shortcodes</summary>
                <pre><code><?php echo htmlspecialchars( <<<'CODE'
[dish_classes]         — upcoming class instances archive grid
[dish_chefs]           — chefs archive grid
[dish_formats]         — class formats archive grid
[dish_upcoming_menus]  — upcoming menus listing

[dish_login]           — login form (redirects if already logged in)
[dish_register]        — registration form
[dish_profile]         — logged-in user profile & booking history
CODE
                ); ?></code></pre>
            </details>
        </div>

        <hr />

        <!-- ── 5. Grid helpers ───────────────────────────────────────────── -->
        <div class="sg--component content-region">
            <header class="sg--component__header">
                <h2 class="sg--component__title">Grid CSS Helpers</h2>
                <p>All grids use the <code>grid-general</code> base class with a column-count modifier. Columns collapse responsively at small viewports.</p>
            </header>

            <details class="sg--component__code" open>
                <summary>Column modifiers</summary>
                <pre><code><?php echo htmlspecialchars( <<<'CODE'
<div class="grid-general grid--2col"> ... </div>
<div class="grid-general grid--3col"> ... </div>
<div class="grid-general grid--4col"> ... </div>
CODE
                ); ?></code></pre>
            </details>
        </div>

    </div>

	<div id="post-<?php the_ID(); ?>" <?php post_class( '' ); ?>>

        <section class="content-region">
            <p class="intro">This is an intro paragraph. Here is a test page with a myriad of HTML elements. We are using this to test all of our theme's styles. As far as I can tell it uses almost every HTML element known to humankind. If you find a better HTML elements sample page, let us know.</p>
            <h1 class="other-class">First Header h1</h1>
            <p class="test-class">
                At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis
                praesentium voluptatum deleniti atque corrupti quos.
            </p>
            <h2>Second header h2</h2>
            <p class="test-class">
                Lorem ipsum dolor sit amet, consectetur adipisicing elit, sed do eiusmod
                tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam.
            </p>
            <h3>Third header h3</h3>
            <p class="test-class">
                At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis.
            </p>
            <h4>Fourth header h4</h4>
            <p class="test-class">
                Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet,
                consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt.
            </p>
            <h5>Fifth header h5</h5>
            <p class="test-class">
                Sed ut perspiciatis unde omnis iste natus error sit voluptatem accusantium
                doloremque laudantium, totam rem aperiam, eaque ipsa quae ab illo inventore.
            </p>
            <h6>Sixth header h6</h6>
            <p class="test-class">
                At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis
                praesentium voluptatum deleniti atque corrupti quos.
            </p>
        </section>

        <hr />

        <section class="content-region">
            <h2 class="heading">Lists</h2>
            <article class="grid-general grid--4col">
                <div>
                    <h3>Unordered list</h3>
                    <ul>
                        <li>Orange</li>
                        <li>Apple</li>
                        <li>Rhubarb</li>
                        <li>Rasberry</li>
                        <li>Blueberry</li>
                        <li>Cherry</li>
                    </ul>
                </div>
                <div>
                    <h3>Ordered list</h3>
                    <ol>
                        <li>First</li>
                        <li>Second</li>
                        <li>Third</li>
                        <li>Fourth</li>
                        <li>Fifth</li>
                        <li>Sixth</li>
                    </ol>
                </div>
                <div>
                    <h3>Definition list</h3>
                    <dl>
                        <dt>Kick</dt>
                        <dd>808</dd>
                        <dt>Snare</dt>
                        <dd>909</dd>
                    </dl>
                    <dl>
                        <dt> Maine </dt>
                        <dd> Augusta </dd>
                        <dt> California </dt>
                        <dd> Sacremento </dd>
                        <dt> Oregon </dt>
                        <dd> Salem </dd>
                        <dt> New York </dt>
                        <dd> Albany </dd>
                    </dl>
                </div>
                <div>
                    <h3>Details and Summary</h3>
                    <details name="faq" >
                        <summary>FAQ 1</summary>
                        <p>Can you smell that?</p>
                    </details>

                    <details name="faq" >
                        <summary>FAQ 2</summary>
                        <p>Something really stinks.</p>
                    </details>

                    <details name="faq" >
                        <summary>FAQ 3</summary>
                        <p>Oh, it's you. 🙂</p>
                    </details>
                </div>
            </article>
        </section>

        <hr />

        <section class="content-region">
            <h2 class="other-class">Dialog with a form</h2>
            <dialog>
                <p>This dialog has entry and exit animations.</p>
                <form method="dialog">
                <button>OK</button>
                </form>
            </dialog>
            <p><button onclick="document.querySelector('dialog').showModal()">Open Dialog</button></p>
        </section>

        <hr />

        <section class="content-region">
            <h2 class="other-class">Forms</h2>
            <form class="general-form" action="#" novalidate autofill="off">
                <fieldset class="fieldset">
                    <legend>Legend Example</legend>
                    <div class="form--row">
                        <label class="form--label" for="sg-search">Search</label>
                        <input class="with-description" type="search" placeholder="Search" id="sg-search" name="sg-search">
                        <p class="form--helper">Helper text if necessary.</p>
                    </div>
                    <div class="form--row">
                        <label class="form--label" for="sg-text">Text Input Label</label>
                        <input class="with-description" type="text" placeholder="Type Something..." id="sg-text" name="sg-text">
                        <p class="form--helper">Helper text if necessary.</p>
                    </div>
                    <div class="form--row">
                        <label class="form--label" for="sg-password">Password <span class="required" aria-hidden="true">*</span></label>
                        <input class="with-description" type="password" id="sg-password" name="sg-password" autocomplete="current-password" required aria-required="true">
                        <p class="form--error" role="alert">Error message when appropriate.</p>
                    </div>
                    <div class="form--row">
                        <label class="form--label" for="sg-first-name">First Name</label>
                        <input type="text" id="sg-first-name" name="sg-first-name" autocomplete="given-name">
                    </div>
                    <div class="form--row">
                        <label class="form--label" for="sg-last-name">Last Name</label>
                        <input type="text" id="sg-last-name" name="sg-last-name" autocomplete="family-name">
                    </div>
                    <div class="form--row">
                        <label class="form--label" for="sg-email">Email</label>
                        <input type="email" id="sg-email" name="sg-email" autocomplete="email">
                    </div>
                    <div class="form--row">
                        <label class="form--label" for="sg-dropdown">Dropdown</label>
                        <select id="sg-dropdown" name="sg-dropdown">
                            <option value="">Select an option</option>
                            <option value="1">Option 1</option>
                            <option value="2">Option 2</option>
                            <option value="3">Option 3</option>
                        </select>
                    </div>
                    <div class="form--row">
                        <fieldset>
                            <legend>Radio Buttons</legend>
                            <ul class="radio-button--list">
                                <li><label><input type="radio" name="sg-radio" value="1"> Label 1</label></li>
                                <li><label><input type="radio" name="sg-radio" value="2"> Label 2</label></li>
                                <li><label><input type="radio" name="sg-radio" value="3"> Label 3</label></li>
                            </ul>
                        </fieldset>
                    </div>
                    <div class="form--row">
                        <label class="form--label" for="sg-url">URL Input</label>
                        <input type="url" id="sg-url" name="sg-url" placeholder="https://example.com">
                    </div>
                    <div class="form--row">
                        <label class="form--label" for="sg-textarea">Text Area</label>
                        <textarea id="sg-textarea" name="sg-textarea" rows="4"></textarea>
                    </div>
                    <div class="form--row">
                        <label class="form--label"><input type="checkbox" name="sg-checkbox" value="1"> This is a checkbox.</label>
                    </div>
                    <div class="form--row">
                        <button type="submit" class="button button--primary">Submit</button>
                        <button type="reset" class="button button--secondary">Reset</button>
                        <button type="button" class="button button--tertiary">Button</button>
                    </div>
                </fieldset>
            </form>
        </section>

        <hr />

        <section class="content-region">
            <h2 class="other-class">Buttons</h2>
            <button>Regular Button</button>
            <button class="purple-btn">Purple Button</button>
            <button>Large Blue Button</button>
        </section>

        <hr />

        <section class="content-region">
            <h2 class="other-class">Icons</h2>
            <ul class="is--flex-list icon--list">
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-octagon-fill" viewBox="0 0 16 16">
  <path d="M11.46.146A.5.5 0 0 0 11.107 0H4.893a.5.5 0 0 0-.353.146L.146 4.54A.5.5 0 0 0 0 4.893v6.214a.5.5 0 0 0 .146.353l4.394 4.394a.5.5 0 0 0 .353.146h6.214a.5.5 0 0 0 .353-.146l4.394-4.394a.5.5 0 0 0 .146-.353V4.893a.5.5 0 0 0-.146-.353zm-6.106 4.5L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 1 1 .708-.708"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-bag-check" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M10.854 8.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 0 1 .708-.708L7.5 10.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
  <path d="M8 1a2.5 2.5 0 0 1 2.5 2.5V4h-5v-.5A2.5 2.5 0 0 1 8 1m3.5 3v-.5a3.5 3.5 0 1 0-7 0V4H1v10a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V4zM2 5h12v9a1 1 0 0 1-1 1H3a1 1 0 0 1-1-1z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar" viewBox="0 0 16 16">
  <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-calendar-check" viewBox="0 0 16 16">
  <path d="M10.854 7.146a.5.5 0 0 1 0 .708l-3 3a.5.5 0 0 1-.708 0l-1.5-1.5a.5.5 0 1 1 .708-.708L7.5 9.793l2.646-2.647a.5.5 0 0 1 .708 0"/>
  <path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5M1 4v10a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V4z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-card-list" viewBox="0 0 16 16">
  <path d="M14.5 3a.5.5 0 0 1 .5.5v9a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-9a.5.5 0 0 1 .5-.5zm-13-1A1.5 1.5 0 0 0 0 3.5v9A1.5 1.5 0 0 0 1.5 14h13a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 14.5 2z"/>
  <path d="M5 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 5 8m0-2.5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m0 5a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7a.5.5 0 0 1-.5-.5m-1-5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0M4 8a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0m0 2.5a.5.5 0 1 1-1 0 .5.5 0 0 1 1 0"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-check-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="m10.97 4.97-.02.022-3.473 4.425-2.093-2.094a.75.75 0 0 0-1.06 1.06L6.97 11.03a.75.75 0 0 0 1.079-.02l3.992-4.99a.75.75 0 0 0-1.071-1.05"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock" viewBox="0 0 16 16">
  <path d="M8 3.5a.5.5 0 0 0-1 0V9a.5.5 0 0 0 .252.434l3.5 2a.5.5 0 0 0 .496-.868L8 8.71z"/>
  <path d="M8 16A8 8 0 1 0 8 0a8 8 0 0 0 0 16m7-8A7 7 0 1 1 1 8a7 7 0 0 1 14 0"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-clock-history" viewBox="0 0 16 16">
  <path d="M8.515 1.019A7 7 0 0 0 8 1V0a8 8 0 0 1 .589.022zm2.004.45a7 7 0 0 0-.985-.299l.219-.976q.576.129 1.126.342zm1.37.71a7 7 0 0 0-.439-.27l.493-.87a8 8 0 0 1 .979.654l-.615.789a7 7 0 0 0-.418-.302zm1.834 1.79a7 7 0 0 0-.653-.796l.724-.69q.406.429.747.91zm.744 1.352a7 7 0 0 0-.214-.468l.893-.45a8 8 0 0 1 .45 1.088l-.95.313a7 7 0 0 0-.179-.483m.53 2.507a7 7 0 0 0-.1-1.025l.985-.17q.1.58.116 1.17zm-.131 1.538q.05-.254.081-.51l.993.123a8 8 0 0 1-.23 1.155l-.964-.267q.069-.247.12-.501m-.952 2.379q.276-.436.486-.908l.914.405q-.24.54-.555 1.038zm-.964 1.205q.183-.183.35-.378l.758.653a8 8 0 0 1-.401.432z"/>
  <path d="M8 1a7 7 0 1 0 4.95 11.95l.707.707A8.001 8.001 0 1 1 8 0z"/>
  <path d="M7.5 3a.5.5 0 0 1 .5.5v5.21l3.248 1.856a.5.5 0 0 1-.496.868l-3.5-2A.5.5 0 0 1 7 9V3.5a.5.5 0 0 1 .5-.5"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-cup-hot" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M.5 6a.5.5 0 0 0-.488.608l1.652 7.434A2.5 2.5 0 0 0 4.104 16h5.792a2.5 2.5 0 0 0 2.44-1.958l.131-.59a3 3 0 0 0 1.3-5.854l.221-.99A.5.5 0 0 0 13.5 6zM13 12.5a2 2 0 0 1-.316-.025l.867-3.898A2.001 2.001 0 0 1 13 12.5M2.64 13.825 1.123 7h11.754l-1.517 6.825A1.5 1.5 0 0 1 9.896 15H4.104a1.5 1.5 0 0 1-1.464-1.175"/>
  <path d="m4.4.8-.003.004-.014.019a4 4 0 0 0-.204.31 2 2 0 0 0-.141.267c-.026.06-.034.092-.037.103v.004a.6.6 0 0 0 .091.248c.075.133.178.272.308.445l.01.012c.118.158.26.347.37.543.112.2.22.455.22.745 0 .188-.065.368-.119.494a3 3 0 0 1-.202.388 5 5 0 0 1-.253.382l-.018.025-.005.008-.002.002A.5.5 0 0 1 3.6 4.2l.003-.004.014-.019a4 4 0 0 0 .204-.31 2 2 0 0 0 .141-.267c.026-.06.034-.092.037-.103a.6.6 0 0 0-.09-.252A4 4 0 0 0 3.6 2.8l-.01-.012a5 5 0 0 1-.37-.543A1.53 1.53 0 0 1 3 1.5c0-.188.065-.368.119-.494.059-.138.134-.274.202-.388a6 6 0 0 1 .253-.382l.025-.035A.5.5 0 0 1 4.4.8m3 0-.003.004-.014.019a4 4 0 0 0-.204.31 2 2 0 0 0-.141.267c-.026.06-.034.092-.037.103v.004a.6.6 0 0 0 .091.248c.075.133.178.272.308.445l.01.012c.118.158.26.347.37.543.112.2.22.455.22.745 0 .188-.065.368-.119.494a3 3 0 0 1-.202.388 5 5 0 0 1-.253.382l-.018.025-.005.008-.002.002A.5.5 0 0 1 6.6 4.2l.003-.004.014-.019a4 4 0 0 0 .204-.31 2 2 0 0 0 .141-.267c.026-.06.034-.092.037-.103a.6.6 0 0 0-.09-.252A4 4 0 0 0 6.6 2.8l-.01-.012a5 5 0 0 1-.37-.543A1.53 1.53 0 0 1 6 1.5c0-.188.065-.368.119-.494.059-.138.134-.274.202-.388a6 6 0 0 1 .253-.382l.025-.035A.5.5 0 0 1 7.4.8m3 0-.003.004-.014.019a4 4 0 0 0-.204.31 2 2 0 0 0-.141.267c-.026.06-.034.092-.037.103v.004a.6.6 0 0 0 .091.248c.075.133.178.272.308.445l.01.012c.118.158.26.347.37.543.112.2.22.455.22.745 0 .188-.065.368-.119.494a3 3 0 0 1-.202.388 5 5 0 0 1-.252.382l-.019.025-.005.008-.002.002A.5.5 0 0 1 9.6 4.2l.003-.004.014-.019a4 4 0 0 0 .204-.31 2 2 0 0 0 .141-.267c.026-.06.034-.092.037-.103a.6.6 0 0 0-.09-.252A4 4 0 0 0 9.6 2.8l-.01-.012a5 5 0 0 1-.37-.543A1.53 1.53 0 0 1 9 1.5c0-.188.065-.368.119-.494.059-.138.134-.274.202-.388a6 6 0 0 1 .253-.382l.025-.035A.5.5 0 0 1 10.4.8"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-currency-dollar" viewBox="0 0 16 16">
  <path d="M4 10.781c.148 1.667 1.513 2.85 3.591 3.003V15h1.043v-1.216c2.27-.179 3.678-1.438 3.678-3.3 0-1.59-.947-2.51-2.956-3.028l-.722-.187V3.467c1.122.11 1.879.714 2.07 1.616h1.47c-.166-1.6-1.54-2.748-3.54-2.875V1H7.591v1.233c-1.939.23-3.27 1.472-3.27 3.156 0 1.454.966 2.483 2.661 2.917l.61.162v4.031c-1.149-.17-1.94-.8-2.131-1.718zm3.391-3.836c-1.043-.263-1.6-.825-1.6-1.616 0-.944.704-1.641 1.8-1.828v3.495l-.2-.05zm1.591 1.872c1.287.323 1.852.859 1.852 1.769 0 1.097-.826 1.828-2.2 1.939V8.73z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-dash-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="M4 8a.5.5 0 0 1 .5-.5h7a.5.5 0 0 1 0 1h-7A.5.5 0 0 1 4 8"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-envelope" viewBox="0 0 16 16">
  <path d="M0 4a2 2 0 0 1 2-2h12a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v.217l7 4.2 7-4.2V4a1 1 0 0 0-1-1zm13 2.383-4.708 2.825L15 11.105zm-.034 6.876-5.64-3.471L8 9.583l-1.326-.795-5.64 3.47A1 1 0 0 0 2 13h12a1 1 0 0 0 .966-.741M1 11.105l4.708-2.897L1 5.383z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-exclamation-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="M7.002 11a1 1 0 1 1 2 0 1 1 0 0 1-2 0M7.1 4.995a.905.905 0 1 1 1.8 0l-.35 3.507a.552.552 0 0 1-1.1 0z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-file-ruled" viewBox="0 0 16 16">
  <path d="M2 2a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2zm2-1a1 1 0 0 0-1 1v4h10V2a1 1 0 0 0-1-1zm9 6H6v2h7zm0 3H6v2h7zm0 3H6v2h6a1 1 0 0 0 1-1zm-8 2v-2H3v1a1 1 0 0 0 1 1zm-2-3h2v-2H3zm0-3h2V7H3z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-fire" viewBox="0 0 16 16">
  <path d="M8 16c3.314 0 6-2 6-5.5 0-1.5-.5-4-2.5-6 .25 1.5-1.25 2-1.25 2C11 4 9 .5 6 0c.357 2 .5 4-2 6-1.25 1-2 2.729-2 4.5C2 14 4.686 16 8 16m0-1c-1.657 0-3-1-3-2.75 0-.75.25-2 1.25-3C6.125 10 7 10.5 7 10.5c-.375-1.25.5-3.25 2-3.5-.179 1-.25 2 1 3 .625.5 1 1.364 1 2.25C11 14 9.657 15 8 15"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-fork-knife" viewBox="0 0 16 16">
  <path d="M13 .5c0-.276-.226-.506-.498-.465-1.703.257-2.94 2.012-3 8.462a.5.5 0 0 0 .498.5c.56.01 1 .13 1 1.003v5.5a.5.5 0 0 0 .5.5h1a.5.5 0 0 0 .5-.5zM4.25 0a.25.25 0 0 1 .25.25v5.122a.128.128 0 0 0 .256.006l.233-5.14A.25.25 0 0 1 5.24 0h.522a.25.25 0 0 1 .25.238l.233 5.14a.128.128 0 0 0 .256-.006V.25A.25.25 0 0 1 6.75 0h.29a.5.5 0 0 1 .498.458l.423 5.07a1.69 1.69 0 0 1-1.059 1.711l-.053.022a.92.92 0 0 0-.58.884L6.47 15a.971.971 0 1 1-1.942 0l.202-6.855a.92.92 0 0 0-.58-.884l-.053-.022a1.69 1.69 0 0 1-1.059-1.712L3.462.458A.5.5 0 0 1 3.96 0z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gear" viewBox="0 0 16 16">
  <path d="M8 4.754a3.246 3.246 0 1 0 0 6.492 3.246 3.246 0 0 0 0-6.492M5.754 8a2.246 2.246 0 1 1 4.492 0 2.246 2.246 0 0 1-4.492 0"/>
  <path d="M9.796 1.343c-.527-1.79-3.065-1.79-3.592 0l-.094.319a.873.873 0 0 1-1.255.52l-.292-.16c-1.64-.892-3.433.902-2.54 2.541l.159.292a.873.873 0 0 1-.52 1.255l-.319.094c-1.79.527-1.79 3.065 0 3.592l.319.094a.873.873 0 0 1 .52 1.255l-.16.292c-.892 1.64.901 3.434 2.541 2.54l.292-.159a.873.873 0 0 1 1.255.52l.094.319c.527 1.79 3.065 1.79 3.592 0l.094-.319a.873.873 0 0 1 1.255-.52l.292.16c1.64.893 3.434-.902 2.54-2.541l-.159-.292a.873.873 0 0 1 .52-1.255l.319-.094c1.79-.527 1.79-3.065 0-3.592l-.319-.094a.873.873 0 0 1-.52-1.255l.16-.292c.893-1.64-.902-3.433-2.541-2.54l-.292.159a.873.873 0 0 1-1.255-.52zm-2.633.283c.246-.835 1.428-.835 1.674 0l.094.319a1.873 1.873 0 0 0 2.693 1.115l.291-.16c.764-.415 1.6.42 1.184 1.185l-.159.292a1.873 1.873 0 0 0 1.116 2.692l.318.094c.835.246.835 1.428 0 1.674l-.319.094a1.873 1.873 0 0 0-1.115 2.693l.16.291c.415.764-.42 1.6-1.185 1.184l-.291-.159a1.873 1.873 0 0 0-2.693 1.116l-.094.318c-.246.835-1.428.835-1.674 0l-.094-.319a1.873 1.873 0 0 0-2.692-1.115l-.292.16c-.764.415-1.6-.42-1.184-1.185l.159-.291A1.873 1.873 0 0 0 1.945 8.93l-.319-.094c-.835-.246-.835-1.428 0-1.674l.319-.094A1.873 1.873 0 0 0 3.06 4.377l-.16-.292c-.415-.764.42-1.6 1.185-1.184l.292.159a1.873 1.873 0 0 0 2.692-1.115z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-geo-alt" viewBox="0 0 16 16">
  <path d="M12.166 8.94c-.524 1.062-1.234 2.12-1.96 3.07A32 32 0 0 1 8 14.58a32 32 0 0 1-2.206-2.57c-.726-.95-1.436-2.008-1.96-3.07C3.304 7.867 3 6.862 3 6a5 5 0 0 1 10 0c0 .862-.305 1.867-.834 2.94M8 16s6-5.686 6-10A6 6 0 0 0 2 6c0 4.314 6 10 6 10"/>
  <path d="M8 8a2 2 0 1 1 0-4 2 2 0 0 1 0 4m0 1a3 3 0 1 0 0-6 3 3 0 0 0 0 6"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-gift" viewBox="0 0 16 16">
  <path d="M3 2.5a2.5 2.5 0 0 1 5 0 2.5 2.5 0 0 1 5 0v.006c0 .07 0 .27-.038.494H15a1 1 0 0 1 1 1v2a1 1 0 0 1-1 1v7.5a1.5 1.5 0 0 1-1.5 1.5h-11A1.5 1.5 0 0 1 1 14.5V7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1h2.038A3 3 0 0 1 3 2.506zm1.068.5H7v-.5a1.5 1.5 0 1 0-3 0c0 .085.002.274.045.43zM9 3h2.932l.023-.07c.043-.156.045-.345.045-.43a1.5 1.5 0 0 0-3 0zM1 4v2h6V4zm8 0v2h6V4zm5 3H9v8h4.5a.5.5 0 0 0 .5-.5zm-7 8V7H2v7.5a.5.5 0 0 0 .5.5z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-hourglass-split" viewBox="0 0 16 16">
  <path d="M2.5 15a.5.5 0 1 1 0-1h1v-1a4.5 4.5 0 0 1 2.557-4.06c.29-.139.443-.377.443-.59v-.7c0-.213-.154-.451-.443-.59A4.5 4.5 0 0 1 3.5 3V2h-1a.5.5 0 0 1 0-1h11a.5.5 0 0 1 0 1h-1v1a4.5 4.5 0 0 1-2.557 4.06c-.29.139-.443.377-.443.59v.7c0 .213.154.451.443.59A4.5 4.5 0 0 1 12.5 13v1h1a.5.5 0 0 1 0 1zm2-13v1c0 .537.12 1.045.337 1.5h6.326c.216-.455.337-.963.337-1.5V2zm3 6.35c0 .701-.478 1.236-1.011 1.492A3.5 3.5 0 0 0 4.5 13s.866-1.299 3-1.48zm1 0v3.17c2.134.181 3 1.48 3 1.48a3.5 3.5 0 0 0-1.989-3.158C8.978 9.586 8.5 9.052 8.5 8.351z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-hourglass" viewBox="0 0 16 16">
  <path d="M2 1.5a.5.5 0 0 1 .5-.5h11a.5.5 0 0 1 0 1h-1v1a4.5 4.5 0 0 1-2.557 4.06c-.29.139-.443.377-.443.59v.7c0 .213.154.451.443.59A4.5 4.5 0 0 1 12.5 13v1h1a.5.5 0 0 1 0 1h-11a.5.5 0 1 1 0-1h1v-1a4.5 4.5 0 0 1 2.557-4.06c.29-.139.443-.377.443-.59v-.7c0-.213-.154-.451-.443-.59A4.5 4.5 0 0 1 3.5 3V2h-1a.5.5 0 0 1-.5-.5m2.5.5v1a3.5 3.5 0 0 0 1.989 3.158c.533.256 1.011.791 1.011 1.491v.702c0 .7-.478 1.235-1.011 1.491A3.5 3.5 0 0 0 4.5 13v1h7v-1a3.5 3.5 0 0 0-1.989-3.158C8.978 9.586 8.5 9.052 8.5 8.351v-.702c0-.7.478-1.235 1.011-1.491A3.5 3.5 0 0 0 11.5 3V2z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-info-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="m8.93 6.588-2.29.287-.082.38.45.083c.294.07.352.176.288.469l-.738 3.468c-.194.897.105 1.319.808 1.319.545 0 1.178-.252 1.465-.598l.088-.416c-.2.176-.492.246-.686.246-.275 0-.375-.193-.304-.533zM9 4.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-list-ul" viewBox="0 0 16 16">
  <path fill-rule="evenodd" d="M5 11.5a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m0-4a.5.5 0 0 1 .5-.5h9a.5.5 0 0 1 0 1h-9a.5.5 0 0 1-.5-.5m-3 1a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2m0 4a1 1 0 1 0 0-2 1 1 0 0 0 0 2"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-measuring-cup" viewBox="0 0 16 16">
  <path d="M.038.309A.5.5 0 0 1 .5 0H14a2 2 0 0 1 2 2v5.959a1.041 1.041 0 0 1-2.069.17l-.849-5.094A.041.041 0 0 0 13 3.04V14a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V3.743a2.5 2.5 0 0 0-.732-1.768L.146.854A.5.5 0 0 1 .038.309M1.708 1l.267.268A3.5 3.5 0 0 1 3 3.743V14a1 1 0 0 0 1 1h7a1 1 0 0 0 1-1V3.041a1.041 1.041 0 0 1 2.069-.17l.849 5.094A.041.041 0 0 0 15 7.96V2a1 1 0 0 0-1-1zM4 3h3.5a.5.5 0 1 1 0 1H4zm0 2h1.5a.5.5 0 1 1 0 1H4zm0 2h3.5a.5.5 0 1 1 0 1H4zm0 2h1.5a.5.5 0 1 1 0 1H4zm0 2h3.5a.5.5 0 0 1 0 1H4zm0 2h1.5a.5.5 0 0 1 0 1H4z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-p-circle" viewBox="0 0 16 16">
  <path d="M1 8a7 7 0 1 0 14 0A7 7 0 0 0 1 8m15 0A8 8 0 1 1 0 8a8 8 0 0 1 16 0M5.5 4.002h2.962C10.045 4.002 11 5.104 11 6.586c0 1.494-.967 2.578-2.55 2.578H6.784V12H5.5zm2.77 4.072c.893 0 1.419-.545 1.419-1.488s-.526-1.482-1.42-1.482H6.778v2.97z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person-circle" viewBox="0 0 16 16">
  <path d="M11 6a3 3 0 1 1-6 0 3 3 0 0 1 6 0"/>
  <path fill-rule="evenodd" d="M0 8a8 8 0 1 1 16 0A8 8 0 0 1 0 8m8-7a7 7 0 0 0-5.468 11.37C3.242 11.226 4.805 10 8 10s4.757 1.225 5.468 2.37A7 7 0 0 0 8 1"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-person" viewBox="0 0 16 16">
  <path d="M8 8a3 3 0 1 0 0-6 3 3 0 0 0 0 6m2-3a2 2 0 1 1-4 0 2 2 0 0 1 4 0m4 8c0 1-1 1-1 1H3s-1 0-1-1 1-4 6-4 6 3 6 4m-1-.004c-.001-.246-.154-.986-.832-1.664C11.516 10.68 10.289 10 8 10s-3.516.68-4.168 1.332c-.678.678-.83 1.418-.832 1.664z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-people" viewBox="0 0 16 16">
  <path d="M15 14s1 0 1-1-1-4-5-4-5 3-5 4 1 1 1 1zm-7.978-1L7 12.996c.001-.264.167-1.03.76-1.72C8.312 10.629 9.282 10 11 10c1.717 0 2.687.63 3.24 1.276.593.69.758 1.457.76 1.72l-.008.002-.014.002zM11 7a2 2 0 1 0 0-4 2 2 0 0 0 0 4m3-2a3 3 0 1 1-6 0 3 3 0 0 1 6 0M6.936 9.28a6 6 0 0 0-1.23-.247A7 7 0 0 0 5 9c-4 0-5 3-5 4q0 1 1 1h4.216A2.24 2.24 0 0 1 5 13c0-1.01.377-2.042 1.09-2.904.243-.294.526-.569.846-.816M4.92 10A5.5 5.5 0 0 0 4 13H1c0-.26.164-1.03.76-1.724.545-.636 1.492-1.256 3.16-1.275ZM1.5 5.5a3 3 0 1 1 6 0 3 3 0 0 1-6 0m3-2a2 2 0 1 0 0 4 2 2 0 0 0 0-4"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-plus-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="M8 4a.5.5 0 0 1 .5.5v3h3a.5.5 0 0 1 0 1h-3v3a.5.5 0 0 1-1 0v-3h-3a.5.5 0 0 1 0-1h3v-3A.5.5 0 0 1 8 4"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-printer" viewBox="0 0 16 16">
  <path d="M2.5 8a.5.5 0 1 0 0-1 .5.5 0 0 0 0 1"/>
  <path d="M5 1a2 2 0 0 0-2 2v2H2a2 2 0 0 0-2 2v3a2 2 0 0 0 2 2h1v1a2 2 0 0 0 2 2h6a2 2 0 0 0 2-2v-1h1a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-1V3a2 2 0 0 0-2-2zM4 3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1v2H4zm1 5a2 2 0 0 0-2 2v1H2a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h12a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1v-1a2 2 0 0 0-2-2zm7 2v3a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h6a1 1 0 0 1 1 1"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-question-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="M5.255 5.786a.237.237 0 0 0 .241.247h.825c.138 0 .248-.113.266-.25.09-.656.54-1.134 1.342-1.134.686 0 1.314.343 1.314 1.168 0 .635-.374.927-.965 1.371-.673.489-1.206 1.06-1.168 1.987l.003.217a.25.25 0 0 0 .25.246h.811a.25.25 0 0 0 .25-.25v-.105c0-.718.273-.927 1.01-1.486.609-.463 1.244-.977 1.244-2.056 0-1.511-1.276-2.241-2.673-2.241-1.267 0-2.655.59-2.75 2.286m1.557 5.763c0 .533.425.927 1.01.927.609 0 1.028-.394 1.028-.927 0-.552-.42-.94-1.029-.94-.584 0-1.009.388-1.009.94"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-receipt" viewBox="0 0 16 16">
  <path d="M1.92.506a.5.5 0 0 1 .434.14L3 1.293l.646-.647a.5.5 0 0 1 .708 0L5 1.293l.646-.647a.5.5 0 0 1 .708 0L7 1.293l.646-.647a.5.5 0 0 1 .708 0L9 1.293l.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .708 0l.646.647.646-.647a.5.5 0 0 1 .801.13l.5 1A.5.5 0 0 1 15 2v12a.5.5 0 0 1-.053.224l-.5 1a.5.5 0 0 1-.8.13L13 14.707l-.646.647a.5.5 0 0 1-.708 0L11 14.707l-.646.647a.5.5 0 0 1-.708 0L9 14.707l-.646.647a.5.5 0 0 1-.708 0L7 14.707l-.646.647a.5.5 0 0 1-.708 0L5 14.707l-.646.647a.5.5 0 0 1-.708 0L3 14.707l-.646.647a.5.5 0 0 1-.801-.13l-.5-1A.5.5 0 0 1 1 14V2a.5.5 0 0 1 .053-.224l.5-1a.5.5 0 0 1 .367-.27m.217 1.338L2 2.118v11.764l.137.274.51-.51a.5.5 0 0 1 .707 0l.646.647.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.646.646.646-.646a.5.5 0 0 1 .708 0l.509.509.137-.274V2.118l-.137-.274-.51.51a.5.5 0 0 1-.707 0L12 1.707l-.646.647a.5.5 0 0 1-.708 0L10 1.707l-.646.647a.5.5 0 0 1-.708 0L8 1.707l-.646.647a.5.5 0 0 1-.708 0L6 1.707l-.646.647a.5.5 0 0 1-.708 0L4 1.707l-.646.647a.5.5 0 0 1-.708 0z"/>
  <path d="M3 4.5a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 1 1 0 1h-6a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h6a.5.5 0 0 1 0 1h-6a.5.5 0 0 1-.5-.5m8-6a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5m0 2a.5.5 0 0 1 .5-.5h1a.5.5 0 0 1 0 1h-1a.5.5 0 0 1-.5-.5"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-star" viewBox="0 0 16 16">
  <path d="M2.866 14.85c-.078.444.36.791.746.593l4.39-2.256 4.389 2.256c.386.198.824-.149.746-.592l-.83-4.73 3.522-3.356c.33-.314.16-.888-.282-.95l-4.898-.696L8.465.792a.513.513 0 0 0-.927 0L5.354 5.12l-4.898.696c-.441.062-.612.636-.283.95l3.523 3.356-.83 4.73zm4.905-2.767-3.686 1.894.694-3.957a.56.56 0 0 0-.163-.505L1.71 6.745l4.052-.576a.53.53 0 0 0 .393-.288L8 2.223l1.847 3.658a.53.53 0 0 0 .393.288l4.052.575-2.906 2.77a.56.56 0 0 0-.163.506l.694 3.957-3.686-1.894a.5.5 0 0 0-.461 0z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-stopwatch" viewBox="0 0 16 16">
  <path d="M8.5 5.6a.5.5 0 1 0-1 0v2.9h-3a.5.5 0 0 0 0 1H8a.5.5 0 0 0 .5-.5z"/>
  <path d="M6.5 1A.5.5 0 0 1 7 .5h2a.5.5 0 0 1 0 1v.57c1.36.196 2.594.78 3.584 1.64l.012-.013.354-.354-.354-.353a.5.5 0 0 1 .707-.708l1.414 1.415a.5.5 0 1 1-.707.707l-.353-.354-.354.354-.013.012A7 7 0 1 1 7 2.071V1.5a.5.5 0 0 1-.5-.5M8 3a6 6 0 1 0 .001 12A6 6 0 0 0 8 3"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-telephone" viewBox="0 0 16 16">
  <path d="M3.654 1.328a.678.678 0 0 0-1.015-.063L1.605 2.3c-.483.484-.661 1.169-.45 1.77a17.6 17.6 0 0 0 4.168 6.608 17.6 17.6 0 0 0 6.608 4.168c.601.211 1.286.033 1.77-.45l1.034-1.034a.678.678 0 0 0-.063-1.015l-2.307-1.794a.68.68 0 0 0-.58-.122l-2.19.547a1.75 1.75 0 0 1-1.657-.459L5.482 8.062a1.75 1.75 0 0 1-.46-1.657l.548-2.19a.68.68 0 0 0-.122-.58zM1.884.511a1.745 1.745 0 0 1 2.612.163L6.29 2.98c.329.423.445.974.315 1.494l-.547 2.19a.68.68 0 0 0 .178.643l2.457 2.457a.68.68 0 0 0 .644.178l2.189-.547a1.75 1.75 0 0 1 1.494.315l2.306 1.794c.829.645.905 1.87.163 2.611l-1.034 1.034c-.74.74-1.846 1.065-2.877.702a18.6 18.6 0 0 1-7.01-4.42 18.6 18.6 0 0 1-4.42-7.009c-.362-1.03-.037-2.137.703-2.877z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-ticket-perforated" viewBox="0 0 16 16">
  <path d="M4 4.85v.9h1v-.9zm7 0v.9h1v-.9zm-7 1.8v.9h1v-.9zm7 0v.9h1v-.9zm-7 1.8v.9h1v-.9zm7 0v.9h1v-.9zm-7 1.8v.9h1v-.9zm7 0v.9h1v-.9z"/>
  <path d="M1.5 3A1.5 1.5 0 0 0 0 4.5V6a.5.5 0 0 0 .5.5 1.5 1.5 0 1 1 0 3 .5.5 0 0 0-.5.5v1.5A1.5 1.5 0 0 0 1.5 13h13a1.5 1.5 0 0 0 1.5-1.5V10a.5.5 0 0 0-.5-.5 1.5 1.5 0 0 1 0-3A.5.5 0 0 0 16 6V4.5A1.5 1.5 0 0 0 14.5 3zM1 4.5a.5.5 0 0 1 .5-.5h13a.5.5 0 0 1 .5.5v1.05a2.5 2.5 0 0 0 0 4.9v1.05a.5.5 0 0 1-.5.5h-13a.5.5 0 0 1-.5-.5v-1.05a2.5 2.5 0 0 0 0-4.9z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-truck-front" viewBox="0 0 16 16">
  <path d="M5 11a1 1 0 1 1-2 0 1 1 0 0 1 2 0m8 0a1 1 0 1 1-2 0 1 1 0 0 1 2 0m-6-1a1 1 0 1 0 0 2h2a1 1 0 1 0 0-2zM4 2a1 1 0 0 0-1 1v3.9c0 .625.562 1.092 1.17.994C5.075 7.747 6.792 7.5 8 7.5s2.925.247 3.83.394A1.008 1.008 0 0 0 13 6.9V3a1 1 0 0 0-1-1zm0 1h8v3.9q0 .002 0 0l-.002.004-.005.002h-.004C11.088 6.761 9.299 6.5 8 6.5s-3.088.26-3.99.406h-.003l-.005-.002L4 6.9q0 .002 0 0z"/>
  <path d="M1 2.5A2.5 2.5 0 0 1 3.5 0h9A2.5 2.5 0 0 1 15 2.5v9c0 .818-.393 1.544-1 2v2a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1-.5-.5V14H5v1.5a.5.5 0 0 1-.5.5h-2a.5.5 0 0 1-.5-.5v-2a2.5 2.5 0 0 1-1-2zM3.5 1A1.5 1.5 0 0 0 2 2.5v9A1.5 1.5 0 0 0 3.5 13h9a1.5 1.5 0 0 0 1.5-1.5v-9A1.5 1.5 0 0 0 12.5 1z"/>
</svg></li>
                <li><svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-x-circle" viewBox="0 0 16 16">
  <path d="M8 15A7 7 0 1 1 8 1a7 7 0 0 1 0 14m0 1A8 8 0 1 0 8 0a8 8 0 0 0 0 16"/>
  <path d="M4.646 4.646a.5.5 0 0 1 .708 0L8 7.293l2.646-2.647a.5.5 0 0 1 .708.708L8.707 8l2.647 2.646a.5.5 0 0 1-.708.708L8 8.707l-2.646 2.647a.5.5 0 0 1-.708-.708L7.293 8 4.646 5.354a.5.5 0 0 1 0-.708"/>
</svg></li>
                <li> </li>
                <li> </li>
                <li> </li>
            </ul>


        <hr />

        <section class="content-region">
            <h2 class="other-class">An Example Article</h2>
            <article class="entry--content">
                <h1 class="other-class">Title</h1>
                <p class="test-class">
                Lorem ipsum dolor sit amet, <b>consectetur adipisicing elit</b>, sed do eiusmod
                tempor incididunt ut labore et dolore magna aliqua. Ut enim ad minim veniam,
                quis nostrud <em>exercitation ullamco laboris nisi ut aliquip ex ea commodo
                consequat</em>. Duis aute irure dolor in reprehenderit in voluptate velit esse
                cillum dolore eu fugiat nulla pariatur. Excepteur sint occaecat cupidatat
                non proident, sunt in culpa qui officia deserunt mollit anim id est laborum.
                </p>
                <blockquote>
                    <p class="test-class">
                    This is a GREAT pull quote.
                    </p>
                    <cite><a href="#">- Author</a></cite>
                </blockquote>
                <img src="https://placehold.co/600x400" alt="Figure Example" class="alignright" />
                <p class="test-class">
                Neque porro quisquam est, qui dolorem ipsum quia dolor sit amet,
                consectetur, adipisci velit, sed quia non numquam eius modi tempora incidunt
                ut labore et dolore magnam aliquam quaerat voluptatem. Ut enim ad minima
                veniam, quis nostrum exercitationem ullam corporis suscipit laboriosam, nisi
                ut aliquid ex ea commodi consequatur? Quis autem vel eum iure reprehenderit
                qui in ea voluptate velit esse quam nihil molestiae consequatur, vel illum
                qui dolorem eum fugiat quo voluptas nulla pariatur?"
                </p>
                
                <p class="test-class">
                At vero eos et accusamus et iusto odio dignissimos ducimus qui blanditiis
                praesentium voluptatum deleniti atque corrupti quos dolores et quas
                molestias excepturi sint occaecati cupiditate non provident, similique sunt
                in culpa qui officia deserunt mollitia animi, id est laborum et dolorum
                fuga. Et harum quidem rerum facilis est et expedita distinctio.
                </p>
            </article>
        </section>

        <hr />

        <section class="content-region">
            <h2 class="other-class">Code examples</h2>
            <pre>
                <code>  
                sudo ipfw pipe 1 config bw 256KByte/s
                sudo ipfw add 1 pipe 1 src-port 3000
                </code>
            </pre>
        </section>

        <hr />

        <section class="content-region">
            <h2 class="other-class">Interactive &amp; Native HTML Elements</h2>
            <div class="form--row">
                <label for="sg-progress">Progress (80 of 100)</label>
                <progress id="sg-progress" value="80" max="100">80%</progress>
            </div>
            <div class="form--row">
                <label for="sg-meter">Meter — fundraising goal ($824 of $1000)</label>
                <meter id="sg-meter" min="0" max="1000" low="300" high="700" optimum="1000" value="824">$824 of $1000</meter>
            </div>
            <div class="form--row">
                <label for="sg-range">Range (0–100)</label>
                <input type="range" id="sg-range" name="sg-range" min="0" max="100" value="40">
            </div>
            <div class="form--row">
                <label for="sg-number">Number</label>
                <input type="number" id="sg-number" name="sg-number" min="0" max="99" step="1" value="4">
            </div>
            <div class="form--row">
                <label for="sg-date">Date</label>
                <input type="date" id="sg-date" name="sg-date">
            </div>
            <div class="form--row">
                <label for="sg-time">Time</label>
                <input type="time" id="sg-time" name="sg-time">
            </div>
            <div class="form--row">
                <label for="sg-color">Colour Picker</label>
                <input type="color" id="sg-color" name="sg-color" value="#ff7824">
            </div>
            <div class="form--row">
                <label for="sg-output">Output element (linked to range above)</label>
                <output id="sg-output" for="sg-range">40</output>
            </div>
            <p><time datetime="2026-04-01">1 April 2026</time></p>
        </section>

        <hr />

        <section class="content-region">
            <h2 class="other-class">Random Stuff</h2>
            <p><small>This is for things like copyright info.</small></p>
            <p><s>Content that isn't accurate or relevant anymore.</s></p>
            <p><span>Generic <code>&lt;span&gt;</code> wrapper.</span></p>
            <p><abbr title="HyperText Markup Language">HTML</abbr> — abbreviation with a title attribute.</p>
            <p><mark>Highlighted text with <code>&lt;mark&gt;</code>.</mark></p>
            <p><cite>A Book Title</cite> — the <code>&lt;cite&gt;</code> element.</p>
            <p>This is inline text with <sub>subscript</sub> and <sup>superscript</sup> elements.</p>
            <p>
                <var>f</var>(<var>x</var>) = <var>a</var><sub>0</sub> + <var>a</var><sub>1</sub><var>x</var> +
                <var>a</var><sub>2</sub><var>x</var><sup>2</sup>, where <var>a</var><sup>2</sup> ≠ 0
            </p>
            <p><time datetime="2026-04-01">1 April 2026</time> — the <code>&lt;time&gt;</code> element.</p>
            <p><data value="398">Mini Chocolate Croissant</data> — <code>&lt;data&gt;</code> linking content to a machine-readable value.</p>
        </section>

        <hr />

        <section class="content-region">
            <figure>
                <img src="https://placehold.co/600x400" alt="Figure Example">
                <figcaption>
                    Photo of the sky at night.
                </figcaption>
            </figure>
        </section>

        <hr />

        <section class="content-region">
            <!--
            http://www.w3.org/html/wg/drafts/html/master/text-level-semantics.html#the-samp-element
            -->

            <pre>
                <code>
                    /Sites/html master  ☠ ☢
                    $  <kbd>ls -gto</kbd>

                    total 104
                    -rw-r--r--   1   10779 Jun  5 16:24 index.html
                    -rw-r--r--   1    1255 Jun  5 16:00 _config.yml
                    drwxr-xr-x  11     374 Jun  5 15:57 _site
                    -rw-r--r--   1    1597 Jun  5 14:16 README.md
                    drwxr-xr-x   5     170 Jun  5 14:15 _sass
                    -rw-r--r--   1     564 Jun  4 15:59 Rakefile
                    drwxr-xr-x   6     204 Jun  4 15:59 _includes
                    drwxr-xr-x   4     136 Jun  4 15:59 _layouts
                    drwxr-xr-x   3     102 Jun  4 15:59 _resources
                    drwxr-xr-x   3     102 Jun  4 15:59 css
                    -rw-r--r--   1    1977 Jun  4 15:59 favicon.icns
                    -rw-r--r--   1    6518 Jun  4 15:59 favicon.ico
                    -rw-r--r--   1    1250 Jun  4 15:59 touch-icon-ipad-precomposed.png
                    -rw-r--r--   1    2203 Jun  4 15:59 touch-icon-ipad-retina-precomposed.png
                    -rw-r--r--   1    1046 Jun  4 15:59 touch-icon-iphone-precomposed.png
                    -rw-r--r--   1    1779 Jun  4 15:59 touch-icon-iphone-retina-precomposed.png
                </code>
            </pre>
        </section>

        <hr />

        <section class="content-region">
            <h2 class="other-class">Tables</h2>
            <!--
            From the HTML spec (http://www.w3.org/TR/html401/struct/tables.html)

            TFOOT must appear before TBODY within a TABLE definition so that user agents can
            render the foot before receiving all of the (potentially numerous) rows of data.
            The following summarizes which tags are required and which may be omitted:

            The TBODY start tag is always required except when the table contains only one
            table body and no table head or foot sections. The TBODY end tag may always be
            safely omitted.

            The start tags for THEAD and TFOOT are required when the table head and foot sections
            are present respectively, but the corresponding end tags may always be safely
            omitted.

            Conforming user agent parsers must obey these rules for reasons of backward
            compatibility.
            -->
            <table>
                <caption>This is a caption for a table</caption>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Date</th>
                        <th>Address</th>
                    </tr>
                </thead>
                <tfoot>
                    Table footer info
                </tfoot>
                <tbody>
                    <tr>
                        <td>#999-32ac</td>
                        <td>First Name</td>
                        <td>13 May, 2013</td>
                        <td>999 Spruce Lane, Somewhere, CA 94101</td>
                    </tr>
                    <tr>
                        <td>#888-32dd</td>
                        <td>Sample Name</td>
                        <td>17 May, 1984</td>
                        <td>999 Spruce Lane, Somewhere, CA 94101</td>
                    </tr>
                </tbody>
            </table>
        </section>

    </div> 
</main>

<?php get_sidebar(''); ?>

</div>
<?php get_footer(); ?>
