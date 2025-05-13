document.addEventListener('DOMContentLoaded', function() {
    // =============================================
    // STATE AND CONFIGURATION
    // =============================================
    const state = {
        contentCache: new Map(),
        currentGender: '',
        isLoading: false,
        retryCount: 0,
        maxRetries: 3,
        prefetchedGenders: new Set(),
        carouselInstances: new Map() // Track carousel instances
    };

    const config = {
        ajaxurl: '/wp-admin/admin-ajax.php',
        validGenders: ['women', 'men', 'kids'],
        loadingTimeout: 30000,
        retryDelay: 1000,
        animationDuration: 300,
        prefetchDelay: 2000,
        get nonce() {
            return document.getElementById('gender-tabs-nonce')?.value || '';
        }
    };

    // =============================================
    // DOM ELEMENT CACHE
    // =============================================
    const elements = {
        tabsContainer: document.getElementById('gender-tabs-container'),
        contentContainer: document.getElementById('woocommerce-gender-content'),
        nonceInput: document.getElementById('gender-tabs-nonce'),
        intersectionObserver: null,
        get allTabs() {
            return Array.from(document.querySelectorAll('.tab-btn'));
        },
        get allMenus() {
            return Array.from(document.querySelectorAll('.comboMenu'));
        }
    };

    // =============================================
    // CAROUSEL AND SLIDER INITIALIZATION
    // =============================================
    const CarouselManager = {
        async initializeAll() {
            // Clean up existing instances first
            await this.destroyAll();

            // Force a small delay to ensure DOM is ready
            await new Promise(resolve => setTimeout(resolve, 100));

            // Initialize banner carousel
            if (typeof ABCarousel !== 'undefined') {
                const bannerCarousels = document.querySelectorAll('.abc-banner-carousel:not(.initialized)');
                bannerCarousels.forEach(carousel => {
                    if (carousel) {
                        new ABCarousel(carousel);
                        carousel.classList.add('initialized');
                    }
                });
            }

            // Initialize category grid
            if (typeof initResponsiveGrids !== 'undefined') {
                initResponsiveGrids();
            }

            // Initialize image loading states
            if (typeof initImageLoadStates !== 'undefined') {
                initImageLoadStates();
            }

            // Initialize countdown timer
            if (typeof WCCountdownTimer !== 'undefined') {
                new WCCountdownTimer();
            }

            // Initialize custom category slider
            if (typeof AWSSlider !== 'undefined') {
                document.querySelectorAll('.aws-slider:not(.initialized)').forEach(slider => {
                    if (slider) {
                        new AWSSlider(slider);
                        slider.classList.add('initialized');
                    }
                });
            }

            // Initialize product carousel with proper cleanup and visibility handling
            const productCarousels = document.querySelectorAll('.pc-carousel-wrapper:not(.initialized)');
            for (const carousel of productCarousels) {
                if (carousel && typeof ProductCarousel !== 'undefined') {
                    try {
                        // Ensure container exists and has children
                        const container = carousel.querySelector('.pc-carousel-container');
                        if (!container || !container.children.length) {
                            console.warn('Invalid carousel structure:', carousel);
                            continue;
                        }

                        // Force reflow and ensure visibility
                        carousel.style.display = 'none';
                        carousel.offsetHeight; // Force reflow
                        carousel.style.display = '';
                        carousel.style.visibility = 'hidden';

                        // Initialize carousel
                        const instance = new ProductCarousel(carousel);
                        state.carouselInstances.set(carousel, instance);

                        // Show carousel after initialization
                        carousel.style.visibility = '';
                        carousel.classList.add('initialized');

                        // Force layout update
                        instance.handleResize();
                    } catch (error) {
                        console.error('Error initializing product carousel:', error);
                    }
                }
            }

            // Initialize offers carousel
            const offerCarousels = document.querySelectorAll('.oc-carousel-wrapper:not(.initialized)');
            offerCarousels.forEach(carousel => {
                if (carousel) {
                    if (!carousel.getAttribute('data-original-content')) {
                        carousel.setAttribute('data-original-content', carousel.innerHTML);
                    }
                    carousel.classList.remove('cg-carousel-mode');
                    
                    if (typeof window.initCarousel === 'function') {
                        try {
                            window.initCarousel(carousel);
                            carousel.classList.add('initialized');
                        } catch (error) {
                            console.error('Error initializing offer carousel:', error);
                        }
                    }
                }
            });

            // Trigger resize event after all carousels are initialized
            window.dispatchEvent(new Event('resize'));
        },

        async destroyAll() {
            // Clean up product carousel instances
            state.carouselInstances.forEach((instance, element) => {
                try {
                    if (instance && typeof instance.destroy === 'function') {
                        instance.destroy();
                    }
                    if (element) {
                        element.style.visibility = '';
                        element.style.display = '';
                    }
                } catch (error) {
                    console.error('Error destroying carousel instance:', error);
                }
            });
            state.carouselInstances.clear();

            // Clean up other carousels
            if (window.abcCarousels) {
                window.abcCarousels.forEach(carousel => {
                    if (carousel && carousel.cleanup) carousel.cleanup();
                });
                window.abcCarousels = [];
            }

            // Remove initialized classes
            document.querySelectorAll('.initialized').forEach(el => {
                el.classList.remove('initialized');
            });

            // Clean up observers
            if (typeof imageObserver !== 'undefined' && imageObserver) {
                document.querySelectorAll('.cg-category-image').forEach(img => {
                    if (img) imageObserver.unobserve(img);
                });
            }

            // Wait for cleanup to complete
            await new Promise(resolve => setTimeout(resolve, 100));
        }
    };

    // =============================================
    // PERFORMANCE UTILITIES
    // =============================================
    class Performance {
        constructor() {
            this.prefetchContent = this.prefetchContent.bind(this);
            this.setupIntersectionObserver = this.setupIntersectionObserver.bind(this);
            
            this.requestIdleCallback = (window.requestIdleCallback || 
                ((cb) => setTimeout(() => cb({
                    didTimeout: false,
                    timeRemaining: () => 1
                }), 1))).bind(window);

            this.cancelIdleCallback = (window.cancelIdleCallback || window.clearTimeout).bind(window);
        }

        async prefetchContent(gender) {
            if (state.contentCache.has(gender) || state.prefetchedGenders.has(gender)) return;
            
            state.prefetchedGenders.add(gender);
            
            try {
                const formData = new URLSearchParams({
                    action: 'load_gender_content',
                    gender,
                    nonce: config.nonce
                });

                const response = await fetch(config.ajaxurl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                        'X-WP-Nonce': config.nonce,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    state.contentCache.set(gender, data.data.content);
                }
            } catch (error) {
                console.warn(`Prefetch failed for ${gender}:`, error);
            }
        }

        setupIntersectionObserver() {
            if (!('IntersectionObserver' in window)) return;

            const observerCallback = (entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        const gender = entry.target.dataset.gender;
                        if (gender) {
                            this.prefetchContent(gender);
                        }
                    }
                });
            };

            elements.intersectionObserver = new IntersectionObserver(
                observerCallback.bind(this),
                { threshold: 0.1 }
            );

            elements.allTabs.forEach(tab => {
                elements.intersectionObserver.observe(tab);
            });
        }
    }

    const performance = new Performance();

    // =============================================
    // UTILITIES
    // =============================================
    const utils = {
        debounce(func, wait) {
            let timeout;
            return function(...args) {
                clearTimeout(timeout);
                timeout = setTimeout(() => func.apply(this, args), wait);
            };
        },

        async fetchWithTimeout(url, options, timeout) {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), timeout);
            
            try {
                const response = await fetch(url, { ...options, signal: controller.signal });
                clearTimeout(timeoutId);
                return response;
            } catch (error) {
                clearTimeout(timeoutId);
                throw error;
            }
        },

        delay(ms) {
            return new Promise(resolve => setTimeout(resolve, ms));
        },

        extractGenderFromUrl(url) {
            return config.validGenders.find(gender => url.includes(`/${gender}`)) || 'men';
        },

        isValidGender(gender) {
            return config.validGenders.includes(gender);
        },

        handleError(error, retryCallback) {
            console.error('Error:', error);
            const message = error.message || 'An error occurred. Please try again.';
            UI.showErrorState(message, true, retryCallback);
        }
    };

    // =============================================
    // UI MANAGEMENT
    // =============================================
    const UI = {
        updateActiveTab(activeBtn) {
            if (!activeBtn) return;
            
            elements.allMenus.forEach(menu => {
                const btn = menu.querySelector('.tab-btn');
                menu.classList.remove('home-active-tab-class');
                if (btn) {
                    btn.style.fontWeight = '400';
                    btn.setAttribute('aria-selected', 'false');
                    btn.setAttribute('tabindex', '-1');
                }
            });
            
            const activeMenu = activeBtn.closest('.comboMenu');
            if (activeMenu) {
                activeMenu.classList.add('home-active-tab-class');
                activeBtn.style.fontWeight = '600';
                activeBtn.setAttribute('aria-selected', 'true');
                activeBtn.setAttribute('tabindex', '0');
            }
        },

        addRippleEffect(button, event) {
            const ripple = button.querySelector('.ripple');
            if (!ripple) return;

            const rect = button.getBoundingClientRect();
            const size = Math.max(rect.width, rect.height);
            
            ripple.style.cssText = `
                width: ${size}px;
                height: ${size}px;
                left: ${event.clientX - rect.left - size/2}px;
                top: ${event.clientY - rect.top - size/2}px;
            `;
            
            ripple.classList.remove('active');
            requestAnimationFrame(() => ripple.classList.add('active'));
        },

        showLoadingState() {
            if (!elements.contentContainer) return;
            
            elements.contentContainer.classList.add('loading');
            elements.contentContainer.setAttribute('aria-busy', 'true');
            elements.contentContainer.innerHTML = `
                <div class="loading-content" role="status" aria-live="polite">
                    <div class="loading-spinner" aria-hidden="true"></div>
                    <div>Loading collection...</div>
                </div>`;
        },

        showErrorState(message, isRetryable = true, retryCallback) {
            if (!elements.contentContainer) return;
            
            elements.contentContainer.classList.remove('loading');
            elements.contentContainer.setAttribute('aria-busy', 'false');
            elements.contentContainer.innerHTML = `
                <div class="error-message" role="alert">
                    <p>${message || 'Error loading content. Please try again.'}</p>
                    ${isRetryable ? '<button class="retry-btn" type="button">Retry</button>' : ''}
                </div>`;

            if (isRetryable && retryCallback) {
                const retryBtn = elements.contentContainer.querySelector('.retry-btn');
                retryBtn?.addEventListener('click', retryCallback, { once: true });
            }
        },

        updateBodyClass(gender) {
            document.body.classList.remove('men-page', 'women-page', 'kids-page');
            document.body.classList.add(`${gender}-page`);
        },

        ensureTabsVisible() {
            if (!elements.tabsContainer) return;
            
            elements.tabsContainer.style.cssText = `
                display: flex !important;
                visibility: visible !important;
                opacity: 1 !important;
                position: relative !important;
                height: 50px !important;
            `;
        },

        setupAccessibility() {
            elements.tabsContainer?.setAttribute('role', 'tablist');
            elements.allTabs.forEach(tab => {
                tab.setAttribute('role', 'tab');
                tab.setAttribute('aria-selected', 'false');
                tab.setAttribute('tabindex', '-1');
            });
            elements.contentContainer?.setAttribute('role', 'tabpanel');
        }
    };

    async function loadContent(url, isRetry = false) {
        if (!elements.contentContainer || state.isLoading) return;

        state.isLoading = true;
        UI.showLoadingState();

        try {
            const gender = utils.extractGenderFromUrl(url);
            const formData = new URLSearchParams({
                action: 'load_gender_content',
                gender,
                nonce: config.nonce
            });

            const response = await fetch(config.ajaxurl, {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (!data.success) throw new Error('Failed to load content');

            // Clean up existing carousels
            await CarouselManager.destroyAll();

            // Update content
            elements.contentContainer.innerHTML = data.data.content;

            // Add longer delay before initialization
            await utils.delay(500);
            
            // Initialize all carousels
            await CarouselManager.initializeAll();

            // Additional delay before triggering resize
            await utils.delay(200);

            // Trigger a resize event to ensure proper layout
            window.dispatchEvent(new Event('resize'));

            window.dispatchEvent(new CustomEvent('gender-tab-loaded', {
                detail: { gender }
            }));

            state.contentCache.set(gender, data.data.content);
            history.pushState({ gender }, '', url);
            
        } catch (error) {
            console.error('Tab load error:', error);
            UI.showErrorState('Failed to load content. Please try again.');
        } finally {
            state.isLoading = false;
            elements.contentContainer.classList.remove('loading');
        }
    }

    // =============================================
    // EVENT HANDLERS
    // =============================================
    function initializeEventListeners() {
        elements.tabsContainer?.addEventListener('click', (e) => {
            const tabBtn = e.target.closest('.tab-btn');
            if (!tabBtn) return;
            
            e.preventDefault();
            UI.addRippleEffect(tabBtn, e);
            UI.updateActiveTab(tabBtn);
            loadContent(tabBtn.href);
        });

        elements.tabsContainer?.addEventListener('keydown', (e) => {
            const targetTab = e.target.closest('.tab-btn');
            if (!targetTab) return;

            let newTab;
            switch (e.key) {
                case 'ArrowLeft':
                case 'ArrowUp':
                    e.preventDefault();
                    newTab = targetTab.closest('.comboMenu').previousElementSibling?.querySelector('.tab-btn');
                    break;
                case 'ArrowRight':
                case 'ArrowDown':
                    e.preventDefault();
                    newTab = targetTab.closest('.comboMenu').nextElementSibling?.querySelector('.tab-btn');
                    break;
                case 'Home':
                    e.preventDefault();
                    newTab = elements.allTabs[0];
                    break;
                case 'End':
                    e.preventDefault();
                    newTab = elements.allTabs[elements.allTabs.length - 1];
                    break;
            }

            if (newTab) {
                newTab.focus();
                UI.updateActiveTab(newTab);
                loadContent(newTab.href);
            }
        });

        window.addEventListener('popstate', (e) => {
            if (e.state?.gender) {
                const tabBtn = document.querySelector(`.tab-btn.${e.state.gender}`);
                if (tabBtn) {
                    UI.updateActiveTab(tabBtn);
                    loadContent(tabBtn.href);
                }
            }
        });
    }

    // =============================================
    // INITIALIZATION
    // =============================================
    function init() {
        UI.ensureTabsVisible();
        UI.setupAccessibility();
        
        const path = window.location.pathname;
        const initialGender = path.includes('/women') ? 'women' : 
                            path.includes('/kids') ? 'kids' : 'men';
        
        const initialTab = document.querySelector(`.tab-btn.${initialGender}`);
        if (initialTab) {
            UI.updateActiveTab(initialTab);
            UI.updateBodyClass(initialGender);
        }

        CarouselManager.initializeAll();
        initializeEventListeners();
        performance.setupIntersectionObserver();

        setTimeout(() => {
            config.validGenders.forEach(gender => {
                if (gender !== initialGender) {
                    performance.requestIdleCallback(() => {
                        performance.prefetchContent(gender);
                    });
                }
            });
        }, config.prefetchDelay);
    }

    init();
});
