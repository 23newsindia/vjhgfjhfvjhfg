// Optimized countdown timer implementation
class WCCountdownTimer {
    constructor() {
        this.timers = new Map();
        this.serverTimeDiff = 0;
        this.init();
    }

    init() {
        // Calculate server time difference
        const serverTime = window.wcCountdownSettings?.serverTime || Math.floor(Date.now() / 1000);
        this.serverTimeDiff = serverTime - Math.floor(Date.now() / 1000);

        // Initialize timers
        this.initializeAllTimers();
        
        // Use RequestAnimationFrame for smooth updates
        this.animate();
        
        // Handle visibility changes
        document.addEventListener('visibilitychange', () => {
            if (document.visibilityState === 'visible') {
                this.initializeAllTimers();
            }
        });

        // Handle dynamic content
        this.observeNewTimers();
    }

    initializeAllTimers() {
        document.querySelectorAll('[class*="wc-countdown-timer-"]:not([data-initialized])').forEach(el => {
            this.setupTimer(el);
            el.dataset.initialized = 'true';
        });
    }

    setupTimer(element) {
        const timerId = element.id;
        const startHour = parseInt(element.dataset.startHour) || 23;
        const endHour = parseInt(element.dataset.endHour) || 23;
        
        // Get or calculate end time
        let endTime = this.getEndTime(startHour, endHour);
        
        // Store timer data
        this.timers.set(timerId, {
            element,
            endTime,
            startHour,
            endHour,
            isMobile: element.classList.contains('wc-countdown-timer-mobile'),
            isCompact: element.dataset.compact === 'true'
        });

        // Apply initial styles
        this.applyStyles(element);
    }

    getEndTime(startHour, endHour) {
        const now = this.getCurrentTime();
        const currentHour = new Date(now * 1000).getHours();
        
        let endTime;
        if (currentHour >= endHour || currentHour < startHour) {
            // Set for next day's start hour
            endTime = new Date();
            endTime.setDate(endTime.getDate() + 1);
            endTime.setHours(startHour, 0, 0, 0);
        } else {
            // Set for today's end hour
            endTime = new Date();
            endTime.setHours(endHour, 0, 0, 0);
        }
        
        return Math.floor(endTime.getTime() / 1000);
    }

    getCurrentTime() {
        return Math.floor(Date.now() / 1000) + this.serverTimeDiff;
    }

    animate() {
        const now = this.getCurrentTime();
        
        this.timers.forEach((timer, timerId) => {
            const timeLeft = timer.endTime - now;
            
            if (timeLeft <= 0) {
                // Reset timer for next period
                timer.endTime = this.getEndTime(timer.startHour, timer.endHour);
            }
            
            this.updateDisplay(timer.element, timer.endTime - now, timer.isMobile);
        });

        requestAnimationFrame(() => this.animate());
    }

    updateDisplay(element, timeLeft, isMobile) {
        const hours = Math.floor(timeLeft / 3600);
        const minutes = Math.floor((timeLeft % 3600) / 60);
        const seconds = timeLeft % 60;

        if (isMobile) {
            const numberEl = element.querySelector('.number1');
            if (numberEl) {
                numberEl.textContent = `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
            }
        } else {
            const numbers = element.querySelectorAll('.number');
            if (numbers.length >= 3) {
                numbers[0].textContent = String(hours).padStart(2, '0');
                numbers[1].textContent = String(minutes).padStart(2, '0');
                numbers[2].textContent = String(seconds).padStart(2, '0');
            }
        }
    }

    applyStyles(element) {
        const bgColor = element.dataset.bgColor || '#dedede';
        const textColor = element.dataset.textColor || '#ed2d2f';
        const labelColor = element.dataset.labelColor || '#7b7b72';

        if (element.classList.contains('wc-countdown-timer-mobile')) {
            element.querySelector('.number1')?.style.setProperty('color', textColor, 'important');
        } else {
            element.querySelectorAll('.number').forEach(el => {
                el.style.setProperty('background-color', bgColor, 'important');
                el.style.setProperty('color', textColor, 'important');
            });

            element.querySelectorAll('.unit').forEach(el => {
                el.style.setProperty('color', labelColor, 'important');
            });
        }
    }

    observeNewTimers() {
        const observer = new MutationObserver(mutations => {
            mutations.forEach(mutation => {
                if (mutation.addedNodes.length) {
                    this.initializeAllTimers();
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => new WCCountdownTimer());
} else {
    new WCCountdownTimer();
}
