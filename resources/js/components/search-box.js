/**
 * Header search with instant suggestions (WAI-ARIA combobox pattern).
 *
 * Typing fetches up to six matching products after a short pause. Arrow keys
 * move through them, Enter opens the highlighted product (or, with nothing
 * highlighted, submits the form to the full results page), Escape closes.
 */
export default ({ url, term = '' } = {}) => ({
    url,
    term,
    items: [],
    open: false,
    loading: false,
    active: -1,
    controller: null,

    async fetchSuggestions() {
        const query = this.term.trim();
        this.active = -1;

        if (query.length < 2) {
            this.items = [];
            this.open = false;
            return;
        }

        // Drop any request still in flight so slow responses cannot overwrite newer ones.
        this.controller?.abort();
        this.controller = new AbortController();
        this.loading = true;

        try {
            const response = await fetch(`${this.url}?q=${encodeURIComponent(query)}`, {
                headers: { Accept: 'application/json' },
                signal: this.controller.signal,
            });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            this.items = (await response.json()).items ?? [];
            this.open = true;
        } catch (error) {
            if (error.name === 'AbortError') return;
            // Suggestions are a convenience: on failure, stay quiet and let Enter search normally.
            this.items = [];
            this.open = false;
        } finally {
            this.loading = false;
        }
    },

    reopen() {
        if (this.term.trim().length >= 2 && (this.items.length || this.open)) this.open = true;
    },

    close() {
        this.open = false;
        this.active = -1;
    },

    move(step) {
        if (!this.open || !this.items.length) return;
        // -1 means "nothing highlighted" (Enter then submits the form).
        const count = this.items.length;
        this.active = this.active + step;
        if (this.active >= count) this.active = -1;
        if (this.active < -1) this.active = count - 1;
    },

    // Enter: go to the highlighted product, otherwise let the form submit.
    choose(event) {
        if (this.open && this.active >= 0 && this.items[this.active]) {
            event.preventDefault();
            window.location.href = this.items[this.active].url;
        }
    },
});
