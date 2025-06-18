/*
 * Mobile Navigation JavaScript
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
            } else {
                icon.className = 'fas fa-bars';
            }
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!navToggler.contains(e.target) && 
                !navCollapse.contains(e.target) && 
                navCollapse.classList.contains('show')) {
                navCollapse.classList.remove('show');
                navToggler.querySelector('i').className = 'fas fa-bars';
            }
        });
        
        // Close menu on window resize
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 1024) {
                navCollapse.classList.remove('show');
                navToggler.querySelector('i').className = 'fas fa-bars';
            }
        });
    }
    
    // Touch device optimizations
    if ('ontouchstart' in window) {
        document.body.classList.add('touch-device');
    }
}); 