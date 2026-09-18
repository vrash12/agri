{{--
  Stops a second submission of a sheet form after the browser has accepted the
  first one. The button is only locked once the form is actually being sent, so
  a validation failure never leaves staff with a dead Save button.
--}}
@once
@push('scripts')
<script>
(() => {
  document.querySelectorAll('form[data-sheet-form]').forEach((form) => {
    const button = form.querySelector('.rice-sheet-submit');
    const status = form.querySelector('[data-sheet-form-status]');

    form.addEventListener('submit', () => {
      if (!form.checkValidity() || !button || button.disabled) return;
      button.disabled = true;
      button.classList.add('is-saving');
      form.setAttribute('aria-busy', 'true');
      if (status) status.textContent = 'Saving. Please wait before trying again.';
    });
  });
})();
</script>
@endpush
@endonce
