/**
 * Listing filters.
 *
 * Desktop: a sidebar; changing any filter applies it straight away.
 * Mobile (< 1024 px): a slide-in drawer; filters are applied together with
 * the "Show results" button so the page does not reload after every tap.
 */
export default () => ({
    open: false,
    trapped: false, // focus trap for the mobile drawer; follows `open` one tick later
    isDesktop: true,
    sliderTimer: null,

    init() {
        // The drawer only becomes visible once `open` has been rendered, and
        // focus can only move into visible elements, so trap focus a tick later.
        this.$watch('open', (value) => this.$nextTick(() => (this.trapped = value)));

        const query = window.matchMedia('(min-width: 1024px)');
        this.isDesktop = query.matches;
        query.addEventListener('change', (event) => {
            this.isDesktop = event.matches;
            if (event.matches) this.open = false;
        });

        // Coming back with the browser's Back button can restore fields we disabled on submit.
        window.addEventListener('pageshow', () => {
            document.querySelectorAll('[data-default][disabled]').forEach((field) => (field.disabled = false));
        });
    },

    // Called on any change inside the filter form.
    changed(form, event) {
        if (!this.isDesktop) return;

        clearTimeout(this.sliderTimer);

        // A slider moved by keyboard fires a change on every key press; wait
        // until the visitor pauses instead of reloading on each step.
        if (event?.target?.type === 'range') {
            this.sliderTimer = setTimeout(() => form.requestSubmit(), 700);
            return;
        }

        form.requestSubmit();
    },

    // The sort menu sits outside the drawer, so it always applies immediately.
    submit(form) {
        clearTimeout(this.sliderTimer);
        form.requestSubmit();
    },

    // On submit, leave fields that still hold their default out of the URL
    // (e.g. the default sort order), so shared links stay short.
    tidy(form) {
        for (const field of form.elements) {
            if (field.dataset.default !== undefined && field.value === field.dataset.default) {
                field.disabled = true;
            }
        }
    },
});
