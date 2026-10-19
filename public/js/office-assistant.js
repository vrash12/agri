(function () {
  'use strict';
  const root = document.querySelector('[data-office-assistant]');
  if (!root) return;
  const find = (name) => root.querySelector('[data-assistant-' + name + ']');
  const dialog = find('dialog'), open = find('open'), input = find('input');
  const form = find('form'), send = find('send'), clear = find('clear');
  const welcome = find('welcome'), messages = find('messages'), body = find('body');
  const status = find('status'), count = find('count'), jump = find('jump');
  const cooldown = find('cooldown');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  const guidePicker = find('guide-picker');
  const guideButtons = [...root.querySelectorAll('[data-assistant-guide]')];
  const guides = new Map(guideButtons.map(button => [button.dataset.assistantGuide, {
    id: button.dataset.assistantGuide, name: button.dataset.name,
    avatar: button.dataset.avatar, portrait: button.dataset.portrait, alt: button.dataset.alt
  }]));
  const defaultGuide = guides.get(root.dataset.guide) || { id: 'ka-berto', name: 'Ka Berto' };
  let activeGuide = defaultGuide;
  const guidePreference = 'eagrilgu.assistant-guide.v1';
  let context = null, busy = false, controller = null, serial = 0, pending = null;
  let composing = false, sessionExpired = false;
  let cooldownUntil = 0, cooldownTimer = null;
  let suggestedDraft = null, latestSuggestions = new Set();
  const suggestionControls = new WeakSet();

  const validToken = (value) => typeof value === 'string' && value.length > 0
    && value.length <= 4096 && !/[\u0000-\u0020\u007f]/.test(value);
  const nearBottom = () => body.scrollHeight - body.scrollTop - body.clientHeight <= 48;
  const coolingDown = () => cooldownUntil > Date.now();

  function announce(text) { status.textContent = text; }
  function selectGuide(id, persist = true) {
    if (busy || sessionExpired || !guides.has(id)) return;
    activeGuide = guides.get(id);
    root.dataset.guide = id;
    root.querySelectorAll('[data-assistant-avatar]').forEach(image => { image.src = activeGuide.avatar; });
    root.querySelectorAll('[data-assistant-guide-name]').forEach(label => { label.textContent = activeGuide.name; });
    const launcherName = find('launcher-name'), portrait = find('portrait');
    if (launcherName) launcherName.textContent = 'Ask ' + activeGuide.name;
    if (portrait) { portrait.src = activeGuide.portrait; portrait.alt = activeGuide.alt; }
    open.setAttribute('aria-label', 'Chat with ' + activeGuide.name);
    messages.setAttribute('aria-label', 'Conversation with ' + activeGuide.name);
    input.setAttribute('placeholder', 'Ask ' + activeGuide.name + '…');
    guideButtons.forEach(button => button.setAttribute('aria-pressed', String(button.dataset.assistantGuide === id)));
    if (guidePicker) guidePicker.open = false;
    if (persist) {
      // Only an allowlisted appearance preference is retained, never chat or records.
      try { window.localStorage.setItem(guidePreference, id); } catch (_) { /* Storage is optional. */ }
      announce(activeGuide.name + ' is your guide. Your conversation is unchanged.');
      input.focus();
    }
  }
  function replyLabel(guide, source) {
    return guide.name + (source === 'records' ? ' · Office records' : source === 'help' ? ' · System guide' : '');
  }
  function updateCount() { count.textContent = input.value.length + ' / 800'; }
  function refreshControls() {
    const blocked = busy || sessionExpired || coolingDown();
    send.disabled = blocked;
    input.disabled = sessionExpired;
    form.setAttribute('aria-busy', String(busy));
    guideButtons.forEach(button => { button.disabled = busy || sessionExpired; });
    messages.querySelectorAll('button').forEach((button) => {
      button.disabled = blocked || (suggestionControls.has(button) && !latestSuggestions.has(button));
    });
  }
  function setBusy(value) {
    busy = value;
    refreshControls();
  }
  function refreshCooldown(announceEnd = false) {
    if (cooldownTimer !== null) window.clearTimeout(cooldownTimer);
    cooldownTimer = null;
    const remaining = Math.max(0, Math.ceil((cooldownUntil - Date.now()) / 1000));
    if (cooldown) {
      cooldown.hidden = !remaining;
      cooldown.textContent = remaining ? 'Try again in ' + remaining + ' second' + (remaining === 1 ? '.' : 's.') : '';
    }
    refreshControls();
    if (remaining) cooldownTimer = window.setTimeout(() => refreshCooldown(true), 1000);
    else if (cooldownUntil) {
      cooldownUntil = 0;
      if (announceEnd && !sessionExpired) announce('You can try sending again.');
    }
  }
  function waitAfterRateLimit(response) {
    const header = response.headers?.get('Retry-After');
    let seconds = 60;
    // Bound untrusted headers; support the two HTTP Retry-After forms without
    // allowing an invalid or distant date to leave this browser unusable.
    if (typeof header === 'string' && header.length <= 80) {
      const value = header.trim();
      if (/^\d{1,10}$/.test(value)) seconds = Number(value);
      else if (/^[A-Za-z]{3},/.test(value)) {
        const timestamp = Date.parse(value);
        if (Number.isFinite(timestamp)) seconds = Math.ceil((timestamp - Date.now()) / 1000);
      }
    }
    seconds = Math.max(1, Math.min(300, seconds));
    cooldownUntil = Math.max(cooldownUntil, Date.now() + seconds * 1000);
    refreshCooldown();
  }
  function restoreComposerFocus(origin) {
    if (!origin || !dialog.open || sessionExpired) return;
    // Disabling/removing the initiating reply control can drop native focus to
    // the page body. Preserve any deliberate move to another dialog control.
    if (document.activeElement === origin || document.activeElement === document.body) input.focus();
  }
  function keepReading(atBottom) {
    if (atBottom) {
      body.scrollTop = body.scrollHeight;
      jump.hidden = true;
    } else {
      jump.hidden = false;
    }
  }
  function message(role, text, source, guideId = activeGuide.id) {
    const atBottom = nearBottom();
    const guide = guides.get(guideId) || defaultGuide;
    const entry = document.createElement('div');
    entry.className = 'assistant-message' + (role === 'user' ? ' is-user' : '');
    if (role !== 'user') entry.setAttribute('data-guide', guide.id);
    const label = document.createElement('strong');
    label.textContent = role === 'user' ? 'You' : replyLabel(guide, source);
    const content = document.createElement('p');
    content.textContent = text;
    entry.append(label, content);
    messages.append(entry);
    welcome.hidden = true;
    keepReading(atBottom);
    return { entry, label, content, guide };
  }
  function setThinking(reply) {
    // Keep the pending turn in the conversation while the server works. The
    // class is intentionally separate from the text so the shared stylesheet
    // can animate a small typing indicator without changing the accessible
    // announcement or exposing provider details.
    reply.content.classList.add('assistant-thinking');
    reply.content.setAttribute('role', 'status');
    reply.content.setAttribute('aria-live', 'polite');
    reply.content.setAttribute('aria-label', reply.guide.name + ' is checking your question');
    reply.content.textContent = 'Checking…';
  }
  function clearThinking(reply) {
    reply.content.classList.remove('assistant-thinking');
    reply.content.removeAttribute('role');
    reply.content.removeAttribute('aria-live');
    reply.content.removeAttribute('aria-label');
  }
  function actionContainer(reply) {
    const actions = document.createElement('div');
    actions.className = 'assistant-reply-actions';
    reply.entry.append(actions);
    return actions;
  }
  function stageSuggestion(text, replyContext) {
    if (busy || sessionExpired || coolingDown()) return;
    if (input.value.trim()) {
      announce('Finish or clear your current draft before choosing a suggestion.');
      input.focus();
      return;
    }
    input.value = text;
    // A suggestion belongs to its reply, even if a farmer choice changes the
    // conversation before this editable draft is sent. Editing releases it.
    suggestedDraft = { message: text, context: replyContext };
    input.removeAttribute('aria-invalid');
    updateCount();
    announce('Suggested message ready. Edit it or send when you are ready.');
    input.focus();
  }
  function showReply(reply, text, data) {
    const atBottom = nearBottom();
    latestSuggestions = new Set();
    clearThinking(reply);
    reply.entry.classList.remove('is-pending', 'is-error');
    reply.entry.removeAttribute('aria-busy');
    reply.label.textContent = replyLabel(reply.guide, data?.source);
    reply.content.textContent = text;
    reply.entry.replaceChildren(reply.label, reply.content);
    if (data) {
      const details = [];
      if (typeof data.scope === 'string' && data.scope.trim()) details.push(data.scope.slice(0, 200));
      if (Number.isInteger(data.year) && data.year >= 1900 && data.year <= 2100) details.push('Year ' + data.year);
      if (details.length) {
        const metadata = document.createElement('p');
        metadata.className = 'assistant-message-meta';
        metadata.textContent = details.join(' · ');
        reply.entry.append(metadata);
      }
      const sourceTitles = (Array.isArray(data.sources) ? data.sources : []).slice(0, 3)
        .filter(source => typeof source?.id === 'string' && /^[a-z0-9-]{1,80}$/.test(source.id)
          && typeof source.title === 'string' && source.title.trim() && source.title.length <= 120)
        .map(source => source.title);
      if (sourceTitles.length) {
        const evidence = document.createElement('p');
        evidence.className = 'assistant-message-meta';
        evidence.textContent = 'Based on: ' + [...new Set(sourceTitles)].join(' · ');
        reply.entry.append(evidence);
      }
      const actions = actionContainer(reply);
      (Array.isArray(data.actions) ? data.actions : []).slice(0, 6).forEach((action) => {
        if (!validToken(action.choice) || typeof action.label !== 'string'
          || !action.label.trim() || action.label.length > 200) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = action.label;
        button.addEventListener('click', () => {
          if (busy || sessionExpired) return;
          return sendQuestion({ question: 'Continue with my choice.', visible: action.label,
            choice: action.choice, context: data.context || null }, null,
            document.activeElement === button ? button : null);
        });
        actions.append(button);
      });
      (Array.isArray(data.links) ? data.links : []).slice(0, 3).forEach((link) => {
        if (typeof link.url !== 'string' || link.url.length > 2048 || typeof link.label !== 'string'
          || !link.label.trim() || link.label.length > 200) return;
        try {
          const url = new URL(link.url, window.location.href);
          if (url.origin !== window.location.origin || !['http:', 'https:'].includes(url.protocol)
            || url.username || url.password) return;
          const anchor = document.createElement('a');
          anchor.href = url.href;
          anchor.textContent = link.label;
          actions.append(anchor);
        } catch (_) { /* Malformed navigation is never rendered. */ }
      });
      if (!actions.childElementCount) actions.remove();
      const suggestions = document.createElement('div');
      suggestions.className = 'assistant-followups';
      suggestions.setAttribute('role', 'group');
      suggestions.setAttribute('aria-label', 'Suggested follow-up messages');
      (Array.isArray(data.suggestions) ? data.suggestions : []).slice(0, 3).forEach((suggestion) => {
        if (typeof suggestion?.label !== 'string' || !suggestion.label.trim() || suggestion.label.length > 80
          || typeof suggestion.message !== 'string' || !suggestion.message.trim() || suggestion.message.length > 200
          || /[\u0000-\u001f\u007f-\u009f]/.test(suggestion.label + suggestion.message)) return;
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = suggestion.label;
        suggestionControls.add(button);
        latestSuggestions.add(button);
        button.addEventListener('click', () => {
          if (!latestSuggestions.has(button)) return;
          stageSuggestion(suggestion.message.trim(), data.context);
        });
        suggestions.append(button);
      });
      if (suggestions.childElementCount) reply.entry.append(suggestions);
    }
    refreshControls();
    keepReading(atBottom);
  }
  function retryReply(reply, text, request) {
    showReply(reply, text);
    reply.entry.classList.add('is-error');
    const retry = document.createElement('button');
    retry.type = 'button';
    retry.textContent = 'Retry';
    retry.addEventListener('click', () => {
      if (busy || sessionExpired) return;
      return sendQuestion(request, reply, document.activeElement === retry ? retry : null);
    });
    actionContainer(reply).append(retry);
    announce(text + (coolingDown() && cooldown ? ' ' + cooldown.textContent : ''));
  }
  function cancel(reason, preserveReply) {
    serial++;
    if (controller) controller.abort();
    controller = null;
    if (pending && preserveReply) retryReply(pending.reply, reason, pending.request);
    pending = null;
    setBusy(false);
  }
  function reset() {
    cancel('', false);
    context = null;
    suggestedDraft = null;
    latestSuggestions = new Set();
    messages.replaceChildren();
    welcome.hidden = false;
    jump.hidden = true;
    input.value = '';
    input.removeAttribute('aria-invalid');
    updateCount();
    announce('');
    refreshCooldown();
  }
  function expireSession() {
    reset();
    sessionExpired = true;
    setBusy(false);
    const text = 'Your session has ended. Sign in again, then reopen your farm guide.';
    message('assistant', text);
    announce(text);
  }
  function localError(text) {
    latestSuggestions = new Set();
    const reply = message('assistant', text);
    reply.entry.classList.add('is-error');
    refreshControls();
    announce(text);
    input.focus();
  }

  async function sendQuestion(request, existingReply, focusOrigin) {
    if (busy || sessionExpired) return;
    if (coolingDown()) {
      refreshCooldown();
      announce(cooldown?.textContent || 'Wait a moment before trying again.');
      return;
    }
    const question = request?.question ?? input.value.trim();
    if (!question || question.length > 800) {
      input.setAttribute('aria-invalid', 'true');
      localError('Enter a message within 800 characters.');
      return;
    }
    const draftContext = suggestedDraft?.message === question ? suggestedDraft.context : context;
    const outgoing = request ? { ...request, guide: guides.has(request.guide) ? request.guide : activeGuide.id }
      : { question, visible: question, context: draftContext, choice: null, guide: activeGuide.id };
    if ((outgoing.context !== null && outgoing.context !== undefined && !validToken(outgoing.context))
      || (outgoing.choice && !validToken(outgoing.choice))) {
      localError('That reply choice is no longer available. Ask your question again.');
      return;
    }
    const requestId = ++serial;
    if (!existingReply) message('user', outgoing.visible || question);
    const reply = existingReply || message('assistant', 'Checking…', null, outgoing.guide);
    showReply(reply, 'Checking…');
    setThinking(reply);
    reply.entry.classList.add('is-pending');
    reply.entry.setAttribute('aria-busy', 'true');
    pending = { reply, request: outgoing };
    controller = new AbortController();
    const activeController = controller;
    const timeout = window.setTimeout(() => activeController.abort(), 30000);
    setBusy(true);

    // The visible conversation stays in browser memory. Only opaque reference hints
    // accompany this turn; no transcript, client-selected mode or farmer ID is sent.
    const payload = { message: question, module: root.dataset.module, guide: outgoing.guide };
    if (outgoing.context) payload.context = outgoing.context;
    if (outgoing.choice) payload.choice = outgoing.choice;
    try {
      const response = await fetch(root.dataset.endpoint, {
        method: 'POST', credentials: 'same-origin', signal: activeController.signal,
        headers: { 'Accept': 'application/json', 'Content-Type': 'application/json',
          'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '' },
        body: JSON.stringify(payload)
      });
      if (requestId !== serial) return;
      if (response.status === 401 || response.status === 419 || response.redirected) {
        expireSession();
        return;
      }
      const data = await response.json().catch(() => ({}));
      if (requestId !== serial) return;
      if (!response.ok) {
        if (response.status === 429) waitAfterRateLimit(response);
        const expected = [403, 404, 409, 422, 429, 503].includes(response.status);
        const error = data.errors?.message?.[0] || data.message;
        const safeText = expected && typeof error === 'string' && error.length <= 800
          ? error : 'Your guide could not answer. Please try again shortly.';
        if (response.status === 403) {
          const draft = input.value;
          reset();
          input.value = draft;
          updateCount();
          localError(safeText);
          return;
        }
        retryReply(reply, safeText, outgoing);
        return;
      }
      if (typeof data.answer !== 'string' || !data.answer.trim() || data.answer.length > 6000
        || !['records', 'help', 'clarification', 'conversation'].includes(data.source)
        || (data.context !== null && !validToken(data.context))) {
        retryReply(reply, 'The reply could not be verified. Try again or start a new chat.', outgoing);
        return;
      }
      context = data.context;
      showReply(reply, data.answer, data);
      if (!outgoing.choice && input.value.trim() === question) {
        input.value = '';
        suggestedDraft = null;
      }
      input.removeAttribute('aria-invalid');
      updateCount();
      announce('');
    } catch (error) {
      if (requestId === serial) retryReply(reply, error.name === 'AbortError'
        ? 'The reply took too long. Your message is still here; try again.'
        : 'Could not connect. Check your connection, then try again.', outgoing);
    } finally {
      window.clearTimeout(timeout);
      if (requestId === serial) {
        controller = null;
        pending = null;
        setBusy(false);
        restoreComposerFocus(focusOrigin);
      }
    }
  }

  // Reading a stale or tampered preference cannot add a fifth guide or asset URL.
  let savedGuide;
  try { savedGuide = window.localStorage.getItem(guidePreference); } catch (_) { /* Storage is optional. */ }
  selectGuide(guides.has(savedGuide) ? savedGuide : defaultGuide.id, false);
  guideButtons.forEach(button => button.addEventListener('click', () => selectGuide(button.dataset.assistantGuide)));
  if (guidePicker) guidePicker.addEventListener('keydown', event => {
    if (event.key !== 'Escape' || !guidePicker.open) return;
    event.preventDefault();
    event.stopPropagation();
    guidePicker.open = false;
    guidePicker.querySelector('summary')?.focus();
  });
  open.hidden = false;
  open.addEventListener('click', () => {
    refreshCooldown();
    dialog.showModal();
    open.setAttribute('aria-expanded', 'true');
    if (sessionExpired) body.focus(); else input.focus();
  });
  find('close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => {
    if (guidePicker) guidePicker.open = false;
    cancel('Message canceled. You can retry when you reopen your farm guide.', true);
    open.setAttribute('aria-expanded', 'false');
    open.focus();
  });
  clear.addEventListener('click', () => { reset(); if (!sessionExpired) input.focus(); });
  input.addEventListener('input', () => {
    suggestedDraft = null;
    input.removeAttribute('aria-invalid');
    updateCount();
  });
  input.addEventListener('compositionstart', () => { composing = true; });
  input.addEventListener('compositionend', () => { composing = false; });
  input.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.shiftKey || event.isComposing || composing || event.keyCode === 229) return;
    event.preventDefault();
    return sendQuestion();
  });
  body.addEventListener('scroll', () => { if (nearBottom()) jump.hidden = true; });
  jump.addEventListener('click', () => {
    body.scrollTop = body.scrollHeight;
    jump.hidden = true;
    body.focus();
  });
  root.querySelectorAll('[data-assistant-suggestion]').forEach((button) => {
    button.addEventListener('click', () => {
      if (busy || sessionExpired) return;
      input.value = button.childNodes[0].textContent.trim();
      input.removeAttribute('aria-invalid');
      updateCount();
      input.focus();
    });
  });
  form.addEventListener('submit', (event) => {
    event.preventDefault();
    return sendQuestion(null, null, document.activeElement === send ? send : null);
  });
  window.addEventListener('pagehide', () => {
    reset();
    if (dialog.open) dialog.close();
  });
})();
