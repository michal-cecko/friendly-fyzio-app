import './bootstrap';

// --- Public site interactions (no framework, keeps the frontend dependency-free) ---

// Header dropdown menus: toggle on click, close others and on outside click.
document.addEventListener('click', (event) => {
    const toggle = event.target.closest('[data-dropdown-toggle]');
    const insideDropdown = event.target.closest('[data-dropdown]');
    const openMenus = document.querySelectorAll('[data-dropdown-menu]:not(.hidden)');

    if (!insideDropdown) {
        openMenus.forEach((menu) => menu.classList.add('hidden'));
    }

    if (toggle) {
        event.preventDefault();
        const menu = toggle.closest('[data-dropdown]')?.querySelector('[data-dropdown-menu]');
        if (!menu) return;
        openMenus.forEach((other) => {
            if (other !== menu) other.classList.add('hidden');
        });
        menu.classList.toggle('hidden');
    }
});

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
