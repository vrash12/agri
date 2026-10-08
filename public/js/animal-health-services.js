(() => {
  'use strict';
  function init() {
    const form = document.getElementById('animalServicesForm');
    if (!form) return;
    const list = document.getElementById('animalRows');
    const template = document.getElementById('animalRowTemplate');
    const serviceSuggestions = JSON.parse(template.dataset.serviceSuggestions || '{}');
    const breedSuggestions = JSON.parse(template.dataset.breedSuggestions || '{}');
    const add = document.getElementById('animalAddRow');
    const status = document.getElementById('animalRowStatus');
    const owner = document.getElementById('owner_name');
    const municipality = document.getElementById('municipality_id');
    const lookupStatus = document.getElementById('animalLookupStatus');
    const lookupPanel = document.getElementById('animalLookupPanel');
    const priorSelect = document.getElementById('animalPriorSelect');
    const usePrior = document.getElementById('animalUsePrior');
    const rows = () => [...list.querySelectorAll('[data-animal-row]')];
    let nextIndex = rows().length;
    let timer, controller, generation = 0, prior = [];
    let lookupKey = null;
    const refresh = () => {
      const all = rows();
      let total = 0;
      all.forEach((row, i) => {
        row.querySelector('[data-row-number]').textContent = String(i + 1);
        row.querySelectorAll('[data-field]').forEach(input => {
          input.name = `animals[${i}][${input.dataset.field}]`;
        });
        row.querySelector('[data-remove-animal]').hidden = all.length === 1;
        const count = Number(row.querySelector('[data-field="animal_count"]').value);
        if (Number.isFinite(count) && count > 0) total += count;
        const date = row.querySelector('[data-field="vaccination_date"]');
        row.querySelector('[data-field="next_service_date"]').min = date.value;
        const replaceOptions = (field, key, options) => {
          const datalist = row.querySelector(`[data-suggestions="${field}"]`);
          if (datalist.dataset.forValue === key) return;
          datalist.dataset.forValue = key;
          datalist.replaceChildren(...options.map(value => { const option = document.createElement('option'); option.value = value; return option; }));
        };
        const service = row.querySelector('[data-field="service_type"]').value;
        const species = row.querySelector('[data-field="pet_type"]').value;
        replaceOptions('service_name', service, serviceSuggestions[service] || []);
        replaceOptions('pet_breed', species, breedSuggestions[species] || []);
      });
      add.disabled = all.length >= 20;
      usePrior.disabled = all.length >= 20 || !priorSelect.value;
      status.textContent = `${all.length} of 20 rows · ${total.toLocaleString()} animals served`;
    };
    const applyAnimal = (row, animal) => {
      ['pet_type', 'pet_name', 'pet_breed', 'pet_color', 'animal_count'].forEach(field => {
        if (animal[field] !== undefined && animal[field] !== null) row.querySelector(`[data-field="${field}"]`).value = animal[field];
      });
      if (animal.pet_breed || animal.pet_color) row.querySelector('details').open = true;
    };
    const addRow = (animal = {}) => {
      if (rows().length >= 20) return;
      // An owner's first selected animal uses the empty starting row, avoiding
      // an extra required blank row. Never replace entered or edited information.
      const empty = animal.pet_type && rows().find(row => [...row.querySelectorAll('[data-field]')].every(input =>
        !input.value || (input.dataset.field === 'animal_count' && input.value === '1') ||
        (input.dataset.field === 'service_type' && input.value === 'vaccination') ||
        input.dataset.field === 'vaccination_date'));
      if (empty) {
        applyAnimal(empty, animal);
        refresh();
        empty.querySelector('[data-field="service_name"]').focus();
        return;
      }
      const fragment = template.content.cloneNode(true);
      const row = fragment.querySelector('[data-animal-row]');
      // Replace only template attribute tokens; never parse owner/animal data as HTML.
      row.querySelectorAll('*').forEach(element => {
        ['id', 'name', 'for', 'aria-describedby', 'list'].forEach(attribute => {
          if (element.hasAttribute(attribute)) element.setAttribute(attribute, element.getAttribute(attribute).replaceAll('__ROW__', String(nextIndex)));
        });
      });
      nextIndex++;
      applyAnimal(row, animal);
      list.appendChild(fragment);
      refresh();
      row.querySelector('[data-field="pet_type"]').focus();
    };
    add.hidden = false;
    add.addEventListener('click', () => addRow());
    list.addEventListener('click', event => {
      const remove = event.target.closest('[data-remove-animal]');
      if (!remove || rows().length <= 1) return;
      const row = remove.closest('[data-animal-row]');
      if ([...row.querySelectorAll('[data-field]')].some(input => input.value && !['animal_count', 'service_type', 'vaccination_date'].includes(input.dataset.field)) && !window.confirm('Remove this animal row and its unsaved details?')) return;
      row.remove();
      refresh();
      add.focus();
    });
    list.addEventListener('input', refresh);
    list.addEventListener('change', refresh);
    const clearLookup = () => {
      controller?.abort(); generation++;
      prior = [];
      lookupPanel.hidden = true;
      priorSelect.replaceChildren(new Option('Select an animal or group', ''));
      refresh();
    };
    const lookup = async () => {
      const name = owner.value.trim(), scope = municipality?.value || '';
      if (name.length < 2 || (municipality && !scope)) {
        lookupStatus.textContent = 'Enter the owner name and select a municipality to find previous animals.';
        return;
      }
      const current = generation;
      controller = new AbortController();
      lookupStatus.textContent = 'Looking for previous animals…';
      try {
        const query = new URLSearchParams({name});
        if (scope) query.set('municipality_id', scope);
        const response = await fetch(`${form.dataset.lookupUrl}?${query}`, {headers: {'X-Requested-With': 'XMLHttpRequest'}, signal: controller.signal});
        if (!response.ok) throw new Error('Lookup unavailable');
        const data = await response.json();
        if (current !== generation || name !== owner.value.trim() || scope !== (municipality?.value || '')) return;
        if (!data.exists) { lookupStatus.textContent = 'No previous service found. Enter the owner and animal details below.'; return; }
        prior = data.pets || [];
        prior.forEach((animal, i) => priorSelect.add(new Option([animal.pet_type, animal.pet_name || 'Unnamed animal / group', `${animal.animal_count || 1} served`, animal.last_service_date].filter(Boolean).join(' · '), String(i))));
        lookupPanel.hidden = false;
        lookupStatus.textContent = data.has_more ? 'Showing animals from the latest 200 services. Older records remain in the register.' : 'Previous animals found. Add the ones receiving a service today.';
        ['barangay', 'birthday'].forEach(field => {
          const input = document.getElementById(field);
          if (!input.value && data.owner?.[field]) input.value = data.owner[field];
        });
        refresh();
      } catch (error) {
        if (error.name !== 'AbortError' && current === generation) {
          lookupKey = null;
          lookupStatus.textContent = 'Could not load previous animals. Enter their details manually or try the owner name again.';
        }
      }
    };
    const schedule = () => {
      const key = JSON.stringify([owner.value.trim(), municipality?.value || '']);
      // Blur/change after typing the same owner must not clear the selector
      // just as staff are clicking a previously served animal.
      if (key === lookupKey) return;
      lookupKey = key;
      clearTimeout(timer); clearLookup();
      timer = setTimeout(lookup, 350);
    };
    owner.addEventListener('input', schedule);
    owner.addEventListener('change', schedule);
    municipality?.addEventListener('change', schedule);
    priorSelect.addEventListener('change', refresh);
    usePrior.addEventListener('click', () => {
      if (priorSelect.value === '') return;
      const animal = prior[Number(priorSelect.value)];
      if (animal) addRow(animal);
    });
    form.addEventListener('submit', () => {
      if (!form.checkValidity()) return;
      const button = form.querySelector('button[type="submit"]');
      button.disabled = true;
      button.textContent = 'Saving services…';
    });
    refresh();
    if (owner.value.trim().length >= 2) schedule();
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
})();
