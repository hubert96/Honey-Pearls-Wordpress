(() => {
  'use strict';
  function mount(root) {
    if (root.dataset.ready) return;
    root.dataset.ready = '1';
    const cards = [...root.querySelectorAll('.hpr-card')];
    if (!cards.length) return;
    const controls = root.querySelector('.hpr-controls');
    const pause = root.querySelector('.hpr-pause');
    const position = root.querySelector('.hpr-position');
    const reduced = matchMedia('(prefers-reduced-motion: reduce)');
    let index = 0, paused = reduced.matches, hovered = false, focused = false, visible = false, timer, busy = false;
    cards.forEach((card, i) => { card.inert = i !== 0; card.setAttribute('aria-hidden', String(i !== 0)); });
    cards[0].classList.add('is-active');
    root.classList.add('is-ready');
    controls.hidden = cards.length < 2;
    function schedule() {
      clearTimeout(timer);
      if (cards.length > 1 && !paused && !hovered && !focused && visible && !document.hidden) timer = setTimeout(() => move(1, false), 8000);
    }
    function move(direction, manual = true) {
      if (busy || cards.length < 2) return;
      busy = true;
      root.classList.toggle('is-reverse', direction < 0);
      const previous = cards[index];
      previous.classList.remove('is-active'); previous.classList.add('is-leaving');
      previous.inert = true; previous.setAttribute('aria-hidden', 'true');
      index = (index + direction + cards.length) % cards.length;
      const next = cards[index];
      next.classList.add('is-active'); next.inert = false; next.setAttribute('aria-hidden', 'false');
      next.querySelector('.hpr-text').scrollTop = 0;
      position.setAttribute('aria-live', manual ? 'polite' : 'off');
      position.textContent = `${index + 1} / ${cards.length}`;
      setTimeout(() => { previous.classList.remove('is-leaving'); busy = false; }, reduced.matches ? 0 : 560);
      schedule();
    }
    function updatePause() { pause.textContent = paused ? '▶' : 'Ⅱ'; pause.setAttribute('aria-pressed', String(paused)); pause.setAttribute('aria-label', paused ? 'Start automatic rotation' : 'Pause automatic rotation'); schedule(); }
    root.querySelectorAll('[data-dir]').forEach(button => button.addEventListener('click', () => move(Number(button.dataset.dir))));
    pause.addEventListener('click', () => { paused = !paused; updatePause(); });
    root.addEventListener('mouseenter', () => { hovered = true; schedule(); });
    root.addEventListener('mouseleave', () => { hovered = false; schedule(); });
    root.addEventListener('focusin', () => { focused = true; schedule(); });
    root.addEventListener('focusout', () => setTimeout(() => { focused = root.contains(document.activeElement); schedule(); }, 0));
    // Touching a review pauses it; page scrolling is never intercepted.
    root.addEventListener('touchstart', () => { paused = true; updatePause(); }, {passive:true});
    document.addEventListener('visibilitychange', schedule);
    reduced.addEventListener('change', () => { if (reduced.matches) { paused = true; updatePause(); } });
    new IntersectionObserver(entries => { visible = entries[0].isIntersecting; schedule(); }, {threshold:0.25}).observe(root);
    updatePause();
  }
  function init() { document.querySelectorAll('.hpr').forEach(mount); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init); else init();
})();
