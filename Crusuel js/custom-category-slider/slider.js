document.addEventListener('DOMContentLoaded', function() {
    // Initialize all sliders on the page
    document.querySelectorAll('.aws-slider').forEach(sliderEl => {
        new AWSSlider(sliderEl);
    });
});

class AWSSlider {
    constructor(sliderEl) {
        this.slider = sliderEl;
        this.inner = sliderEl.querySelector('.aws-slider-inner');
        this.slides = sliderEl.querySelectorAll('.aws-slide');
        this.dots = sliderEl.querySelectorAll('.aws-dot');
        this.currentIndex = 0;
        this.slideCount = this.slides.length;
        this.isAnimating = false;
        this.autoPlayInterval = null;
        this.prioritizeFirstSlide();
        this.initLazyLoading();
        
        this.init();
    }
   initLazyLoading() {
        const lazyLoadObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.removeAttribute('data-src');
                        lazyLoadObserver.unobserve(img);
                    }
                }
            });
        });

        this.slides.forEach(slide => {
            const img = slide.querySelector('.aws-slide-image');
            if (img && img.dataset.src) {
                lazyLoadObserver.observe(img);
            }
        });
    }

  prioritizeFirstSlide() {
        // Mark first slide as important for LCP
        if (this.slides.length > 0) {
            const firstImg = this.slides[0].querySelector('img');
            if (firstImg) {
                firstImg.loading = 'eager';
                firstImg.fetchpriority = 'high';
                firstImg.decoding = 'sync';
                
                // Force load if not already loaded
                if (!firstImg.complete) {
                    firstImg.addEventListener('load', () => {
                        // Optional: report to analytics that LCP image loaded
                    });
                }
            }
        }
    }
    
    init() {
    // Set up initial slide positions
    this.updateSliderDimensions();
    
    // Safely set up dot navigation if dots exist
    if (this.dots && this.dots.length > 0) {
        this.dots.forEach(dot => {
            dot?.addEventListener('click', (e) => {
                const index = parseInt(e.target?.getAttribute('data-index'));
                if (!isNaN(index)) {
                    this.goToSlide(index);
                }
            });
        });
    }
    
    // Set up touch events for mobile
    this.setupTouchEvents();
    
    // Only start autoplay if there are multiple slides
    if (this.slideCount > 1) {
        this.startAutoPlay();
    }
    
    // Handle window resize
    window.addEventListener('resize', () => {
        this.updateSliderDimensions();
        this.goToSlide(this.currentIndex, false);
    });
}
    
    updateSliderDimensions() {
  // Let CSS handle all dimensions
  this.inner.style.width = null;
  this.slides.forEach(slide => {
    slide.style.width = null;
    slide.style.flexBasis = null;
    slide.style.minWidth = '100%';
  });
}
    
    setupTouchEvents() {
        let touchStartX = 0;
        let touchEndX = 0;
        
        this.inner.addEventListener('touchstart', (e) => {
            touchStartX = e.touches[0].clientX;
            this.pauseAutoPlay();
        }, { passive: true });
        
        this.inner.addEventListener('touchend', (e) => {
            touchEndX = e.changedTouches[0].clientX;
            this.handleSwipe(touchStartX, touchEndX);
            this.resumeAutoPlay();
        }, { passive: true });
    }
    
    handleSwipe(startX, endX) {
        const diff = startX - endX;
        
        if (Math.abs(diff) > 50) { // Minimum swipe distance
            if (diff > 0) {
                this.nextSlide();
            } else {
                this.prevSlide();
            }
        }
    }
    
    goToSlide(index, animate = true) {
        if (this.isAnimating || index < 0 || index >= this.slideCount || index === this.currentIndex) {
            return;
        }
        
        this.isAnimating = animate;
        
        // Calculate translation
        const translateX = -index * 100;
        this.inner.style.transition = animate ? 'transform 0.5s ease' : 'none';
        this.inner.style.transform = `translateX(${translateX}%)`;
        
        // Update dots
        this.updateDots(index);
        
        this.currentIndex = index;
        
        if (animate) {
            setTimeout(() => {
                this.isAnimating = false;
            }, 500);
        }
    }
    
    updateDots(index) {
    // Add null check for dots
    if (!this.dots || this.dots.length === 0) return;
    
    // Add bounds checking
    if (index < 0 || index >= this.dots.length) return;
    
    this.dots.forEach(dot => {
        if (dot && dot.classList) {
            dot.classList.remove('active');
        }
    });
    
    if (this.dots[index] && this.dots[index].classList) {
        this.dots[index].classList.add('active');
    }
}  
    nextSlide() {
        const nextIndex = (this.currentIndex + 1) % this.slideCount;
        this.goToSlide(nextIndex);
    }
    
    prevSlide() {
        const prevIndex = (this.currentIndex - 1 + this.slideCount) % this.slideCount;
        this.goToSlide(prevIndex);
    }
    
    startAutoPlay() {
        this.autoPlayInterval = setInterval(() => {
            this.nextSlide();
        }, 5000);
    }
    
    pauseAutoPlay() {
        if (this.autoPlayInterval) {
            clearInterval(this.autoPlayInterval);
            this.autoPlayInterval = null;
        }
    }
    
    resumeAutoPlay() {
        if (!this.autoPlayInterval) {
            this.startAutoPlay();
        }
    }
}
