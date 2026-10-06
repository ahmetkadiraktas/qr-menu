(function () {
  'use strict';

  // Aktif kategori chip'ini gorunur alana kaydir
  var chipBar = document.getElementById('chipBar');
  if (chipBar) {
    var active = chipBar.querySelector('.chip.active');
    if (active) {
      var left = active.offsetLeft - (chipBar.clientWidth - active.offsetWidth) / 2;
      chipBar.scrollTo({ left: Math.max(0, left), behavior: 'auto' });
    }
  }

  // Kampanya seridi: birden fazlaysa sirayla degisir; kapatilinca bu ziyarette tekrar cikmaz
  var band = document.getElementById('promoBand');
  if (band) {
    var bandItems = band.querySelectorAll('.promo-band-item');
    var bandIdx = 0;
    if (bandItems.length > 1) {
      setInterval(function () {
        if (band.hidden) return;
        bandItems[bandIdx].classList.remove('active');
        bandIdx = (bandIdx + 1) % bandItems.length;
        bandItems[bandIdx].classList.add('active');
      }, 5000);
    }
    var bandClose = document.getElementById('promoBandClose');
    if (bandClose) {
      bandClose.addEventListener('click', function () {
        band.hidden = true;
        try { window.sessionStorage.setItem('bandClosed', band.getAttribute('data-key') || ''); } catch (err) {}
      });
    }
  }

  // Kampanya penceresi: her ziyarette (sekme oturumu) bir kez kendiliginden acilir
  var promo = document.getElementById('promo');
  var promoSlides = document.getElementById('promoSlides');
  if (promo && promoSlides) {
    var promoKey = promo.getAttribute('data-key') || '';
    var promoDots = promo.querySelectorAll('.promo-dots i');
    var promoIdx = 0;

    var promoSeen = function () {
      try { return window.sessionStorage.getItem('promoSeen') === promoKey; } catch (err) { return false; }
    };
    var openPromo = function () {
      promo.hidden = false;
      document.body.classList.add('promo-open');
      promoSlides.scrollLeft = 0;
      try { window.sessionStorage.setItem('promoSeen', promoKey); } catch (err) {}
      var closeBtn = document.getElementById('promoClose');
      if (closeBtn) closeBtn.focus();
    };
    var closePromo = function () {
      promo.hidden = true;
      document.body.classList.remove('promo-open');
    };
    var promoGo = function (dir) {
      var next = Math.min(promoSlides.children.length - 1, Math.max(0, promoIdx + dir));
      promoSlides.scrollTo({ left: next * promoSlides.clientWidth, behavior: 'smooth' });
    };

    promo.addEventListener('click', function (e) {
      if (e.target === promo) closePromo();          // pencerenin disina dokunma
    });
    var promoCloseBtn = document.getElementById('promoClose');
    if (promoCloseBtn) promoCloseBtn.addEventListener('click', closePromo);
    document.addEventListener('keydown', function (e) {
      if (promo.hidden) return;
      if (e.key === 'Escape') closePromo();
      if (e.key === 'ArrowRight') promoGo(1);
      if (e.key === 'ArrowLeft') promoGo(-1);
    });
    promoSlides.addEventListener('scroll', function () {
      var i = Math.round(promoSlides.scrollLeft / (promoSlides.clientWidth || 1));
      if (i === promoIdx) return;
      promoIdx = i;
      for (var d = 0; d < promoDots.length; d++) promoDots[d].classList.toggle('active', d === i);
    }, { passive: true });
    var promoNavs = promo.querySelectorAll('.promo-nav');
    for (var pn = 0; pn < promoNavs.length; pn++) {
      promoNavs[pn].addEventListener('click', function () { promoGo(parseInt(this.getAttribute('data-dir'), 10) || 1); });
    }
    var promoOpeners = document.querySelectorAll('[data-promo-open]');
    for (var po = 0; po < promoOpeners.length; po++) {
      promoOpeners[po].addEventListener('click', openPromo);
    }
    if (!promoSeen()) openPromo();
  }

  // Kopyala dugmeleri (Wi-Fi sifresi). Pano destegi yoksa dugme gizli kalir.
  if (navigator.clipboard && navigator.clipboard.writeText) {
    var copyBtns = document.querySelectorAll('[data-copy]');
    for (var b = 0; b < copyBtns.length; b++) {
      copyBtns[b].hidden = false;
      copyBtns[b].addEventListener('click', function () {
        var btn = this;
        var label = btn.textContent;
        navigator.clipboard.writeText(btn.getAttribute('data-copy') || '').then(function () {
          btn.textContent = btn.getAttribute('data-copied') || 'Kopyalandı';
          setTimeout(function () { btn.textContent = label; }, 1800);
        }).catch(function () {});
      });
    }
  }

  // Galeri: nokta gostergeleri, sayac ve ok tuslari
  var gallery = document.getElementById('gallery');
  if (gallery) {
    var slides = gallery.children;
    var dots = document.querySelectorAll('#galleryDots i');
    var idxEl = document.getElementById('galleryIdx');
    var current = 0;

    function update() {
      var w = gallery.clientWidth || 1;
      var i = Math.round(gallery.scrollLeft / w);
      if (i === current) return;
      current = i;
      for (var d = 0; d < dots.length; d++) dots[d].classList.toggle('active', d === i);
      if (idxEl) idxEl.textContent = String(i + 1);
    }
    var ticking = false;
    gallery.addEventListener('scroll', function () {
      if (ticking) return;
      ticking = true;
      window.requestAnimationFrame(function () { update(); ticking = false; });
    }, { passive: true });

    var navs = document.querySelectorAll('.gallery-nav');
    for (var n = 0; n < navs.length; n++) {
      navs[n].addEventListener('click', function () {
        var dir = parseInt(this.getAttribute('data-dir'), 10) || 1;
        var next = Math.min(slides.length - 1, Math.max(0, current + dir));
        gallery.scrollTo({ left: next * gallery.clientWidth, behavior: 'smooth' });
      });
    }
    document.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
        var next = Math.min(slides.length - 1, Math.max(0, current + (e.key === 'ArrowRight' ? 1 : -1)));
        gallery.scrollTo({ left: next * gallery.clientWidth, behavior: 'smooth' });
      }
    });
  }
})();
