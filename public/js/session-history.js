(function () {
  'use strict';

  // A browser history snapshot can outlive the authenticated session. Keep its
  // private content hidden until a fresh request has checked the current user.
  window.addEventListener('pagehide', function (event) {
    if (event.persisted) document.documentElement.style.visibility = 'hidden';
  });
  window.addEventListener('pageshow', function (event) {
    if (event.persisted) {
      document.documentElement.style.visibility = 'hidden';
      window.location.reload();
    }
  });
})();
