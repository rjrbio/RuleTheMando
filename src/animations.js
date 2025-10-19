/**
 * Animaciones y efectos interactivos avanzados para RuleTheMando
 */

// Efecto de parallax en el hero banner
document.addEventListener('DOMContentLoaded', function() {
    
    // Parallax para el hero banner
    const heroImage = document.querySelector('.hero-image');
    if (heroImage) {
        window.addEventListener('scroll', function() {
            const scrolled = window.pageYOffset;
            const rate = scrolled * 0.5;
            heroImage.style.transform = `translateY(${rate}px)`;
        });
    }

    // Animación de aparición para las tarjetas de juegos
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -100px 0px'
    };

    const observer = new IntersectionObserver(function(entries) {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                observer.unobserve(entry.target);
            }
        });
    }, observerOptions);

    // Observar todas las tarjetas de juegos
    const gameCards = document.querySelectorAll('.game-card');
    gameCards.forEach((card, index) => {
        card.style.opacity = '0';
        card.style.animationDelay = `${index * 0.1}s`;
        observer.observe(card);
    });

    // Efecto de partículas flotantes
    createFloatingParticles();

    // Efecto de cursor personalizado (opcional)
    createCursorEffect();

    // Smooth scroll para enlaces internos
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
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

    // Añadir efecto de hover mejorado a los botones
    const buttons = document.querySelectorAll('.btn');
    buttons.forEach(button => {
        button.addEventListener('mouseenter', function(e) {
            const x = e.offsetX;
            const y = e.offsetY;
            const ripple = document.createElement('span');
            ripple.style.left = x + 'px';
            ripple.style.top = y + 'px';
            ripple.className = 'ripple';
            this.appendChild(ripple);
            
            setTimeout(() => {
                ripple.remove();
            }, 600);
        });
    });

    // Animación de contadores (si existen)
    animateCounters();

    // Efecto de typing para títulos (opcional)
    const mainTitle = document.querySelector('.hero-banner h1');
    if (mainTitle && mainTitle.textContent) {
        // Solo si se desea este efecto
        // typeWriterEffect(mainTitle);
    }
});

/**
 * Crea partículas flotantes decorativas
 */
function createFloatingParticles() {
    const particleContainer = document.createElement('div');
    particleContainer.className = 'floating-particles';
    particleContainer.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        pointer-events: none;
        z-index: 0;
        overflow: hidden;
    `;
    
    // Crear partículas
    for (let i = 0; i < 20; i++) {
        const particle = document.createElement('div');
        particle.className = 'particle';
        
        const size = Math.random() * 4 + 2;
        const left = Math.random() * 100;
        const animationDuration = Math.random() * 20 + 10;
        const delay = Math.random() * 5;
        
        particle.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            background: radial-gradient(circle, rgba(255,122,24,0.6), rgba(6,182,212,0.4));
            border-radius: 50%;
            left: ${left}%;
            bottom: -10px;
            animation: floatUp ${animationDuration}s ${delay}s infinite ease-in-out;
            opacity: 0;
        `;
        
        particleContainer.appendChild(particle);
    }
    
    document.body.appendChild(particleContainer);
    
    // Añadir animación CSS
    if (!document.getElementById('particle-animation')) {
        const style = document.createElement('style');
        style.id = 'particle-animation';
        style.textContent = `
            @keyframes floatUp {
                0% {
                    transform: translateY(0) translateX(0) rotate(0deg);
                    opacity: 0;
                }
                10% {
                    opacity: 1;
                }
                90% {
                    opacity: 1;
                }
                100% {
                    transform: translateY(-100vh) translateX(${Math.random() * 100 - 50}px) rotate(360deg);
                    opacity: 0;
                }
            }
            
            .ripple {
                position: absolute;
                border-radius: 50%;
                background: rgba(255, 255, 255, 0.6);
                width: 20px;
                height: 20px;
                animation: rippleEffect 0.6s ease-out;
                pointer-events: none;
            }
            
            @keyframes rippleEffect {
                to {
                    width: 100px;
                    height: 100px;
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * Efecto de cursor personalizado
 */
function createCursorEffect() {
    const cursor = document.createElement('div');
    cursor.className = 'custom-cursor';
    cursor.style.cssText = `
        position: fixed;
        width: 20px;
        height: 20px;
        border: 2px solid rgba(255, 122, 24, 0.8);
        border-radius: 50%;
        pointer-events: none;
        z-index: 9999;
        transition: transform 0.2s ease;
        display: none;
    `;
    document.body.appendChild(cursor);
    
    const cursorInner = document.createElement('div');
    cursorInner.style.cssText = `
        position: fixed;
        width: 8px;
        height: 8px;
        background: rgba(6, 182, 212, 0.8);
        border-radius: 50%;
        pointer-events: none;
        z-index: 9999;
        transition: transform 0.15s ease;
        display: none;
    `;
    document.body.appendChild(cursorInner);
    
    // Solo en dispositivos con mouse
    if (window.matchMedia("(pointer: fine)").matches) {
        cursor.style.display = 'block';
        cursorInner.style.display = 'block';
        
        document.addEventListener('mousemove', (e) => {
            cursor.style.left = e.clientX + 'px';
            cursor.style.top = e.clientY + 'px';
            
            cursorInner.style.left = (e.clientX + 6) + 'px';
            cursorInner.style.top = (e.clientY + 6) + 'px';
        });
        
        // Efecto al hacer hover en elementos interactivos
        const interactiveElements = document.querySelectorAll('a, button, .game-card');
        interactiveElements.forEach(el => {
            el.addEventListener('mouseenter', () => {
                cursor.style.transform = 'scale(1.5)';
                cursorInner.style.transform = 'scale(1.5)';
            });
            el.addEventListener('mouseleave', () => {
                cursor.style.transform = 'scale(1)';
                cursorInner.style.transform = 'scale(1)';
            });
        });
    }
}

/**
 * Anima contadores numéricos
 */
function animateCounters() {
    const counters = document.querySelectorAll('.countdown-number');
    
    counters.forEach(counter => {
        const updateCounter = () => {
            const target = parseInt(counter.getAttribute('data-target') || counter.textContent);
            const current = parseInt(counter.textContent);
            
            if (current < target) {
                counter.textContent = current + 1;
                setTimeout(updateCounter, 50);
            }
        };
        
        // Iniciar animación cuando sea visible
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    updateCounter();
                    observer.unobserve(entry.target);
                }
            });
        });
        
        observer.observe(counter);
    });
}

/**
 * Efecto de escritura para títulos
 */
function typeWriterEffect(element) {
    const text = element.textContent;
    element.textContent = '';
    element.style.opacity = '1';
    
    let i = 0;
    const speed = 100;
    
    function type() {
        if (i < text.length) {
            element.textContent += text.charAt(i);
            i++;
            setTimeout(type, speed);
        }
    }
    
    type();
}

// Efecto de background dinámico con el movimiento del mouse
document.addEventListener('mousemove', function(e) {
    const mouseX = e.clientX / window.innerWidth;
    const mouseY = e.clientY / window.innerHeight;
    
    const body = document.body;
    if (body && body.style) {
        body.style.backgroundPosition = `${mouseX * 50}px ${mouseY * 50}px`;
    }
});

// Añadir clase cuando se hace scroll
let lastScrollTop = 0;
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
    
    if (navbar) {
        if (scrollTop > lastScrollTop && scrollTop > 100) {
            // Scroll hacia abajo
            navbar.style.transform = 'translateY(-100%)';
        } else {
            // Scroll hacia arriba
            navbar.style.transform = 'translateY(0)';
        }
    }
    
    lastScrollTop = scrollTop;
}, false);
