{{--
  Progressive enhancements shared by the LGU profile and website forms. The forms
  submit and validate normally without this script; the server remains the only
  authority on file type, size and dimensions.
--}}
@once
<script>
(() => {
  const formatSize = bytes => bytes >= 1048576 ? `${(bytes / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;

  // Show the chosen image in its tile before saving and warn early about files the server will refuse.
  document.querySelectorAll('.lgu-set input[type=file][data-preview-tile]').forEach(input => {
    const tile = document.getElementById(input.dataset.previewTile);
    const status = document.getElementById(`${input.id}_status`);
    const removal = document.getElementById(`remove_${input.id}`);
    const original = tile ? Array.from(tile.childNodes).map(node => node.cloneNode(true)) : [];
    let objectUrl = null;

    const restore = () => {
      if (objectUrl) URL.revokeObjectURL(objectUrl);
      objectUrl = null;
      tile?.replaceChildren(...original.map(node => node.cloneNode(true)));
    };

    input.addEventListener('change', () => {
      restore();
      const file = input.files?.[0];
      if (status) { status.textContent = ''; delete status.dataset.state; }
      if (!file) { input.form?.dispatchEvent(new Event('lgu:preview-changed')); return; }

      const accepted = (input.accept || '').split(',').map(type => type.trim()).filter(Boolean);
      const limit = Number(input.dataset.maxBytes || 0);
      const problems = [];
      if (accepted.length && !accepted.includes(file.type)) problems.push('This file type is not accepted');
      if (limit && file.size > limit) problems.push(`This file is ${formatSize(file.size)}; the limit is ${formatSize(limit)}`);

      if (problems.length) {
        status.dataset.state = 'warning';
        status.textContent = `${problems.join('. ')}. Choose another file before saving.`;
      } else {
        if (removal?.checked) removal.checked = false;
        status.textContent = `Selected ${file.name} (${formatSize(file.size)}). It is saved when you save these settings.`;
        if (tile && 'URL' in window) {
          objectUrl = URL.createObjectURL(file);
          const image = new Image();
          image.alt = 'Selected image preview';
          image.src = objectUrl;
          tile.replaceChildren(image);
        }
      }
      input.form?.dispatchEvent(new Event('lgu:preview-changed'));
    });
  });

  // Visible character counters for long text; the maxlength attribute still enforces the limit.
  document.querySelectorAll('.lgu-set textarea[maxlength]').forEach(area => {
    const limit = Number(area.getAttribute('maxlength'));
    const counter = document.createElement('span');
    counter.className = 'lgu-set-counter';
    counter.setAttribute('aria-hidden', 'true');
    const update = () => { counter.textContent = `${area.value.length.toLocaleString()} / ${limit.toLocaleString()}`; };
    area.insertAdjacentElement('afterend', counter);
    area.addEventListener('input', update);
    update();
  });
})();
</script>
@endonce
