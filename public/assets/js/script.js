// Main JavaScript File for CURA Pharma Locator

// Wait for DOM to be fully loaded
document.addEventListener('DOMContentLoaded', function() {
    // ===========================
    // COMMON FUNCTIONALITY
    // ===========================
    
    // Initialize AOS (Animate on Scroll) - for all pages
    if (typeof AOS !== 'undefined') {
        AOS.init({
            duration: 1000,
            once: true,
            offset: 100
        });
    }
    
    // Mobile menu toggle - FIXED VERSION
    const mobileMenuButton = document.getElementById('mobile-menu-button');
    const mobileMenu = document.getElementById('mobile-menu');
    
    if (mobileMenuButton && mobileMenu) {
        mobileMenuButton.addEventListener('click', function() {
            if (mobileMenu.classList.contains('hidden')) {
                mobileMenu.classList.remove('hidden');
                this.innerHTML = '<i class="fas fa-times text-2xl"></i>';
            } else {
                mobileMenu.classList.add('hidden');
                this.innerHTML = '<i class="fas fa-bars text-2xl"></i>';
            }
        });
    }
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (!mobileMenu || !mobileMenuButton) return;
        
        const isClickInsideMenu = mobileMenu.contains(event.target);
        const isClickOnButton = mobileMenuButton.contains(event.target);
        
        if (!isClickInsideMenu && !isClickOnButton && !mobileMenu.classList.contains('hidden')) {
            mobileMenu.classList.add('hidden');
            if (mobileMenuButton) {
                mobileMenuButton.innerHTML = '<i class="fas fa-bars text-2xl"></i>';
            }
        }
    });
    
    // Set active navigation link
    function setActiveNavLink() {
        const currentPage = window.location.pathname.split('/').pop() || 'index.html';
        const navLinks = document.querySelectorAll('.nav-link, .mobile-nav-link');
        
        navLinks.forEach(link => {
            const href = link.getAttribute('href');
            // Remove active class from all links
            link.classList.remove('active');
            
            // Add active class to current page link
            if (href === currentPage || (currentPage === '' && href === 'index.html')) {
                link.classList.add('active');
            }
        });
    }
    
    // Set active navigation link on page load
    setActiveNavLink();
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            const targetId = this.getAttribute('href');
            if (targetId === '#') return;
            
            const targetElement = document.querySelector(targetId);
            if (targetElement) {
                e.preventDefault();
                window.scrollTo({
                    top: targetElement.offsetTop - 100,
                    behavior: 'smooth'
                });
                
                // Close mobile menu if open
                if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                    mobileMenu.classList.add('hidden');
                    if (mobileMenuButton) {
                        mobileMenuButton.innerHTML = '<i class="fas fa-bars text-2xl"></i>';
                    }
                }
            }
        });
    });
    
    // ===========================
    // HOME PAGE FUNCTIONALITY
    // ===========================
    
    // Animated counters for hero section
    function animateCounter(elementId, finalValue, duration = 2000) {
        const element = document.getElementById(elementId);
        if (!element) return;
        
        let startValue = 0;
        const increment = finalValue / (duration / 16);
        let current = 0;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= finalValue) {
                element.textContent = finalValue.toLocaleString();
                clearInterval(timer);
            } else {
                element.textContent = Math.floor(current).toLocaleString();
            }
        }, 16);
    }
    
    // Start counters when page loads (only on home page)
    if (document.querySelector('.hero-section')) {
        setTimeout(() => {
            animateCounter('pharmacyCount', 543);
            animateCounter('medicationCount', 12470);
            animateCounter('reservationCount', 8921);
        }, 500);
    }
    
    // ===========================
    // AUTH PAGES FUNCTIONALITY
    // ===========================
    
    // Show toast notification
    function showToast(message, type = 'info') {
        // Check if Toastify is loaded
        if (typeof Toastify === 'undefined') {
            console.log(message);
            return;
        }
        
        const backgroundColor = type === 'success' ? '#10b981' : 
                              type === 'error' ? '#ef4444' : 
                              type === 'warning' ? '#f59e0b' : 
                              '#3b82f6';
        
        Toastify({
            text: message,
            duration: 3000,
            gravity: "top",
            position: "right",
            backgroundColor: backgroundColor,
            stopOnFocus: true,
            className: "toastify"
        }).showToast();
    }
    
    // Email validation
    function validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    }
    
    // Login Page Functionality
    if (document.getElementById('loginForm')) {
        // Password visibility toggle
        const togglePasswordBtn = document.getElementById('togglePassword');
        const passwordInput = document.getElementById('password');
        
        if (togglePasswordBtn && passwordInput) {
            togglePasswordBtn.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                this.innerHTML = type === 'password' ? '<i class="fas fa-eye"></i>' : '<i class="fas fa-eye-slash"></i>';
            });
        }
        
        // Login form submission
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const email = document.getElementById('email')?.value || '';
                const password = document.getElementById('password')?.value || '';
                
                // Show loading state
                const submitBtn = document.getElementById('submitBtn');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i> Signing In...';
                }
                
                // Simulate API call
                await new Promise(resolve => setTimeout(resolve, 1500));
                
                // Mock login success
                showToast('Login successful! Redirecting...', 'success');
                
                // Redirect to dashboard
                setTimeout(() => {
                    window.location.href = 'dashboard.html';
                }, 1000);
            });
        }
    }
    
    // ===========================
    // COMMON ENHANCEMENTS
    // ===========================
    
    // Add interactive effects to form inputs
    const formInputs = document.querySelectorAll('.form-input');
    formInputs.forEach(input => {
        input.addEventListener('focus', function() {
            const icon = this.parentElement.querySelector('.input-icon');
            if (icon) icon.style.color = '#2563eb';
        });
        
        input.addEventListener('blur', function() {
            const icon = this.parentElement.querySelector('.input-icon');
            if (icon) icon.style.color = '#9ca3af';
        });
    });
});