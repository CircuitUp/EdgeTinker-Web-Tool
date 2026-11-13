/**
 * Hero slideshow.
 *
 * Auto-advances, and pauses while hovered, while focus is inside it, while the
 * tab is hidden, or when the visitor presses the pause button. Visitors who
 * ask for reduced motion get no auto-advance at all (arrows and dots still work).
 * Supports arrow keys and touch swipe.
 */
export default ({ count = 0, interval = 6000 } = {}) => ({
    current: 0,
    count,
    interval,
    timer: null,
    hovered: false,
    focused: false,
    userPaused: false,
    reducedMotion: false,
    touchStartX: null,

    init() {
        const query = window.matchMedia('(prefers-reduced-motion: reduce)');
        this.reducedMotion = query.matches;
        query.addEventListener('change', (event) => {
            this.reducedMotion = event.matches;
            this.restart();
        });
        document.addEventListener('visibilitychange', () => this.restart());
        this.restart();
    },

    destroy() {
        clearInterval(this.timer);
    },

    get playing() {
        return !this.userPaused && !this.reducedMotion;
    },

    shouldRun() {
        return this.count > 1 && this.playing && !this.hovered && !this.focused && !document.hidden;
    },

    restart() {
        clearInterval(this.timer);
        this.timer = null;
        if (this.shouldRun()) {
            this.timer = setInterval(() => this.step(1), this.interval);
        }
    },

    step(direction) {
        this.current = (this.current + direction + this.count) % this.count;
    },

    next() {
        this.step(1);
        this.restart();
    },

    previous() {
        this.step(-1);
        this.restart();
    },

    goTo(index) {
        this.current = index;
        this.restart();
    },

    togglePlay() {
        this.userPaused = !this.userPaused;
        this.restart();
    },

    setHovered(value) {
        this.hovered = value;
        this.restart();
    },

    setFocused(value) {
        this.focused = value;
        this.restart();
    },

    onTouchStart(event) {
        this.touchStartX = event.changedTouches[0].clientX;
    },

    onTouchEnd(event) {
        if (this.touchStartX === null) return;
        const delta = event.changedTouches[0].clientX - this.touchStartX;
        this.touchStartX = null;
        if (Math.abs(delta) < 40) return;
        delta < 0 ? this.next() : this.previous();
    },
});
