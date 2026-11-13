/**
 * Products mega menu.
 *
 * - Hover opens after a short delay and closes after a short delay, so the
 *   menu does not flicker when the pointer passes over or briefly leaves it.
 * - Click / tap / Enter / Space on the trigger toggles it.
 * - Arrow keys move between category links; Home/End jump; Escape closes and
 *   returns focus to the trigger.
 * - `active` is the index of the highlighted category, which drives the image
 *   panel on the right.
 */
export default () => ({
    open: false,
    active: 0,
    openTimer: null,
    closeTimer: null,
    openedByHover: false,

    OPEN_DELAY: 120,
    CLOSE_DELAY: 220,

    items() {
        return Array.from(this.$refs.list?.querySelectorAll('[data-mega-item]') ?? []);
    },

    clearTimers() {
        clearTimeout(this.openTimer);
        clearTimeout(this.closeTimer);
    },

    hoverIn() {
        this.clearTimers();
        if (this.open) return;
        this.openTimer = setTimeout(() => {
            this.open = true;
            this.openedByHover = true;
        }, this.OPEN_DELAY);
    },

    hoverOut() {
        this.clearTimers();
        this.closeTimer = setTimeout(() => this.close(), this.CLOSE_DELAY);
    },

    toggle() {
        this.clearTimers();
        // A click straight after a hover-open should keep the menu open, not close it.
        if (this.open && this.openedByHover) {
            this.openedByHover = false;
            return;
        }
        this.open = !this.open;
        this.openedByHover = false;
    },

    close(returnFocus = false) {
        this.clearTimers();
        this.open = false;
        this.openedByHover = false;
        if (returnFocus) this.$refs.trigger.focus();
    },

    focusItem(index) {
        const items = this.items();
        if (!items.length) return;
        const next = (index + items.length) % items.length;
        this.active = next;
        items[next].focus();
    },

    openAndFocusFirst() {
        this.clearTimers();
        this.open = true;
        this.openedByHover = false;
        this.$nextTick(() => this.focusItem(0));
    },

    onListKeydown(event) {
        const items = this.items();
        const current = items.indexOf(document.activeElement);
        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                this.focusItem(current + 1);
                break;
            case 'ArrowUp':
                event.preventDefault();
                this.focusItem(current - 1);
                break;
            case 'Home':
                event.preventDefault();
                this.focusItem(0);
                break;
            case 'End':
                event.preventDefault();
                this.focusItem(items.length - 1);
                break;
        }
    },

    // Close when keyboard focus leaves the whole menu (trigger + panel).
    onFocusOut(event) {
        if (!this.$root.contains(event.relatedTarget)) {
            this.close();
        }
    },
});
