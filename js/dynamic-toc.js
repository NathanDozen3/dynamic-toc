document.addEventListener('DOMContentLoaded', function () {
    const tocItems = document.querySelectorAll('.toc .tocaccordion-item');

    tocItems.forEach(function (item, idx) {
        const title = item.querySelector('.tocaccordion-title');
        const body = item.querySelector('.tocaccordion-body');
        const icon = item.querySelector('.tocaccordion-icon');

        if (!title || !body) return;

        // Give the body a stable id for aria-controls
        const bodyId = body.id || `ttm-toc-body-${idx + 1}`;
        body.id = bodyId;
        title.setAttribute('aria-controls', bodyId);
        title.setAttribute('aria-expanded', 'false');
        icon.textContent = '+';
        body.style.maxHeight = '0';

        const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        const updateOpenHeight = function () {
            if (title.classList.contains('active')) {
                if (prefersReduced) {
                    body.style.maxHeight = 'none';
                } else {
                    body.style.maxHeight = body.scrollHeight + 'px';
                }
            }
        };

        title.addEventListener('click', function () {
            const isOpen = title.classList.contains('active');

            // Close other items for exclusive behavior
            tocItems.forEach(function (other) {
                const otherTitle = other.querySelector('.tocaccordion-title');
                const otherBody = other.querySelector('.tocaccordion-body');
                const otherIcon = other.querySelector('.tocaccordion-icon');
                if (otherTitle && otherBody && otherTitle !== title) {
                    otherBody.style.maxHeight = '0';
                    otherTitle.classList.remove('active');
                    otherTitle.setAttribute('aria-expanded', 'false');
                    if (otherIcon) otherIcon.textContent = '+';
                }
            });

            if (isOpen) {
                body.style.maxHeight = '0';
                title.classList.remove('active');
                title.setAttribute('aria-expanded', 'false');
                icon.textContent = '+';
            } else {
                title.classList.add('active');
                title.setAttribute('aria-expanded', 'true');
                icon.textContent = '-';
                if (prefersReduced) {
                    body.style.maxHeight = 'none';
                } else {
                    body.style.maxHeight = body.scrollHeight + 'px';
                }
            }

            // After transition, remove max-height if reduced-motion is disabled (allow natural height changes)
            if (!prefersReduced) {
                setTimeout(function () {
                    if (title.classList.contains('active')) {
                        body.style.maxHeight = body.scrollHeight + 'px';
                    }
                }, 350);
            }
        });

        // Recalculate heights on resize (debounced)
        let resizeTimer = null;
        window.addEventListener('resize', function () {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(updateOpenHeight, 150);
        });
    });
});