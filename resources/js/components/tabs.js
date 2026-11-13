/**
 * Accessible tabs (WAI-ARIA tabs pattern): arrow keys move between tabs,
 * Home/End jump to the first/last, and only the selected tab is in the tab order.
 */
export default ({ initial = null } = {}) => ({
    selected: initial,

    tabButtons() {
        return Array.from(this.$refs.tablist.querySelectorAll('[role="tab"]'));
    },

    select(key) {
        this.selected = key;
    },

    onKeydown(event) {
        const buttons = this.tabButtons();
        const current = buttons.indexOf(document.activeElement);
        let next = null;

        switch (event.key) {
            case 'ArrowRight':
                next = (current + 1) % buttons.length;
                break;
            case 'ArrowLeft':
                next = (current - 1 + buttons.length) % buttons.length;
                break;
            case 'Home':
                next = 0;
                break;
            case 'End':
                next = buttons.length - 1;
                break;
            default:
                return;
        }

        event.preventDefault();
        buttons[next].focus();
        this.select(buttons[next].dataset.tab);
    },
});
