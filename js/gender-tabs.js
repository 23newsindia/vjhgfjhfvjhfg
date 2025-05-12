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
        prefetchedGenders: new Set()
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
    // DOM ELEMENT CACHE AND PERFORMANCE OPTIMIZATION
    // =============================================
    const elements = {
        tabsContainer: document.getElementById('gender-tabs-container'),
        contentContainer: document.getElementById('woocommerce-gender-content'),
        nonceInput: document.getElementById('gender-tabs-nonce'),
        intersectionObserver: null,
        get allTabs() {
            return [...document.querySelectorAll('.tab-btn')];
        },
        get allMenus() {
            return [...document.querySelectorAll('.comboMenu')];
        }
    };

    // =============================================
    // PERFORMANCE UTILITIES
    // =============================================
    const performance = {
        requestIdleCallback: window.requestIdleCallback || function(cb) {
            return setTimeout(() => cb({
                didTimeout: false,
                timeRemaining: () => 1
            }), 1);
        },

        cancelIdleCallback: window.cancelIdleCallback || window.clearTimeout,

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
        },

        setupIntersectionObserver() {
            if (!('IntersectionObserver' in window)) return;

            elements.intersectionObserver = new IntersectionObserver(
                (entries) => {
                    entries.forEach(entry => {
                        if (entry.isIntersecting) {
                            const gender = entry.target.dataset.gender;
                            if (gender) {
                                performance.requestIdleCallback(() => {
                                    performance.prefetchContent(gender);
                                });
                            }
                        }
                    });
                },
                { threshold: 0.1 }
            );

            elements.allTabs.forEach(tab => {
                elements.intersectionObserver?.observe(tab);
            });
        }
    };

    // =============================================
    // ENHANCED UTILITIES
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
    // ENHANCED UI MANAGEMENT WITH ACCESSIBILITY
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

    // =============================================
    // ENHANCED CONTENT MANAGEMENT
    // =============================================
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

        // Parse and insert HTML
        const parser = new DOMParser();
        const doc = parser.parseFromString(data.data.content, 'text/html');
        elements.contentContainer.innerHTML = doc.body.innerHTML;

        // Execute scripts safely
        const scripts = elements.contentContainer.querySelectorAll('script');
        scripts.forEach(script => {
            const newScript = document.createElement('script');
            if (script.src) {
                newScript.src = script.src;
                newScript.async = false;
            } else {
                newScript.textContent = script.textContent;
            }
            document.body.appendChild(newScript).parentNode.removeChild(newScript);
        });

        // Dispatch custom event
        window.dispatchEvent(new CustomEvent('gender-tab-loaded', {
            detail: { gender }
        }));

        // Cache content
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
    // ENHANCED EVENT HANDLERS
    // =============================================
    function initializeEventListeners() {
        // Tab click handler with keyboard support
        elements.tabsContainer?.addEventListener('click', (e) => {
            const tabBtn = e.target.closest('.tab-btn');
            if (!tabBtn) return;
            
            e.preventDefault();
            UI.addRippleEffect(tabBtn, e);
            UI.updateActiveTab(tabBtn);
            loadContent(tabBtn.href);
        });

        // Keyboard navigation
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

        // Browser navigation
        window.addEventListener('popstate', (e) => {
            if (e.state?.gender) {
                const tabBtn = document.querySelector(`.tab-btn.${e.state.gender}`);
                if (tabBtn) {
                    UI.updateActiveTab(tabBtn);
                    loadContent(tabBtn.href);
                }
            }
        });

        // Visibility observer
        if ('IntersectionObserver' in window) {
            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.attributeName === 'style') {
                        UI.ensureTabsVisible();
                    }
                });
            });

            if (elements.tabsContainer) {
                observer.observe(elements.tabsContainer, {
                    attributes: true,
                    attributeFilter: ['style']
                });
            }
        }
    }
// Slider reinitialization handler
function handleSliderReinit() {
    if (typeof Swiper === 'function') {
        document.querySelectorAll('.gender-slider').forEach(slider => {
            new Swiper(slider, {
                loop: true,
                pagination: { el: '.swiper-pagination' }
            });
        });
    }
    
    // Initialize other sliders (e.g., offers_carousel)
    if (typeof jQuery !== 'undefined' && jQuery().owlCarousel) {
        jQuery('.offers-carousel').owlCarousel({ /* options */ });
    }
}

// Listen for tab changes
window.addEventListener('gender-tab-loaded', handleSliderReinit);

  

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

        initializeEventListeners();
        performance.setupIntersectionObserver();

        // Prefetch other content after initial load
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
