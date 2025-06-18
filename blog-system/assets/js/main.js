// Modern Blog Sistemi - Ana JavaScript Dosyası
// Gelişmiş kullanıcı deneyimi ve güvenlik özellikleri

// Global değişkenler
const ANIMATION_DURATION = 300;
const DEBOUNCE_DELAY = 300;
const AUTO_SAVE_INTERVAL = 30000; // 30 saniye

// Utility fonksiyonları
const utils = {
    // Debounce function
    debounce(func, delay) {
        let timeoutId;
        return function (...args) {
            clearTimeout(timeoutId);
            timeoutId = setTimeout(() => func.apply(this, args), delay);
        };
    },
    
    // Throttle function
    throttle(func, delay) {
        let lastCall = 0;
        return function (...args) {
            const now = new Date().getTime();
            if (now - lastCall < delay) return;
            lastCall = now;
            return func.apply(this, args);
        };
    },
    
    // Local storage helper
    storage: {
        set(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
            } catch (e) {
                console.warn('LocalStorage not available:', e);
            }
        },
        get(key) {
            try {
                return JSON.parse(localStorage.getItem(key));
            } catch (e) {
                return null;
            }
        },
        remove(key) {
            try {
                localStorage.removeItem(key);
            } catch (e) {
                console.warn('LocalStorage not available:', e);
            }
        }
    },
    
    // Animation helpers
    fadeIn(element, duration = ANIMATION_DURATION) {
        element.style.opacity = '0';
        element.style.display = 'block';
        
        let start = performance.now();
        
        function animate(timestamp) {
            let progress = (timestamp - start) / duration;
            if (progress > 1) progress = 1;
            
            element.style.opacity = progress;
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        }
        
        requestAnimationFrame(animate);
    },
    
    fadeOut(element, duration = ANIMATION_DURATION) {
        let start = performance.now();
        let startOpacity = parseFloat(element.style.opacity) || 1;
        
        function animate(timestamp) {
            let progress = (timestamp - start) / duration;
            if (progress > 1) progress = 1;
            
            element.style.opacity = startOpacity * (1 - progress);
            
            if (progress < 1) {
                requestAnimationFrame(animate);
            } else {
                element.style.display = 'none';
            }
        }
        
        requestAnimationFrame(animate);
    }
};

// Modern Authentication System
class AuthManager {
    constructor() {
        this.failedAttempts = 0;
        this.maxAttempts = 5;
        this.lockoutDuration = 15 * 60 * 1000; // 15 dakika
        this.init();
    }
    
    init() {
        this.setupAutoFill();
        this.setupValidation();
        this.setupSecurity();
    }
    
    setupAutoFill() {
        const usernameField = document.getElementById('username');
        const passwordField = document.getElementById('password');
        
        if (usernameField && passwordField) {
            // Güvenli auto-fill
            const savedUsername = utils.storage.get('lastUsername');
            if (savedUsername && usernameField.value === '') {
                usernameField.value = savedUsername;
            }
            
            // Auto-fill testi
            if (window.location.search.includes('test=1')) {
                usernameField.value = 'admin';
                passwordField.value = 'admin123';
                this.showMessage('Test verileri yüklendi!', 'info');
            }
        }
    }
    
    setupValidation() {
        const form = document.querySelector('form[method="post"]');
        if (!form) return;
        
        form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.validateAndSubmit(form);
        });
        
        // Real-time validation
        const inputs = form.querySelectorAll('input[required]');
        inputs.forEach(input => {
            input.addEventListener('blur', () => this.validateField(input));
            input.addEventListener('input', utils.debounce(() => this.validateField(input), DEBOUNCE_DELAY));
        });
    }
    
    validateField(field) {
        const value = field.value.trim();
        const fieldName = field.name;
        let isValid = true;
        let message = '';
        
        // Reset styles
        field.classList.remove('is-valid', 'is-invalid');
        
        if (!value) {
            isValid = false;
            message = `${this.getFieldLabel(fieldName)} boş olamaz!`;
        } else {
            switch (fieldName) {
                case 'username':
                    if (value.length < 3) {
                        isValid = false;
                        message = 'Kullanıcı adı en az 3 karakter olmalıdır!';
                    }
                    break;
                case 'password':
                    if (value.length < 6) {
                        isValid = false;
                        message = 'Şifre en az 6 karakter olmalıdır!';
                    }
                    break;
            }
        }
        
        field.classList.add(isValid ? 'is-valid' : 'is-invalid');
        
        // Feedback göster
        this.showFieldFeedback(field, message, isValid);
        
        return isValid;
    }
    
    validateAndSubmit(form) {
        const inputs = form.querySelectorAll('input[required]');
        let allValid = true;
        
        inputs.forEach(input => {
            if (!this.validateField(input)) {
                allValid = false;
            }
        });
        
        if (allValid) {
            this.submitForm(form);
        } else {
            this.showMessage('Lütfen tüm alanları doğru şekilde doldurun!', 'error');
        }
    }
    
    async submitForm(form) {
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.innerHTML;
        
        // Loading state
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Giriş yapılıyor...';
        submitBtn.disabled = true;
        
        try {
            // Username'i kaydet (güvenlik için sadece giriş başarılıysa)
            const username = form.querySelector('input[name="username"]').value;
            
            // Form submit
            const formData = new FormData(form);
            const response = await fetch(form.action || window.location.href, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            
            if (response.ok) {
                // Başarılı giriş
                utils.storage.set('lastUsername', username);
                this.showMessage('Giriş başarılı! Yönlendiriliyorsunuz...', 'success');
                
                setTimeout(() => {
                    if (response.redirected) {
                        window.location.href = response.url;
                    } else {
                        window.location.href = 'dashboard.php';
                    }
                }, 1500);
            } else {
                throw new Error('Giriş başarısız');
            }
            
        } catch (error) {
            // Hata durumu
            this.failedAttempts++;
            this.showMessage('Giriş başarısız! Bilgilerinizi kontrol edin.', 'error');
            
            // Rate limiting simulation
            if (this.failedAttempts >= 3) {
                const lockTime = 5000; // 5 saniye
                this.lockForm(lockTime);
            }
            
        } finally {
            // Reset button
            setTimeout(() => {
                submitBtn.innerHTML = originalText;
                submitBtn.disabled = false;
            }, 2000);
        }
    }
    
    lockForm(duration) {
        const form = document.querySelector('form[method="post"]');
        const inputs = form.querySelectorAll('input, button');
        
        inputs.forEach(input => input.disabled = true);
        
        let remaining = Math.ceil(duration / 1000);
        const interval = setInterval(() => {
            this.showMessage(`Çok fazla başarısız giriş! ${remaining} saniye bekleyin...`, 'warning');
            remaining--;
            
            if (remaining <= 0) {
                clearInterval(interval);
                inputs.forEach(input => input.disabled = false);
                this.showMessage('Tekrar deneyebilirsiniz.', 'info');
            }
        }, 1000);
    }
    
    showFieldFeedback(field, message, isValid) {
        // Mevcut feedback'i kaldır
        const existingFeedback = field.parentNode.querySelector('.field-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        if (message) {
            const feedback = document.createElement('div');
            feedback.className = `field-feedback small ${isValid ? 'text-success' : 'text-danger'} mt-1`;
            feedback.innerHTML = `<i class="fas fa-${isValid ? 'check' : 'exclamation-triangle'}"></i> ${message}`;
            field.parentNode.appendChild(feedback);
        }
    }
    
    showMessage(text, type) {
        // Mevcut mesajları kaldır
        const existingAlerts = document.querySelectorAll('.auth-message');
        existingAlerts.forEach(alert => alert.remove());
        
        const alertClass = {
            'success': 'alert-success',
            'error': 'alert-danger',
            'warning': 'alert-warning',
            'info': 'alert-info'
        }[type] || 'alert-info';
        
        const iconClass = {
            'success': 'fa-check-circle',
            'error': 'fa-exclamation-triangle',
            'warning': 'fa-exclamation-triangle',
            'info': 'fa-info-circle'
        }[type] || 'fa-info-circle';
        
        const alert = document.createElement('div');
        alert.className = `alert ${alertClass} auth-message`;
        alert.innerHTML = `<i class="fas ${iconClass}"></i> ${text}`;
        
        const form = document.querySelector('form[method="post"]');
        if (form) {
            form.insertBefore(alert, form.firstChild);
            utils.fadeIn(alert);
            
            // Auto remove
            setTimeout(() => {
                if (alert.parentNode) {
                    utils.fadeOut(alert);
                    setTimeout(() => alert.remove(), ANIMATION_DURATION);
                }
            }, 5000);
        }
    }
    
    getFieldLabel(fieldName) {
        const labels = {
            'username': 'Kullanıcı adı',
            'password': 'Şifre',
            'email': 'E-posta'
        };
        return labels[fieldName] || fieldName;
    }
    
    setupSecurity() {
        // Console temizleme (production)
        if (window.location.hostname !== 'localhost' && !window.location.hostname.includes('127.0.0.1')) {
            console.clear();
            console.log('%c🔒 Güvenlik Uyarısı', 'color: red; font-size: 20px; font-weight: bold;');
            console.log('%cBu konsol geliştirici araçları içindir. Buraya kod yapıştırmayın!', 'color: red; font-size: 14px;');
        }
        
        // Right-click protection (optional)
        if (window.location.search.includes('secure=1')) {
            document.addEventListener('contextmenu', e => e.preventDefault());
            document.addEventListener('selectstart', e => e.preventDefault());
        }
    }
}

// Enhanced UI Manager
class UIManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupTooltips();
        this.setupAlerts();
        this.setupAnimations();
        this.setupKeyboardShortcuts();
        this.setupFormEnhancements();
    }
    
    setupTooltips() {
        // Bootstrap tooltip'lerini aktifleştir
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    }
    
    setupAlerts() {
        // Alert'leri otomatik kapat
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert:not(.auth-message)');
            alerts.forEach(alert => {
                if (alert.classList.contains('fade')) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            });
        }, 5000);
    }
    
    setupAnimations() {
        // Intersection Observer for animations
        if ('IntersectionObserver' in window) {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('animate-in');
                    }
                });
            });
            
            document.querySelectorAll('.card, .alert').forEach(el => {
                observer.observe(el);
            });
        }
        
        // Loading animation
        window.addEventListener('load', () => {
            document.body.classList.add('loaded');
        });
    }
    
    setupKeyboardShortcuts() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + Enter: Submit form
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                const form = document.querySelector('form[method="post"]');
                if (form) {
                    form.dispatchEvent(new Event('submit'));
                }
            }
            
            // Escape: Close modals/alerts
            if (e.key === 'Escape') {
                document.querySelectorAll('.alert').forEach(alert => {
                    utils.fadeOut(alert);
                });
            }
        });
    }
    
    setupFormEnhancements() {
        // Form validation
        const forms = document.querySelectorAll('.needs-validation');
        Array.prototype.slice.call(forms).forEach(form => {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
        
        // Resim yükleme önizlemesi
        this.setupImagePreview();
        
        // Karakter sayacı
        this.setupCharacterCounter();
        
        // Auto-save functionality
        this.setupAutoSave();
    }
    
    setupImagePreview() {
        const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
        imageInputs.forEach(input => {
            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        let preview = document.getElementById('image-preview');
                        if (!preview) {
                            preview = document.createElement('div');
                            preview.id = 'image-preview';
                            preview.className = 'mt-3';
                            input.parentNode.appendChild(preview);
                        }
                        
                        preview.innerHTML = `
                            <div class="text-center">
                                <img src="${e.target.result}" class="img-fluid rounded shadow" style="max-height: 200px;">
                                <p class="mt-2 small text-muted">Önizleme</p>
                            </div>
                        `;
                        utils.fadeIn(preview);
                    };
                    reader.readAsDataURL(file);
                }
            });
        });
    }
    
    setupCharacterCounter() {
        const textareas = document.querySelectorAll('textarea[data-max-length]');
        textareas.forEach(textarea => {
            const maxLength = parseInt(textarea.getAttribute('data-max-length'));
            const counter = document.createElement('small');
            counter.className = 'text-muted character-counter';
            counter.style.float = 'right';
            textarea.parentNode.appendChild(counter);
            
            const updateCounter = () => {
                const currentLength = textarea.value.length;
                counter.textContent = `${currentLength}/${maxLength} karakter`;
                
                counter.className = 'character-counter ';
                if (currentLength > maxLength * 0.9) {
                    counter.className += 'text-warning';
                } else if (currentLength > maxLength) {
                    counter.className += 'text-danger';
                } else {
                    counter.className += 'text-muted';
                }
            };
            
            textarea.addEventListener('input', updateCounter);
            updateCounter();
        });
    }
    
    setupAutoSave() {
        const autoSaveInputs = document.querySelectorAll('[data-auto-save]');
        autoSaveInputs.forEach(input => {
            const key = `autosave_${input.name || input.id}`;
            
            // Load saved value
            const savedValue = utils.storage.get(key);
            if (savedValue && !input.value) {
                input.value = savedValue;
            }
            
            // Save on change
            const saveValue = utils.debounce(() => {
                utils.storage.set(key, input.value);
                this.showAutoSaveIndicator();
            }, AUTO_SAVE_INTERVAL);
            
            input.addEventListener('input', saveValue);
        });
    }
    
    showAutoSaveIndicator() {
        let indicator = document.getElementById('autosave-indicator');
        if (!indicator) {
            indicator = document.createElement('div');
            indicator.id = 'autosave-indicator';
            indicator.className = 'position-fixed bottom-0 end-0 m-3 alert alert-success alert-sm';
            indicator.style.zIndex = '9999';
            document.body.appendChild(indicator);
        }
        
        indicator.innerHTML = '<i class="fas fa-check"></i> Otomatik kaydedildi';
        utils.fadeIn(indicator);
        
        setTimeout(() => {
            utils.fadeOut(indicator);
        }, 2000);
    }
}

// Performance Monitor
class PerformanceMonitor {
    constructor() {
        this.init();
    }
    
    init() {
        if ('performance' in window) {
            window.addEventListener('load', () => {
                setTimeout(() => {
                    const perfData = performance.getEntriesByType('navigation')[0];
                    if (perfData) {
                        const loadTime = perfData.loadEventEnd - perfData.loadEventStart;
                        console.log(`Sayfa yükleme süresi: ${loadTime.toFixed(2)}ms`);
                        
                        if (loadTime > 3000) {
                            console.warn('Sayfa yükleme süresi yavaş!');
                        }
                    }
                }, 0);
            });
        }
    }
}

// Security Manager
class SecurityManager {
    constructor() {
        this.init();
    }
    
    init() {
        this.setupCSRFProtection();
        this.setupClickjackingProtection();
        this.monitorConsole();
    }
    
    setupCSRFProtection() {
        // CSRF token'ı tüm formlara ekle
        const forms = document.querySelectorAll('form[method="post"]');
        forms.forEach(form => {
            const csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken && !form.querySelector('input[name="csrf_token"]')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'csrf_token';
                input.value = csrfToken.getAttribute('content');
                form.appendChild(input);
            }
        });
    }
    
    setupClickjackingProtection() {
        // Frame busting
        if (top !== self) {
            top.location = self.location;
        }
    }
    
    monitorConsole() {
        // Console komutlarını izle (geliştirme ortamında)
        if (window.location.hostname === 'localhost' || window.location.hostname.includes('127.0.0.1')) {
            const originalLog = console.log;
            console.log = function(...args) {
                originalLog.apply(console, ['[Blog System]', ...args]);
            };
        }
    }
}

// Ana uygulama başlatıcı
document.addEventListener('DOMContentLoaded', function() {
    console.log('🚀 Modern Blog Sistemi başlatılıyor...');
    
    // Manager'ları başlat
    const authManager = new AuthManager();
    const uiManager = new UIManager();
    const performanceMonitor = new PerformanceMonitor();
    const securityManager = new SecurityManager();
    
    console.log('✅ Tüm sistemler aktif!');
    
    // Global error handler
    window.addEventListener('error', (e) => {
        console.error('JavaScript Hatası:', e.error);
        
        // Kullanıcıya hata mesajı göster (production'da daha genel)
        if (window.location.hostname === 'localhost') {
            authManager.showMessage(`Hata: ${e.error.message}`, 'error');
        } else {
            authManager.showMessage('Bir hata oluştu. Lütfen sayfayı yenileyin.', 'error');
        }
    });
    
    // Service Worker registration (gelecek için hazır)
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            // navigator.serviceWorker.register('/sw.js');
        });
    }
});

// Export for testing
if (typeof module !== 'undefined' && module.exports) {
    module.exports = { AuthManager, UIManager, utils };
} 