/**
 * Banner styles for the editor canvas: a line-by-line mirror of
 * App\Support\Banner\BannerStyles and Color (PHP), which render the live site.
 * Both are tested against tests/fixtures/banner/styles.json, so the canvas
 * and the live page always produce the same CSS.
 */

const SUFFIX = { desktop: 'd', tablet: 't', mobile: 'm' };

const BUTTON_SHADOWS = {
    none: 'none',
    sm: '0 1px 2px rgba(15,23,42,0.2)',
    md: '0 4px 12px rgba(15,23,42,0.25)',
    lg: '0 10px 30px rgba(15,23,42,0.35)',
};

let fontStacks = {};

/** Font key → CSS font-family stack (from BannerFonts::catalogue()). */
export function setFontStacks(map) {
    fontStacks = { ...map };
}

function fontStack(key) {
    return fontStacks[key] ?? fontStacks.inter ?? 'var(--et-font-sans)';
}

/** At most 3 decimals, no trailing zeros: 12.5, 0.9, 48. */
export function num(value) {
    const text = String(Number(Number(value).toFixed(3)));

    return text === '-0' ? '0' : text;
}

export function colorCss(c) {
    return `rgba(${c.r},${c.g},${c.b},${num(c.a)})`;
}

/** Breakpoint value with inheritance: mobile ← tablet ← desktop. */
export function resolve(values, breakpoint) {
    if (breakpoint === 'desktop') return values.desktop;
    if (breakpoint === 'tablet') return values.tablet ?? values.desktop;

    return values.mobile ?? values.tablet ?? values.desktop;
}

export function overlayBackground(overlay) {
    if (!overlay.enabled) return 'none';
    if (overlay.mode === 'solid') return colorCss(overlay.solid.color);

    const g = overlay.gradient;
    const stops = [...g.stops].sort((a, b) => a.position - b.position);
    const list = stops.map((stop) => `${colorCss(stop.color)} ${num(stop.position)}%`).join(',');

    return overlay.mode === 'radial-gradient'
        ? `radial-gradient(${g.shape} at ${num(g.center.x)}% ${num(g.center.y)}%,${list})`
        : `linear-gradient(${num(g.angle)}deg,${list})`;
}

export function bannerStyle(config) {
    return Object.entries(SUFFIX).map(([bp, s]) => `--bnr-h-${s}:${num(config.height[bp])}px`).join(';');
}

export function overlayStyle(slide) {
    const vars = [];
    for (const [bp, s] of Object.entries(SUFFIX)) {
        const overlay = resolve(slide.overlay, bp);
        vars.push(`--ov-${s}:${overlayBackground(overlay)}`);
        vars.push(`--ovo-${s}:${num(overlay.enabled ? overlay.opacity : 0)}`);
        vars.push(`--ovb-${s}:${overlay.blendMode}`);
    }

    return vars.join(';');
}

export function backgroundStyle(slide) {
    const bg = slide.background;

    return `background-color:${colorCss(bg.color)};--bg-pos:${num(bg.position.x)}% ${num(bg.position.y)}%;--bg-fit:${bg.size}`;
}

export function elementStyle(el, layer) {
    const t = el.typography;
    const vars = [];

    for (const [bp, s] of Object.entries(SUFFIX)) {
        const p = resolve(el.position, bp);
        const w = resolve(el.width, bp);
        const rotate = `rotate(${num(el.rotation)}deg)`;

        vars.push(`--pos-${s}:${p ? 'absolute' : 'relative'}`);
        vars.push(`--l-${s}:${p ? num(p.x) + '%' : 'auto'}`);
        vars.push(`--t-${s}:${p ? num(p.y) + '%' : 'auto'}`);
        vars.push(`--w-${s}:${w !== null && w !== undefined ? num(w) + '%' : 'auto'}`);
        vars.push(`--tf-${s}:${p ? `translate(${p.ax ? '-' + p.ax : '0'}%,${p.ay ? '-' + p.ay : '0'}%) ${rotate}` : rotate}`);
        vars.push(`--mt-${s}:${p ? '0' : num(el.gap)}px`);
        vars.push(`--fs-${s}:${num(resolve(t.fontSize, bp))}px`);
        vars.push(`--lh-${s}:${num(resolve(t.lineHeight, bp))}`);
    }

    const decoration = `${t.underline ? 'underline ' : ''}${t.strike ? 'line-through' : ''}`.trim();
    const shadow = t.shadow;

    vars.push(`z-index:${layer + 1}`);
    vars.push(`font-family:${fontStack(t.fontFamily)}`);
    vars.push(`font-weight:${t.fontWeight}`);
    vars.push(`font-style:${t.italic ? 'italic' : 'normal'}`);
    vars.push(`letter-spacing:${num(t.letterSpacing)}em`);
    vars.push(`text-align:${t.textAlign}`);
    vars.push(`text-transform:${t.textTransform}`);
    vars.push(`text-decoration-line:${decoration || 'none'}`);
    vars.push(`text-shadow:${shadow ? `${num(shadow.x)}px ${num(shadow.y)}px ${num(shadow.blur)}px ${colorCss(shadow.color)}` : 'none'}`);

    if (el.type === 'button' && el.button) {
        const b = el.button;
        vars.push(`--fg:${colorCss(b.text)}`);
        vars.push(`--bg:${colorCss(b.bg)}`);
        vars.push(`--bd:${colorCss(b.border)}`);
        vars.push(`--fg-h:${colorCss(b.hover.text)}`);
        vars.push(`--bg-h:${colorCss(b.hover.bg)}`);
        vars.push(`--bd-h:${colorCss(b.hover.border)}`);
        vars.push(`border-width:${b.borderWidth}px`);
        vars.push(`border-radius:${b.radius}px`);
        vars.push(`padding:${b.py}px ${b.px}px`);
        vars.push(`box-shadow:${BUTTON_SHADOWS[b.shadow]}`);
    } else {
        vars.push(`color:${colorCss(t.color)}`);
    }

    return vars.join(';');
}
