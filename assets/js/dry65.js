/* Dry65 — main JS */
(function () {
  'use strict';

  /* ---- Share buttons (copy link to clipboard) ---- */
  document.querySelectorAll('[data-copy]').forEach(btn => {
    btn.addEventListener('click', async () => {
      const url = btn.getAttribute('data-copy');
      try {
        await navigator.clipboard.writeText(url);
        const original = btn.innerHTML;
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>';
        setTimeout(() => { btn.innerHTML = original; }, 1500);
      } catch (e) {
        console.warn('Clipboard API not available');
      }
    });
  });

  /* ---- Mobile menu ---- */
  const burger = document.getElementById('nav-burger');
  const menu   = document.getElementById('mobile-menu');
  const header = document.getElementById('site-header');

  if (burger && menu) {
    burger.addEventListener('click', () => {
      const open = menu.classList.toggle('open');
      burger.setAttribute('aria-expanded', open);
      menu.setAttribute('aria-hidden', !open);
      // inert: kad je meni zatvoren, svi linkovi unutar nisu focusable
      if (open) menu.removeAttribute('inert');
      else menu.setAttribute('inert', '');
      document.body.style.overflow = open ? 'hidden' : '';
      const spans = burger.querySelectorAll('span');
      if (open) {
        spans[0].style.transform = 'translateY(7px) rotate(45deg)';
        spans[1].style.opacity   = '0';
        spans[2].style.transform = 'translateY(-7px) rotate(-45deg)';
      } else {
        spans[0].style.transform = '';
        spans[1].style.opacity   = '';
        spans[2].style.transform = '';
      }
    });
    // Close on nav link click
    menu.querySelectorAll('a').forEach(a => {
      a.addEventListener('click', () => {
        menu.classList.remove('open');
        burger.setAttribute('aria-expanded', 'false');
        menu.setAttribute('aria-hidden', 'true');
        menu.setAttribute('inert', '');
        document.body.style.overflow = '';
        burger.querySelectorAll('span').forEach(s => { s.style.transform = ''; s.style.opacity = ''; });
      });
    });
  }

  /* ---- Sticky nav shadow ---- */
  if (header) {
    const onScroll = () => header.classList.toggle('scrolled', window.scrollY > 8);
    onScroll();
    window.addEventListener('scroll', onScroll, { passive: true });
  }

  /* ---- Reveal on scroll ---- */
  const revealEls = document.querySelectorAll('.reveal');
  if (revealEls.length) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach(e => {
        if (e.isIntersecting) {
          const delay = parseInt(e.target.dataset.delay || 0, 10);
          setTimeout(() => e.target.classList.add('in'), delay);
          io.unobserve(e.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -8% 0px' });
    revealEls.forEach(el => io.observe(el));
  }

  /* ---- Pricing page: length guide (cards now only open modal) ---- */
  // selectLength logic uklonjena — kartice više ne highlight-uju tabelu.
  // Klik na length-card otvara modal (handler je u Length Price Modal sekciji).

  /* ---- Packages page: length tabs + price updater (desktop + mobile) ---- */
  const pkgTabSets = document.querySelectorAll('.pkg-tabs-d, .pkg-tabs-m');
  if (pkgTabSets.length) {
    // Lengths: izvuci iz prvog tab seta
    const lengths = JSON.parse(pkgTabSets[0].dataset.lengths || '[]');
    const allTabs = document.querySelectorAll('.pkg-tabs-d .length-tab, .pkg-tabs-m .length-tab');

    function formatRsd(n) {
      return new Intl.NumberFormat('sr-RS', { maximumFractionDigits: 0 }).format(n).replace(/\s/g, '.');
    }

    function selectPkgLength(id) {
      const length = lengths.find(l => l.id === id);
      if (!length) return;

      // Sinhronizuj sve tab setove (i desktop i mobile)
      allTabs.forEach(t => t.classList.toggle('sel', t.dataset.id === id));

      // Updateuj sve cene (i desktop i mobile imaju .pkg-price)
      document.querySelectorAll('.pkg-price').forEach(el => {
        const count   = parseInt(el.dataset.count, 10);
        const monthly = length.price * count;
        el.textContent = formatRsd(monthly);
      });
    }

    allTabs.forEach(tab => tab.addEventListener('click', () => {
      selectPkgLength(tab.dataset.id);
      try { localStorage.setItem('dry65_length_v2', tab.dataset.id); } catch(e) {}
    }));

    // Default: localStorage, fallback 'kratka'
    let initialPkg = 'kratka';
    try { initialPkg = localStorage.getItem('dry65_length_v2') || 'kratka'; } catch(e) {}
    if (!lengths.find(l => l.id === initialPkg)) initialPkg = 'kratka';
    selectPkgLength(initialPkg);
  }

  /* ---- Length Price Modal (otvara se klikom na length-card) ---- */
  const lpModal = document.getElementById('lp-modal');
  if (lpModal) {
    const lpTitle  = lpModal.querySelector('#lp-title');
    const lpItems  = lpModal.querySelectorAll('.lp-item');
    const lpLengths = JSON.parse(lpModal.dataset.lengths || '{}');
    function lpFormatRsd(n) {
      return new Intl.NumberFormat('sr-RS', { maximumFractionDigits: 0 }).format(n).replace(/\s/g, '.');
    }
    // Spreči scroll u pozadini bez fixed-position trika (nema skoka)
    function lpPreventTouch(e) {
      // Dozvoli skrol unutar sheet-a
      const sheet = lpModal.querySelector('.lp-sheet');
      if (sheet && sheet.contains(e.target)) return;
      e.preventDefault();
    }
    function lpOpen(id) {
      const lng = lpLengths[id];
      if (!lng) return;
      lpTitle.textContent = lng.label + ' kosa';
      lpItems.forEach(item => {
        const prices = JSON.parse(item.dataset.prices || '[]');
        const price  = prices[lng.index];
        const priceEl = item.querySelector('.lp-price');
        if (priceEl) priceEl.innerHTML = price ? lpFormatRsd(price) + '<span class="u">din</span>' : '—';
      });
      document.documentElement.classList.add('lp-locked');
      document.body.classList.add('lp-locked');
      document.addEventListener('touchmove', lpPreventTouch, { passive: false });
      lpModal.classList.add('open');
      lpModal.setAttribute('aria-hidden', 'false');
    }
    function lpClose() {
      lpModal.classList.remove('open');
      lpModal.setAttribute('aria-hidden', 'true');
      document.documentElement.classList.remove('lp-locked');
      document.body.classList.remove('lp-locked');
      document.removeEventListener('touchmove', lpPreventTouch);
    }
    document.querySelectorAll('.length-card').forEach(card => {
      card.addEventListener('click', () => lpOpen(card.dataset.id));
    });
    lpModal.querySelectorAll('[data-lp-close]').forEach(el => el.addEventListener('click', lpClose));
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape' && lpModal.classList.contains('open')) lpClose();
    });
  }

  /* ---- Reviews slider arrows ---- */
  const rsTrack = document.getElementById('reviews-slider');
  if (rsTrack) {
    const wrap = rsTrack.closest('.reviews-slider-wrap');
    const prev = wrap && wrap.querySelector('.rs-prev');
    const next = wrap && wrap.querySelector('.rs-next');
    function step() {
      const card = rsTrack.querySelector('.rs-card');
      if (!card) return rsTrack.clientWidth * 0.8;
      return card.offsetWidth + 16;
    }
    if (prev) prev.addEventListener('click', () => rsTrack.scrollBy({ left: -step(), behavior: 'smooth' }));
    if (next) next.addEventListener('click', () => rsTrack.scrollBy({ left:  step(), behavior: 'smooth' }));
  }

})();
