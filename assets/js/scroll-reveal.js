/**
 * Anchor Framework — Scroll Reveal + Interactive Elements
 * - IntersectionObserver scroll reveal
 * - FAQ accordion
 * - Services tabs
 * - Expanding CTA
 */
(function() {
    'use strict';

    // ─── Scroll Reveal ────────────────────────────────────────────────────────
    var revealElements = document.querySelectorAll('.anchor-reveal, .anchor-reveal-stagger');

    if (revealElements.length > 0) {
        var prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        if (prefersReducedMotion) {
            revealElements.forEach(function(el) { el.classList.add('revealed'); });
        } else {
            var revealObserver = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('revealed');
                        revealObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.05, rootMargin: '0px 0px -40px 0px' });

            revealElements.forEach(function(el) { revealObserver.observe(el); });
        }
    }

    // ─── FAQ Accordion ────────────────────────────────────────────────────────
    document.querySelectorAll('.anchor-faq-item__question').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var item = this.closest('.anchor-faq-item');
            if (!item) return;

            var answer = item.querySelector('.anchor-faq-item__answer');
            var isOpen = item.classList.contains('is-open');

            var parent = item.parentElement;
            if (parent) {
                parent.querySelectorAll('.anchor-faq-item.is-open').forEach(function(openItem) {
                    if (openItem !== item) {
                        openItem.classList.remove('is-open');
                        var openAnswer = openItem.querySelector('.anchor-faq-item__answer');
                        if (openAnswer) openAnswer.setAttribute('aria-hidden', 'true');
                        var openBtn = openItem.querySelector('.anchor-faq-item__question');
                        if (openBtn) openBtn.setAttribute('aria-expanded', 'false');
                    }
                });
            }

            item.classList.toggle('is-open', !isOpen);
            if (answer) answer.setAttribute('aria-hidden', isOpen ? 'true' : 'false');
            this.setAttribute('aria-expanded', !isOpen ? 'true' : 'false');
        });
    });

    // ─── Services Tabs ────────────────────────────────────────────────────────
    document.querySelectorAll('.anchor-tab-pill').forEach(function(pill) {
        pill.addEventListener('click', function() {
            var uid   = this.dataset.tabsUid;
            var index = parseInt(this.dataset.tabIndex, 10);

            // Update pills
            document.querySelectorAll('.anchor-tab-pill[data-tabs-uid="' + uid + '"]').forEach(function(p, i) {
                var active = (i === index);
                p.classList.toggle('anchor-tab-pill--active', active);
                p.setAttribute('aria-selected', active ? 'true' : 'false');
            });

            // Fade out panels, then swap
            var grid = document.getElementById(uid + '-grid');
            if (!grid) return;

            var panels = grid.querySelectorAll('[data-tab-panel]');
            var images = grid.querySelectorAll('[data-tab-image]');

            panels.forEach(function(panel, i) {
                var active = (i === index);
                panel.classList.toggle('anchor-tabs__panel--active', active);
                panel.setAttribute('aria-hidden', active ? 'false' : 'true');
            });

            images.forEach(function(img, i) {
                img.classList.toggle('anchor-tabs__image--active', i === index);
            });
        });
    });

    // ─── Expanding CTA ────────────────────────────────────────────────────────
    var ctaCard = document.querySelector('.anchor-cta-expand-card');

    if (ctaCard) {
        var ease      = 'cubic-bezier(0.25, 0.8, 0.25, 1)';
        var imageUrl  = ctaCard.dataset.expandImage || '';
        var ctaActive = false;
        var ticking   = false;

        // Create and inject fixed background element into <body>
        var ctaBg = document.createElement('div');
        ctaBg.className = 'anchor-cta-expand-bg';
        ctaBg.setAttribute('aria-hidden', 'true');

        if (imageUrl) {
            var bgImg = document.createElement('img');
            bgImg.src = imageUrl;
            bgImg.alt = '';
            bgImg.setAttribute('aria-hidden', 'true');
            ctaBg.appendChild(bgImg);
        }

        var bgOverlay = document.createElement('div');
        bgOverlay.className = 'anchor-cta-expand-bg__overlay';
        ctaBg.appendChild(bgOverlay);

        document.body.appendChild(ctaBg);

        // The section is ctaCard's closest anchor-section ancestor
        var ctaSection = ctaCard.closest('.anchor-section') || ctaCard.parentElement;

        function isNextSectionOverlapping() {
            var next = ctaSection ? ctaSection.nextElementSibling : null;
            if (!next) return false;
            return next.getBoundingClientRect().top < window.innerHeight * 0.6;
        }

        function updateCTA() {
            var rect     = ctaCard.getBoundingClientRect();
            var vh       = window.innerHeight;
            var topPct   = rect.top / vh;
            var bottomPct = rect.bottom / vh;

            if (!ctaActive) {
                if (topPct < 0.66 && bottomPct > 0.33 && !isNextSectionOverlapping()) {
                    ctaActive = true;
                    ctaBg.style.visibility = 'visible';
                    ctaBg.style.opacity = '1';
                    ctaCard.style.transform = 'scale(1.08)';
                    ctaCard.style.borderRadius = '0';
                    ctaCard.style.backgroundColor = 'transparent';
                }
            } else {
                var shouldExit = isNextSectionOverlapping() || topPct > 0.75;
                if (shouldExit) {
                    ctaActive = false;
                    ctaBg.style.opacity = '0';
                    ctaBg.style.visibility = 'hidden';
                    ctaCard.style.transform = '';
                    ctaCard.style.borderRadius = '';
                    ctaCard.style.backgroundColor = '';
                }
            }
            ticking = false;
        }

        function onCTAScroll() {
            if (!ticking) {
                requestAnimationFrame(updateCTA);
                ticking = true;
            }
        }

        window.addEventListener('scroll', onCTAScroll, { passive: true });
        window.addEventListener('resize', onCTAScroll, { passive: true });

        // Run once on init in case the card is already in view
        updateCTA();
    }

    // ─── Hero Carousel ───────────────────────────────────────────────────
    document.querySelectorAll('[data-carousel]').forEach(function(carousel) {
        var slides = carousel.querySelectorAll('[data-carousel-slide]');
        var dots   = carousel.querySelectorAll('[data-carousel-dot]');
        var count  = slides.length;
        var current = 0;

        if (count < 2) return;

        function goTo(index) {
            slides[current].classList.remove('anchor-carousel__slide--active');
            if (dots[current]) dots[current].classList.remove('anchor-carousel__dot--active');
            current = (index + count) % count;
            slides[current].classList.add('anchor-carousel__slide--active');
            if (dots[current]) dots[current].classList.add('anchor-carousel__dot--active');
        }

        var autoTimer = null;
        function resetAuto() {
            if (autoTimer) clearInterval(autoTimer);
            autoTimer = setInterval(function() { goTo(current + 1); }, 5000);
        }

        var prev = carousel.querySelector('[data-carousel-prev]');
        var next = carousel.querySelector('[data-carousel-next]');
        if (prev) prev.addEventListener('click', function() { goTo(current - 1); resetAuto(); });
        if (next) next.addEventListener('click', function() { goTo(current + 1); resetAuto(); });

        dots.forEach(function(dot) {
            dot.addEventListener('click', function() {
                goTo(parseInt(this.dataset.carouselDot, 10));
                resetAuto();
            });
        });

        resetAuto();
    });

    // ── Sticky footer bar: show after 100px scroll ──────────────
    var stickyFooter = document.querySelector('[data-sticky-footer]');
    if (stickyFooter) {
        var stickyVisible = false;
        window.addEventListener('scroll', function() {
            var shouldShow = window.scrollY >= 100;
            if (shouldShow !== stickyVisible) {
                stickyVisible = shouldShow;
                stickyFooter.classList.toggle('anchor-sticky-footer--visible', shouldShow);
            }
        }, { passive: true });
    }

})();
