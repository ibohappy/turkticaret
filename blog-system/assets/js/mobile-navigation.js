/*
 * Mobile-First Navigation JavaScript
 * Enhanced Blog System
 */

document.addEventListener('DOMContentLoaded', function() {
    // Mobile navigation toggle
    const navToggler = document.querySelector('.navbar-toggler');
    const navCollapse = document.querySelector('.navbar-collapse');
    
    if (navToggler && navCollapse) {
        navToggler.addEventListener('click', function(e) {
            e.preventDefault();
            navCollapse.classList.toggle('show');
            
            // Toggle hamburger icon
            const icon = this.querySelector('i');
            if (navCollapse.classList.contains('show')) {
                icon.className = 'fas fa-times';
                document.body.style.overflow = 'hidden'; // Prevent scrolling when menu is open
            } else {
                icon.className = 'fas fa-bars';
                document.body.style.overflow = '';
            }
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navToggler.contains(e.target) && 
                !navCollapse.contains(e.target) && 
                navCollapse.classList.contains('show')) {
                navCollapse.classList.remove('show');
                navToggler.querySelector('i').className = 'fas fa-bars';
                document.body.style.overflow = '';
            }
        });
        
        // Close mobile menu when clicking on a link
        const navLinks = navCollapse.querySelectorAll('.nav-link');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth < 1024) { // Only on mobile/tablet
                    navCollapse.classList.remove('show');
                    navToggler.querySelector('i').className = 'fas fa-bars';
                    document.body.style.overflow = '';
                }
            });
        });
        
        // Handle window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                navCollapse.classList.remove('show');
                navToggler.querySelector('i').className = 'fas fa-bars';
                document.body.style.overflow = '';
            }
        });
    }
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Touch device optimizations
    if ('ontouchstart' in window) {
        document.body.classList.add('touch-device');
        
        // Add touch feedback to interactive elements
        const touchElements = document.querySelectorAll('.btn, .card, .nav-link, .category-card');
        touchElements.forEach(element => {
            element.addEventListener('touchstart', function() {
                this.classList.add('touch-active');
            });
            
            element.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.classList.remove('touch-active');
                }, 150);
            });
        });
    }
    
    // Lazy loading for images
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    if (img.dataset.src) {
                        img.src = img.dataset.src;
                        img.classList.remove('lazy');
                        img.classList.add('loaded');
                        observer.unobserve(img);
                    }
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.01
        });
        
        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
    
    // Performance optimization: throttled scroll event
    let ticking = false;
    function updateOnScroll() {
        const scrolled = window.scrollY > 50;
        document.body.classList.toggle('scrolled', scrolled);
        
        // Hide mobile menu when scrolling
        if (scrolled && navCollapse && navCollapse.classList.contains('show')) {
            navCollapse.classList.remove('show');
            if (navToggler) {
                navToggler.querySelector('i').className = 'fas fa-bars';
            }
            document.body.style.overflow = '';
        }
        
        ticking = false;
    }
    
    window.addEventListener('scroll', function() {
        if (!ticking) {
            requestAnimationFrame(updateOnScroll);
            ticking = true;
        }
    });
    
    // Form validation enhancements
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input[required], textarea[required], select[required]');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
            
            input.addEventListener('input', function() {
                if (this.classList.contains('is-invalid')) {
                    validateField(this);
                }
            });
        });
        
        form.addEventListener('submit', function(e) {
            let isValid = true;
            
            inputs.forEach(input => {
                if (!validateField(input)) {
                    isValid = false;
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                
                // Focus first invalid field
                const firstInvalid = form.querySelector('.is-invalid');
                if (firstInvalid) {
                    firstInvalid.focus();
                }
            }
        });
    });
    
    function validateField(field) {
        const value = field.value.trim();
        let isValid = true;
        let message = '';
        
        // Check if required field is empty
        if (field.hasAttribute('required') && value === '') {
            isValid = false;
            message = 'Bu alan zorunludur.';
        }
        
        // Validate email
        if (field.type === 'email' && value !== '') {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(value)) {
                isValid = false;
                message = 'Geçerli bir e-posta adresi girin.';
            }
        }
        
        // Update field appearance
        if (isValid) {
            field.classList.remove('is-invalid');
            field.classList.add('is-valid');
        } else {
            field.classList.remove('is-valid');
            field.classList.add('is-invalid');
        }
        
        // Show/hide error message
        let errorElement = field.parentElement.querySelector('.invalid-feedback');
        if (!errorElement) {
            errorElement = document.createElement('div');
            errorElement.className = 'invalid-feedback';
            field.parentElement.appendChild(errorElement);
        }
        errorElement.textContent = message;
        
        return isValid;
    }
    
    // Search form enhancements
    const searchInputs = document.querySelectorAll('input[type="search"], input[name*="search"]');
    searchInputs.forEach(input => {
        let searchTimeout;
        
        input.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            const query = this.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    // Add visual feedback
                    this.classList.add('searching');
                    
                    // Remove feedback after a short delay
                    setTimeout(() => {
                        this.classList.remove('searching');
                    }, 500);
                }, 300);
            }
        });
        
        // Clear search
        const clearButton = document.createElement('button');
        clearButton.type = 'button';
        clearButton.className = 'btn btn-sm btn-outline-secondary search-clear';
        clearButton.innerHTML = '<i class="fas fa-times"></i>';
        clearButton.style.display = 'none';
        
        clearButton.addEventListener('click', function() {
            input.value = '';
            this.style.display = 'none';
            input.focus();
        });
        
        input.addEventListener('input', function() {
            clearButton.style.display = this.value ? 'block' : 'none';
        });
        
        // Insert clear button after input
        if (input.parentElement.classList.contains('input-group') || 
            input.parentElement.classList.contains('d-flex')) {
            input.parentElement.appendChild(clearButton);
        }
    });
    
    // Loading states for buttons
    const buttons = document.querySelectorAll('button[type="submit"], input[type="submit"]');
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            const form = this.closest('form');
            if (form && form.checkValidity()) {
                this.disabled = true;
                const originalText = this.innerHTML;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Yükleniyor...';
                
                // Re-enable after 5 seconds (fallback)
                setTimeout(() => {
                    this.disabled = false;
                    this.innerHTML = originalText;
                }, 5000);
            }
        });
    });
    
    // Accessibility improvements
    document.addEventListener('keydown', function(e) {
        // Escape key closes mobile menu
        if (e.key === 'Escape' && navCollapse && navCollapse.classList.contains('show')) {
            navCollapse.classList.remove('show');
            if (navToggler) {
                navToggler.querySelector('i').className = 'fas fa-bars';
                navToggler.focus();
            }
            document.body.style.overflow = '';
        }
        
        // Enter key activates buttons
        if (e.key === 'Enter' && e.target.classList.contains('btn')) {
            e.target.click();
        }
    });
    
    // Focus management for mobile menu
    if (navToggler && navCollapse) {
        const focusableElements = navCollapse.querySelectorAll('a, button, input, select, textarea');
        const firstFocusable = focusableElements[0];
        const lastFocusable = focusableElements[focusableElements.length - 1];
        
        navToggler.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                this.click();
                if (navCollapse.classList.contains('show') && firstFocusable) {
                    firstFocusable.focus();
                }
            }
        });
        
        // Trap focus within mobile menu
        document.addEventListener('keydown', function(e) {
            if (navCollapse.classList.contains('show') && e.key === 'Tab') {
                if (e.shiftKey) {
                    if (document.activeElement === firstFocusable) {
                        e.preventDefault();
                        lastFocusable.focus();
                    }
                } else {
                    if (document.activeElement === lastFocusable) {
                        e.preventDefault();
                        firstFocusable.focus();
                    }
                }
            }
        });
    }
    
    console.log('🚀 Mobile-first navigation initialized successfully!');
}); 