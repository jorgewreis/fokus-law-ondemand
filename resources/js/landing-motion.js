import gsap from 'gsap';
import { ScrollTrigger } from 'gsap/ScrollTrigger';
import Lenis from 'lenis';
import 'lenis/dist/lenis.css';

gsap.registerPlugin(ScrollTrigger);

const GUARD_SELECTOR = 'form, .glass, .table-scroll';

function motionAllowed(win = globalThis) {
    return !win.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function markScrollGuards(root) {
    root.querySelectorAll(GUARD_SELECTOR).forEach((element) => {
        element.setAttribute('data-lenis-prevent', '');
    });
}

function reveal(targets, { y = 28, stagger = 0.08 } = {}, win = window) {
    const list = gsap.utils.toArray(targets).filter(Boolean);
    if (!list.length) {
        return;
    }

    gsap.set(list, { y, opacity: 0 });

    const show = (batch) => {
        if (!batch.length) {
            return;
        }
        gsap.to(batch, { y: 0, opacity: 1, duration: 0.7, ease: 'power2.out', stagger, overwrite: true });
    };

    if (typeof win.IntersectionObserver !== 'function') {
        show(list);
        return;
    }

    const observer = new win.IntersectionObserver((entries, obs) => {
        const batch = entries.filter((entry) => entry.isIntersecting).map((entry) => entry.target);
        if (!batch.length) {
            return;
        }
        show(batch);
        batch.forEach((el) => obs.unobserve(el));
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });

    list.forEach((el) => observer.observe(el));

    win.setTimeout(() => {
        const stuck = list.filter((el) => {
            const box = el.getBoundingClientRect();
            return getComputedStyle(el).opacity === '0' && box.top < win.innerHeight && box.bottom > 0;
        });
        show(stuck);
    }, 1500);
}

function bindAnchors(lenis) {
    document.addEventListener('click', (event) => {
        const link = event.target instanceof Element ? event.target.closest('a[href^="#"]') : null;
        if (!link || event.defaultPrevented || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        const id = decodeURIComponent(link.getAttribute('href').slice(1));
        const target = id ? document.getElementById(id) : null;
        if (!target) {
            return;
        }

        event.preventDefault();
        lenis.scrollTo(target, { offset: -72 });
    }, true);
}

const MOTION_TARGETS = '.hero-copy .eyebrow, #page-title, .lede, .unit, .glass-shell, .kpi, #pauta .board, .cards .card';

function enableMotionClass(doc) {
    doc.documentElement.classList.add('js-motion');
}

function revealVisible(doc) {
    doc.documentElement.classList.remove('js-motion');
    const list = doc.querySelectorAll(MOTION_TARGETS);
    if (list.length && typeof gsap !== 'undefined') {
        gsap.set(list, { clearProps: 'opacity,transform,visibility' });
    }
}

export function boot(doc = document, win = window) {
    markScrollGuards(doc);

    if (!motionAllowed(win) || !doc.querySelector('.landing')) {
        revealVisible(doc);
        return null;
    }

    enableMotionClass(doc);

    const lenis = new Lenis({
        lerp: 0.1,
        anchors: false,
        autoRaf: false,
        stopInertiaOnNavigate: true,
        syncTouch: false,
    });

    lenis.on('scroll', ScrollTrigger.update);
    gsap.ticker.add((time) => {
        lenis.raf(time * 1000);
    });
    gsap.ticker.lagSmoothing(0);
    bindAnchors(lenis);

    const heroMedia = doc.querySelector('.hero-media');
    const hero = doc.querySelector('.hero');
    if (heroMedia && hero) {
        gsap.fromTo(heroMedia, { y: -18 }, {
            y: 18,
            ease: 'none',
            scrollTrigger: { trigger: hero, start: 'top top', end: 'bottom top', scrub: true },
        });
    }

    const timeline = gsap.timeline({ defaults: { ease: 'power3.out' } });
    const eyebrow = doc.querySelector('.hero-copy .eyebrow');
    const lede = doc.querySelector('.lede');
    const unit = doc.querySelector('.unit');
    const glass = doc.querySelector('.glass-shell') || doc.querySelector('.glass');
    const title = doc.getElementById('page-title');

    const fadeIn = (el, vars, pos) => {
        if (!el) {
            return;
        }
        gsap.set(el, { y: vars.y, opacity: 0 });
        timeline.to(el, { y: 0, opacity: 1, duration: vars.duration, ease: 'power3.out' }, pos);
    };

    fadeIn(eyebrow, { y: 12, duration: 0.45 }, 0);
    fadeIn(title, { y: 22, duration: 0.7 }, 0.06);
    fadeIn(lede, { y: 16, duration: 0.5 }, 0.2);
    fadeIn(unit, { y: 16, duration: 0.5 }, 0.28);
    fadeIn(glass, { y: 18, duration: 0.55 }, 0.16);

    reveal('.kpi', { trigger: '.kpis', stagger: 0.06 });
    reveal('#pauta .board', { trigger: '#pauta', y: 24 });
    reveal('.cards .card', { trigger: '.cards', stagger: 0.08 });

    return lenis;
}

if (typeof document !== 'undefined' && typeof window !== 'undefined') {
    if (motionAllowed(window) && document.querySelector('.landing')) {
        enableMotionClass(document);
    }
    boot();
}
