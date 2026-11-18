/**
 * Content Studio scripts.
 *
 * Livewire ships its own copy of Alpine and starts it, so this file must NOT
 * import or start Alpine (two copies on one page break each other). Shared
 * pieces are registered when Livewire's Alpine fires `alpine:init`.
 */
import 'trix';
import tabs from './components/tabs';
import bannerEditor from './banner/editor';

document.addEventListener('alpine:init', () => {
    const Alpine = window.Alpine;

    Alpine.data('tabs', tabs);
    Alpine.data('bannerEditor', bannerEditor);

    Alpine.store('toasts', {
        items: [],
        nextId: 1,
        push(type, message, timeout = 6000) {
            const id = this.nextId++;
            this.items.push({ id, type, message });
            if (timeout > 0) setTimeout(() => this.dismiss(id), timeout);
        },
        dismiss(id) {
            this.items = this.items.filter((toast) => toast.id !== id);
        },
    });

    /**
     * Rich-text editor (Trix) bound to a Livewire property.
     * File attachments are switched off: images belong in the media library.
     */
    Alpine.data('richText', ({ model }) => ({
        init() {
            const editor = this.$refs.editor;
            editor.addEventListener('trix-file-accept', (event) => event.preventDefault());
            editor.addEventListener('trix-change', () => {
                // `false` = do not send a request now; the value travels with the next action.
                this.$wire.set(model, this.$refs.input.value, false);
            });
        },
    }));

    /** Drag-to-reorder for a list; calls a Livewire method with (from, to). */
    Alpine.data('sortable', ({ method }) => ({
        dragging: null,
        start(index, event) {
            this.dragging = index;
            event.dataTransfer.effectAllowed = 'move';
        },
        drop(index) {
            if (this.dragging !== null && this.dragging !== index) this.$wire.call(method, this.dragging, index);
            this.dragging = null;
        },
    }));
});

// Toasts raised by Livewire components: $this->dispatch('toast', type: 'success', message: '…')
window.addEventListener('toast', (event) => {
    const detail = Array.isArray(event.detail) ? event.detail[0] : event.detail;
    window.Alpine?.store('toasts').push(detail?.type ?? 'info', detail?.message ?? '');
});
