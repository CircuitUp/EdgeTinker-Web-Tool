# Home banner editor

The home page banner (the slideshow at the top of the home page) is designed visually in
**Content Studio → Home banner**. This page has a guide for editors and notes for developers.

---

## Part 1: Editor guide

### The screen

| Area | What it does |
| --- | --- |
| **Toolbar** | Screen size (Desktop / Tablet / Mobile), undo and redo, Grid, Guides, Snap, Rulers, zoom, and Reset to default, Preview, Save draft, Publish (or Submit for approval). |
| **Slides** | One tab per slide. Add, duplicate, reorder (arrows) or delete slides. Up to 6. |
| **Layers** (left) | Every text and button on the slide, plus the Overlay and the Background. Click to select. |
| **Canvas** (middle) | The banner exactly as the home page draws it. Drag things around here. |
| **Inspector** (right) | Settings for whatever is selected. With nothing selected: the slide background and settings for the whole banner. |

### Everyday tasks

- **Move text or a button:** drag it on the canvas. Magenta lines appear when it lines up with the
  centre, the edges or another element, and it snaps into place. Hold **Alt** to place it freely.
  The arrow keys move it by 1 px (**Shift** + arrow: 10 px).
- **Change the words:** double-click the text on the canvas and type (Enter or click elsewhere to
  finish), or use the text box in the inspector.
- **Make text wider or narrower:** drag the small squares on the left or right of the selection box.
  **Rotate:** drag the round handle above it (hold Shift for 15° steps).
- **Add text or a button:** "+ Add Text" or "+ Add Button" in Layers (up to 12 per slide).
- **Put something in front or behind:** drag it up or down in Layers, or use the arrow buttons on
  its row. The top of the list is drawn on top. Screen readers read the banner from the bottom of
  the list up, so keep the main heading at the bottom.
- **Hide** (eye), **lock** (padlock, so it cannot be dragged by accident), **duplicate** or **delete**
  from its row in Layers.
- **Undo / redo:** the arrows in the toolbar, or Ctrl+Z / Shift+Ctrl+Z (⌘ on a Mac).

### Screen sizes

The banner has three layouts: **Desktop** (banner 1024 px wide or more), **Tablet** (640–1023 px)
and **Mobile** (narrower than 640 px). Switch with the toolbar.

Smaller screens **follow the larger one** until you change something on them: an element you place
on Desktop sits in the same place (in %) on Tablet and Mobile. When you move it, or change its size,
line height or width, while Tablet is selected, only Tablet changes. The inspector shows a coloured
tag next to these settings, and "Use the larger screen's …" undoes an override.

Text that has never been dragged uses the **automatic layout**: a column on the left, centred
vertically, the original design of the banner. It reflows nicely on phones, so it is a good choice for
the main heading. "Back to automatic layout" (Desktop) returns an element to the column.

### Overlay

The overlay is the colour layer between the background picture and the text. Select **Overlay** in
Layers:

- **On / Off** switch. Off means no colour layer at all.
- **Solid**: one colour.
- **Linear** or **Radial** gradient: 2 to 4 colour stops. Drag a stop along the bar, click the bar to
  add one, select a stop to change its colour, opacity (0 % = fully transparent) and position. Set the
  angle with the dial (linear) or the centre and shape (radial). "Swap colours" reverses them.
- **Overall opacity** and **blend mode**.

Each screen size can have its own overlay ("Give tablet its own overlay"). The default design uses a
gradient on desktop, darkest behind the text and clear on the right, and an even tint on tablet and
mobile, where the text covers the whole picture.

### Colours

Every colour (text, overlay stops, button background, border, hover colours, shadow) opens the same
picker: drag in the square, choose the hue and **opacity**, or type a value in HEX
(`#6D28D9`, `#6D28D980` with opacity), RGB or HSL. The pipette picks a colour from anywhere on screen
(in browsers that support it). Your recent colours and the EdgeTinker brand colours are listed
underneath. The chequered pattern means transparent.

### Fonts

Choose from the site fonts (Inter, JetBrains Mono, system fonts) and a list of Google Fonts, each
shown in its own typeface. The first time a Google font is used, the server downloads it and serves
it from this site, so visitors never connect to Google. If the download fails you see a message, and
the text uses a similar standard font.

### Background

With nothing selected, the inspector shows the slide background: an **image** or **video** from the
media library (or upload a new one), or a plain **colour**. Click the image preview to set the
**focal point**, the part that always stays in view when the banner is cropped on narrow screens. Add
a short description for screen readers unless the picture is purely decorative.

Videos should be short MP4 or WebM loops (up to 30 MB). They play muted and stand still for visitors
who have asked their device for less motion.

Under **Whole banner**: the banner height for each screen size, whether elements are positioned within
the page content width (lines up with the rest of the page) or the full banner width, and the shade
behind the slide dots and arrows.

### Warnings

- **Readability:** for the selected element, the inspector checks the contrast between the text and
  what is behind it (picture, overlay and button), at its weakest point, against WCAG AA (4.5:1, or
  3:1 for large text). It cannot check over a video.
- **Outside the banner / safe area:** listed under "Check these" for every screen size. Click a
  warning to jump to it.

These are warnings only: you can still save and publish.

### Saving and publishing

- **Preview** opens the real home page with your banner in a new tab. Nothing is saved.
- **Save draft** keeps your work privately. Opening the editor again continues the draft.
- **Submit for approval** (Content Creator) sends it to the Content Admin, who sees a summary of
  every slide in the review queue. **Publish** (Content Admin) puts it live at once.
- **Reset to default** brings back the original design (overlay, fonts, colours, layout). Each slide
  keeps its background and the words of its heading, sub-text and button. It can be undone, and
  nothing changes on the live site until you publish.
- The browser warns you if you try to leave with unsaved changes.

---

## Part 2: Developer notes

### Where things are

| File | Role |
| --- | --- |
| `app/Support/Banner/BannerConfig.php` | Schema, defaults, `normalise()`, validation `rules()`, legacy migration, `resetDesign()` |
| `app/Support/Banner/BannerStyles.php` | Config → inline CSS custom properties (live site) |
| `resources/js/banner/styles.js` | The same, in JavaScript (editor canvas) |
| `resources/css/banner.css` | The one stylesheet that draws the banner (live and editor) |
| `resources/views/components/banner/hero.blade.php` | Live slideshow (controls, slides) |
| `resources/views/components/banner/slide.blade.php` | One slide: live branch and editor branch, same markup |
| `app/Support/Banner/BannerPresenter.php` | Resolves media URLs, styles and fonts for the live template (cached) |
| `app/Support/Banner/BannerFonts.php`, `FontInstaller.php` | Font catalogue; downloads Google Fonts to `storage/app/public/banner-fonts` |
| `app/Studio/ContentTypes/BannerType.php` | Approval workflow integration (draft, submit, approve, summary, preview) |
| `app/Livewire/Studio/Banner/Editor.php` | Editor screen: loading, uploads, fonts, templates, saving |
| `resources/js/banner/editor.js`, `color.js` | The editor (Alpine component `bannerEditor`) |
| `resources/views/livewire/studio/banner/*` | Editor markup and inspector panels |
| `database/migrations/2026_11_18_000100_create_banners_table.php` | Creates `banners`, converts `banner_slides` |

### Data model

One row in `banners` (`key = 'home'`, `config` JSON). Pending edits are revisions
(`entity_type = 'banner'`) holding a complete config; approval copies it into the row and clears the
`home.data` cache.

```text
BannerConfig {
  version: 1
  frame: 'container' | 'full'
  height: { desktop: px, tablet: px, mobile: px }
  scrim: bool                                   // shade behind the slide controls
  slides: Slide[]                               // 1–6
}
Slide {
  id, name
  background: { type: 'image' | 'video' | 'color', image: { media_id, path }, video: { media_id },
                color: RGBA, alt, position: { x, y } (%), size: 'cover' | 'contain' }
  overlay: { desktop: Overlay, tablet: Overlay | null, mobile: Overlay | null }
  elements: Element[]                           // array order = z-order = reading order, max 12
}
Overlay {
  enabled, mode: 'solid' | 'linear-gradient' | 'radial-gradient'
  solid: { color: RGBA }
  gradient: { angle, shape: 'circle' | 'ellipse', center: { x, y }, stops: [{ color: RGBA, position }] (2–4) }
  opacity: 0–1, blendMode: 'normal' | 'multiply' | 'overlay' | 'screen' | 'soft-light'
}
Element {
  id, type: 'text' | 'button', name, visible, locked
  tag: 'h2' | 'h3' | 'p' (text) | null (button)
  content                                       // plain text, line breaks kept
  href, newTab, icon: 'none' | 'arrow-right' | 'external'          // buttons
  position: { desktop|tablet|mobile: { x, y, ax, ay } | null }      // %, anchor 0/50/100
  width: { desktop|tablet|mobile: % | null }, rotation (deg), gap (px, automatic layout only)
  typography: { fontFamily, fontWeight, fontSize: {d,t,m}, lineHeight: {d,t,m}, letterSpacing (em),
                textAlign, textTransform, italic, underline, strike, color: RGBA,
                shadow: { x, y, blur, color } | null }
  button: { bg, text, border: RGBA, borderWidth, radius, px, py,
            hover: { bg, text, border }, shadow: 'none' | 'sm' | 'md' | 'lg' } | null
}
RGBA = { r: 0–255, g, b, a: 0–1 }
```

Rules worth knowing:

- **Inheritance:** a `null` tablet value inherits from desktop and a `null` mobile value from tablet
  (`BannerConfig::resolve()`). A `null` desktop **position** means automatic layout.
- **Positions** are percentages of the content frame (`.bnr-area`). `ax`/`ay` choose which point of
  the element sits at x/y (applied with `translate(-ax%, -ay%)`), so centred elements stay centred at
  every width.
- **Breakpoints** are on the banner's own width (container queries), not the window. That is why the
  editor canvas (1440 / 820 / 390 px) renders each layout faithfully.
- **Rendering:** each responsive value is written three times as CSS variables (`--fs-d`, `--fs-t`,
  `--fs-m`…) and `banner.css` picks one per container width. Nothing in the banner needs JavaScript
  to lay out, so there is no layout shift.
- **Validation:** `normalise()` makes any input well-formed (types, clamps, allowed values, stripped
  markup, unique ids); `rules()` rejects what cannot be repaired (empty text, unsafe links, missing
  media, 0 or more than 6 slides). Stored data is read with `fromStored()`, which falls back to an
  empty banner if it is unreadable or from an unknown `version`.
- **The page keeps one `<h1>`** (the visually hidden site name). Banner text is `h2`, `h3` or `p`.

### Keeping the canvas and the live site identical

`BannerStyles.php` and `styles.js` must produce the same CSS. `tests/Unit/Banner/BannerStylesTest.php`
writes its cases to `tests/fixtures/banner/styles.json`, and `npm test` (`tests/js/banner-styles.test.mjs`)
runs the JavaScript against the same file. After an intended change to the CSS:

```bash
UPDATE_BANNER_FIXTURES=1 php artisan test --filter BannerStylesTest
npm test
```

`slide.blade.php` has a live branch and an editor branch side by side; change both together.

### Adding a new element type (for example an image or logo)

1. **Schema:** in `BannerConfig`, add the type to the `type` allow-list in `normaliseElement()`, add
   its own fields (for example `image: { media_id, alt, fit }`) with defaults, plus a template method
   like `textElement()`. Add validation to `rules()` (for example the media id must exist).
2. **Styles:** add any type-specific CSS variables to `BannerStyles::element()` **and**
   `styles.js`, then extend the fixture cases and regenerate (see above). Add the class to `banner.css`.
3. **Markup:** add a branch to `components/banner/element.blade.php` (live) and to the editor branch
   of `components/banner/slide.blade.php`, with the same classes.
4. **Editor:** add a "+ Add …" button (`addElement('image')` already asks the server for a
   `template('image')`; add that case in `Editor::template()`), a layer label, and an inspector partial
   included from `editor.blade.php` when `selected.type === 'image'`.
5. **Workflow:** describe it in `BannerType::summary()` so reviewers can see the change, and include
   its media in `Banner::usesMedia()` so files in use cannot be deleted.
6. **Version:** if old stored configs need converting, bump `BannerConfig::VERSION` and migrate old
   data in `fromStored()` instead of falling back to empty.

### Tests

- `tests/Unit/Banner/BannerConfigTest.php`: colour parsing, normalising, inheritance, transparent
  colours, validation, fallback for bad stored data, reset.
- `tests/Unit/Banner/BannerStylesTest.php` and `tests/js/banner-styles.test.mjs`: gradient CSS
  (transparent stops, overlay off, radial), element CSS, PHP/JS parity.
- `tests/Feature/Banner/BannerMigrationTest.php`: conversion of the old slides, rollback, damaged data.
- `tests/Feature/Banner/BannerEditorTest.php`: draft, submit, approve, publish → visible on the home
  page, validation errors, preview, uploads, fonts, permissions.

### Fonts on the server

`FontInstaller` needs outgoing HTTPS to `fonts.googleapis.com` and `fonts.gstatic.com` the first time
a font is used (Hostinger allows this). Files go to `storage/app/public/banner-fonts/{family}/`, only
the Latin and Latin Extended subsets, with a `font.css` using `font-display: swap`; the home page
preloads the Latin files of the fonts in use. If PHP cannot verify SSL certificates (common on local
Windows setups), set `curl.cainfo` in php.ini.

### Ideas for later

- Image or logo elements, and icons inside text.
- Scheduled banners (start and end dates per slide) and per-slide display duration.
- A per-element entrance animation (respecting reduced motion).
- Contrast check over video backgrounds (sampling a frame).
- A vector version of the logo, for a crisper `favicon.svg`.
