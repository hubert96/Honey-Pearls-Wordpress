(() => {
  'use strict';
  function init(root = document) {
    root.querySelectorAll('.hp-ig').forEach(feed => {
      if (feed.dataset.hpReady) return;
      feed.dataset.hpReady = 'true';
      const track = feed.querySelector('.hp-ig__posts');
      const prev = feed.querySelector('[data-hp-prev]');
      const next = feed.querySelector('[data-hp-next]');
      if (track && prev && next) {
        const cards = Array.from(track.children);
        const dots = Array.from(feed.querySelectorAll('[data-hp-dot]'));
        const play = feed.querySelector('[data-hp-play]');
        const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
        let index = 0, frame = 0, paused = reduced.matches, hovered = false, focused = false;
        let lastMove = Date.now();
        const slideWidth = () => track.getBoundingClientRect().width;
        const fitHeight = i => { track.style.height = `${cards[i].getBoundingClientRect().height}px`; };
        const reflectPlay = () => {
          play.textContent = paused ? '▶' : 'Ⅱ';
          play.setAttribute('aria-label', paused ? 'Play automatic slideshow' : 'Pause automatic slideshow');
        };
        reflectPlay();
        const update = () => {
          index = Math.round(track.scrollLeft / Math.max(1, slideWidth()));
          dots.forEach((dot, i) => { if (i === index) dot.setAttribute('aria-current', 'true'); else dot.removeAttribute('aria-current'); });
          if (!frame) fitHeight(index);
        };
        const cancel = () => { cancelAnimationFrame(frame); frame = 0; track.style.scrollSnapType = ''; lastMove = Date.now(); };
        const go = (target, manual = true) => {
          cancel();
          target = (target + cards.length) % cards.length;
          fitHeight(target);
          const from = track.scrollLeft, to = cards[target].offsetLeft - cards[0].offsetLeft;
          const started = performance.now();
          track.style.scrollSnapType = 'none';
          const animate = now => {
            const t = reduced.matches ? 1 : Math.min(1, (now - started) / 620);
            const eased = t < .5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
            track.scrollLeft = from + (to - from) * eased;
            if (t < 1) frame = requestAnimationFrame(animate);
            else { frame = 0; track.style.scrollSnapType = ''; update(); }
          };
          frame = requestAnimationFrame(animate);
          if (manual) feed.querySelector('[data-hp-status]').textContent = `Post ${target + 1} of ${cards.length}`;
        };
        prev.addEventListener('click', () => go(index - 1));
        next.addEventListener('click', () => go(index + 1));
        dots.forEach((dot, i) => dot.addEventListener('click', () => go(i)));
        play.addEventListener('click', () => { paused = !paused; lastMove = Date.now(); reflectPlay(); });
        track.addEventListener('keydown', event => {
          if (event.target !== track || !['ArrowLeft', 'ArrowRight'].includes(event.key)) return;
          event.preventDefault(); go(index + (event.key === 'ArrowRight' ? 1 : -1));
        });
        track.addEventListener('pointerdown', cancel, {passive: true});
        track.addEventListener('wheel', cancel, {passive: true});
        feed.addEventListener('mouseenter', () => { hovered = true; });
        feed.addEventListener('mouseleave', () => { hovered = false; lastMove = Date.now(); });
        feed.addEventListener('focusin', () => { focused = true; });
        feed.addEventListener('focusout', event => { focused = feed.contains(event.relatedTarget); lastMove = Date.now(); });
        reduced.addEventListener('change', () => { if (reduced.matches) { paused = true; cancel(); reflectPlay(); } });
        const timer = setInterval(() => {
          if (!feed.isConnected) { clearInterval(timer); cancel(); observer.disconnect(); return; }
          const rect = feed.getBoundingClientRect();
          if (!paused && !hovered && !focused && !document.hidden && !feed.querySelector('dialog[open]') && rect.bottom > 0 && rect.top < innerHeight && Date.now() - lastMove >= 6000) go(index + 1, false);
        }, 500);
        track.addEventListener('scroll', update, {passive: true});
        let width = slideWidth();
        const observer = new ResizeObserver(() => {
          if (width !== slideWidth()) { cancel(); track.scrollLeft = cards[index].offsetLeft - cards[0].offsetLeft; width = slideWidth(); }
          update();
        });
        observer.observe(track);
        cards.forEach(card => observer.observe(card));
        update();
      }
      const dialog = feed.querySelector('dialog');
      if (!dialog || typeof dialog.showModal !== 'function') return;
      let trigger;
      feed.querySelectorAll('[data-hp-image]').forEach(link => link.addEventListener('click', event => {
        if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        trigger = link;
        const image = dialog.querySelector('img');
        image.src = link.dataset.hpImage;
        image.alt = link.querySelector('img').alt;
        dialog.querySelector('p').textContent = link.dataset.hpCaption;
        dialog.querySelector('a').href = link.href;
        dialog.showModal();
      }));
      dialog.querySelector('button').addEventListener('click', () => dialog.close());
      dialog.addEventListener('click', event => { if (event.target === dialog) {
        const r = dialog.getBoundingClientRect();
        if (event.clientX < r.left || event.clientX > r.right || event.clientY < r.top || event.clientY > r.bottom) dialog.close();
      }});
      dialog.addEventListener('close', () => trigger?.focus());
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', () => init());
  else init();
  // Elementor can insert widgets after the initial page load.
  new MutationObserver(records => {
    if (records.some(r => Array.from(r.addedNodes).some(n => n.nodeType === 1 && (n.matches?.('.hp-ig') || n.querySelector?.('.hp-ig'))))) init();
  }).observe(document.documentElement, {childList: true, subtree: true});
})();
