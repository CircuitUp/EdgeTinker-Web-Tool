import Alpine from 'alpinejs';
import focus from '@alpinejs/focus';
import collapse from '@alpinejs/collapse';

import megaMenu from './components/mega-menu';
import slideshow from './components/slideshow';
import tabs from './components/tabs';
import gallery from './components/gallery';

Alpine.plugin(focus);
Alpine.plugin(collapse);

Alpine.data('megaMenu', megaMenu);
Alpine.data('slideshow', slideshow);
Alpine.data('tabs', tabs);
Alpine.data('gallery', gallery);

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

window.Alpine = Alpine;
Alpine.start();
