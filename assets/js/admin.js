(function () {
  'use strict';

  // Mobil sidebar
  var toggle = document.getElementById('menuToggle');
  var sidebar = document.getElementById('sidebar');
  if (toggle && sidebar) {
    toggle.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    document.addEventListener('click', function (e) {
      if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggle) {
        sidebar.classList.remove('open');
      }
    });
  }

  // Silme onayi: tarayici dialogu yerine buton uzerinde iki asamali onay
  var forms = document.querySelectorAll('form[data-confirm]');
  for (var i = 0; i < forms.length; i++) {
    forms[i].addEventListener('submit', function (e) {
      var btn = this.querySelector('button[type=submit]');
      if (!btn || btn.getAttribute('data-armed') === '1') return;
      e.preventDefault();
      var original = btn.innerHTML;
      btn.setAttribute('data-armed', '1');
      btn.innerHTML = 'Emin misiniz? Tekrar tıklayın';
      btn.title = this.getAttribute('data-confirm') || '';
      setTimeout(function () {
        btn.removeAttribute('data-armed');
        btn.innerHTML = original;
        btn.title = '';
      }, 3500);
    });
  }

  // Tek dugmede iki asamali onay (popup icindeki resim silme gibi, kendi formu olmayan dugmeler)
  var confirmBtns = document.querySelectorAll('button[data-confirm]');
  for (var c = 0; c < confirmBtns.length; c++) {
    confirmBtns[c].addEventListener('click', function (e) {
      var btn = this;
      if (btn.getAttribute('data-armed') === '1') return;
      e.preventDefault();
      var original = btn.innerHTML;
      btn.setAttribute('data-armed', '1');
      btn.innerHTML = 'Emin misiniz?';
      setTimeout(function () {
        btn.removeAttribute('data-armed');
        btn.innerHTML = original;
      }, 3500);
    });
  }

  // Uc nokta menuleri: biri acilinca digerleri, disari tiklaninca hepsi kapanir
  var kebabs = document.querySelectorAll('details.kebab');
  function closeKebabs(except) {
    for (var k = 0; k < kebabs.length; k++) {
      if (kebabs[k] !== except) kebabs[k].removeAttribute('open');
    }
  }
  for (var kb = 0; kb < kebabs.length; kb++) {
    kebabs[kb].addEventListener('toggle', function () { if (this.open) closeKebabs(this); });
  }
  document.addEventListener('click', function (e) {
    var inside = e.target.closest ? e.target.closest('details.kebab') : null;
    closeKebabs(inside);
  });

  // Surukle-birak siralama (Menu sayfasi): birakinca yeni sira arka planda kaydedilir
  if (typeof Sortable === 'function') {
    var sortLists = document.querySelectorAll('[data-sort]');
    var sortStatus = document.querySelector('[data-sort-status]');
    var sortHint = sortStatus ? sortStatus.innerHTML : '';
    var sortTimer = null;
    var saveOrder = function (list) {
      var ids = [];
      var items = list.querySelectorAll('[data-id]');
      for (var s = 0; s < items.length; s++) ids.push(items[s].getAttribute('data-id'));
      var csrf = document.querySelector('input[name=csrf]');
      var body = new FormData();
      body.append('csrf', csrf ? csrf.value : '');
      body.append('action', list.getAttribute('data-sort'));
      body.append('back_cat', list.getAttribute('data-sort-cat') || '0');
      body.append('ids', ids.join(','));
      fetch(window.location.pathname, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (res) {
        if (!res.ok) throw new Error('HTTP ' + res.status);
        if (!sortStatus) return;
        var wasHidden = sortStatus.hasAttribute('data-was-hidden') || sortStatus.hidden;
        if (wasHidden) sortStatus.setAttribute('data-was-hidden', '1');
        sortStatus.hidden = false;
        sortStatus.textContent = 'Sıra kaydedildi ✓';
        clearTimeout(sortTimer);
        sortTimer = setTimeout(function () {
            if (wasHidden) { sortStatus.hidden = true; } else { sortStatus.innerHTML = sortHint; }
        }, 2500);
      }).catch(function () {
        window.location.reload();   // kaydedilemediyse ekrandaki sira gercegi yansitsin
      });
    };
    for (var sl = 0; sl < sortLists.length; sl++) {
      Sortable.create(sortLists[sl], {
        handle: '.drag-handle',
        draggable: '[data-id]',
        animation: 150,
        ghostClass: 'drag-ghost',
        // One cikan urunler menude her zaman ustte durur: iki grup birbirinin arasina tasinamaz
        onMove: function (evt) {
          return evt.dragged.getAttribute('data-group') === evt.related.getAttribute('data-group');
        },
        onEnd: function (evt) {
          if (evt.oldIndex !== evt.newIndex) saveOrder(evt.from);
        }
      });
    }
  }

  // Popup'lar (modal)
  var modals = document.querySelectorAll('.modal');
  function visibleModal() {
    for (var m = 0; m < modals.length; m++) {
      if (!modals[m].hidden) return modals[m];
    }
    return null;
  }
  function openModal(modal) {
    modal.hidden = false;
    document.body.classList.add('modal-open');
    var first = modal.querySelector('input[type=text], textarea');
    if (first) first.focus();
  }
  function closeModal(modal) {
    // Adresle acilan popup (duzenleme) kapaninca popup'siz adrese donulur ki sayfa yenilenince tekrar acilmasin
    var href = modal.getAttribute('data-close-href');
    if (href) {
      window.location.href = href;
      return;
    }
    modal.hidden = true;
    document.body.classList.remove('modal-open');
  }
  if (visibleModal()) document.body.classList.add('modal-open');

  var modalOpeners = document.querySelectorAll('[data-modal-open]');
  for (var mo = 0; mo < modalOpeners.length; mo++) {
    modalOpeners[mo].addEventListener('click', function (e) {
      var target = document.getElementById(this.getAttribute('data-modal-open'));
      // Baska bir popup adresle acikken (dolu form) baglantinin kendisi calisir ve bos popup'li sayfa yuklenir
      if (!target || visibleModal()) return;
      e.preventDefault();
      openModal(target);
    });
  }
  var modalClosers = document.querySelectorAll('[data-modal-close]');
  for (var mc = 0; mc < modalClosers.length; mc++) {
    modalClosers[mc].addEventListener('click', function () {
      var modal = this.closest('.modal');
      if (modal) closeModal(modal);
    });
  }
  // Disina tiklama: metin secerken fare disarida birakilirsa kapanmasin diye basis da arka planda olmali
  var downOnBackdrop = false;
  for (var mb = 0; mb < modals.length; mb++) {
    modals[mb].addEventListener('mousedown', function (e) { downOnBackdrop = e.target === this; });
    modals[mb].addEventListener('click', function (e) {
      if (e.target === this && downOnBackdrop) closeModal(this);
    });
  }
  document.addEventListener('keydown', function (e) {
    var modal = e.key === 'Escape' ? visibleModal() : null;
    if (modal) closeModal(modal);
  });

  // "Ingilizce ekle": popup genisler, Turkce kutularin yanina Ingilizceleri gelir. Gizlenen kutular degerini korur.
  var enToggles = document.querySelectorAll('[data-en-toggle]');
  for (var et = 0; et < enToggles.length; et++) {
    enToggles[et].addEventListener('click', function () {
      var box = this.closest('.modal-box');
      if (!box) return;
      var on = box.classList.toggle('with-en');
      this.setAttribute('aria-pressed', on ? 'true' : 'false');
      this.textContent = this.getAttribute(on ? 'data-label-on' : 'data-label-off');
    });
  }

  // Sekmeler (Ayarlar sayfasi). JS yoksa tum bolumler alt alta acik kalir.
  var tabLinks = document.querySelectorAll('[data-tab]');
  if (tabLinks.length) {
    var tabPanels = document.querySelectorAll('[data-panel]');
    var tabField = document.querySelector('[data-tab-field]');
    var saveBar = document.querySelector('[data-save-bar]');
    var showTab = function (name, updateUrl) {
      var target = null;
      for (var tp = 0; tp < tabPanels.length; tp++) {
        if (tabPanels[tp].getAttribute('data-panel') === name) target = tabPanels[tp];
      }
      if (!target) return false;
      for (var hp = 0; hp < tabPanels.length; hp++) tabPanels[hp].hidden = tabPanels[hp] !== target;
      for (var tl = 0; tl < tabLinks.length; tl++) {
        var on = tabLinks[tl].getAttribute('data-tab') === name;
        tabLinks[tl].classList.toggle('active', on);
        if (on) tabLinks[tl].setAttribute('aria-current', 'page'); else tabLinks[tl].removeAttribute('aria-current');
      }
      // Kaydet cubugu yalnizca ana formun icindeki sekmelerde gorunur; form hangi sekmeden kaydedildigini tasir
      var inMainForm = saveBar ? saveBar.parentNode.contains(target) : false;
      if (saveBar) saveBar.hidden = !inMainForm;
      if (tabField && inMainForm) tabField.value = name;
      if (updateUrl && window.history && window.history.replaceState) window.history.replaceState(null, '', '#' + name);
      return true;
    };
    for (var tc = 0; tc < tabLinks.length; tc++) {
      tabLinks[tc].addEventListener('click', function (e) {
        e.preventDefault();
        showTab(this.getAttribute('data-tab'), true);
      });
    }
    if (!showTab(window.location.hash.replace('#', ''), false)) {
      showTab(tabLinks[0].getAttribute('data-tab'), false);
    }
    window.addEventListener('hashchange', function () { showTab(window.location.hash.replace('#', ''), false); });
    // Gizli bir sekmedeki zorunlu alan bossa tarayici sessizce durur: o sekme acilir ki kullanici neyin eksik oldugunu gorsun
    document.addEventListener('invalid', function (e) {
      var panel = e.target.closest ? e.target.closest('[data-panel]') : null;
      if (panel && panel.hidden) showTab(panel.getAttribute('data-panel'), true);
    }, true);
  }

  // Tema secici: secili temaya ait vurgu rengi alani gosterilir
  var themeRadios = document.querySelectorAll('[data-theme-radio]');
  var accentPanels = document.querySelectorAll('[data-accent-for]');
  if (themeRadios.length && accentPanels.length) {
    var syncAccent = function () {
      var selected = '';
      for (var tr = 0; tr < themeRadios.length; tr++) {
        if (themeRadios[tr].checked) selected = themeRadios[tr].value;
      }
      for (var ap = 0; ap < accentPanels.length; ap++) {
        accentPanels[ap].hidden = accentPanels[ap].getAttribute('data-accent-for') !== selected;
      }
    };
    for (var th = 0; th < themeRadios.length; th++) themeRadios[th].addEventListener('change', syncAccent);
    syncAccent();
  }

  // Dosya secince onizleme
  var inputs = document.querySelectorAll('input[type=file][data-preview]');
  for (var k = 0; k < inputs.length; k++) {
    inputs[k].addEventListener('change', function () {
      var target = document.getElementById(this.getAttribute('data-preview'));
      if (!target) return;
      target.innerHTML = '';
      var files = this.files || [];
      for (var f = 0; f < files.length && f < 20; f++) {
        if (!files[f].type || files[f].type.indexOf('image/') !== 0) continue;
        var img = document.createElement('img');
        img.src = URL.createObjectURL(files[f]);
        img.onload = function () { URL.revokeObjectURL(this.src); };
        target.appendChild(img);
      }
    });
  }
})();
