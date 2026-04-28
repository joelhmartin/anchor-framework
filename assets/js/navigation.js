/**
 * Anchor Framework — Navigation
 * Handles header scroll state, mobile menu, dropdowns, and sub-menu toggles.
 */
(function() {
    'use strict';

    const nav = document.getElementById('anchor-nav');
    const hamburger = document.getElementById('anchor-hamburger');
    const overlay = document.getElementById('anchor-mobile-overlay');
    const socialBar = document.getElementById('anchor-social-bar');

    if (!nav) return;

    let scrolled = false;
    let mobileOpen = false;

    // --- Scroll state ---
    function updateScrollState() {
        const isScrolled = window.scrollY > 80;
        if (isScrolled === scrolled) return;
        scrolled = isScrolled;

        nav.classList.toggle('anchor-nav--scrolled', scrolled);

        // Logo swap
        const logoLight = nav.querySelector('.anchor-nav__logo-light');
        const logoDark = nav.querySelector('.anchor-nav__logo-dark');
        const logoText = nav.querySelector('.anchor-nav__logo-text');

        if (logoLight && logoDark) {
            logoLight.style.display = (scrolled && !mobileOpen) ? 'none' : '';
            logoDark.style.display = (scrolled && !mobileOpen) ? '' : 'none';
        }
        if (logoText) {
            logoText.style.color = (scrolled && !mobileOpen) ? 'var(--anchor-navy)' : 'white';
        }

        // Hamburger bar colors
        const bars = hamburger ? hamburger.querySelectorAll('.anchor-hamburger__bar') : [];
        bars.forEach(bar => {
            if (mobileOpen) {
                bar.style.background = 'white';
            } else {
                bar.style.background = scrolled ? 'var(--anchor-navy)' : 'white';
            }
        });

        // Social bar
        if (socialBar) {
            socialBar.classList.toggle('is-hidden', scrolled || mobileOpen);
        }

        // CTA button state
        const cta = nav.querySelector('.anchor-nav__cta');
        if (cta) {
            cta.classList.toggle('anchor-nav__cta--scrolled', scrolled);
        }
    }

    window.addEventListener('scroll', updateScrollState, { passive: true });
    updateScrollState();

    // --- Mobile menu toggle ---
    if (hamburger && overlay) {
        hamburger.addEventListener('click', function() {
            mobileOpen = !mobileOpen;

            hamburger.classList.toggle('is-open', mobileOpen);
            overlay.classList.toggle('is-open', mobileOpen);
            nav.classList.toggle('anchor-nav--open', mobileOpen);

            // Body scroll lock
            document.body.style.overflow = mobileOpen ? 'hidden' : '';

            // Update colors
            const bars = hamburger.querySelectorAll('.anchor-hamburger__bar');
            bars.forEach(bar => {
                bar.style.background = mobileOpen ? 'white' : (scrolled ? 'var(--anchor-navy)' : 'white');
            });

            // Logo
            const logoLight = nav.querySelector('.anchor-nav__logo-light');
            const logoDark = nav.querySelector('.anchor-nav__logo-dark');
            const logoText = nav.querySelector('.anchor-nav__logo-text');

            if (logoLight && logoDark) {
                logoLight.style.display = mobileOpen ? '' : (scrolled ? 'none' : '');
                logoDark.style.display = mobileOpen ? 'none' : (scrolled ? '' : 'none');
            }
            if (logoText) {
                logoText.style.color = mobileOpen ? 'white' : (scrolled ? 'var(--anchor-navy)' : 'white');
            }

            // Social bar
            if (socialBar) {
                socialBar.classList.toggle('is-hidden', scrolled || mobileOpen);
            }
        });

        // Close on link click
        overlay.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if (mobileOpen) {
                    hamburger.click();
                }
            });
        });
    }

    // --- Mobile sub-menu toggles ---
    document.querySelectorAll('.anchor-mobile-overlay__toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            const targetId = this.dataset.target;
            const sub = document.getElementById(targetId);
            if (!sub) return;

            const isOpen = sub.classList.contains('is-open');

            // Close all other subs and deactivate their buttons
            document.querySelectorAll('.anchor-mobile-overlay__sub').forEach(s => {
                s.classList.remove('is-open');
            });
            document.querySelectorAll('.anchor-mobile-overlay__toggle').forEach(b => {
                b.classList.remove('is-active');
            });

            // Toggle this one
            if (!isOpen) {
                sub.classList.add('is-open');
                this.classList.add('is-active');
            }
        });
    });

    // --- Desktop dropdown hover ---
    document.querySelectorAll('.anchor-nav__item-wrap').forEach(wrap => {
        let timeout;
        const dropdown = wrap.querySelector('.anchor-nav__dropdown');
        const btn = wrap.querySelector('.anchor-nav__link');

        if (!dropdown) return;

        wrap.addEventListener('mouseenter', function() {
            clearTimeout(timeout);
            dropdown.classList.add('is-open');
            if (btn) btn.setAttribute('aria-expanded', 'true');
        });

        wrap.addEventListener('mouseleave', function() {
            timeout = setTimeout(function() {
                dropdown.classList.remove('is-open');
                if (btn) btn.setAttribute('aria-expanded', 'false');
            }, 150);
        });
    });

})();
