import { ref } from 'vue';
import gsap from 'gsap';

export function useLoginAnimations() {
    const isAnimating = ref(false);

    function cardEntrance(cardEl) {
        if (!cardEl) return;
        gsap.fromTo(cardEl,
            {
                opacity: 0,
                y: 60,
                scale: 0.95,
                rotateX: 10,
            },
            {
                opacity: 1,
                y: 0,
                scale: 1,
                rotateX: 0,
                duration: 1,
                ease: 'power4.out',
                clearProps: 'transform',
            }
        );
    }

    function inputFocus(inputEl) {
        if (!inputEl) return;
        gsap.to(inputEl, {
            boxShadow: '0 0 25px rgba(124, 58, 237, 0.15)',
            duration: 0.3,
            ease: 'power2.out',
        });
    }

    function inputBlur(inputEl) {
        if (!inputEl) return;
        gsap.to(inputEl, {
            boxShadow: 'none',
            duration: 0.3,
            ease: 'power2.out',
        });
    }

    function logoHover(logoEl) {
        if (!logoEl) return;
        gsap.to(logoEl, {
            scale: 1.1,
            duration: 0.3,
            ease: 'power2.out',
        });
    }

    function logoLeave(logoEl) {
        if (!logoEl) return;
        gsap.to(logoEl, {
            scale: 1,
            duration: 0.3,
            ease: 'power2.out',
        });
    }

    function errorShake(cardEl) {
        if (!cardEl) return;
        isAnimating.value = true;
        gsap.to(cardEl, {
            x: -8,
            duration: 0.05,
            repeat: 5,
            yoyo: true,
            ease: 'power2.inOut',
            onComplete: () => {
                gsap.set(cardEl, { x: 0 });
                isAnimating.value = false;
            },
        });
    }

    function successAnimation(logoEl, nameEl, callback) {
        if (!logoEl || !nameEl) return;
        isAnimating.value = true;

        const tl = gsap.timeline({
            onComplete: () => {
                isAnimating.value = false;
                if (callback) callback();
            },
        });

        // Phase 1: Green glow + pulse
        tl.to(logoEl, {
            boxShadow: '0 0 40px rgba(16, 185, 129, 0.6), 0 0 80px rgba(16, 185, 129, 0.3)',
            duration: 0.3,
            ease: 'power2.out',
        });

        // Phase 2-3: Pulse + Scale up
        tl.to(logoEl, {
            scale: 1.15,
            duration: 0.4,
            ease: 'back.out(1.7)',
        });

        // Phase 4: Move forward (translateZ)
        tl.to(logoEl, {
            z: 50,
            opacity: 0,
            duration: 0.5,
            ease: 'power3.in',
        }, '-=0.2');

        // Phase 5: Name dissolves
        if (nameEl) {
            tl.to(nameEl.children, {
                opacity: 0,
                y: -20,
                stagger: 0.03,
                duration: 0.4,
                ease: 'power3.out',
            }, '-=0.3');
        }

        // Phase 6: Welcome message (handled by caller via callback)
    }

    function pageTransition(cardEl, callback) {
        if (!cardEl) return;
        gsap.to(cardEl, {
            opacity: 0,
            scale: 0.9,
            filter: 'blur(10px)',
            duration: 0.5,
            ease: 'power3.in',
            onComplete: () => {
                if (callback) callback();
            },
        });
    }

    function buttonRipple(btnEl, x, y) {
        if (!btnEl) return;
        const rect = btnEl.getBoundingClientRect();
        const ripple = document.createElement('span');
        const size = Math.max(rect.width, rect.height);
        ripple.style.cssText = `
            position: absolute;
            width: ${size}px;
            height: ${size}px;
            left: ${x - rect.left - size / 2}px;
            top: ${y - rect.top - size / 2}px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.3);
            pointer-events: none;
            z-index: 3;
            transform: scale(0);
        `;
        btnEl.style.position = 'relative';
        btnEl.style.overflow = 'hidden';
        btnEl.appendChild(ripple);

        gsap.to(ripple, {
            scale: 4,
            opacity: 0,
            duration: 0.6,
            ease: 'power2.out',
            onComplete: () => {
                ripple.remove();
            },
        });
    }

    return {
        isAnimating,
        cardEntrance,
        inputFocus,
        inputBlur,
        logoHover,
        logoLeave,
        errorShake,
        successAnimation,
        pageTransition,
        buttonRipple,
    };
}
