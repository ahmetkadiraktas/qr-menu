(function () {
  'use strict';

  var box = document.getElementById('qrBox');
  var urlInput = document.getElementById('qrUrl');
  var captionInput = document.getElementById('qrCaption');
  var captionOut = document.getElementById('qrCaptionOut');
  var urlOut = document.getElementById('qrUrlOut');
  var errorEl = document.getElementById('qrError');
  if (!box || !urlInput || typeof qrcode !== 'function') return;

  document.body.classList.add('qr-page');   // yazdirma stilleri sadece bu sayfada gecerli olsun

  var QUIET = 4;          // kodun etrafindaki bos alan (modul)
  var PNG_SIZE = 1200;    // indirilen PNG kenar uzunlugu (px)
  var current = null;     // gecerli QR nesnesi; adres hataliysa null

  function showError(msg) {
    if (!errorEl) return;
    errorEl.textContent = msg;
    errorEl.hidden = msg === '';
  }

  function build(text) {
    if (!/^https?:\/\/[^\s]+$/i.test(text)) {
      throw new Error('Adres http:// veya https:// ile başlamalı ve boşluk içermemeli.');
    }
    var qr = qrcode(0, 'M');
    qr.addData(text);
    try {
      qr.make();
    } catch (err) {
      throw new Error('Adres QR kod için çok uzun.');
    }
    return qr;
  }

  function toSvg(qr) {
    var n = qr.getModuleCount();
    var size = n + QUIET * 2;
    var path = '';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) {
        if (qr.isDark(r, c)) path += 'M' + (c + QUIET) + ' ' + (r + QUIET) + 'h1v1h-1z';
      }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' + size + ' ' + size + '" shape-rendering="crispEdges">'
      + '<rect width="' + size + '" height="' + size + '" fill="#fff"/><path d="' + path + '" fill="#000"/></svg>';
  }

  function toCanvas(qr) {
    var n = qr.getModuleCount();
    var cell = Math.max(1, Math.floor(PNG_SIZE / (n + QUIET * 2)));
    var canvas = document.createElement('canvas');
    canvas.width = canvas.height = cell * (n + QUIET * 2);
    var ctx = canvas.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.fillStyle = '#000';
    for (var r = 0; r < n; r++) {
      for (var c = 0; c < n; c++) {
        if (qr.isDark(r, c)) ctx.fillRect((c + QUIET) * cell, (r + QUIET) * cell, cell, cell);
      }
    }
    return canvas;
  }

  function download(blob, filename) {
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
  }

  function render() {
    var text = urlInput.value.trim();
    if (captionOut && captionInput) captionOut.textContent = captionInput.value.trim();
    try {
      current = build(text);
      box.innerHTML = toSvg(current);
      if (urlOut) urlOut.textContent = text.replace(/^https?:\/\//i, '').replace(/\/$/, '');
      showError('');
    } catch (err) {
      current = null;
      box.innerHTML = '';
      if (urlOut) urlOut.textContent = '';
      showError(text === '' ? 'Menü adresini yazın.' : err.message);
    }
  }

  urlInput.addEventListener('input', render);
  if (captionInput) captionInput.addEventListener('input', render);

  var actions = {
    qrPrint: function () { window.print(); },
    qrPng: function () {
      toCanvas(current).toBlob(function (blob) {
        if (blob) download(blob, 'menu-qr.png');
      }, 'image/png');
    },
    qrSvg: function () {
      download(new Blob([toSvg(current)], { type: 'image/svg+xml' }), 'menu-qr.svg');
    }
  };
  Object.keys(actions).forEach(function (id) {
    var btn = document.getElementById(id);
    if (!btn) return;
    btn.addEventListener('click', function () {
      if (current) actions[id]();
    });
  });

  render();
})();
