import './bootstrap';

// --- Public site interactions (no framework, keeps the frontend dependency-free) ---
// Header dropdowns open on hover/focus via CSS (group-hover); only the mobile
// menu and banner dismissal need JS.

// Mobile navigation toggle.
document.addEventListener('click', (event) => {
    if (event.target.closest('[data-mobile-toggle]')) {
        document.querySelector('[data-mobile-menu]')?.classList.toggle('hidden');
    }
});

// Dismissible banners: hide previously dismissed banners, persist dismissals.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-banner]').forEach((banner) => {
        if (localStorage.getItem(`ff_banner_dismissed_${banner.dataset.banner}`)) {
            banner.remove();
        }
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
