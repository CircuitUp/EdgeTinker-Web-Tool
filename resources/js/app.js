import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

import megaMenu from './components/mega-menu';
import slideshow from './components/slideshow';
import tabs from './components/tabs';
import gallery from './components/gallery';
import priceRange from './components/price-range';
import filterPanel from './components/filter-panel';
import searchBox from './components/search-box';

Alpine.plugin(focus);
Alpine.plugin(collapse);

Alpine.data('megaMenu', megaMenu);
Alpine.data('slideshow', slideshow);
Alpine.data('tabs', tabs);
Alpine.data('gallery', gallery);
Alpine.data('priceRange', priceRange);
Alpine.data('filterPanel', filterPanel);
Alpine.data('searchBox', searchBox);

/**
 * Toasts. Anywhere in the page:
 *   $store.toasts.push('success', 'Saved')            (Alpine)
 *   window.dispatchEvent(new CustomEvent('toast', { detail: { type, message } }))
 */
Alpine.store('toasts', {
    items: [],
    nextId: 1,
    push(type, message, timeout = 5000) {
        const id = this.nextId++;
        this.items.push({ id, type, message });
        if (timeout > 0) {
            setTimeout(() => this.dismiss(id), timeout);
        }
    },
    dismiss(id) {
        this.items = this.items.filter((toast) => toast.id !== id);
    },
});

window.addEventListener('toast', (event) => {
    const { type = 'info', message = '' } = event.detail ?? {};
    Alpine.store('toasts').push(type, message);
});

/** Wiki "On this page" list: highlights the heading currently being read. */
Alpine.data('tocSpy', () => ({
    current: null,
    init() {
        const headings = [...document.querySelectorAll('.wiki-content h2[id], .wiki-content h3[id]')];
        if (!headings.length) return;
        this.current = headings[0].id;

        const update = () => {
            // The current heading is the last one that has scrolled above a line near the top of the window.
            let active = headings[0].id;
            for (const heading of headings) {
                if (heading.getBoundingClientRect().top <= 120) active = heading.id;
            }
            this.current = active;
        };
        window.addEventListener('scroll', update, { passive: true });
        update();
    },
}));

// "Copy" buttons on wiki code blocks.
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy]');
    if (!button) return;

    const code = button.closest('.code-block')?.querySelector('pre')?.innerText ?? '';
    try {
        await navigator.clipboard.writeText(code);
        button.textContent = 'Copied';
    } catch {
        button.textContent = 'Press Ctrl+C';
        // Clipboard access can be blocked; select the code so it can be copied by hand.
        const range = document.createRange();
        range.selectNodeContents(button.closest('.code-block').querySelector('pre'));
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
    }
    setTimeout(() => (button.textContent = 'Copy'), 2000);
});

// After a form is sent, move focus to its result message.
document.addEventListener('DOMContentLoaded', () => {
    document.querySelector('[data-focus-on-load]')?.focus();
});

window.Alpine = Alpine;
Alpine.start();
