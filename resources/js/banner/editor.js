/**
 * Content Studio: visual editor for the home page banner (Alpine component "bannerEditor").
 *
 * The banner is a BannerConfig object (see app/Support/Banner/BannerConfig.php).
 * The canvas renders it with the same markup and stylesheet as the live site
 * (<x-banner.slide editor>), using styles.js, the JavaScript copy of the PHP
 * style generator. Saving goes through the Livewire component (Studio\Banner\Editor).
 *
 * Coordinates: element positions are percentages of the banner's content
 * frame (".bnr-area"); drag maths, guides, grid and rulers work in canvas
 * pixels (the banner at its breakpoint width, before zoom).
 */
import { setFontStacks, bannerStyle, overlayStyle, backgroundStyle, elementStyle, overlayBackground, resolve } from './styles';
import * as C from './color';

export const CANVAS_WIDTH = { desktop: 1440, tablet: 820, mobile: 390 };
const BREAKPOINTS = ['desktop', 'tablet', 'mobile'];
const LABELS = { desktop: 'Desktop', tablet: 'Tablet', mobile: 'Mobile' };
const LARGER = { tablet: 'desktop', mobile: 'tablet' };
const HISTORY_LIMIT = 100;
const SNAP_PX = 6;

/** Brand palette (design tokens from resources/css/app.css). */
export const BRAND = [
    ['Primary 900', '#2E1065'], ['Primary 800', '#451A8E'], ['Primary 700', '#5B21B6'], ['Primary 600', '#6D28D9'],
    ['Primary 400', '#A78BFA'], ['Primary 100', '#EDE9FE'], ['Primary 50', '#F5F3FF'], ['Accent', '#F59E0B'],
    ['Neutral 900', '#0F172A'], ['Neutral 600', '#475569'], ['Neutral 200', '#E2E8F0'], ['White', '#FFFFFF'], ['Black', '#000000'],
];

const ICONS = {
    'arrow-right': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>',
    external: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M15 3h6v6"/><path d="M10 14 21 3"/><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>',
};

const clone = (value) => JSON.parse(JSON.stringify(value));
const round = (n, d = 2) => Math.round(n * 10 ** d) / 10 ** d;
const newId = (prefix) => prefix + Math.random().toString(36).slice(2, 10).padEnd(8, '0');
const isTyping = (target) => target && (target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(target.tagName));

function storage(key, fallback) {
    try {
        return JSON.parse(localStorage.getItem(key)) ?? fallback;
    } catch {
        return fallback;
    }
}

export default (boot) => ({
    // ── State ────────────────────────────────────────────────────────────────
    config: boot.config,
    media: boot.media ?? {},
    fonts: boot.fonts,
    fontCss: boot.fontCss ?? {},
    isAdmin: boot.isAdmin,
    limits: boot.limits,
    icons: ICONS,
    brand: BRAND,
    breakpoints: BREAKPOINTS,
    bpLabels: LABELS,

    slideIndex: 0,
    selectedId: null, // element id, 'overlay', or null (banner and background settings)
    bp: 'desktop',
    zoom: 'fit',
    fitScale: 1,
    show: { grid: false, guides: true, snap: true, rulers: true, ...storage('banner-editor.show', {}) },
    grid: { size: 16, color: '#FFFFFF', opacity: 0.3, ...storage('banner-editor.grid', {}) },
    guides: storage('banner-editor.guides', { desktop: { v: [], h: [] }, tablet: { v: [], h: [] }, mobile: { v: [], h: [] } }),
    hoverPreview: false,

    past: [],
    future: [],
    snapshot: '',
    savedSnapshot: '',
    restoring: false,
    historyTimer: null,

    saving: false,
    leaving: false,
    errors: [],
    warnings: [],
    contrast: null,

    drag: null,
    guideDrag: null,
    smart: [],
    readout: null,
    box: null,
    area: { x: 0, y: 0, w: 1, h: 1 },
    editingId: null,

    picker: { open: false, label: '', get: null, set: null, h: 0, s: 0, v: 0, a: 1, text: '', format: 'hex', x: 0, y: 0 },
    recent: storage('banner-editor.recent', []),
    stopIndex: 0,
    fontMenu: false,
    fontsLoaded: {},
    library: { open: false, kind: 'image', items: [], loading: false, uploading: false, error: '' },
    measureTimer: null,
    layerDragging: null,
    editOriginal: '',
    samplers: null,

    // ── Lifecycle ────────────────────────────────────────────────────────────
    init() {
        setFontStacks(Object.fromEntries(this.fonts.map((f) => [f.key, f.stack])));
        this.snapshot = this.savedSnapshot = JSON.stringify(this.config);
        if (this.config.slides.length === 0) this.addSlide(true);

        this.usedFonts().forEach(([family, weight, italic]) => this.ensureFont(family, weight, italic));

        this.$watch('config', () => {
            if (!this.restoring) this.scheduleHistory();
            this.scheduleMeasure();
        });
        this.$watch('bp', () => this.$nextTick(() => { this.fit(); this.scheduleMeasure(); }));
        this.$watch('slideIndex', () => this.scheduleMeasure());
        this.$watch('selectedId', () => this.scheduleMeasure());
        this.$watch('show', (v) => this.persist('banner-editor.show', v));
        this.$watch('grid', (v) => this.persist('banner-editor.grid', v));

        new ResizeObserver(() => this.fit()).observe(this.$refs.stage);
        window.addEventListener('keydown', (e) => this.onKey(e));
        window.addEventListener('beforeunload', (e) => {
            if (this.dirty && !this.leaving) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Keep the selection box glued to the selected element (it can move with fonts, edits, breakpoints).
        const tick = () => {
            this.measureSelection();
            requestAnimationFrame(tick);
        };
        requestAnimationFrame(tick);
        this.$nextTick(() => { this.fit(); this.scheduleMeasure(); });
    },

    persist(key, value) {
        try {
            localStorage.setItem(key, JSON.stringify(value));
        } catch {
            // Private mode or storage full: preferences just are not remembered.
        }
    },

    // ── Getters ──────────────────────────────────────────────────────────────
    get s() {
        return this.config.slides[this.slideIndex] ?? this.config.slides[0];
    },
    get selected() {
        return this.s?.elements.find((el) => el.id === this.selectedId) ?? null;
    },
    get canvasWidth() {
        return CANVAS_WIDTH[this.bp];
    },
    get canvasHeight() {
        return this.config.height[this.bp];
    },
    get scale() {
        return this.zoom === 'fit' ? this.fitScale : this.zoom;
    },
    get dirty() {
        return JSON.stringify(this.config) !== this.savedSnapshot;
    },
    get layers() {
        return this.s.elements.map((el, index) => ({ el, index })).reverse();
    },
    get overlayOwn() {
        return this.s.overlay[this.bp];
    },
    get overlay() {
        return resolve(this.s.overlay, this.bp);
    },
    get larger() {
        return LARGER[this.bp];
    },
    get currentGuides() {
        return this.guides[this.bp];
    },

    // Styles used by the shared slide template.
    bannerStyle() {
        return bannerStyle(this.config);
    },
    slideStyle(slide) {
        return `${backgroundStyle(slide)};${overlayStyle(slide)}`;
    },
    elementStyle(el, layer) {
        return elementStyle(el, layer);
    },
    overlayCss(overlay) {
        return overlayBackground(overlay);
    },

    mediaUrl(id) {
        return id ? (this.media[id]?.url ?? null) : null;
    },
    imageUrl(ref) {
        if (ref.media_id) return this.mediaUrl(ref.media_id);
        if (!ref.path) return null;

        return ref.path.startsWith('placeholders/')
            ? `${boot.placeholderBase}/${ref.path.slice('placeholders/'.length)}`
            : `${boot.storageBase.replace(/\/$/, '')}/${ref.path}`;
    },

    // ── History (undo / redo) ────────────────────────────────────────────────
    scheduleHistory() {
        clearTimeout(this.historyTimer);
        this.historyTimer = setTimeout(() => this.commit(), this.drag ? 600 : 350);
    },
    commit() {
        clearTimeout(this.historyTimer);
        const now = JSON.stringify(this.config);
        if (now === this.snapshot) return;
        this.past.push(this.snapshot);
        if (this.past.length > HISTORY_LIMIT) this.past.shift();
        this.snapshot = now;
        this.future = [];
    },
    restore(json) {
        this.restoring = true;
        this.config = JSON.parse(json);
        this.snapshot = json;
        this.slideIndex = Math.min(this.slideIndex, this.config.slides.length - 1);
        if (this.selectedId && this.selectedId !== 'overlay' && !this.selected) this.selectedId = null;
        this.$nextTick(() => { this.restoring = false; });
    },
    undo() {
        this.commit();
        if (!this.past.length) return;
        this.future.push(this.snapshot);
        this.restore(this.past.pop());
    },
    redo() {
        if (!this.future.length) return;
        this.past.push(this.snapshot);
        this.restore(this.future.pop());
    },

    // ── Zoom and canvas size ─────────────────────────────────────────────────
    fit() {
        const stage = this.$refs.stage;
        if (!stage) return;
        const ruler = this.show.rulers ? 24 : 0;
        const available = stage.clientWidth - ruler - 16;
        this.fitScale = Math.max(0.1, Math.min(1, available / this.canvasWidth));
    },

    // ── Measuring ────────────────────────────────────────────────────────────
    canvasRect(root) {
        return root.getBoundingClientRect();
    },
    /** Box of a DOM node in canvas pixels: centre, unrotated size, and its axis-aligned bounds. */
    boxOf(node, root, scale) {
        const r = node.getBoundingClientRect();
        const c = root.getBoundingClientRect();
        const cx = (r.left + r.width / 2 - c.left) / scale;
        const cy = (r.top + r.height / 2 - c.top) / scale;

        return { cx, cy, w: node.offsetWidth, h: node.offsetHeight, left: (r.left - c.left) / scale, top: (r.top - c.top) / scale, right: (r.right - c.left) / scale, bottom: (r.bottom - c.top) / scale };
    },
    measureSelection() {
        const root = this.$refs.canvas;
        if (!root) return;
        const areaNode = root.querySelector('.bnr-area');
        if (areaNode) {
            const a = this.boxOf(areaNode, root, this.scale);
            const area = { x: round(a.left), y: round(a.top), w: round(a.right - a.left), h: round(a.bottom - a.top) };
            if (JSON.stringify(area) !== JSON.stringify(this.area)) this.area = area;
        }

        const el = this.selected;
        const node = el && el.visible ? root.querySelector(`[data-el="${el.id}"]`) : null;
        const box = node ? { ...this.boxOf(node, root, this.scale), rotation: el.rotation } : null;
        if (JSON.stringify(box) !== JSON.stringify(this.box)) this.box = box;
    },
    scheduleMeasure() {
        clearTimeout(this.measureTimer);
        this.measureTimer = setTimeout(() => this.checkAll(), 200);
    },

    // ── Selection ────────────────────────────────────────────────────────────
    select(id) {
        if (this.editingId && this.editingId !== id) this.finishInlineEdit();
        if (this.picker.open && id !== this.selectedId) this.closeColor();
        this.selectedId = id;
        this.stopIndex = 0;
    },
    selectSlide(index) {
        this.slideIndex = index;
        this.selectedId = null;
    },

    // ── Positions ────────────────────────────────────────────────────────────
    position(el) {
        return resolve(el.position, this.bp);
    },
    /** The element's position at this breakpoint, created from where it is now if it is laid out automatically. */
    ensurePosition(el) {
        const p = this.position(el);
        if (p && el.position[this.bp]) return el.position[this.bp];

        let next = p ? clone(p) : null;
        if (!next) {
            const node = this.$refs.canvas.querySelector(`[data-el="${el.id}"]`);
            const b = this.boxOf(node, this.$refs.canvas, this.scale);
            next = { x: round(((b.cx - b.w / 2) - this.area.x) / this.area.w * 100, 3), y: round(((b.cy - b.h / 2) - this.area.y) / this.area.h * 100, 3), ax: 0, ay: 0 };
            // In the column, text is as wide as the column. Keep that width, or the text would re-wrap (and jump) once placed freely.
            if (el.type === 'text' && resolve(el.width, this.bp) === null) {
                el.width[this.bp] = round(Math.min(100, b.w / this.area.w * 100), 2);
            }
        }
        el.position[this.bp] = next;

        return next;
    },
    resetPosition(el) {
        el.position[this.bp] = null;
        this.commit();
    },
    align(where) {
        const el = this.selected;
        if (!el || el.locked) return;
        const p = this.ensurePosition(el);
        const map = { left: ['x', 'ax', 0], center: ['x', 'ax', 50], right: ['x', 'ax', 100], top: ['y', 'ay', 0], middle: ['y', 'ay', 50], bottom: ['y', 'ay', 100] };
        const [axis, anchor, value] = map[where];
        p[axis] = value;
        p[anchor] = value;
        this.commit();
    },
    setAnchor(el, axis, value) {
        // Change which point of the element sits at x/y without moving it.
        const p = this.ensurePosition(el);
        const size = axis === 'ax' ? this.box.w / this.area.w : this.box.h / this.area.h;
        const coord = axis === 'ax' ? 'x' : 'y';
        p[coord] = round(p[coord] + (value - p[axis]) / 100 * size * 100, 3);
        p[axis] = value;
    },
    nudge(dx, dy) {
        const el = this.selected;
        if (!el || el.locked) return;
        const p = this.ensurePosition(el);
        p.x = round(p.x + dx / this.area.w * 100, 3);
        p.y = round(p.y + dy / this.area.h * 100, 3);
    },

    // ── Dragging, resizing and rotating on the canvas ────────────────────────
    pointerDown(event, el) {
        if (event.button !== 0 || this.editingId === el.id) return;
        event.stopPropagation();
        this.select(el.id);
        if (el.locked) return;
        event.preventDefault();

        const p = this.ensurePosition(el);
        const node = this.$refs.canvas.querySelector(`[data-el="${el.id}"]`);
        const b = this.boxOf(node, this.$refs.canvas, this.scale);
        this.startDrag(event, { mode: 'move', el, w: b.w, h: b.h, x0: p.x / 100 * this.area.w, y0: p.y / 100 * this.area.h });
    },
    handleDown(event, mode) {
        const el = this.selected;
        if (!el || el.locked || event.button !== 0) return;
        event.preventDefault();
        event.stopPropagation();
        const p = this.ensurePosition(el);
        this.startDrag(event, { mode, el, w: this.box.w, h: this.box.h, x0: p.x / 100 * this.area.w, y0: p.y / 100 * this.area.h, rot0: el.rotation });
    },
    startDrag(event, drag) {
        this.commit();
        this.drag = { ...drag, sx: event.clientX, sy: event.clientY, others: this.otherBoxes(drag.el.id) };
        const move = (e) => this.dragMove(e);
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            window.removeEventListener('pointercancel', up);
            this.drag = null;
            this.smart = [];
            this.readout = null;
            this.commit();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        window.addEventListener('pointercancel', up);
    },
    otherBoxes(exceptId) {
        const root = this.$refs.canvas;

        return this.s.elements.filter((el) => el.visible && el.id !== exceptId).map((el) => {
            const node = root.querySelector(`[data-el="${el.id}"]`);

            return node ? this.boxOf(node, root, this.scale) : null;
        }).filter(Boolean);
    },
    dragMove(e) {
        const d = this.drag;
        const p = d.el.position[this.bp];
        const dx = (e.clientX - d.sx) / this.scale;
        const dy = (e.clientY - d.sy) / this.scale;

        if (d.mode === 'move') {
            let ax = d.x0 + dx;
            let ay = d.y0 + dy;
            this.smart = [];
            if (this.show.snap && !e.altKey) [ax, ay] = this.snap(d, ax, ay, p);
            p.x = round(ax / this.area.w * 100, 3);
            p.y = round(ay / this.area.h * 100, 3);
            this.readout = `X ${round(p.x, 1)}% · Y ${round(p.y, 1)}%  (${Math.round(ax)}, ${Math.round(ay)} px)`;
        } else if (d.mode === 'rotate') {
            const angle = Math.atan2(e.clientY - this.screenCenter().y, e.clientX - this.screenCenter().x) * 180 / Math.PI + 90;
            let r = ((angle + 540) % 360) - 180;
            if (e.shiftKey) r = Math.round(r / 15) * 15;
            d.el.rotation = round(r, 1);
            this.readout = `${d.el.rotation}°`;
        } else {
            // Width handles: the opposite edge stays where it is.
            const sign = d.mode === 'resize-left' ? -1 : 1;
            const width = Math.max(20, d.w + sign * dx);
            d.el.width[this.bp] = round(Math.min(100, Math.max(5, width / this.area.w * 100)), 2);
            const shift = (width - d.w) * (sign === -1 ? (100 - p.ax) : p.ax) / 100 * sign;
            p.x = round((d.x0 + shift) / this.area.w * 100, 3);
            this.readout = `Width ${d.el.width[this.bp]}%  (${Math.round(width)} px)`;
        }
    },
    screenCenter() {
        const c = this.$refs.canvas.getBoundingClientRect();

        return { x: c.left + this.box.cx * this.scale, y: c.top + this.box.cy * this.scale };
    },
    /** Snap the dragged element's edges or centre to the banner, the content frame, guides, the grid and other elements. */
    snap(d, ax, ay, p) {
        const threshold = SNAP_PX / this.scale;
        const a = this.area;
        const W = this.canvasWidth;
        const H = this.canvasHeight;
        // The element's left/top in canvas pixels for a given anchor coordinate.
        const left = (x) => a.x + x - d.w * p.ax / 100;
        const top = (y) => a.y + y - d.h * p.ay / 100;

        const xs = [{ at: 0, label: 'banner edge' }, { at: W / 2, label: 'banner centre' }, { at: W, label: 'banner edge' },
            { at: a.x, label: 'content edge' }, { at: a.x + a.w / 2, label: 'content centre' }, { at: a.x + a.w, label: 'content edge' }];
        const ys = [{ at: 0, label: 'top' }, { at: H / 2, label: 'middle' }, { at: H, label: 'bottom' }];
        if (this.show.guides) {
            this.currentGuides.v.forEach((g) => xs.push({ at: g, label: 'guide' }));
            this.currentGuides.h.forEach((g) => ys.push({ at: g, label: 'guide' }));
        }
        d.others.forEach((o) => {
            xs.push({ at: o.left, other: o }, { at: o.cx, other: o }, { at: o.right, other: o });
            ys.push({ at: o.top, other: o }, { at: o.cy, other: o }, { at: o.bottom, other: o });
        });

        const best = (edges, targets) => {
            let found = null;
            for (const e of edges) {
                for (const t of targets) {
                    const diff = t.at - e;
                    if (Math.abs(diff) <= threshold && (!found || Math.abs(diff) < Math.abs(found.diff))) found = { diff, t };
                }
            }

            return found;
        };

        const l = left(ax);
        const t = top(ay);
        const fx = best([l, l + d.w / 2, l + d.w], xs);
        const fy = best([t, t + d.h / 2, t + d.h], ys);
        let gridX = null;
        let gridY = null;
        if (this.show.grid) {
            const g = +this.grid.size;
            gridX = Math.round(l / g) * g - l;
            gridY = Math.round(t / g) * g - t;
        }

        if (fx) {
            ax += fx.diff;
            this.smart.push(this.smartLine('v', fx.t, left(ax), top(ay), d));
        } else if (gridX !== null && Math.abs(gridX) <= threshold) {
            ax += gridX;
        }
        if (fy) {
            ay += fy.diff;
            this.smart.push(this.smartLine('h', fy.t, left(ax), top(ay), d));
        } else if (gridY !== null && Math.abs(gridY) <= threshold) {
            ay += gridY;
        }

        return [ax, ay];
    },
    /** A magenta alignment line, with the distance to the element (or edge) it lines up with. */
    smartLine(axis, target, l, t, d) {
        if (axis === 'v') {
            const from = target.other ? Math.min(target.other.top, t) : 0;
            const to = target.other ? Math.max(target.other.bottom, t + d.h) : this.canvasHeight;
            const gap = target.other ? Math.round(Math.max(target.other.top - (t + d.h), t - target.other.bottom, 0)) : null;

            return { axis, at: target.at, from, to, label: target.other ? (gap ? `${gap} px apart` : 'aligned') : target.label };
        }
        const from = target.other ? Math.min(target.other.left, l) : 0;
        const to = target.other ? Math.max(target.other.right, l + d.w) : this.canvasWidth;
        const gap = target.other ? Math.round(Math.max(target.other.left - (l + d.w), l - target.other.right, 0)) : null;

        return { axis, at: target.at, from, to, label: target.other ? (gap ? `${gap} px apart` : 'aligned') : target.label };
    },

    // ── Guides and rulers ────────────────────────────────────────────────────
    rulerTicks(length) {
        const ticks = [];
        for (let at = 0; at <= length; at += 50) ticks.push({ at, label: at % 100 === 0 ? at : '' });

        return ticks;
    },
    /** Pull a new guide out of a ruler, or move an existing one. Dropping it back on the ruler removes it. */
    guideDown(event, axis, index = null) {
        event.preventDefault();
        const list = this.currentGuides[axis];
        if (index === null) {
            list.push(axis === 'v' ? 0 : 0);
            index = list.length - 1;
        }
        const ruler = this.show.rulers ? 24 : 0;
        const move = (e) => {
            const c = this.$refs.canvas.getBoundingClientRect();
            const value = axis === 'v' ? (e.clientX - c.left) / this.scale : (e.clientY - c.top) / this.scale;
            list[index] = Math.round(value);
            this.guideDrag = { axis, index, remove: value < -ruler / 2 / this.scale + 0 && value < 0 };
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            if (list[index] < 0 || list[index] > (axis === 'v' ? this.canvasWidth : this.canvasHeight)) list.splice(index, 1);
            this.guideDrag = null;
            this.persist('banner-editor.guides', this.guides);
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        move(event);
    },
    clearGuides() {
        this.guides[this.bp] = { v: [], h: [] };
        this.persist('banner-editor.guides', this.guides);
    },
    gridStyle() {
        const g = Math.max(4, +this.grid.size || 16);
        const c = this.grid.color;

        return `background-image:linear-gradient(to right,${c} 1px,transparent 1px),linear-gradient(to bottom,${c} 1px,transparent 1px);background-size:${g}px ${g}px;opacity:${this.grid.opacity}`;
    },

    // ── Inline text editing ──────────────────────────────────────────────────
    startInlineEdit(event, el) {
        if (el.locked) return;
        event.preventDefault();
        this.select(el.id);
        const span = event.currentTarget.querySelector('.bnr-content');
        if (!span) return;
        this.editingId = el.id;
        this.editOriginal = el.content;
        try {
            span.contentEditable = 'plaintext-only';
        } catch {
            span.contentEditable = 'true';
        }
        if (span.contentEditable !== 'plaintext-only') span.contentEditable = 'true';
        span.focus();
        const range = document.createRange();
        range.selectNodeContents(span);
        const selection = window.getSelection();
        selection.removeAllRanges();
        selection.addRange(range);

        const finish = () => this.finishInlineEdit();
        span.addEventListener('blur', finish, { once: true });
        span.onkeydown = (e) => {
            e.stopPropagation();
            if (e.key === 'Escape') {
                span.textContent = this.editOriginal;
                span.blur();
            } else if (e.key === 'Enter' && (el.type === 'button' || !e.shiftKey)) {
                e.preventDefault();
                span.blur();
            }
        };
    },
    finishInlineEdit() {
        const el = this.s.elements.find((x) => x.id === this.editingId);
        const span = el ? this.$refs.canvas.querySelector(`[data-el="${el.id}"] .bnr-content`) : null;
        this.editingId = null;
        if (!el || !span) return;
        span.contentEditable = 'false';
        span.onkeydown = null;
        const text = span.innerText.replace(/\n$/, '').slice(0, el.type === 'button' ? 60 : 300);
        el.content = el.type === 'button' ? text.replace(/\s*\n\s*/g, ' ') : text;
        span.textContent = el.content;
        this.commit();
    },

    // ── Layers ───────────────────────────────────────────────────────────────
    async addElement(kind) {
        if (this.s.elements.length >= this.limits.elements) {
            this.toast('warning', `A slide can have at most ${this.limits.elements} elements.`);

            return;
        }
        const el = await this.$wire.template(kind);
        this.s.elements.push(el);
        this.select(el.id);
        this.commit();
    },
    duplicate(el = this.selected) {
        if (!el || this.s.elements.length >= this.limits.elements) return;
        const copy = clone(el);
        copy.id = newId(el.type === 'button' ? 'b' : 't');
        copy.name = `${el.name} copy`;
        BREAKPOINTS.forEach((bp) => {
            if (copy.position[bp]) copy.position[bp].y = round(copy.position[bp].y + 4, 3);
        });
        this.s.elements.splice(this.s.elements.indexOf(el) + 1, 0, copy);
        this.select(copy.id);
        this.commit();
    },
    remove(el = this.selected) {
        if (!el) return;
        this.s.elements.splice(this.s.elements.indexOf(el), 1);
        if (this.selectedId === el.id) this.selectedId = null;
        this.commit();
    },
    /** Move a layer: `from`/`to` are positions in the elements array (higher = drawn on top, read later). */
    moveLayer(from, to) {
        const list = this.s.elements;
        if (to < 0 || to >= list.length || from === to) return;
        const [el] = list.splice(from, 1);
        list.splice(to, 0, el);
        this.commit();
    },
    layerDragStart(event, index) {
        this.layerDragging = index;
        event.dataTransfer.effectAllowed = 'move';
        event.dataTransfer.setData('text/plain', String(index));
    },
    layerDrop(index) {
        if (this.layerDragging !== null && this.layerDragging !== undefined) this.moveLayer(this.layerDragging, index);
        this.layerDragging = null;
    },

    // ── Slides ───────────────────────────────────────────────────────────────
    async addSlide(silent = false) {
        if (this.config.slides.length >= this.limits.slides) {
            this.toast('warning', `The banner can have at most ${this.limits.slides} slides.`);

            return;
        }
        const slide = await this.$wire.template('slide');
        slide.name = `Slide ${this.config.slides.length + 1}`;
        this.config.slides.push(slide);
        this.selectSlide(this.config.slides.length - 1);
        if (!silent) this.commit();
    },
    duplicateSlide() {
        if (this.config.slides.length >= this.limits.slides) return;
        const copy = clone(this.s);
        copy.id = newId('s');
        copy.name = `${this.s.name} copy`;
        copy.elements.forEach((el) => { el.id = newId(el.type === 'button' ? 'b' : 't'); });
        this.config.slides.splice(this.slideIndex + 1, 0, copy);
        this.selectSlide(this.slideIndex + 1);
        this.commit();
    },
    removeSlide() {
        if (this.config.slides.length <= 1) {
            this.toast('warning', 'The banner needs at least one slide.');

            return;
        }
        if (!window.confirm(`Delete “${this.s.name}”? You can undo this.`)) return;
        this.config.slides.splice(this.slideIndex, 1);
        this.selectSlide(Math.max(0, this.slideIndex - 1));
        this.commit();
    },
    moveSlide(direction) {
        const to = this.slideIndex + direction;
        if (to < 0 || to >= this.config.slides.length) return;
        const [slide] = this.config.slides.splice(this.slideIndex, 1);
        this.config.slides.splice(to, 0, slide);
        this.slideIndex = to;
        this.commit();
    },

    // ── Responsive values ────────────────────────────────────────────────────
    /** Value of a {desktop, tablet, mobile} field at the current breakpoint (inherited when not set). */
    bpValue(values) {
        return resolve(values, this.bp);
    },
    bpOwn(values) {
        return this.bp === 'desktop' || values[this.bp] !== null;
    },
    bpSet(values, value, min, max) {
        const n = parseFloat(value);
        if (Number.isNaN(n)) return;
        values[this.bp] = round(Math.min(max, Math.max(min, n)), 3);
    },
    bpClear(values) {
        if (this.bp !== 'desktop') values[this.bp] = null;
    },
    inheritNote(values) {
        if (this.bpOwn(values)) return '';

        return `Same as ${values.tablet !== null && this.bp === 'mobile' ? 'tablet' : 'desktop'}`;
    },

    // ── Overlay ──────────────────────────────────────────────────────────────
    customiseOverlay() {
        this.s.overlay[this.bp] = clone(this.overlay);
        this.commit();
    },
    inheritOverlay() {
        if (this.bp !== 'desktop') this.s.overlay[this.bp] = null;
        this.commit();
    },
    get stop() {
        return this.overlayOwn?.gradient.stops[this.stopIndex] ?? null;
    },
    gradientBar(overlay) {
        const stops = [...overlay.gradient.stops].sort((a, b) => a.position - b.position);

        return `linear-gradient(90deg,${stops.map((s) => `${C.css(s.color)} ${s.position}%`).join(',')})`;
    },
    addStop(event) {
        const o = this.overlayOwn;
        if (!o || o.gradient.stops.length >= 4) return;
        const r = event.currentTarget.getBoundingClientRect();
        const position = round(Math.min(100, Math.max(0, (event.clientX - r.left) / r.width * 100)), 1);
        o.gradient.stops.push({ color: this.colorAt(o.gradient.stops, position), position });
        this.stopIndex = o.gradient.stops.length - 1;
        this.commit();
    },
    removeStop() {
        const o = this.overlayOwn;
        if (!o || o.gradient.stops.length <= 2) return;
        o.gradient.stops.splice(this.stopIndex, 1);
        this.stopIndex = 0;
        this.commit();
    },
    swapStops() {
        const stops = this.overlayOwn?.gradient.stops;
        if (!stops) return;
        const sorted = [...stops].sort((a, b) => a.position - b.position);
        const colors = sorted.map((s) => clone(s.color)).reverse();
        sorted.forEach((s, i) => { s.color = colors[i]; });
        this.commit();
    },
    stopDown(event, index) {
        event.preventDefault();
        event.stopPropagation();
        this.stopIndex = index;
        const bar = event.currentTarget.parentElement.getBoundingClientRect();
        const stop = this.overlayOwn.gradient.stops[index];
        const move = (e) => { stop.position = round(Math.min(100, Math.max(0, (e.clientX - bar.left) / bar.width * 100)), 1); };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            this.commit();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
    },
    stopKey(event, index) {
        const stop = this.overlayOwn.gradient.stops[index];
        const step = event.shiftKey ? 10 : 1;
        if (event.key === 'ArrowLeft') stop.position = Math.max(0, round(stop.position - step, 1));
        else if (event.key === 'ArrowRight') stop.position = Math.min(100, round(stop.position + step, 1));
        else if (event.key === 'Delete' || event.key === 'Backspace') this.removeStop();
        else return;
        event.preventDefault();
    },
    angleDown(event) {
        event.preventDefault();
        const dial = event.currentTarget.getBoundingClientRect();
        const o = this.overlayOwn;
        const move = (e) => {
            const deg = Math.atan2(e.clientX - (dial.left + dial.width / 2), -(e.clientY - (dial.top + dial.height / 2))) * 180 / Math.PI;
            let angle = (deg + 360) % 360;
            if (e.shiftKey) angle = Math.round(angle / 15) * 15;
            o.gradient.angle = Math.round(angle) % 360;
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            this.commit();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        move(event);
    },
    padDown(event, point) {
        event.preventDefault();
        const pad = event.currentTarget.getBoundingClientRect();
        const move = (e) => {
            point.x = round(Math.min(100, Math.max(0, (e.clientX - pad.left) / pad.width * 100)), 1);
            point.y = round(Math.min(100, Math.max(0, (e.clientY - pad.top) / pad.height * 100)), 1);
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
            this.commit();
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        move(event);
    },

    // ── Colour picker ────────────────────────────────────────────────────────
    /** Open the picker for a colour; `get`/`set` read and write it wherever it lives in the config. */
    openColor(event, label, get, set) {
        const color = get();
        const hsv = C.rgbToHsv(color);
        const r = event.currentTarget.getBoundingClientRect();
        const left = Math.min(window.innerWidth - 300, Math.max(8, r.left - 140));
        const top = r.bottom + 400 > window.innerHeight ? Math.max(8, r.top - 410) : r.bottom + 6;
        this.picker = { open: true, label, get, set, h: hsv.h, s: hsv.s, v: hsv.v, a: color.a, text: '', format: this.picker.format || 'hex', x: left, y: top, before: JSON.stringify(color) };
        this.syncPickerText();
        this.$nextTick(() => this.$refs.pickerPanel?.focus());
    },
    closeColor() {
        if (!this.picker.open) return;
        const color = this.picker.get();
        if (JSON.stringify(color) !== this.picker.before) {
            const key = C.toHex(color, true);
            this.recent = [key, ...this.recent.filter((c) => c !== key)].slice(0, 12);
            this.persist('banner-editor.recent', this.recent);
        }
        this.picker.open = false;
        this.commit();
    },
    pickerColor() {
        return C.hsvToRgb(this.picker.h, this.picker.s, this.picker.v, this.picker.a);
    },
    applyPicker() {
        this.picker.set(this.pickerColor());
        this.syncPickerText();
    },
    syncPickerText() {
        const c = this.picker.get();
        if (this.picker.format === 'hex') this.picker.text = C.toHex(c, c.a < 1);
        else if (this.picker.format === 'rgb') this.picker.text = `rgba(${c.r}, ${c.g}, ${c.b}, ${c.a})`;
        else {
            const h = C.rgbToHsl(c);
            this.picker.text = `hsla(${h.h}, ${h.s}%, ${h.l}%, ${c.a})`;
        }
    },
    pickerTyped() {
        const c = C.parse(this.picker.text);
        if (!c) return;
        const hsv = C.rgbToHsv(c);
        Object.assign(this.picker, { h: hsv.h, s: hsv.s, v: hsv.v, a: c.a });
        this.picker.set(c);
    },
    setColorFrom(hex) {
        const c = C.parse(hex);
        if (!c) return;
        const hsv = C.rgbToHsv(c);
        // Swatches keep the current transparency unless the swatch has its own.
        const a = hex.length === 9 ? c.a : this.picker.a;
        Object.assign(this.picker, { h: hsv.h, s: hsv.s, v: hsv.v, a });
        this.applyPicker();
    },
    svDown(event) {
        event.preventDefault();
        const r = event.currentTarget.getBoundingClientRect();
        const move = (e) => {
            this.picker.s = Math.min(1, Math.max(0, (e.clientX - r.left) / r.width));
            this.picker.v = Math.min(1, Math.max(0, 1 - (e.clientY - r.top) / r.height));
            this.applyPicker();
        };
        const up = () => {
            window.removeEventListener('pointermove', move);
            window.removeEventListener('pointerup', up);
        };
        window.addEventListener('pointermove', move);
        window.addEventListener('pointerup', up);
        move(event);
    },
    svKey(event) {
        const step = event.shiftKey ? 0.1 : 0.01;
        const moves = { ArrowLeft: ['s', -step], ArrowRight: ['s', step], ArrowUp: ['v', step], ArrowDown: ['v', -step] };
        if (!moves[event.key]) return;
        event.preventDefault();
        const [k, d] = moves[event.key];
        this.picker[k] = Math.min(1, Math.max(0, this.picker[k] + d));
        this.applyPicker();
    },
    get canEyedrop() {
        return 'EyeDropper' in window;
    },
    async eyedrop() {
        try {
            const result = await new window.EyeDropper().open();
            this.setColorFrom(result.sRGBHex);
        } catch {
            // Cancelled.
        }
    },
    swatch(color) {
        return C.css(color);
    },

    // ── Fonts ────────────────────────────────────────────────────────────────
    font(key) {
        return this.fonts.find((f) => f.key === key) ?? this.fonts[0];
    },
    usedFonts() {
        return this.config.slides.flatMap((s) => s.elements.map((el) => [el.typography.fontFamily, el.typography.fontWeight, el.typography.italic]));
    },
    addStylesheet(url) {
        if (!url || document.querySelector(`link[data-banner-font="${url}"]`)) return;
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = url;
        link.dataset.bannerFont = url;
        document.head.appendChild(link);
    },
    /** Make a Google font face available (downloaded to this server on first use). */
    async ensureFont(family, weight = 400, italic = false) {
        const font = this.font(family);
        if (!font.google) return true;
        const key = `${family}|${weight}|${italic}`;
        if (this.fontsLoaded[key]) return true;
        this.fontsLoaded[key] = 'loading';
        const result = await this.$wire.loadFont(family, weight, italic);
        if (result.error) {
            this.fontsLoaded[key] = false;
            this.toast('error', result.error);

            return false;
        }
        this.addStylesheet(result.css);
        this.fontsLoaded[key] = true;

        return true;
    },
    openFontMenu() {
        this.fontMenu = true;
        // Load each family's regular weight so the list can show every name in its own typeface.
        this.fonts.filter((f) => f.google).forEach((f) => {
            if (this.fontCss[f.key]) this.addStylesheet(this.fontCss[f.key]);
            else this.ensureFont(f.key, f.weights.includes(400) ? 400 : f.weights[0], false);
        });
    },
    async chooseFont(key) {
        const el = this.selected;
        const font = this.font(key);
        this.fontMenu = false;
        if (!el) return;
        el.typography.fontFamily = key;
        if (!font.weights.includes(el.typography.fontWeight)) {
            el.typography.fontWeight = font.weights.reduce((a, b) => (Math.abs(b - el.typography.fontWeight) < Math.abs(a - el.typography.fontWeight) ? b : a));
        }
        if (!font.italic) el.typography.italic = false;
        this.commit();
        await this.ensureFont(key, el.typography.fontWeight, el.typography.italic);
    },
    async typographyChanged() {
        const t = this.selected?.typography;
        if (t) await this.ensureFont(t.fontFamily, t.fontWeight, t.italic);
    },
    toggleBold() {
        const t = this.selected.typography;
        const weights = this.font(t.fontFamily).weights;
        t.fontWeight = t.fontWeight >= 600 ? (weights.includes(400) ? 400 : weights[0]) : (weights.includes(700) ? 700 : weights[weights.length - 1]);
        this.commit();
        this.typographyChanged();
    },

    // ── Backgrounds and the media library ────────────────────────────────────
    async openLibrary(kind) {
        this.library = { open: true, kind, items: [], loading: true, uploading: false, error: '' };
        this.library.items = await this.$wire.library(kind);
        this.library.loading = false;
    },
    useMedia(item) {
        this.media[item.id] = item;
        const bg = this.s.background;
        if (item.kind === 'video') {
            bg.type = 'video';
            bg.video.media_id = item.id;
        } else {
            bg.type = 'image';
            bg.image = { media_id: item.id, path: null };
        }
        this.library.open = false;
        this.commit();
    },
    uploadMedia(event) {
        const file = event.target.files[0];
        if (!file) return;
        this.library.uploading = true;
        this.library.error = '';
        this.$wire.upload('upload', file, async () => {
            const result = await this.$wire.storeUpload(this.library.kind);
            this.library.uploading = false;
            if (result.error) {
                this.library.error = result.error;

                return;
            }
            this.useMedia(result.media);
        }, () => {
            this.library.uploading = false;
            this.library.error = 'The upload failed. Check the file and try again.';
        });
        event.target.value = '';
    },
    backgroundName() {
        const bg = this.s.background;
        if (bg.type === 'color') return 'Solid colour';
        if (bg.type === 'video') return this.media[bg.video.media_id]?.name ?? 'No video chosen';

        return bg.image.media_id ? (this.media[bg.image.media_id]?.name ?? 'Image') : (bg.image.path ? bg.image.path.split('/').pop() : 'No image chosen');
    },

    // ── Quality checks ───────────────────────────────────────────────────────
    /** Contrast of the selected element and elements outside the banner or the safe area, at every breakpoint. */
    async checkAll() {
        const warnings = [];
        for (const bp of BREAKPOINTS) {
            const root = this.$refs[`measure_${bp}`];
            if (!root) continue;
            const W = CANVAS_WIDTH[bp];
            const H = this.config.height[bp];
            const areaNode = root.querySelector('.bnr-area');
            const a = areaNode ? this.boxOf(areaNode, root, 1) : { left: 0, top: 0, right: W, bottom: H };
            for (const el of this.s.elements) {
                if (!el.visible) continue;
                const node = root.querySelector(`[data-el="${el.id}"]`);
                if (!node) continue;
                const b = this.boxOf(node, root, 1);
                const name = `${el.name} (“${el.content.slice(0, 24)}${el.content.length > 24 ? '…' : ''}”)`;
                if (b.left < -0.5 || b.top < -0.5 || b.right > W + 0.5 || b.bottom > H + 0.5) {
                    warnings.push({ id: el.id, bp, kind: 'outside', text: `${name} is partly outside the banner on ${LABELS[bp].toLowerCase()}.` });
                } else if (b.left < a.left - 0.5 || b.right > a.right + 0.5 || b.top < 8 || b.bottom > H - 8) {
                    warnings.push({ id: el.id, bp, kind: 'safe', text: `${name} is outside the safe area on ${LABELS[bp].toLowerCase()}.` });
                }
            }
        }
        this.warnings = warnings;
        this.contrast = await this.checkContrast();
    },
    /** Lowest text contrast of the selected element against what is behind it (background, overlay, controls shade). */
    async checkContrast() {
        const el = this.selected;
        if (!el || !el.visible || !el.content) return null;
        const root = this.$refs[`measure_${this.bp}`];
        const node = root?.querySelector(`[data-el="${el.id}"]`);
        if (!node) return null;
        const b = this.boxOf(node, root, 1);
        const W = CANVAS_WIDTH[this.bp];
        const H = this.config.height[this.bp];
        const bg = this.s.background;
        if (bg.type === 'video') return { video: true };

        const sampler = bg.type === 'image' ? await this.imageSampler(this.imageUrl(bg.image), W, H, bg) : null;
        const overlay = resolve(this.s.overlay, this.bp);
        const t = el.typography;
        const size = resolve(t.fontSize, this.bp);
        const large = size >= 24 || (size >= 18.66 && t.fontWeight >= 700);
        const needed = large ? 3 : 4.5;
        let worst = Infinity;

        for (let i = 0; i < 5; i++) {
            for (let j = 0; j < 5; j++) {
                const x = b.left + (b.right - b.left) * (i + 0.5) / 5;
                const y = b.top + (b.bottom - b.top) * (j + 0.5) / 5;
                let base = sampler ? sampler(x, y) : C.rgba(bg.color.r, bg.color.g, bg.color.b, 1);
                if (sampler === null && bg.color.a < 1) base = C.over(bg.color, C.rgba(46, 16, 101, 1));
                let surface = C.over(this.overlayColorAt(overlay, x, y, W, H), base);
                if (this.config.scrim && y > H - 112) surface = C.over(C.rgba(15, 23, 42, 0.45 * (1 - (H - y) / 112)), surface);
                let text = el.type === 'button' ? el.button.text : t.color;
                if (el.type === 'button') surface = C.over(el.button.bg, surface);
                text = C.over(text, surface);
                worst = Math.min(worst, C.contrast(text, surface));
            }
        }

        return { ratio: Math.round(worst * 100) / 100, needed, large, ok: worst >= needed, blend: overlay.enabled && overlay.blendMode !== 'normal' };
    },
    overlayColorAt(o, x, y, W, H) {
        if (!o.enabled) return C.rgba(0, 0, 0, 0);
        let c;
        if (o.mode === 'solid') {
            c = o.solid.color;
        } else if (o.mode === 'linear-gradient') {
            const rad = o.gradient.angle * Math.PI / 180;
            const L = Math.abs(W * Math.sin(rad)) + Math.abs(H * Math.cos(rad));
            const t = ((x - W / 2) * Math.sin(rad) - (y - H / 2) * Math.cos(rad)) / L + 0.5;
            c = this.colorAt(o.gradient.stops, t * 100);
        } else {
            const cx = o.gradient.center.x / 100 * W;
            const cy = o.gradient.center.y / 100 * H;
            const fx = Math.max(cx, W - cx);
            const fy = Math.max(cy, H - cy);
            const t = o.gradient.shape === 'circle'
                ? Math.hypot(x - cx, y - cy) / Math.hypot(fx, fy)
                : Math.hypot((x - cx) / (fx * Math.SQRT2), (y - cy) / (fy * Math.SQRT2));
            c = this.colorAt(o.gradient.stops, t * 100);
        }

        return { ...c, a: c.a * o.opacity };
    },
    colorAt(stops, position) {
        const sorted = [...stops].sort((a, b) => a.position - b.position);
        if (position <= sorted[0].position) return clone(sorted[0].color);
        for (let i = 1; i < sorted.length; i++) {
            if (position <= sorted[i].position) {
                const span = sorted[i].position - sorted[i - 1].position || 1;

                return C.mix(sorted[i - 1].color, sorted[i].color, (position - sorted[i - 1].position) / span);
            }
        }

        return clone(sorted[sorted.length - 1].color);
    },
    /** Reads colours from the background image as it is drawn on the banner (cover/contain, focal point). */
    async imageSampler(url, W, H, bg) {
        if (!url) return null;
        const key = `${url}|${W}|${H}|${bg.size}|${bg.position.x}|${bg.position.y}`;
        this.samplers ??= {};
        if (this.samplers[key]) return this.samplers[key];
        const img = new Image();
        img.src = url;
        try {
            await img.decode();
        } catch {
            return null;
        }
        const scale = 0.25;
        const canvas = document.createElement('canvas');
        canvas.width = Math.max(1, Math.round(W * scale));
        canvas.height = Math.max(1, Math.round(H * scale));
        const ctx = canvas.getContext('2d', { willReadFrequently: true });
        const iw = img.naturalWidth || W;
        const ih = img.naturalHeight || H;
        const ratio = bg.size === 'cover' ? Math.max(W / iw, H / ih) : Math.min(W / iw, H / ih);
        const dw = iw * ratio;
        const dh = ih * ratio;
        ctx.fillStyle = C.css({ ...bg.color, a: 1 });
        ctx.fillRect(0, 0, canvas.width, canvas.height);
        ctx.drawImage(img, (W - dw) * bg.position.x / 100 * scale, (H - dh) * bg.position.y / 100 * scale, dw * scale, dh * scale);
        let data;
        try {
            data = ctx.getImageData(0, 0, canvas.width, canvas.height).data;
        } catch {
            return null;
        }
        const sampler = (x, y) => {
            const px = Math.min(canvas.width - 1, Math.max(0, Math.round(x * scale)));
            const py = Math.min(canvas.height - 1, Math.max(0, Math.round(y * scale)));
            const i = (py * canvas.width + px) * 4;

            return C.rgba(data[i], data[i + 1], data[i + 2], 1);
        };
        this.samplers[key] = sampler;

        return sampler;
    },
    warningsFor(id) {
        return this.warnings.filter((w) => w.id === id);
    },

    // ── Keyboard ─────────────────────────────────────────────────────────────
    onKey(e) {
        if (this.picker.open && e.key === 'Escape') {
            this.closeColor();

            return;
        }
        const mod = e.ctrlKey || e.metaKey;
        if (mod && e.key.toLowerCase() === 'z' && !isTyping(e.target)) {
            e.preventDefault();
            e.shiftKey ? this.redo() : this.undo();

            return;
        }
        if (mod && e.key.toLowerCase() === 'y' && !isTyping(e.target)) {
            e.preventDefault();
            this.redo();

            return;
        }
        if (isTyping(e.target) || this.library.open || this.fontMenu) return;

        const el = this.selected;
        const step = e.shiftKey ? 10 : 1;
        const arrows = { ArrowLeft: [-step, 0], ArrowRight: [step, 0], ArrowUp: [0, -step], ArrowDown: [0, step] };
        if (el && arrows[e.key] && !e.target.closest?.('[data-no-nudge]')) {
            e.preventDefault();
            this.nudge(...arrows[e.key]);
        } else if (el && (e.key === 'Delete' || e.key === 'Backspace') && !e.target.closest?.('[data-no-nudge]')) {
            e.preventDefault();
            this.remove(el);
        } else if (el && mod && e.key.toLowerCase() === 'd') {
            e.preventDefault();
            this.duplicate(el);
        } else if (e.key === 'Escape') {
            this.selectedId = null;
        }
    },

    // ── Saving, preview and reset ────────────────────────────────────────────
    toast(type, message) {
        window.Alpine.store('toasts').push(type, message);
    },
    async save(submit) {
        if (this.editingId) this.finishInlineEdit();
        this.commit();
        this.saving = true;
        this.leaving = true;
        this.errors = [];
        const result = await this.$wire.save(clone(this.config), submit);
        if (result && result.errors) {
            this.leaving = false;
            this.saving = false;
            this.errors = Object.entries(result.errors).map(([key, messages]) => ({ key, message: messages[0] }));
            this.toast('error', `Not saved: ${this.errors[0].message}`);
        }
    },
    goToError(key) {
        const m = key.match(/^slides\.(\d+)(?:\.elements\.(\d+))?/);
        if (!m) return;
        this.slideIndex = +m[1];
        this.selectedId = m[2] !== undefined ? (this.config.slides[+m[1]]?.elements[+m[2]]?.id ?? null) : null;
    },
    preview() {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = boot.previewUrl;
        form.target = '_blank';
        for (const [name, value] of [['_token', boot.csrf], ['config', JSON.stringify(this.config)]]) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = value;
            form.appendChild(input);
        }
        document.body.appendChild(form);
        form.submit();
        form.remove();
    },
    async resetToDefault() {
        if (!window.confirm('Restore the original banner design? The overlay, fonts, colours and positions go back to the default; each slide keeps its background and the words of its heading, sub-text and button. Other elements are removed. You can undo this.')) return;
        this.commit();
        this.config = await this.$wire.resetDesign(clone(this.config));
        this.selectedId = null;
        this.commit();
        this.toast('success', 'The original design is back. Save or publish to keep it.');
    },
});
