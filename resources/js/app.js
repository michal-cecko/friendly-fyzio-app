import './bootstrap';

// --- Public site interactions (no framework, keeps the frontend dependency-free) ---
// Header dropdowns open on hover/focus via CSS (group-hover); only the mobile
// menu and banner dismissal need JS.

// Mobile navigation toggle.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-mobile-toggle]');
    if (toggle) {
        const menu = document.querySelector('[data-mobile-menu]');
        const isOpen = menu?.classList.toggle('hidden') === false;
        toggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        document.body.classList.toggle('overflow-hidden', isOpen);
        return;
    }

    // Mobile menu accordions: expand a section's sub-items and rotate its chevron.
    const accordion = event.target.closest('[data-accordion-trigger]');
    if (accordion) {
        accordion.parentElement.querySelector('[data-accordion-panel]')?.classList.toggle('hidden');
        accordion.querySelector('[data-accordion-icon]')?.classList.toggle('rotate-90');
        accordion.querySelector('[data-accordion-label]')?.classList.toggle('text-primary');
        return;
    }

    // "Zobrazit další…" buttons (see components/site/show-more.blade.php): reveal
    // the capped-away items of the listed container and flip the button's label.
    const showMore = event.target.closest('[data-show-more]');
    if (showMore) {
        const list = document.getElementById(showMore.dataset.showMore);
        const expanded = showMore.getAttribute('aria-expanded') === 'true';

        list?.querySelectorAll('[data-show-more-item]').forEach((item) => {
            item.classList.toggle('hidden', expanded);
        });

        showMore.setAttribute('aria-expanded', expanded ? 'false' : 'true');
        showMore.querySelector('[data-show-more-label]').textContent = expanded
            ? showMore.dataset.moreLabel
            : showMore.dataset.lessLabel;
        showMore.querySelector('[data-show-more-icon]')?.classList.toggle('rotate-180', !expanded);
    }
});

// Dismissible banners: hide previously dismissed banners, persist dismissals.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-banner]').forEach((banner) => {
        if (localStorage.getItem(`ff_banner_dismissed_${banner.dataset.banner}`)) {
            banner.remove();
        }
    });

    // Pop-up / floating banners fade in shortly after load instead of instantly.
    document.querySelectorAll('[data-banner-delay]').forEach((banner) => {
        const delay = Number(banner.dataset.bannerDelay) || 2000;
        window.setTimeout(() => {
            banner.classList.remove('invisible', 'pointer-events-none', 'opacity-0');
        }, delay);
    });
});

document.addEventListener('click', (event) => {
    const dismiss = event.target.closest('[data-banner-dismiss]');
    if (!dismiss) return;

    const banner = dismiss.closest('[data-banner]');
    if (!banner) return;

    localStorage.setItem(`ff_banner_dismissed_${banner.dataset.banner}`, '1');
    banner.remove();
});
