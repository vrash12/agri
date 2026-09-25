(function () {
  'use strict';

  var dialog = document.getElementById('parcelSatelliteModal');
  var frame = document.getElementById('parcelSatelliteFrame');
  var status = document.getElementById('parcelSatelliteModalStatus');
  if (!dialog || !frame || !status || typeof dialog.showModal !== 'function') return;

  var closeButton = document.getElementById('parcelSatelliteModalClose');
  var errorPanel = document.getElementById('parcelSatelliteModalError');
  var errorText = document.getElementById('parcelSatelliteModalErrorText');
  var retry = document.getElementById('parcelSatelliteModalRetry');
  var login = document.getElementById('parcelSatelliteModalLogin');
  var opener = null;
  var currentUrl = '';
  var loadingTimer;
  var previousOverflow = '';

  function fail(message, expired) {
    clearTimeout(loadingTimer);
    status.hidden = true;
    frame.hidden = true;
    errorPanel.hidden = false;
    errorText.textContent = message;
    retry.hidden = !!expired;
    login.hidden = !expired;
    dialog.removeAttribute('aria-busy');
  }

  function load() {
    clearTimeout(loadingTimer);
    status.textContent = 'Loading parcel health view…';
    status.hidden = false;
    frame.hidden = true;
    errorPanel.hidden = true;
    dialog.setAttribute('aria-busy', 'true');
    frame.src = currentUrl;
    loadingTimer = setTimeout(function () {
      frame.src = 'about:blank';
      fail('This is taking longer than expected. Check your connection, then try again.');
    }, 25000);
  }

  function cleanup() {
    clearTimeout(loadingTimer);
    currentUrl = '';
    frame.src = 'about:blank';
    frame.hidden = true;
    status.hidden = false;
    errorPanel.hidden = true;
    dialog.removeAttribute('aria-busy');
    document.body.style.overflow = previousOverflow;
    if (opener && opener.isConnected) opener.focus();
    opener = null;
  }

  function close() { if (dialog.open) dialog.close(); }

  window.__openParcelSatelliteModal = function (plotId, trigger) {
    if (!window.__parcelSatelliteUrl || !plotId) return false;
    var url = new URL(window.__parcelSatelliteUrl.replace('__ID__', encodeURIComponent(String(plotId))), window.location.href);
    if (url.origin !== window.location.origin) return false;
    url.searchParams.set('modal', '1');
    if (!dialog.open) {
      opener = trigger || document.activeElement;
      previousOverflow = document.body.style.overflow;
      dialog.showModal();
      document.body.style.overflow = 'hidden';
    }
    currentUrl = url.href;
    load();
    closeButton.focus();
    return true;
  };

  frame.addEventListener('load', function () {
    if (!dialog.open || !currentUrl) return;
    try {
      var loadedUrl = frame.contentWindow.location.href;
      if (loadedUrl === 'about:blank') return;
      if (new URL(loadedUrl).pathname === new URL(login.href).pathname) {
        fail('Your session has expired. Sign in again to view this parcel.', true);
        return;
      }
      if (loadedUrl !== currentUrl) return;
      var content = frame.contentDocument;
      if (!content || !content.getElementById('satellitePage')) {
        fail('We could not open this parcel. Try again, or close this view and check your access with the office.');
        return;
      }
      clearTimeout(loadingTimer);
      status.hidden = true;
      errorPanel.hidden = true;
      frame.hidden = false;
      dialog.removeAttribute('aria-busy');
      content.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') { event.preventDefault(); close(); }
      });
      ['pointerdown', 'pointermove', 'keydown', 'scroll', 'touchstart'].forEach(function (name) {
        content.addEventListener(name, function () { window.dispatchEvent(new Event(name)); }, { passive: true, capture: true });
      });
    } catch (error) {
      fail('We could not open the satellite view. Check your connection, then try again.');
    }
  });

  closeButton.addEventListener('click', close);
  retry.addEventListener('click', load);
  dialog.addEventListener('cancel', function (event) { event.preventDefault(); close(); });
  dialog.addEventListener('close', cleanup);
  dialog.addEventListener('click', function (event) { if (event.target === dialog) close(); });
}());
