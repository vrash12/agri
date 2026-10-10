(function () {
  'use strict';
  const root = document.querySelector('[data-office-assistant]');
  if (!root) return;
  const find = (name) => root.querySelector('[data-assistant-' + name + ']');
  const dialog = find('dialog'), open = find('open'), input = find('input');
  const form = find('form'), send = find('send'), clear = find('clear');
  const welcome = find('welcome'), messages = find('messages'), body = find('body');
  const status = find('status'), count = find('count'), jump = find('jump');
  if (!dialog || typeof dialog.showModal !== 'function') return;
  let context = null, busy = false, controller = null, serial = 0, pending = null;
  let composing = false, sessionExpired = false;

  const validToken = (value) => typeof value === 'string' && value.length > 0
    && value.length <= 4096 && !/[\u0000-\u0020\u007f]/.test(value);
  const nearBottom = () => body.scrollHeight - body.scrollTop - body.clientHeight <= 48;

  function announce(text) { status.textContent = text; }
  function updateCount() { count.textContent = input.value.length + ' / 800'; }
  function setBusy(value) {
    busy = value;
    send.disabled = value || sessionExpired;
    input.disabled = sessionExpired;
    form.setAttribute('aria-busy', String(value));
    messages.querySelectorAll('button').forEach((button) => { button.disabled = value || sessionExpired; });
  }
  function keepReading(atBottom) {
    if (atBottom) {
      body.scrollTop = body.scrollHeight;
      jump.hidden = true;
    } else {
      jump.hidden = false;
    }
  }
  function message(role, text, source) {
    const atBottom = nearBottom();
    const entry = document.createElement('div');
    entry.className = 'assistant-message' + (role === 'user' ? ' is-user' : '');
    const label = document.createElement('strong');
    label.textContent = role === 'user' ? 'You' : source === 'records' ? 'Office records'
      : source === 'help' ? 'System guide' : 'Assistant';
    const content = document.createElement('p');
    content.textContent = text;
    entry.append(label, content);
    messages.append(entry);
    welcome.hidden = true;
    keepReading(atBottom);
    return { entry, label, content };
  }
  function actionContainer(reply) {
    const actions = document.createElement('div');
    actions.className = 'assistant-reply-actions';
    reply.entry.append(actions);
    return actions;
  }
  function showReply(reply, text, data) {
    const atBottom = nearBottom();
    reply.entry.classList.remove('is-pending', 'is-error');
    reply.entry.removeAttribute('aria-busy');
    reply.label.textContent = data?.source === 'records' ? 'Office records'
      : data?.source === 'help' ? 'System guide' : 'Assistant';
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
            choice: action.choice, context: data.context || null });
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
    }
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
      return sendQuestion(request, reply);
    });
    actionContainer(reply).append(retry);
    announce(text);
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
    messages.replaceChildren();
    welcome.hidden = false;
    jump.hidden = true;
    input.value = '';
    input.removeAttribute('aria-invalid');
    updateCount();
    announce('');
  }
  function expireSession() {
    reset();
    sessionExpired = true;
    setBusy(false);
    const text = 'Your session has ended. Sign in again, then reopen the Assistant.';
    message('assistant', text);
    announce(text);
  }
  function localError(text) {
    const reply = message('assistant', text);
    reply.entry.classList.add('is-error');
    announce(text);
    input.focus();
  }

  async function sendQuestion(request, existingReply) {
    if (busy || sessionExpired) return;
    const question = request?.question ?? input.value.trim();
    if (!question || question.length > 800) {
      input.setAttribute('aria-invalid', 'true');
      localError('Enter a message within 800 characters.');
      return;
    }
    const outgoing = request || { question, visible: question, context, choice: null };
    if ((outgoing.context !== null && outgoing.context !== undefined && !validToken(outgoing.context))
      || (outgoing.choice && !validToken(outgoing.choice))) {
      localError('That reply choice is no longer available. Ask your question again.');
      return;
    }
    const requestId = ++serial;
    if (!existingReply) message('user', outgoing.visible || question);
    const reply = existingReply || message('assistant', 'Working on your question…');
    showReply(reply, 'Working on your question…');
    reply.entry.classList.add('is-pending');
    reply.entry.setAttribute('aria-busy', 'true');
    pending = { reply, request: outgoing };
    controller = new AbortController();
    const activeController = controller;
    const timeout = window.setTimeout(() => activeController.abort(), 30000);
    setBusy(true);

    // The visible conversation stays in browser memory. Only opaque reference hints
    // accompany this turn; no transcript, client-selected mode or farmer ID is sent.
    const payload = { message: question, module: root.dataset.module };
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
        const expected = [403, 404, 409, 422, 429, 503].includes(response.status);
        const error = data.errors?.message?.[0] || data.message;
        const safeText = expected && typeof error === 'string' && error.length <= 800
          ? error : 'The Assistant could not answer. Please try again shortly.';
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
      if (!outgoing.choice && input.value.trim() === question) input.value = '';
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
      }
    }
  }

  open.hidden = false;
  open.addEventListener('click', () => {
    dialog.showModal();
    open.setAttribute('aria-expanded', 'true');
    if (sessionExpired) body.focus(); else input.focus();
  });
  find('close').addEventListener('click', () => dialog.close());
  dialog.addEventListener('close', () => {
    cancel('Message canceled. You can retry when you reopen the Assistant.', true);
    open.setAttribute('aria-expanded', 'false');
    open.focus();
  });
  clear.addEventListener('click', () => { reset(); if (!sessionExpired) input.focus(); });
  input.addEventListener('input', () => {
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
  form.addEventListener('submit', (event) => { event.preventDefault(); return sendQuestion(); });
  window.addEventListener('pagehide', () => {
    reset();
    if (dialog.open) dialog.close();
  });
})();
