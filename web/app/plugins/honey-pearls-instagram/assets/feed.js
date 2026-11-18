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
        const update = () => {
          prev.disabled = track.scrollLeft <= 2;
          next.disabled = track.scrollLeft + track.clientWidth >= track.scrollWidth - 2;
        };
        const move = direction => track.scrollBy({left: direction * track.clientWidth, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth'});
        prev.addEventListener('click', () => move(-1));
        next.addEventListener('click', () => move(1));
        track.addEventListener('scroll', update, {passive: true});
        new ResizeObserver(update).observe(track);
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
