
<div class="region global-footer ra" id="global-footer">
    <footer class="fluid">    
        <h2 class="hide-text">Additional Information</h2>
        <div class="footer-grid ra">
            <div class="footer-span-1">
                <div class="footer-logo"></div>
            </div>
            <div class="footer-area footer-span-2" itemscope itemtype="http://www.schema.org/SiteNavigationElement">
                <h3 class="subtitle">About Dish</h3>
                <?php /* Main Menu - different css */ ?>
                <?php
                    wp_nav_menu( 
                        array(
                            'theme_location'  => 'footer 1',
                            'menu_class' => 'footer-menu',
                            'menu_id' => 'footer-menu',
                            'container' => 'ul'
                        )
                    );
                ?>
            </div>
            <div class="footer-area footer-span-3">
                <h3 class="subtitle">Main Course</h3>
                <?php /* Main Menu - different css */ ?>
                <?php
                    wp_nav_menu( 
                        array(
                            'theme_location'  => 'footer 2',
                            'menu_class' => 'footer-menu',
                            'menu_id' => 'footer-menu-2',
                            'container' => 'ul'
                        )
                    );
                ?>
            </div>
            <div class="footer-area footer-span-4">
                <h3 class="subtitle">Appetizers</h3>
                <?php /* Main Menu - different css */ ?>
                <?php
                    wp_nav_menu( 
                        array(
                            'theme_location'  => 'footer 3',
                            'menu_class' => 'footer-menu',
                            'menu_id' => 'footer-menu-3',
                            'container' => 'ul'
                        )
                    );
                ?>
            </div>
            <div class="footer-area footer-span-5">
                <h3 class="subtitle">In Person</h3>
                <div itemscope itemtype="https://schema.org/LocalBusiness">
                    <div itemprop="name" class="hide-text">Dish Cooking Studio</div>
                    <meta itemprop="openingHours" class="is--hidden" content="Mo,Tu,We,Th,Fr 11:00-18:00" datetime="Mo,Tu,We,Th,Fr 11:00-18:00">
                    <div itemtype="http://schema.org/GeoCoordinates" itemscope="" itemprop="geo">
                        <meta itemprop="latitude" content="43.655060">
                        <meta itemprop="longitude" content="-79.413375">
                    </div>
                    <div itemtype="http://schema.org/PostalAddress" itemscope="" itemprop="address">
                        <div itemprop="streetAddress">587 College St.</div>
                        <div><span itemprop="addressLocality">Toronto</span>, <span itemprop="addressRegion">ON</span>. <span itemprop="postalCode">M6G 1B2</span><br> Canada </div>
                    </div>
                    <p>Phone: <span itemprop="telephone"><a href="tel:+14169205559">416-920-5559</a></span></p>
                    <p class="hide-text"><span>Email:</span> <span itemprop="email">info@dishcookingstudio.com</span></p>
                    <p class="hide-text">Url: <span itemprop="url">https://www.dishcookingstudio.com/</span></p>
                </div>  
            </div>
            <div class="footer-area footer-span-6">
                <h3 class="subtitle">Online</h3>
                <ul class="footer--social-links is--flex-list">

                    <li class="flex"><a class="ico-list--item" href="https://www.instagram.com/dishcookingstudio/" target="_blank" rel="me"><svg xmlns="http://www.w3.org/2000/svg" class="svg-inline i-instagram" style="enable-background:new 0 0 216 216" viewBox="0 0 216 216"><path fill="currentColor" d="M108 56.3c-28.6 0-51.7 23.1-51.7 51.7s23.1 51.7 51.7 51.7 51.7-23.1 51.7-51.7-23.1-51.7-51.7-51.7zm0 85.3c-18.5 0-33.6-15.1-33.6-33.6S89.5 74.4 108 74.4s33.6 15.1 33.6 33.6-15.1 33.6-33.6 33.6zm65.9-87.4c0 6.7-5.4 12.1-12.1 12.1-6.7 0-12.1-5.4-12.1-12.1s5.4-12.1 12.1-12.1 12.1 5.5 12.1 12.1zm34.2 12.2c-.8-16.1-4.5-30.4-16.3-42.2C180 12.4 165.7 8.7 149.6 7.9c-16.7-.9-66.5-.9-83.2 0-16.1.8-30.4 4.5-42.2 16.3S8.7 50.3 7.9 66.4C7 83 7 132.9 7.9 149.6c.8 16.1 4.5 30.4 16.3 42.2s26.1 15.5 42.2 16.3c16.6.9 66.5.9 83.2 0 16.1-.8 30.4-4.5 42.2-16.3 11.8-11.8 15.5-26.1 16.3-42.2.9-16.7.9-66.5 0-83.2zm-21.5 101c-3.5 8.8-10.3 15.6-19.2 19.2-13.3 5.3-44.8 4-59.4 4s-46.2 1.2-59.4-4c-8.8-3.5-15.6-10.3-19.2-19.2-5.3-13.3-4-44.8-4-59.4s-1.2-46.2 4-59.4C33 39.8 39.7 33 48.6 29.4c13.3-5.3 44.8-4 59.4-4s46.2-1.2 59.4 4c8.8 3.5 15.6 10.3 19.2 19.2 5.3 13.3 4 44.8 4 59.4s1.3 46.2-4 59.4z"/></svg><span class="hide-text">Instagram</span></a></li>

                    <li class="flex"><a class="ico-list--item" href="https://www.tiktok.com/@dish.cooking.stud" target="_blank" rel="me"><svg xmlns="http://www.w3.org/2000/svg" class="svg-inline i--tiktok" style="enable-background:new 0 0 216 216" viewBox="0 0 216 216"><path fill="currentColor" d="M196.8 7.2H19.2c-6.6 0-12 5.4-12 12v177.6c0 6.6 5.4 12 12 12h177.6c6.6 0 12-5.4 12-12V19.2c0-6.6-5.4-12-12-12zm-10 84.6c-15.5 0-30.6-4.8-43.2-13.8v62.8c0 11.6-3.6 23-10.2 32.6-6.6 9.6-16 16.9-26.9 21-10.9 4.1-22.8 4.7-34.1 1.9a57.59 57.59 0 0 1-29.1-17.9c-7.7-8.8-12.4-19.7-13.7-31.3s1-23.2 6.5-33.5c5.5-10.2 14.1-18.5 24.5-23.8 10.4-5.3 22.1-7.2 33.6-5.6v31.6c-5.3-1.7-10.9-1.6-16.2.1-5.2 1.8-9.8 5.1-13 9.6-3.2 4.5-4.9 9.9-4.9 15.4 0 5.6 1.8 10.9 5.1 15.4s7.9 7.8 13.2 9.5c5.3 1.7 10.9 1.7 16.2 0 5.2-1.7 9.8-5.1 13.1-9.5 3.2-4.5 5-9.9 5-15.4V18h30.9c0 2.6.2 5.2.7 7.8 1.1 5.7 3.3 11.2 6.6 16.1 3.3 4.9 7.5 9 12.4 12.2 7 4.6 15.2 7.1 23.6 7.1v30.6z"/></svg><span class="hide-text">tiktok</span></a></li>

                    <li class="flex"><a class="ico-list--item" href="https://www.facebook.com/dishcookingstudio/" target="_blank" rel="me"><svg xmlns="http://www.w3.org/2000/svg" class="svg-inline i--linkedin" style="enable-background:new 0 0 216 216" viewBox="0 0 216 216"><path fill="currentColor" d="M52.3 208.8H10.5V74.2h41.8v134.6zm-20.9-153c-13.4 0-24.2-11-24.2-24.4C7.2 18 18 7.2 31.4 7.2c13.4 0 24.2 10.8 24.2 24.2 0 13.4-10.8 24.4-24.2 24.4zm177.4 153H167v-65.5c0-15.6-.3-35.6-21.7-35.6-21.7 0-25.1 17-25.1 34.5v66.6H78.5V74.2h40.1v18.4h.6c5.6-10.6 19.2-21.7 39.5-21.7 42.3 0 50.1 27.9 50.1 64v73.9z"/></svg><span class="hide-text">LinkedIn</span></a></li>
                </ul>
                <p class="footer--contact footer--contact-email"><a href="mailto:info@dishcookingstudio.com">info@dishcookingstudio.com</a></p>
                
                <?php // echo do_shortcode('[mc4wp_form id=164]'); ?>
                <?php // echo do_shortcode('[mc4wp_form id=583]'); ?>

            </div>
        </div>
        <p class="source-org copyright"><a href="/about-dish/cancellation-policy/">Cancellation Policy</a> &nbsp;&mdash;&nbsp; <a href="/privacy-policy/">Privacy Policy</a> &nbsp;&mdash;&nbsp; <a href="/terms-and-conditions/">Terms &amp; Conditions</a><br>&copy; <?php echo date('Y'); ?> <?php bloginfo( 'name' ); ?>. All Rights Reserved</p>      
    </footer>
</div>
<!-- <script src="/assets/js/core/base.min.js" async></script> -->

<?php wp_footer(); ?>

</body>
</html> <!-- View source huh? Oldschool. Nice. -->