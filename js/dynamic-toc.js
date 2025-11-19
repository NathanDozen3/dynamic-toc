document.addEventListener('DOMContentLoaded', function () {
    // Find all TOC toggles on the page
    const toggles = document.querySelectorAll('.ttm-toc__button');

    toggles.forEach(function (button) {
        const panelId = button.getAttribute('aria-controls');
        const panel = document.getElementById(panelId);
        const icon = button.querySelector('.ttm-toc__icon');

        if (!panel) return;

        // Initial state
        button.setAttribute('aria-expanded', 'false');
        panel.hidden = true;
        if (icon) icon.textContent = '+';

        const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const openPanel = function () {
            button.setAttribute('aria-expanded', 'true');
            panel.hidden = false;
            if (icon) icon.textContent = '-';
            if (!prefersReduced) {
                // ensure smooth expand: set max-height to scrollHeight
                panel.style.maxHeight = panel.scrollHeight + 'px';
                setTimeout(function () {
                    panel.style.maxHeight = 'none';
                }, 350);
            } else {
                panel.style.maxHeight = 'none';
            }
        };

        const closePanel = function () {
            button.setAttribute('aria-expanded', 'false');
            if (!prefersReduced) {
                // animate close
                panel.style.maxHeight = panel.scrollHeight + 'px';
                // force repaint to ensure transition
                // eslint-disable-next-line no-unused-expressions
                panel.offsetHeight;
                panel.style.maxHeight = '0';
                setTimeout(function () {
                    panel.hidden = true;
                }, 350);
            } else {
                panel.style.maxHeight = '0';
                panel.hidden = true;
            }
            if (icon) icon.textContent = '+';
        };

        button.addEventListener('click', function () {
            const expanded = button.getAttribute('aria-expanded') === 'true';

            // If exclusive behavior is desired (only one open), close others
            document.querySelectorAll('.ttm-toc__button[aria-expanded="true"]').forEach(function (other) {
                if (other !== button) {
                    const otherPanel = document.getElementById(other.getAttribute('aria-controls'));
                    if (otherPanel) {
                        other.setAttribute('aria-expanded', 'false');
                        otherPanel.hidden = true;
                        otherPanel.style.maxHeight = '0';
                        const otherIcon = other.querySelector('.ttm-toc__icon');
                        if (otherIcon) otherIcon.textContent = '+';
                    }
                }
            });

            if (expanded) {
                closePanel();
            } else {
                openPanel();
            }
        });

        // Keyboard: Escape closes the panel and returns focus to the toggle
        panel.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' || e.key === 'Esc') {
                closePanel();
                button.focus();
            }
        });

        // Recalculate maxHeight on resize (debounced)
        let resizeTimer = null;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function () {
                if (button.getAttribute('aria-expanded') === 'true' && !prefersReduced) {
                    panel.style.maxHeight = panel.scrollHeight + 'px';
                }
            }, 150);
        });
    });
});