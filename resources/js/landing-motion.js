import Lenis from 'lenis';
import 'lenis/dist/lenis.css';

/*
 * Motion da landing (variante B aprovada).
 *
 * Regras:
 * - Sem JS, nada fica escondido: o estado oculto só existe com html.js-motion,
 *   que é adicionado aqui depois que o observador está pronto.
 * - prefers-reduced-motion: reduce → tudo visível e estático, sem Lenis, sem cursor.
 * - Fallback: em REVEAL_FALLBACK_MS todos os alvos são revelados, mesmo fora da
 *   dobra (calendário, avisos e dados da unidade nunca ficam presos).
 * - Sem script inline: este módulo é servido pelo Vite (CSP script-src 'self').
 */

const GUARD_SELECTOR = 'form, .glass, .table-scroll';
const CURSOR_HIDE_SELECTOR = 'form, input, textarea, select, button, label, .glass';
const CURSOR_GROW_SELECTOR = '.card, .kpi, .btn, .navlinks a, .scroll-cue, .tab-label';
export const REVEAL_FALLBACK_MS = 1100;

function motionAllowed(win = globalThis) {
    return !win.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function markScrollGuards(root) {
    root.querySelectorAll(GUARD_SELECTOR).forEach((element) => {
        element.setAttribute('data-lenis-prevent', '');
    });
}

function revealEverything(doc) {
    doc.documentElement.classList.remove('js-motion', 'has-cursor');
    doc.querySelectorAll('[data-reveal]').forEach((el) => el.classList.add('is-in', 'is-done'));
}

export function splitTitle(title, doc = document) {
    if (!title || title.querySelector('.word')) {
        return;
    }

    const frag = doc.createDocumentFragment();
    let delay = 0;

    const wrap = (text, italic) => {
        text.split(/(\s+)/).forEach((part) => {
            if (!part) {
                return;
            }
            if (/^\s+$/.test(part)) {
                frag.appendChild(doc.createTextNode(part));
                return;
            }
            const word = doc.createElement('span');
            word.className = 'word';
            const inner = doc.createElement('span');
            inner.className = 'word-inner';
            inner.style.setProperty('--d', `${delay}ms`);
            if (italic) {
                const em = doc.createElement('em');
                em.textContent = part;
                inner.appendChild(em);
            } else {
                inner.textContent = part;
            }
            word.appendChild(inner);
            frag.appendChild(word);
            delay += 45;
        });
    };

    Array.from(title.childNodes).forEach((node) => {
        if (node.nodeType === 3) {
            wrap(node.textContent, false);
        } else if (node.nodeName === 'BR') {
            frag.appendChild(doc.createElement('br'));
            delay += 70;
        } else if (node.nodeName === 'EM') {
            wrap(node.textContent, true);
        } else {
            frag.appendChild(node.cloneNode(true));
        }
    });

    // Leitores de tela continuam lendo o título inteiro.
    title.setAttribute('aria-label', title.textContent.replace(/\s+/g, ' ').trim());
    title.textContent = '';
    title.appendChild(frag);
}

export function setupReveals(doc = document, win = window) {
    const items = Array.from(doc.querySelectorAll('[data-reveal]'));
    if (!items.length) {
        return () => {};
    }

    const show = (el) => {
        if (el.classList.contains('is-in')) {
            return;
        }
        el.classList.add('is-in');
        win.setTimeout(() => el.classList.add('is-done'), 1200);
    };

    const inViewport = (el) => {
        const box = el.getBoundingClientRect();
        return box.top < win.innerHeight && box.bottom > 0;
    };

    const recheck = () => {
        items.forEach((el) => {
            if (!el.classList.contains('is-in') && inViewport(el)) {
                show(el);
            }
        });
    };

    const revealAll = () => items.forEach(show);

    let observer = null;
    if (typeof win.IntersectionObserver === 'function') {
        // threshold 0 + margem positiva: elementos altos ou parcialmente visíveis disparam.
        observer = new win.IntersectionObserver((entries, obs) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting || entry.intersectionRatio > 0) {
                    show(entry.target);
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0, rootMargin: '12% 0px 18% 0px' });
        items.forEach((el) => observer.observe(el));
    }

    win.addEventListener('resize', recheck, { passive: true });
    win.addEventListener('scroll', recheck, { passive: true });
    const timer = win.setTimeout(revealAll, REVEAL_FALLBACK_MS);

    if (!observer) {
        revealAll();
    }

    return () => {
        win.clearTimeout(timer);
        win.removeEventListener('resize', recheck);
        win.removeEventListener('scroll', recheck);
        observer?.disconnect();
    };
}

function setupParallax(doc, win) {
    const pairs = [
        [doc.querySelector('.hero-media'), doc.querySelector('.hero'), 36],
        [doc.querySelector('.dash-media'), doc.querySelector('.dash'), 28],
    ].filter(([media, host]) => media && host);

    if (!pairs.length) {
        return;
    }

    let ticking = false;
    const update = () => {
        ticking = false;
        pairs.forEach(([media, host, range]) => {
            const rect = host.getBoundingClientRect();
            if (rect.bottom < 0 || rect.top > win.innerHeight) {
                return;
            }
            const progress = -rect.top / Math.max(rect.height, 1);
            const y = Math.max(-range, Math.min(range, progress * range * 1.6));
            media.style.translate = `0 ${y.toFixed(1)}px`;
        });
    };

    win.addEventListener('scroll', () => {
        if (!ticking) {
            ticking = true;
            win.requestAnimationFrame(update);
        }
    }, { passive: true });
    update();
}

function setupCursor(doc, win) {
    const fine = win.matchMedia('(pointer: fine)').matches && win.matchMedia('(min-width: 721px)').matches;
    const dot = doc.querySelector('.cursor-dot');
    const ring = doc.querySelector('.cursor-ring');
    if (!fine || !dot || !ring) {
        return;
    }

    const root = doc.documentElement;
    root.classList.add('has-cursor', 'cursor-hidden');

    let x = -100;
    let y = -100;
    let rx = x;
    let ry = y;

    const loop = () => {
        rx += (x - rx) * 0.18;
        ry += (y - ry) * 0.18;
        dot.style.transform = `translate3d(${x}px, ${y}px, 0)`;
        ring.style.transform = `translate3d(${rx}px, ${ry}px, 0)`;
        win.requestAnimationFrame(loop);
    };

    win.addEventListener('pointermove', (event) => {
        if (event.pointerType && event.pointerType !== 'mouse') {
            root.classList.add('cursor-hidden');
            return;
        }
        x = event.clientX;
        y = event.clientY;
        const target = event.target instanceof Element ? event.target : null;
        const overForm = Boolean(target?.closest(CURSOR_HIDE_SELECTOR));
        root.classList.toggle('cursor-hidden', overForm);
        root.classList.toggle('cursor-grow', !overForm && Boolean(target?.closest(CURSOR_GROW_SELECTOR)));
    }, { passive: true });
    doc.addEventListener('pointerleave', () => root.classList.add('cursor-hidden'));
    win.addEventListener('blur', () => root.classList.add('cursor-hidden'));

    win.requestAnimationFrame(loop);
}

function bindAnchors(lenis, doc) {
    doc.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;
        if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const id = decodeURIComponent(link.getAttribute('href').slice(1));
        const target = id ? doc.getElementById(id) : null;
        if (!target) {
            return;
        }

        event.preventDefault();
        lenis.scrollTo(target, { offset: -72 });
    }, true);
}

export function boot(doc = document, win = window) {
    markScrollGuards(doc);

    if (!motionAllowed(win) || !doc.querySelector('.landing')) {
        revealEverything(doc);
        return null;
    }

    splitTitle(doc.getElementById('page-title'), doc);
    setupReveals(doc, win);
    // Só esconde depois do observador e do fallback estarem ativos.
    doc.documentElement.classList.add('js-motion');

    const lenis = new Lenis({
        lerp: 0.1,
        anchors: false,
        autoRaf: true,
        stopInertiaOnNavigate: true,
        syncTouch: false,
    });
    bindAnchors(lenis, doc);

    setupParallax(doc, win);
    setupCursor(doc, win);

    win.requestAnimationFrame(() => {
        win.requestAnimationFrame(() => doc.documentElement.classList.add('is-hero-ready'));
    });

    return lenis;
}

if (typeof document !== 'undefined' && typeof window !== 'undefined') {
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => boot(), { once: true });
    } else {
        boot();
    }
}
