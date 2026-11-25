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

function reveal(targets, { trigger, y = 28, stagger = 0.08, start = 'top 86%' } = {}) {
    const list = gsap.utils.toArray(targets).filter(Boolean);
    if (!list.length) {
        return;
    }

    const origin = (typeof trigger === 'string' ? document.querySelector(trigger) : trigger) || list[0];
    const box = origin.getBoundingClientRect();
    const vars = { y, opacity: 0, duration: 0.7, ease: 'power2.out', stagger };

    if (box.top < window.innerHeight * 0.92 && box.bottom > 0) {
        gsap.from(list, vars);
        return;
    }

    gsap.from(list, {
        ...vars,
        scrollTrigger: { trigger: origin, start, once: true },
    });
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

export function boot(doc = document, win = window) {
    markScrollGuards(doc);

    if (!motionAllowed(win) || !doc.querySelector('.landing')) {
        return null;
    }

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
    const glass = doc.querySelector('.glass');
    const title = doc.getElementById('page-title');

    if (eyebrow) {
        timeline.from(eyebrow, { y: 12, opacity: 0, duration: 0.45 }, 0);
    }
    if (title) {
        timeline.from(title, { y: 22, opacity: 0, duration: 0.7 }, 0.06);
    }
    if (lede) {
        timeline.from(lede, { y: 16, opacity: 0, duration: 0.5 }, 0.2);
    }
    if (unit) {
        timeline.from(unit, { y: 16, opacity: 0, duration: 0.5 }, 0.28);
    }
    if (glass) {
        timeline.from(glass, { y: 18, opacity: 0, duration: 0.55 }, 0.16);
    }

    reveal('.kpi', { trigger: '.kpis', stagger: 0.06 });
    reveal('#pauta .board', { trigger: '#pauta', y: 24 });
    reveal('.cards .card', { trigger: '.cards', stagger: 0.08 });

    return lenis;
}

if (typeof document !== 'undefined' && typeof window !== 'undefined') {
    boot();
}
