/** Colour helpers for the banner editor. Colours are {r, g, b, a} (0–255, alpha 0–1), as in BannerConfig. */

export const clamp = (v, min, max) => Math.min(max, Math.max(min, v));

export function rgba(r, g, b, a = 1) {
    return { r: Math.round(clamp(r, 0, 255)), g: Math.round(clamp(g, 0, 255)), b: Math.round(clamp(b, 0, 255)), a: Math.round(clamp(a, 0, 1) * 1000) / 1000 };
}

export function toHex(c, withAlpha = false) {
    const h = (v) => Math.round(v).toString(16).padStart(2, '0');

    return `#${h(c.r)}${h(c.g)}${h(c.b)}${withAlpha && c.a < 1 ? h(c.a * 255) : ''}`.toUpperCase();
}

/** "#abc", "#aabbcc", "#aabbccdd", "rgb(…)", "rgba(…)", "hsl(…)", "hsla(…)" → colour, or null. */
export function parse(text) {
    const value = String(text).trim().toLowerCase();
    let m = value.match(/^#?([0-9a-f]{3}|[0-9a-f]{6}|[0-9a-f]{8})$/);
    if (m) {
        let hex = m[1];
        if (hex.length === 3) hex = hex.replace(/(.)/g, '$1$1');
        const n = (i) => parseInt(hex.slice(i, i + 2), 16);

        return rgba(n(0), n(2), n(4), hex.length === 8 ? n(6) / 255 : 1);
    }
    m = value.match(/^rgba?\(\s*([\d.]+)[\s,]+([\d.]+)[\s,]+([\d.]+)(?:\s*[,/]\s*([\d.]+)(%?))?\s*\)$/);
    if (m) return rgba(+m[1], +m[2], +m[3], m[4] === undefined ? 1 : +m[4] / (m[5] ? 100 : 1));
    m = value.match(/^hsla?\(\s*([\d.]+)(?:deg)?[\s,]+([\d.]+)%[\s,]+([\d.]+)%(?:\s*[,/]\s*([\d.]+)(%?))?\s*\)$/);
    if (m) return { ...hslToRgb(+m[1], +m[2], +m[3]), a: m[4] === undefined ? 1 : clamp(+m[4] / (m[5] ? 100 : 1), 0, 1) };

    return null;
}

export function rgbToHsv({ r, g, b }) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b);
    const d = max - Math.min(r, g, b);
    let h = 0;
    if (d) h = max === r ? ((g - b) / d) % 6 : max === g ? (b - r) / d + 2 : (r - g) / d + 4;

    return { h: (h * 60 + 360) % 360, s: max ? d / max : 0, v: max };
}

export function hsvToRgb(h, s, v, a = 1) {
    const f = (n) => {
        const k = (n + h / 60) % 6;

        return v - v * s * Math.max(0, Math.min(k, 4 - k, 1));
    };

    return rgba(f(5) * 255, f(3) * 255, f(1) * 255, a);
}

export function rgbToHsl({ r, g, b }) {
    r /= 255; g /= 255; b /= 255;
    const max = Math.max(r, g, b);
    const min = Math.min(r, g, b);
    const l = (max + min) / 2;
    const d = max - min;
    const s = d === 0 ? 0 : d / (1 - Math.abs(2 * l - 1));
    const { h } = rgbToHsv({ r: r * 255, g: g * 255, b: b * 255 });

    return { h: Math.round(h), s: Math.round(s * 100), l: Math.round(l * 100) };
}

export function hslToRgb(h, s, l) {
    s /= 100; l /= 100;
    const k = (n) => (n + h / 30) % 12;
    const a = s * Math.min(l, 1 - l);
    const f = (n) => l - a * Math.max(-1, Math.min(k(n) - 3, 9 - k(n), 1));

    return rgba(f(0) * 255, f(8) * 255, f(4) * 255);
}

export function css(c) {
    return `rgba(${c.r},${c.g},${c.b},${c.a})`;
}

/** `top` (with its alpha) painted over an opaque `bottom`. */
export function over(top, bottom) {
    const a = top.a;

    return rgba(top.r * a + bottom.r * (1 - a), top.g * a + bottom.g * (1 - a), top.b * a + bottom.b * (1 - a), 1);
}

export function luminance({ r, g, b }) {
    const ch = (v) => {
        v /= 255;

        return v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4;
    };

    return 0.2126 * ch(r) + 0.7152 * ch(g) + 0.0722 * ch(b);
}

export function contrast(a, b) {
    const [l1, l2] = [luminance(a), luminance(b)].sort((x, y) => y - x);

    return (l1 + 0.05) / (l2 + 0.05);
}

/** Linear interpolation between two colours (premultiplied alpha, like browsers draw gradients). */
export function mix(c1, c2, t) {
    const a = c1.a + (c2.a - c1.a) * t;
    if (a === 0) return rgba(0, 0, 0, 0);
    const ch = (k) => (c1[k] * c1.a + (c2[k] * c2.a - c1[k] * c1.a) * t) / a;

    return rgba(ch('r'), ch('g'), ch('b'), a);
}
