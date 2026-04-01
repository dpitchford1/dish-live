/* application wrapper
-------------------------------------------------------------------------------------------------------------------------- */
/**
 * Main Dish application module
 * @namespace dish
 * @description Core functionality for the Dish theme, including header, menu, and UI management 
 */
var dish = dish || {};
window.dish = (function (window, document, dishwrapper){
    "use strict";
    
    // Cache DOM elements once to improve performance
    const doc = document;
    const exitcanvashtml = doc.querySelector('#site-body');
    const exitcanvasbody = doc.querySelector('#page-body');
    const exitcanvas = doc.querySelector('#exit-off-canvas');
    const headerSS = doc.querySelector('#global-header--ss');
    const header = doc.querySelector('#global-header');
    const down = doc.querySelector('#down-arrow');
    //const searchform = doc.querySelector('#searchform');

    // Create links and logo for small screen header using fragment (performance best practice)
    const fragment = doc.createDocumentFragment();
    const toggler = fragment.appendChild(doc.createElement('a'));
    const ssLogo = fragment.appendChild(doc.createElement('a'));
    //const searchtoggle = fragment.appendChild(doc.createElement('a'));

    // Initialize media query
    const mediaQuery = window.matchMedia('(max-width: 767px)');
    
    // Feature detection for better browser support
    const supportsTouch = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
    const supportsPassiveEvents = (function() {
        let passiveSupported = false;
        try {
            const options = {
                get passive() { 
                    passiveSupported = true;
                    return true;
                }
            };
            window.addEventListener('test', null, options);
            window.removeEventListener('test', null, options);
        } catch (e) {
            passiveSupported = false;
        }
        return passiveSupported;
    })();

    /**
     * Enhanced event listener helper that handles touch and mouse events appropriately
     * @param {HTMLElement} element - The DOM element to attach the event to
     * @param {string} eventType - The event type (e.g., 'click', 'scroll')
     * @param {Function} handler - The event handler function
     * @param {Object} options - Additional options for addEventListener
     * @returns {void}
     */
    function addEventListenerWithOptions(element, eventType, handler, options = {}) {
        if (!elementExists(element)) return;
        try {
            // Only make scroll / touch listeners passive
            const passiveEligible = /^(scroll|touchstart|touchmove|wheel)$/i.test(eventType);
            const finalOptions = supportsPassiveEvents
                ? (passiveEligible ? { passive: true, ...options } : { passive: false, ...options })
                : false;

            if (supportsTouch && eventType === 'click') {
                element.addEventListener('touchend', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    handler(e);
                }, { passive: false });
            }
            element.addEventListener(eventType, handler, finalOptions);
        } catch (e) {
            console.error(`Error adding event listener for ${eventType}:`, e);
            element.addEventListener(eventType, handler);
        }
    }

    // Scroll position management
    let scrollpos = window.scrollY;
    let ticking = false;

    /**
     * Checks if an element exists safely
     * @param {*} element - The element to check
     * @returns {boolean} - True if the element exists
     */
    function elementExists(element) {
        return element !== null && element !== undefined;
    }

    /**
     * Throttle function for better performance
     * @param {Function} callback - The function to throttle
     * @param {number} delay - Throttle delay in milliseconds
     * @returns {Function} - Throttled function
     */
    function throttle(callback, delay) {
        let lastCall = 0;
        return function(...args) {
            const now = new Date().getTime();
            if (now - lastCall < delay) {
                return;
            }
            lastCall = now;
            return callback(...args);
        };
    }
    
    /**
     * Debounce function for less frequent updates
     * @param {Function} callback - The function to debounce
     * @param {number} wait - Debounce wait time in milliseconds
     * @returns {Function} - Debounced function
     */
    function debounce(callback, wait) {
        let timeout;
        return function(...args) {
            const context = this;
            clearTimeout(timeout);
            timeout = setTimeout(() => callback.apply(context, args), wait);
        };
    }

    /**
     * Manages the sticky header behavior
     * Uses IntersectionObserver when available, with fallback to scroll events
     * @returns {void}
     */
    function stickyheader(){
        // UPDATED: allow either header or headerSS to drive sticky state
        if (!elementExists(header) && !elementExists(headerSS)) {
            console.warn('[dish] Skipping sticky header: no header elements found');
            return;
        }

        const targetHeader = elementExists(header) ? header : headerSS;

        if ('IntersectionObserver' in window) {
            const sentinel = document.createElement('div');
            sentinel.classList.add('sticky-sentinel');
            sentinel.style.position = 'absolute';
            sentinel.style.top = '0';
            sentinel.style.height = '50px';
            sentinel.style.width = '1px';
            sentinel.style.opacity = '0';
            sentinel.style.pointerEvents = 'none';

            if (targetHeader.parentNode) {
                targetHeader.parentNode.insertBefore(sentinel, targetHeader);
            }

            const headerObserver = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (!entry.isIntersecting) {
                        add_class_on_scroll();
                    } else {
                        remove_class_on_scroll();
                    }
                });
            }, {
                threshold: 0,
                rootMargin: '10px 0px 0px 0px'
            });

            headerObserver.observe(sentinel);
        } else {
            function onScroll() {
                scrollpos = window.scrollY;
                if (!ticking) {
                    window.requestAnimationFrame(() => {
                        if (scrollpos > 50) {
                            add_class_on_scroll();
                        } else {
                            remove_class_on_scroll();
                        }
                        ticking = false;
                    });
                    ticking = true;
                }
            }
            window.addEventListener('scroll', throttle(onScroll, 100),
                supportsPassiveEvents ? { passive: true } : false);
        }

        function add_class_on_scroll() {
            if (elementExists(header))   header.classList.add('is--sticky');
            if (elementExists(headerSS)) headerSS.classList.add('is--sticky');
            if (elementExists(down))     down.classList.add('is--hidden'); // FIX: was adding class to headerSS
        }

        function remove_class_on_scroll() {
            if (elementExists(header))   header.classList.remove('is--sticky');
            if (elementExists(headerSS)) headerSS.classList.remove('is--sticky');
            if (elementExists(down))     down.classList.remove('is--hidden'); // FIX: was removing wrong class from headerSS
        }
    }

    /**
     * Sets multiple attributes on an element
     * @param {HTMLElement} el - Element to set attributes on
     * @param {Object} attrs - Object of attribute name/value pairs
     * @returns {void}
     */
    function setAttributes(el, attrs) {
        // Guard clause to prevent errors with null elements
        if (!elementExists(el)) {
            console.warn('Cannot set attributes on non-existent element');
            return;
        }
        
        // Make sure attrs is an object
        if (!attrs || typeof attrs !== 'object') {
            console.warn('Invalid attributes object');
            return;
        }
        
        try {
            Object.keys(attrs).forEach(key => {
                if (attrs[key] !== undefined && attrs[key] !== null) {
                    el.setAttribute(key, attrs[key]);
                }
            });
        } catch (e) {
            console.error('Error setting attributes:', e);
        }
    }

    /**
     * Sets up the small screen header with menu and search toggles
     * @returns {void}
     */
    function setupSmallScreenHeader() {
        // Check if required elements exist
        if (!elementExists(headerSS)) {
            console.warn('Small screen header element not found, skipping setup');
            return;
        }
        
        // Create links for small screen header
        headerSS.appendChild(fragment);

        // Set small screen html Content
        toggler.innerHTML = 'Menu';
        ssLogo.innerHTML = 'Dish Cooking Studio';
        //searchtoggle.innerHTML = 'Search';

        // set attributes on SS header items
        // toggler link
        setAttributes(toggler, {
            "aria-controls": "global-header",
            "href": "#global-header",
            "id": "menu-trigger",
            "role": "button",
            "class": "menu-trigger ico i--menu",
            "aria-expanded": "false",
            "aria-label": "Toggle menu"
        });

        // create ss logo
        setAttributes(ssLogo, {
            "class": "brand brand-ss",
            "href": "/",
            "id": "menu-ss"
        });

        // search toggler link
        // setAttributes(searchtoggle, {
        //     "aria-controls": "searchform",
        //     "href": "#searchform",
        //     "id": "search-trigger",
        //     "role": "button",
        //     "class": "search-trigger ico i--search",
        //     "aria-expanded": "false",
        //     "aria-label": "Toggle search"
        // });
    }

    /**
     * Sets up the global header menu and search functionality
     * @returns {Object} UIState - The state manager for external access
     */
    function globalheadermenu(){
        if (!elementExists(toggler) || !elementExists(exitcanvas) || !elementExists(header)) {
            console.warn('[Dish] Skipping menu: required elements missing');
            return;
        }

        let lastFocusedElement = null;
        let menuFocusTrapHandler = null;

        function getMenuFocusable() {
            return Array.from(header.querySelectorAll('a[href],button,[tabindex]:not([tabindex="-1"])'))
                .filter(el => !el.hasAttribute('disabled') && el.offsetParent !== null);
        }

        function bindMenuFocusTrap() {
            if (menuFocusTrapHandler) return;
            menuFocusTrapHandler = function(e){
                if (e.key !== 'Tab') return;
                const nodes = getMenuFocusable();
                if (!nodes.length) return;
                const first = nodes[0];
                const last  = nodes[nodes.length - 1];
                if (e.shiftKey && document.activeElement === first) {
                    e.preventDefault(); last.focus();
                } else if (!e.shiftKey && document.activeElement === last) {
                    e.preventDefault(); first.focus();
                }
            };
            header.addEventListener('keydown', menuFocusTrapHandler);
        }

        function unbindMenuFocusTrap() {
            if (!menuFocusTrapHandler) return;
            header.removeEventListener('keydown', menuFocusTrapHandler);
            menuFocusTrapHandler = null;
        }

        function restoreFocus() {
            if (lastFocusedElement && typeof lastFocusedElement.focus === 'function') {
                setTimeout(() => lastFocusedElement.focus(), 50);
            }
        }

        const UIState = {
            menuVisible: false,
            searchVisible: false,

            toggleMenu: function() {
                if (this.searchVisible) this.closeSearch();
                this.menuVisible = !this.menuVisible;
                if (this.menuVisible) {
                    lastFocusedElement = document.activeElement;
                    bindMenuFocusTrap();
                } else {
                    unbindMenuFocusTrap();
                    restoreFocus();
                }
                this.updateUI();
                return this.menuVisible;
            },

            toggleSearch: function() {
                if (this.menuVisible) this.closeMenu();
                this.searchVisible = !this.searchVisible;
                if (this.searchVisible) {
                    lastFocusedElement = document.activeElement;
                } else {
                    restoreFocus();
                }
                this.updateUI();
                return this.searchVisible;
            },

            closeAll: function() {
                const wasOpen = this.menuVisible || this.searchVisible;
                this.menuVisible = false;
                this.searchVisible = false;
                unbindMenuFocusTrap();
                this.updateUI();
                if (wasOpen) restoreFocus();
            },

            closeMenu: function() {
                if (!this.menuVisible) return;
                this.menuVisible = false;
                unbindMenuFocusTrap();
                this.updateUI();
                restoreFocus();
            },

            closeSearch: function() {
                if (!this.searchVisible) return;
                this.searchVisible = false;
                this.updateUI();
                restoreFocus();
            },

            updateUI: function() {
                try {
                    const isMobile = mediaQuery.matches;

                    // Mobile: slide logic; Desktop: force visible without "open" semantics
                    if (isMobile) {
                        setAttributes(header, {
                            "data-nav-slide": this.menuVisible ? "slide visible" : "slide hidden"
                        });
                        toggler.className = this.menuVisible ? 'menu-trigger open ico i--close' : 'menu-trigger ico i--menu';
                        setAttributes(toggler, { "aria-expanded": this.menuVisible ? "true" : "false" });
                    } else {
                        setAttributes(header, { "data-nav-slide": "slide visible" });
                        toggler.className = 'menu-trigger ico i--menu';
                        setAttributes(toggler, { "aria-expanded": "false" });
                    }

                    // Off–canvas/background states only on mobile
                    if (isMobile) {
                        setAttributes(exitcanvasbody, { "data-off-screen": this.menuVisible ? "visible" : "hidden" });
                        setAttributes(exitcanvashtml, { "data-off-canvas": this.searchVisible ? "visible" : "hidden" });
                    } else {
                        setAttributes(exitcanvasbody, { "data-off-screen": "hidden" });
                        setAttributes(exitcanvashtml, { "data-off-canvas": "hidden" });
                    }

                    // ARIA hide background only when mobile overlay active
                    const mainContent = doc.querySelector('#main-content, #main');
                    if (elementExists(mainContent)) {
                        if (isMobile && (this.menuVisible || this.searchVisible)) {
                            mainContent.setAttribute('aria-hidden', 'true');
                        } else {
                            mainContent.removeAttribute('aria-hidden');
                        }
                    }

                    // Hide body children only in mobile overlay state
                    if (isMobile && this.menuVisible) {
                        document.querySelectorAll('body > *:not(#global-header):not(#global-header--ss):not(#exit-off-canvas)').forEach(el=>{
                            if (!header.contains(el)) el.setAttribute('aria-hidden','true');
                        });
                    } else {
                        // Remove only those we set (skip header + overlay)
                        document.querySelectorAll('body > *[aria-hidden="true"]').forEach(el=>{
                            if (el.id !== 'global-header' && el.id !== 'exit-off-canvas') el.removeAttribute('aria-hidden');
                        });
                    }
                } catch(e) {
                    console.error('Error updating UI:', e);
                }
            },

            init: function() {
                try {
                    // Always start closed for mobile; desktop treated as always visible structurally
                    this.menuVisible = false;
                    this.searchVisible = false;
                    setAttributes(exitcanvashtml, { "data-off-canvas": "hidden" });
                    setAttributes(exitcanvasbody, { "data-off-screen": "hidden" });
                    setAttributes(toggler, { "aria-expanded": "false" });
                } catch(e) {
                    console.error('Error initializing UI state:', e);
                    this.menuVisible = false;
                    this.searchVisible = false;
                }
            }
        };

        UIState.init();

        addEventListenerWithOptions(toggler, 'click', function(e){
            e.preventDefault();
            UIState.toggleMenu();
        });
        addEventListenerWithOptions(exitcanvas, 'click', function(e){
            e.preventDefault();
            UIState.closeAll();
        });

        document.addEventListener('keydown', function(e){
            if (e.key === 'Escape') { UIState.closeAll(); return; }
            if (e.key === 'm' && e.altKey) { e.preventDefault(); UIState.toggleMenu(); }
            if (UIState.menuVisible) {
                const menuLinks = header.querySelectorAll('a[href], button');
                if (!menuLinks.length) return;
                let currentIndex = Array.from(menuLinks).indexOf(document.activeElement);
                if (currentIndex === -1) return;
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    menuLinks[(currentIndex + 1) % menuLinks.length].focus();
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    menuLinks[(currentIndex - 1 + menuLinks.length) % menuLinks.length].focus();
                }
            }
        });

        function headerHide(mq){
            // Do not auto-open menu on desktop; just refresh UI for current breakpoint
            UIState.menuVisible = false;
            UIState.searchVisible = false;

            setAttributes(exitcanvashtml, { "data-off-canvas": "hidden" });
            setAttributes(exitcanvasbody, { "data-off-screen": "hidden" });
            setAttributes(header, {
                "aria-labelledby": mq.matches ? "menu-trigger" : null,
                "role": "navigation"
            });

            if (!mq.matches) {
                // Ensure any mobile-only aria-hidden attributes are cleared
                document.querySelectorAll('[aria-hidden="true"]').forEach(el=>{
                    if (el.id !== 'global-header' && el.id !== 'exit-off-canvas') el.removeAttribute('aria-hidden');
                });
            }

            UIState.updateUI();
        }
        mediaQuery.addEventListener('change', headerHide);
        headerHide(mediaQuery);

        return UIState;
    }

    /**
     * Sets up the scroll to top button functionality
     * @returns {void}
     */
    function setupScrollToTop() {
        const toTopButton = doc.querySelector('.js-BackToTop');
        if (!elementExists(toTopButton)) {
            console.warn('Back to top button not found, skipping functionality');
            return;
        }

        // Use our enhanced event listener for better touch support
        addEventListenerWithOptions(toTopButton, 'click', function(e) {
            e.preventDefault();
            
            try {
                // Modern smooth scroll with fallback
                if ('scrollBehavior' in document.documentElement.style) {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                } else {
                    // Fallback for browsers that don't support smooth scrolling
                    function _scrollToTop() {
                        if (doc.body.scrollTop !== 0 || doc.documentElement.scrollTop !== 0) {
                            window.scrollBy(0, -50);
                            setTimeout(_scrollToTop, 10);
                        }
                    }
                    _scrollToTop();
                }
            } catch (e) {
                console.error('Error during scroll to top:', e);
                // Simplest fallback
                window.scrollTo(0, 0);
            }
        });
    }

    /**
     * Initializes all functionality
     * @returns {void}
     */
    function init() {
        setupSmallScreenHeader();
        stickyheader();
        globalheadermenu();
        setupScrollToTop();
    }

    // Safe load event handling
    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        init(); // removed unnecessary setTimeout
    } else {
        document.addEventListener('DOMContentLoaded', init);
    }

    // Return public methods if needed for external access
    return {
        // Public API
        elementExists: elementExists,
        setAttributes: setAttributes,
        throttle: throttle,
        debounce: debounce,
        addEventListenerWithOptions: addEventListenerWithOptions
    };

})(window, document);

