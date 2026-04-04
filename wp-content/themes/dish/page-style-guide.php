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
<div class="fluid has--aside">
<main id="main-content" class="main--content">

    <h2 class="page-title"><?php the_title(); ?></h2>

    <div class="sg--content-wrapper">

        <p class="intro">A living reference for all Dish Events components and CSS helpers. Each entry shows a live render alongside the code to produce it and all available options &mdash; no digging through docs required.</p>

        <hr />

        <!-- ── 1. Chef Cards ──────────────────────────────────────────────── -->
        <div class="sg--component">
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
        <div class="sg--component">
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
        <div class="sg--component">
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
        <div class="sg--component">
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
        <div class="sg--component">
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

        <section>
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

        <section class="">
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

        <section>
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

        <section>
            <h2 class="other-class">Forms</h2>
            <form class="general-form" action="#" novalidate>
                <fieldset class="fieldset">
                    <legend>Legend Example</legend>
                    <div class="form--row">
                        <label for="sg-search">Search</label>
                        <input class="with-description" type="search" placeholder="Search" id="sg-search" name="sg-search">
                        <p class="test-class">Helper text if necessary.</p>
                    </div>
                    <div class="form--row">
                        <label for="sg-text">Text Input Label</label>
                        <input class="with-description" type="text" placeholder="Type Something..." id="sg-text" name="sg-text">
                        <p class="test-class">Helper text if necessary.</p>
                    </div>
                    <div class="form--row">
                        <label for="sg-password">Password <span class="required" aria-hidden="true">*</span></label>
                        <input class="with-description" type="password" id="sg-password" name="sg-password" autocomplete="current-password" required aria-required="true">
                        <p class="form--error" role="alert">Error message when appropriate.</p>
                    </div>
                    <div class="form--row">
                        <label for="sg-first-name">First Name</label>
                        <input type="text" id="sg-first-name" name="sg-first-name" autocomplete="given-name">
                    </div>
                    <div class="form--row">
                        <label for="sg-last-name">Last Name</label>
                        <input type="text" id="sg-last-name" name="sg-last-name" autocomplete="family-name">
                    </div>
                    <div class="form--row">
                        <label for="sg-email">Email</label>
                        <input type="email" id="sg-email" name="sg-email" autocomplete="email">
                    </div>
                    <div class="form--row">
                        <label for="sg-dropdown">Dropdown</label>
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
                            <ul class="nostyle radio-buttons">
                                <li><label><input type="radio" name="sg-radio" value="1"> Label 1</label></li>
                                <li><label><input type="radio" name="sg-radio" value="2"> Label 2</label></li>
                                <li><label><input type="radio" name="sg-radio" value="3"> Label 3</label></li>
                            </ul>
                        </fieldset>
                    </div>
                    <div class="form--row">
                        <label for="sg-url">URL Input</label>
                        <input type="url" id="sg-url" name="sg-url" placeholder="https://example.com">
                    </div>
                    <div class="form--row">
                        <label for="sg-textarea">Text Area</label>
                        <textarea id="sg-textarea" name="sg-textarea" rows="4"></textarea>
                    </div>
                    <div class="form--row">
                        <label><input type="checkbox" name="sg-checkbox" value="1"> This is a checkbox.</label>
                    </div>
                    <div class="form--row">
                        <button type="submit">Submit</button>
                        <button type="reset">Reset</button>
                        <button type="button">Button</button>
                    </div>
                </fieldset>
            </form>
        </section>

        <hr />

        <section>
            <h2 class="other-class">Buttons</h2>
            <button>Regular Button</button>
            <button class="purple-btn">Purple Button</button>
            <button>Large Blue Button</button>
        </section>

        <hr />

        <section>
            <h2 class="other-class">An Example Article</h2>
            <article>
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
                    <a href="#">- Author</a>
                </blockquote>
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

        <section>
            <h2 class="other-class">Code examples</h2>
            <pre>
                <code>  
                sudo ipfw pipe 1 config bw 256KByte/s
                sudo ipfw add 1 pipe 1 src-port 3000
                </code>
            </pre>
        </section>

        <hr />

        <section>
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

        <section>
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

        <section>
            <figure>
                <img src="https://placehold.co/600x400" alt="Figure Example">
                <figcaption>
                    Photo of the sky at night.
                </figcaption>
            </figure>
        </section>

        <hr />

        <section>
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

        <section>
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
