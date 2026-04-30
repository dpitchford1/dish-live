<!doctype html>
<html class="no-js" dir="ltr" <?php language_attributes(); ?> <?php Basecamp_Frontend::html_schema(); ?> id="site-body">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<?php /* Mobile */ ?>
<meta name="viewport" content="width=device-width, initial-scale=1">
<?php /* service worker - uncomment if using */ ?>
<script>if (navigator && navigator.serviceWorker) { navigator.serviceWorker.register('/worker.min.js'); }</script>  

<script>var doc = window.document; doc.documentElement.className = document.documentElement.className.replace(/\bno-js\b/g, '') + 'has-js enhanced';</script>

<?php /* inject critical css inline */ ?>
<?php Basecamp_Frontend::output_critical_css( ABSPATH . 'assets/css/build/critical-css.min.css' ); ?>

<?php // dev > ?>
<link rel="stylesheet" href="/assets/css/build/a-dish-base.min.css" media="screen">
<link rel="stylesheet" href="/assets/css/build/a-dish-global.min.css" media="screen">

<?php /* css files here when ready */ ?>

<?php /* favicon */ ?>
<link rel="icon" href="/favicon.ico" sizes="any">
<!-- <link rel="icon" href="/assets/img/icon/safari-pinned-tab.svg" type="image/svg+xml">
<link rel="icon" type="image/png" sizes="32x32" href="/assets/img/icon/favicon-32x32.png">
<link rel="icon" type="image/png" sizes="16x16" href="/assets/img/icon/favicon-16x16.png">
<link rel="mask-icon" href="/assets/img/icon/safari-pinned-tab.svg" color="#12034a"> -->
<?php /* Theme */ ?>
<!-- <link rel="apple-touch-icon" href="/assets/img/icon/apple-touch-icon.png">
<link rel="apple-touch-icon" sizes="180x180" href="/assets/img/icon/apple-touch-icon.png"> -->

<?php /* APPLE SPECIFIC */ ?>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black">
<meta name="apple-mobile-web-app-title" content="<?php bloginfo( 'name' ); ?>">

<?php /* COPYRIGHTS */ ?>
<meta name="author" content="<?php bloginfo( 'name' ); ?>">
<meta name="copyright" content="© <?php bloginfo( 'name' ); ?>. All right reserved. <?php echo esc_html( gmdate( 'Y' ) ); ?>">
<?php /* SEARCH AND SEO */ ?>
<meta name="robots" content="noindex, nofollow, NOODP, noydir">
<?php if ( is_front_page() ) : ?><link rel="home" title="Home page" href="/"><?php endif ?>

<?php wp_head(); 

// Dynamic login / account link — reads plugin page settings.
$login_page_id   = (int) \Dish\Events\Admin\Settings::get( 'login_page',   0 );
$profile_page_id = (int) \Dish\Events\Admin\Settings::get( 'profile_page', 0 );
$login_url       = $login_page_id   ? get_permalink( $login_page_id )   : wp_login_url();
$account_url     = $profile_page_id ? get_permalink( $profile_page_id ) : admin_url();
?>

</head>
<body <?php Basecamp_Frontend::body_class(); ?> id="page-body" data-off-screen="hidden">
<a href="#global-header" id="exit-off-canvas" class="exit-offcanvas" aria-controls="global-header"><span class="hide-text">Hide Menu</span></a>
<?php /* accessibility nav */ ?>
<a class="quick-links" href="#main-content">Skip to Main Content</a>
<a class="quick-links" href="#global-footer">Skip to Footer</a>
<?php /* small screen header bar */ ?>
<div class="region is--fixed global-header--ss" id="global-header--ss"><span class="hide-text">Dish Cooking Studio</span></div>

<?php /* Header Start */ ?>
<div class="region is--fixed global-header" data-nav-slide="slide" id="global-header">
	<header class="brand-header fluid ov">
        <?php if ( is_front_page() || is_page_template( 'page-dish-home.php' ) ) : ?>
            <div class="brand brand-fs" id="logo"><span class="is--logo">Dish Cooking Studio</span></div>
        <?php else : ?>
            <div class="brand brand-fs" id="logo"><a class="is--logo" href="/" rel="home">Dish Cooking Studio</a></div>
        <?php endif ?>
        
        <?php /* Global Menus */ ?>
        <div class="menu-global">
            <div class="" role="navigation" itemscope itemtype="http://www.schema.org/SiteNavigationElement">
                <?php 
                    wp_nav_menu( 
                        array(
                            'theme_location'  => 'primary',
                            'menu_class' => 'navigation-global',
                            'menu_id' => 'primary-menu',
                            'container' => 'ul'
                        )
                    );
                ?>
            </div>
        </div>
        <?php /* Utility Nav */ ?>
	    <nav class="menu-utilities" itemscope itemtype="http://www.schema.org/SiteNavigationElement" aria-label="Utility Navigation">
	        <p class="hide-text">Submenu:</p>
		    <?php 
				wp_nav_menu( 
					array(
						'theme_location'  => 'utility',
						'menu_class' => 'utility-menu',
						'menu_id' => 'utility-menu',
						'container' => false
					)
				);
            ?>
            <!-- <ul class="utility-menu utility-menu--account">
                <?php if ( is_user_logged_in() ) : ?>
                    <li class="menu-item menu-item--account">
                        <a href="<?php echo esc_url( $account_url ); ?>"><?php esc_html_e( 'My Account', 'dish-events' ); ?></a>
                    </li>
                    <li class="menu-item menu-item--logout">
                        <a href="<?php echo esc_url( wp_logout_url( home_url() ) ); ?>"><?php esc_html_e( 'Log Out', 'dish-events' ); ?></a>
                    </li>
                <?php else : ?>
                    <li class="menu-item menu-item--login">
                        <a href="<?php echo esc_url( $login_url ); ?>"><?php esc_html_e( 'Login', 'dish-events' ); ?></a>
                    </li>
                <?php endif; ?>
            </ul> -->
	    </nav>
    </header>
</div>
<?php /* Header End */ ?>
<?php the_toast(); ?>
<hr class="hide-divider">

