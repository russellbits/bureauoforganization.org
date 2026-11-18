// ---------------------------------------------------------------------------
// Client-side image map visualiser.
//
// Reads the geometry out of every <area> in the <map> that an <img usemap>
// points at, and paints it as translucent orange shapes in a layer stacked on
// top of that image, so you can see where the hotspots actually land. Handles
// any number of imagemaps on a page, and rect / circle / poly / default.
//
// Coords are always x,y pairs against the image's INTRINSIC pixel size, with
// y measured down from the top:
//   rect    left,top,right,bottom   (corners may be given in any order)
//   circle  centreX,centreY,radius
//   poly    x1,y1,x2,y2,...,xn,yn
//
// Usage: import it once from any layout or component and it wires itself up —
//   <script>import '@scripts/imagemap_overlay.js';</script>
//
// MASTER SWITCH — `image_map` on SITE_INFO in ./bureau_config.js.
// It is consulted first and, when set, overrides every other option:
//   1                always on; ?imap=0, localStorage and hide() are ignored
//   0                always off; nothing else can turn it on
//   null / omitted   no opinion, so fall back to, in order:
//                    ?imap=1 / ?imap=0   in the URL
//                    BureauImageMap.show() / .hide()   from the devtools console
//                    whatever is remembered in localStorage, so it survives
//                    navigation
// Keeping it at 0 on a public site means a stray ?imap=1 can never leave the
// overlay stuck on for a visitor.
//
// Pages that swap content in (HTMX) or navigate (Astro client router) are
// rescanned automatically.
// ---------------------------------------------------------------------------

import { SITE_INFO } from './bureau_config.js';

const SVG_NS = 'http://www.w3.org/2000/svg';
const STORAGE_KEY = 'bureau.imagemap';
const QUERY_KEY = 'imap';
const STYLE_ID = 'bim-style';

const OVERLAY_CSS = `
.bim-layer {
    position: absolute;
    z-index: 50;
    margin: 0;
    padding: 0;
    overflow: visible;
    pointer-events: none;
    font-family: "Courier New", Courier, monospace;
    font-size: 10px;
    line-height: 1.3;
    color: #ffcf9e;
}
.bim-svg {
    position: absolute;
    inset: 0;
    width: 100%;
    height: 100%;
    display: block;
    overflow: visible;
}
.bim-shape {
    fill: rgba(255, 140, 0, 0.3);
    stroke: rgba(255, 176, 64, 0.95);
    stroke-width: 1;
    stroke-dasharray: 3 2;
}
.bim-shape.is-oob {
    stroke: rgba(255, 70, 70, 0.95);
}
.bim-shape.is-hot {
    fill: rgba(255, 90, 0, 0.55);
    stroke-dasharray: 0;
}
.bim-tags {
    position: absolute;
    inset: 0;
    overflow: visible;
}
.bim-tag {
    position: absolute;
    padding: 0 2px;
    white-space: nowrap;
    background: rgba(0, 0, 0, 0.66);
    border: 1px solid rgba(255, 176, 64, 0.7);
}
.bim-tag.is-oob {
    color: #ffb3b3;
    border-color: rgba(255, 70, 70, 0.8);
}
.bim-tag.is-hot {
    color: #fff;
    background: rgba(255, 90, 0, 0.85);
    border-color: #fff;
}
.bim-readout {
    position: absolute;
    right: 0;
    bottom: 0;
    left: 0;
    padding: 1px 3px;
    overflow: hidden;
    white-space: nowrap;
    text-overflow: ellipsis;
    background: rgba(0, 0, 0, 0.72);
    color: #ffc46b;
}
`;

/**
 * One <area>, as read from the map.
 * @typedef {object} AreaRec
 * @property {number} index
 * @property {HTMLAreaElement} area
 * @property {number[]} nums      raw coords, as numbers
 * @property {string} shape       rect | circle | poly | default
 * @property {string} coords      coords for display
 * @property {string} title       title, falling back to alt
 * @property {string} href
 * @property {SVGElement | null} el    shape in the overlay, once drawn
 * @property {HTMLDivElement | null} tag   corner label, once drawn
 * @property {boolean} oob        true when the shape leaves the image
 */

/**
 * One instrumented image and the layer sitting on top of it.
 * @typedef {object} Layer
 * @property {HTMLImageElement} img
 * @property {HTMLElement} parent
 * @property {HTMLDivElement} layer
 * @property {SVGSVGElement} svg
 * @property {HTMLDivElement} tags
 * @property {HTMLDivElement} readout
 * @property {AreaRec[]} records
 * @property {boolean} anchored        true if we had to position the parent
 * @property {string} savedPosition    the parent's inline position, if we moved it
 * @property {boolean} alive
 * @property {ResizeObserver | null} ro
 * @property {number} natW             image width the coords are authored against
 * @property {number} natH
 * @property {Array<() => void>} teardown
 */

/** @typedef {{ x: number, y: number, w: number, h: number, el: SVGElement }} Box */

let installed = false;
let active = false;

/** 'on' | 'off' when bureau_config decides, null when it has no opinion. */
let forced = null;

/** Where the current state came from, for `BureauImageMap.source`. */
let source = 'default';

/** True only while install() is applying the initial state. */
let booting = false;

/** @type {Layer[]} */
const layers = [];

// --- master switch ---------------------------------------------------------

/**
 * Read the `image_map` switch out of bureau_config. Anything other than null or
 * undefined counts as a decision, so 0 means "off" rather than "unset".
 * @returns {'on' | 'off' | null}
 */
function configFlag() {
    const value = SITE_INFO ? /** @type {any} */ (SITE_INFO).image_map : undefined;
    if (value === undefined || value === null) return null;
    return value ? 'on' : 'off';
}

// --- reading the map ---------------------------------------------------

/**
 * @param {HTMLAreaElement} area
 * @returns {number[]}
 */
function parseCoords(area) {
    const raw = area.getAttribute('coords') || '';
    return raw
        .split(/[\s,]+/)
        .filter(Boolean)
        .map(Number)
        .filter((n) => Number.isFinite(n));
}

/**
 * Behave like the browser: an absent or unrecognised shape attribute means
 * the shape comes from how many numbers the coords hold (4 = rect,
 * 3 = circle, 6+ and even = poly, anything else = the whole image).
 * @param {HTMLAreaElement} area
 * @param {number[]} nums
 * @returns {string}
 */
function shapeOf(area, nums) {
    const declared = (area.getAttribute('shape') || '').trim().toLowerCase();
    if (declared === 'rect' || declared === 'rectangle') return 'rect';
    if (declared === 'circle') return 'circle';
    if (declared === 'poly' || declared === 'polygon') return 'poly';
    if (declared === 'default') return 'default';
    if (nums.length === 4) return 'rect';
    if (nums.length === 3) return 'circle';
    if (nums.length >= 6 && nums.length % 2 === 0) return 'poly';
    return 'default';
}

/**
 * @param {string} name
 * @returns {HTMLMapElement | null}
 */
function mapNamed(name) {
    for (const map of document.querySelectorAll('map')) {
        if ((map.getAttribute('name') || '') === name) return map;
    }
    return null;
}

/**
 * Image map coords are defined against the image's intrinsic size, so that
 * is the space we draw in and the viewBox scales for us.
 * @param {HTMLImageElement} img
 * @returns {{ natW: number, natH: number }}
 */
function measure(img) {
    return {
        natW: img.naturalWidth || Number(img.getAttribute('width')) || img.offsetWidth || 1,
        natH: img.naturalHeight || Number(img.getAttribute('height')) || img.offsetHeight || 1,
    };
}

// --- geometry ----------------------------------------------------------

/**
 * @param {string} name
 * @param {Record<string, string | number>} attrs
 * @returns {SVGElement}
 */
function svgEl(name, attrs) {
    const el = /** @type {SVGElement} */ (document.createElementNS(SVG_NS, name));
    for (const key in attrs) el.setAttribute(key, String(attrs[key]));
    el.setAttribute('class', 'bim-shape');
    // Keep the outline a crisp 1px however the image is scaled on screen.
    el.setAttribute('vector-effect', 'non-scaling-stroke');
    return el;
}

/**
 * Reduce one area to a bounding box plus the SVG element that draws it.
 * @param {string} shape
 * @param {number[]} nums
 * @param {number} natW
 * @param {number} natH
 * @returns {Box}
 */
function geometry(shape, nums, natW, natH) {
    if (shape === 'rect' && nums.length >= 4) {
        // Corners may be given in any order, so normalise them.
        const x = Math.min(nums[0], nums[2]);
        const y = Math.min(nums[1], nums[3]);
        const w = Math.abs(nums[2] - nums[0]);
        const h = Math.abs(nums[3] - nums[1]);
        return { x, y, w, h, el: svgEl('rect', { x, y, width: w, height: h }) };
    }

    if (shape === 'circle' && nums.length >= 3) {
        const cx = nums[0];
        const cy = nums[1];
        const r = Math.max(0, nums[2]);
        return { x: cx - r, y: cy - r, w: r * 2, h: r * 2, el: svgEl('circle', { cx, cy, r }) };
    }

    if (shape === 'poly' && nums.length >= 6) {
        /** @type {string[]} */
        const points = [];
        let minX = Infinity;
        let minY = Infinity;
        let maxX = -Infinity;
        let maxY = -Infinity;
        for (let i = 0; i + 1 < nums.length; i += 2) {
            const x = nums[i];
            const y = nums[i + 1];
            points.push(`${x},${y}`);
            if (x < minX) minX = x;
            if (y < minY) minY = y;
            if (x > maxX) maxX = x;
            if (y > maxY) maxY = y;
        }
        return {
            x: minX,
            y: minY,
            w: maxX - minX,
            h: maxY - minY,
            el: svgEl('polygon', { points: points.join(' ') }),
        };
    }

    // default, or coords we could not make sense of: the whole image.
    return { x: 0, y: 0, w: natW, h: natH, el: svgEl('rect', { x: 0, y: 0, width: natW, height: natH }) };
}

// --- stylesheet --------------------------------------------------------

function injectStyle() {
    if (document.getElementById(STYLE_ID)) return;
    const style = document.createElement('style');
    style.id = STYLE_ID;
    style.textContent = OVERLAY_CSS;
    document.head.appendChild(style);
}

function ejectStyle() {
    document.getElementById(STYLE_ID)?.remove();
}

// --- layer construction ------------------------------------------------

/**
 * Build the overlay shell for one image. Returns null when there is nothing
 * to instrument.
 * @param {HTMLImageElement} img
 * @returns {Layer | null}
 */
function prepare(img) {
    const usemap = (img.getAttribute('usemap') || '').trim();
    if (!usemap.startsWith('#')) return null;

    const map = mapNamed(usemap.slice(1));
    if (!map) return null;

    const areas = Array.from(map.querySelectorAll('area'));
    if (!areas.length) return null;

    const parent = img.parentElement;
    if (!parent || parent === document.documentElement) return null;

    /** @type {AreaRec[]} */
    const records = areas.map((area, index) => {
        const nums = parseCoords(area);
        return {
            index,
            area,
            nums,
            shape: shapeOf(area, nums),
            coords: nums.join(','),
            title: (area.getAttribute('title') || area.getAttribute('alt') || '').trim(),
            href: area.getAttribute('href') || '',
            el: null,
            tag: null,
            oob: false,
        };
    });

    // Give ourselves something to anchor an absolutely positioned layer to.
    let anchored = false;
    let savedPosition = '';
    if (getComputedStyle(parent).position === 'static') {
        savedPosition = parent.style.position;
        parent.style.position = 'relative';
        anchored = true;
    }

    const layer = document.createElement('div');
    layer.className = 'bim-layer';

    const svg = /** @type {SVGSVGElement} */ (document.createElementNS(SVG_NS, 'svg'));
    svg.setAttribute('class', 'bim-svg');
    svg.setAttribute('preserveAspectRatio', 'none');
    svg.setAttribute('aria-hidden', 'true');

    const tags = document.createElement('div');
    tags.className = 'bim-tags';

    const readout = document.createElement('div');
    readout.className = 'bim-readout';

    layer.append(svg, tags, readout);
    img.after(layer);

    /** @type {Layer} */
    const entry = {
        img,
        parent,
        layer,
        svg,
        tags,
        readout,
        records,
        anchored,
        savedPosition,
        alive: true,
        ro: null,
        natW: 0,
        natH: 0,
        teardown: [],
    };

    // Let the browser's own hit testing drive the highlight: hovering or
    // tabbing into a real hotspot lights up the shape drawn for it.
    for (const rec of records) {
        /** @param {Event} event */
        const on = (event) => highlight(entry, rec, true);
        /** @param {Event} event */
        const off = (event) => highlight(entry, rec, false);
        /**
         * @param {string} type
         * @param {(event: Event) => void} handler
         */
        const bind = (type, handler) => {
            rec.area.addEventListener(type, handler);
            entry.teardown.push(() => rec.area.removeEventListener(type, handler));
        };
        bind('mouseover', on);
        bind('focus', on);
        bind('mouseout', off);
        bind('blur', off);
    }

    return entry;
}

/**
 * (Re)draw every shape. Cheap enough to run again once the bytes arrive.
 * @param {Layer} entry
 */
function build(entry) {
    if (!entry.alive) return;

    const { natW, natH } = measure(entry.img);
    entry.natW = natW;
    entry.natH = natH;
    entry.svg.setAttribute('viewBox', `0 0 ${natW} ${natH}`);
    entry.svg.replaceChildren();
    entry.tags.replaceChildren();

    for (const rec of entry.records) {
        const geo = geometry(rec.shape, rec.nums, natW, natH);
        rec.oob = geo.x < 0 || geo.y < 0 || geo.x + geo.w > natW || geo.y + geo.h > natH;
        rec.el = geo.el;
        if (rec.oob) geo.el.classList.add('is-oob');
        entry.svg.appendChild(geo.el);

        const tag = document.createElement('div');
        tag.className = 'bim-tag';
        tag.textContent = `${rec.index + 1} ${rec.shape}`;
        tag.title = [rec.coords, rec.title, rec.href].filter(Boolean).join(' · ');
        // Percentage placement keeps labels put when the image is resized.
        tag.style.left = `${(geo.x / natW) * 100}%`;
        tag.style.top = `${(geo.y / natH) * 100}%`;
        rec.tag = tag;
        if (rec.oob) tag.classList.add('is-oob');
        entry.tags.appendChild(tag);
    }

    syncBox(entry);
}

/**
 * Line the layer up with the image's box, in the parent's coordinate space.
 * @param {Layer} entry
 */
function syncBox(entry) {
    if (!entry.alive) return;

    // A swap may have pulled the image out from under us.
    if (!entry.img.isConnected) {
        destroy(entry);
        return;
    }

    const { img, parent, layer } = entry;
    const rect = img.getBoundingClientRect();
    let left = 0;
    let top = 0;

    if (img.offsetParent === parent) {
        left = img.offsetLeft;
        top = img.offsetTop;
    } else {
        // Viewport maths, less the parent's border, since the absolute
        // origin sits inside it.
        const pr = parent.getBoundingClientRect();
        left = rect.left - pr.left + parent.clientLeft;
        top = rect.top - pr.top + parent.clientTop;
    }

    layer.style.left = `${left}px`;
    layer.style.top = `${top}px`;
    layer.style.width = `${img.offsetWidth || rect.width}px`;
    layer.style.height = `${img.offsetHeight || rect.height}px`;

    writeSummary(entry);
}

/** @param {Layer} entry */
function writeSummary(entry) {
    const count = entry.records.length;
    const out = entry.records.filter((rec) => rec.oob).length;
    entry.readout.textContent =
        `${count} area${count === 1 ? '' : 's'} · map ${entry.natW}×${entry.natH}` +
        ` · drawn ${Math.round(entry.layer.clientWidth)}×${Math.round(entry.layer.clientHeight)}` +
        (out ? ` · ${out} outside image` : '');
}

/**
 * @param {Layer} entry
 * @param {AreaRec} rec
 * @param {boolean} on
 */
function highlight(entry, rec, on) {
    if (!entry.alive) return;
    if (rec.el) rec.el.classList.toggle('is-hot', on);
    if (rec.tag) rec.tag.classList.toggle('is-hot', on);
    if (on) {
        entry.readout.textContent =
            `${rec.index + 1} ${rec.shape} · ${rec.coords}` +
            (rec.title ? ` · ${rec.title}` : '') +
            (rec.href ? ` → ${rec.href}` : '');
    } else {
        writeSummary(entry);
    }
}

// --- on / off ----------------------------------------------------------

/** Instrument every imagemap on the page that is not already covered. */
function scan() {
    injectStyle();

    for (const img of document.querySelectorAll('img[usemap]')) {
        if (img.dataset.bim === '1') continue;

        const entry = prepare(img);
        if (!entry) continue;

        img.dataset.bim = '1';
        layers.push(entry);
        build(entry);

        // Natural size is unknown until the image arrives; redraw once it does.
        if (!img.complete || !img.naturalWidth) {
            const again = () => build(entry);
            img.addEventListener('load', again, { once: true });
            img.addEventListener('error', again, { once: true });
            entry.teardown.push(() => {
                img.removeEventListener('load', again);
                img.removeEventListener('error', again);
            });
        }

        if (typeof ResizeObserver === 'function') {
            entry.ro = new ResizeObserver(() => syncBox(entry));
            entry.ro.observe(img);
        }
    }
}

/** @param {Layer} entry */
function destroy(entry) {
    entry.alive = false;
    if (entry.ro) entry.ro.disconnect();
    for (const undo of entry.teardown) undo();
    entry.layer.remove();
    if (entry.anchored) entry.parent.style.position = entry.savedPosition;
    delete entry.img.dataset.bim;

    // Drop it from the roster even when we are not tearing everything down.
    const i = layers.indexOf(entry);
    if (i >= 0) layers.splice(i, 1);
}

function destroyAll() {
    for (const entry of layers.splice(0)) destroy(entry);
}

/**
 * Report a toggle that bureau_config has locked out, and hand back the state
 * the caller wanted.
 * @param {string} attempt
 * @param {'on' | 'off'} state
 * @returns {boolean}
 */
function refuse(attempt, state) {
    console.info(
        `[imagemap] ${attempt}() ignored — image_map: ${state === 'off' ? 0 : 1} in ` +
        'src/scripts/bureau_config.js forces it ' + state + '. Edit the config to change that.'
    );
    return state === 'off' ? false : true;
}

export function show() {
    if (forced === 'off') return refuse('show', 'off');
    if (!active) {
        active = true;
        // A config decision is not the user's preference, so don't store it.
        if (forced === null) remember(true);
        if (!booting) source = 'manual';
        scan();
        console.info(
            `[imagemap] on — ${layers.length} map${layers.length === 1 ? '' : 's'} drawn` +
            ` (source: ${source})` +
            (forced === 'on' ? '' : '; BureauImageMap.hide() to turn off')
        );
    }
    return active;
}

export function hide() {
    if (forced === 'on') return refuse('hide', 'on');
    if (active) {
        active = false;
        if (forced === null) remember(false);
        if (!booting) source = 'manual';
        destroyAll();
        ejectStyle();
    }
    return active;
}

export function toggle() {
    return active ? hide() : show();
}

/**
 * Throw away every layer and rebuild from the current DOM. Use after swapping
 * markup in; the automatic hooks cover HTMX and the Astro client router.
 * @returns {boolean} whether the overlay is on
 */
export function refresh() {
    const wasOn = active;
    if (wasOn) {
        destroyAll();
        scan();
    }
    return wasOn;
}

/** @param {boolean} on */
function remember(on) {
    try {
        if (on) localStorage.setItem(STORAGE_KEY, '1');
        else localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Storage blocked; the toggle still works for this page.
    }
}

/** @returns {string | null} */
function remembered() {
    try {
        return localStorage.getItem(STORAGE_KEY);
    } catch {
        return null;
    }
}

/**
 * Wire up listeners and apply the first source that has an opinion: the
 * `image_map` config switch, then the URL, then localStorage. Idempotent, so
 * importing this module from several components is harmless.
 */
export function install() {
    if (installed) return api;
    installed = true;
    booting = true;

    forced = configFlag();

    // Iterate a copy: syncBox may retire a layer whose image has been detached.
    window.addEventListener('resize', () => [...layers].forEach(syncBox));

    // Content swapped in by HTMX, or a whole new document from the Astro
    // client router, brings fresh imagemaps with it.
    for (const type of ['htmx:afterSwap', 'htmx:afterSettle', 'astro:page-load']) {
        document.addEventListener(type, () => {
            if (active) refresh();
        });
    }

    /** @type {any} */ (window).BureauImageMap = api;

    if (forced !== null) {
        // The config overrides the URL and any stored preference entirely.
        source = 'config';
        if (forced === 'on') show();
    } else {
        const flag = new URLSearchParams(location.search).get(QUERY_KEY);
        if (flag === '1' || flag === '0') {
            remember(flag === '1');
            source = 'url';
        }
        if (remembered() === '1') {
            if (source === 'default') source = 'storage';
            show();
        }
    }

    booting = false;
    return api;
}

/** Public surface, also reachable as `window.BureauImageMap` once installed. */
export const api = {
    show,
    hide,
    toggle,
    refresh,
    install,
    get enabled() {
        return active;
    },
    get mapped() {
        return layers.length;
    },
    /** 'on' | 'off' when bureau_config decides, null when it defers. */
    get forced() {
        return forced;
    },
    /** config | url | storage | manual | default. */
    get source() {
        return source;
    },
};

export default api;

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', install, { once: true });
} else {
    install();
}
