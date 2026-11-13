/**
 * Product image gallery.
 *
 * - Thumbnails switch the main image.
 * - On devices with a mouse, hovering the main image zooms in and follows the pointer.
 * - Clicking (or pressing Enter on) the main image opens a full-size lightbox with
 *   previous / next buttons; arrow keys also move, Escape closes.
 */
export default ({ images = [] } = {}) => ({
    images,
    index: 0,
    zoomed: false,
    origin: '50% 50%',
    lightbox: false,
    canHover: false,

    init() {
        this.canHover = window.matchMedia('(hover: hover) and (pointer: fine)').matches;
    },

    get current() {
        return this.images[this.index] ?? { src: '', alt: '' };
    },

    select(index) {
        this.index = (index + this.images.length) % this.images.length;
    },

    next() {
        this.select(this.index + 1);
    },

    previous() {
        this.select(this.index - 1);
    },

    zoomIn() {
        if (this.canHover) this.zoomed = true;
    },

    zoomOut() {
        this.zoomed = false;
        this.origin = '50% 50%';
    },

    track(event) {
        if (!this.zoomed) return;
        const box = event.currentTarget.getBoundingClientRect();
        const x = ((event.clientX - box.left) / box.width) * 100;
        const y = ((event.clientY - box.top) / box.height) * 100;
        this.origin = `${x.toFixed(1)}% ${y.toFixed(1)}%`;
    },

    open() {
        this.zoomOut();
        this.lightbox = true;
    },

    close() {
        this.lightbox = false;
    },
});
