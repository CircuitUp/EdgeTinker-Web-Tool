/**
 * Two-handle price range slider built from two native range inputs, so it is
 * keyboard and screen-reader accessible without extra work.
 *
 * A handle only gets a form `name` (min / max) once it has moved off its end
 * stop, which keeps "no price filter" out of the URL.
 */
export default ({ min = 0, max = 0, step = 1, from = null, to = null, prefix = '' } = {}) => ({
    min,
    max,
    step,
    from: from ?? min,
    to: to ?? max,
    prefix,

    get fromActive() {
        return Number(this.from) > this.min;
    },

    get toActive() {
        return Number(this.to) < this.max;
    },

    // Keep the handles from crossing.
    clampFrom() {
        this.from = Math.min(Number(this.from), Number(this.to));
    },

    clampTo() {
        this.to = Math.max(Number(this.to), Number(this.from));
    },

    percent(value) {
        return this.max === this.min ? 0 : ((Number(value) - this.min) / (this.max - this.min)) * 100;
    },

    get fillStyle() {
        return { left: this.percent(this.from) + '%', right: 100 - this.percent(this.to) + '%' };
    },

    format(value) {
        return this.prefix + Number(value).toLocaleString('en-US');
    },
});
