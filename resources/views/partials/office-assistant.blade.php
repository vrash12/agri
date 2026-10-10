@php
  $assistantRoute = request()->route()?->getName() ?? '';
  $assistantModule = match(true) {
    str_starts_with($assistantRoute, 'farmers.') => 'farmers',
    str_starts_with($assistantRoute, 'farm-plots.satellite.') => 'satellite',
    str_starts_with($assistantRoute, 'farm-plots.') => 'crops',
    str_starts_with($assistantRoute, 'assistance-coverage.') => 'coverage',
    str_starts_with($assistantRoute, 'rice-') => 'assistance',
    str_starts_with($assistantRoute, 'harvest') => 'harvests',
    str_starts_with($assistantRoute, 'anti-rabies') => 'animal-health',
    str_starts_with($assistantRoute, 'farmers-cooperatives') => 'cooperatives',
    str_starts_with($assistantRoute, 'machinery-inventory.') => 'machinery',
    str_starts_with($assistantRoute, 'admins.') => 'accounts',
    str_starts_with($assistantRoute, 'municipality-boundaries') => 'geofences',
    str_starts_with($assistantRoute, 'backups.') => 'backups',
    str_starts_with($assistantRoute, 'audit-logs.') => 'audit',
    $assistantRoute === 'dashboard' => 'dashboard',
    default => 'other',
  };
  $assistantVeterinary = auth()->user()->isProvincialVeterinaryOffice();
  $assistantSuggestions = $assistantVeterinary
    ? ['Summarize Animal Health services', 'How do I record multiple animals for one owner?', 'What can my veterinary account access?']
    : ['Who are you?', 'How do I use this page?', 'How many farmers do we have?'];
@endphp
<div class="office-assistant" data-office-assistant data-endpoint="{{ route('assistant.message') }}" data-module="{{ $assistantModule }}">
  <button class="assistant-launcher" type="button" data-assistant-open aria-haspopup="dialog" aria-controls="officeAssistantDialog" aria-expanded="false" hidden>
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M5 4h14a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2h-8l-5 3v-3H5a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2Z"/><path d="M8 9h8M8 13h5"/></svg>
    <span>Assistant</span>
  </button>
  <dialog class="assistant-dialog" id="officeAssistantDialog" aria-labelledby="officeAssistantTitle" aria-describedby="officeAssistantPrivacy" data-assistant-dialog>
    <header class="assistant-header">
      <span class="assistant-brand" aria-hidden="true"><x-brand compact /></span>
      <h2 id="officeAssistantTitle">AgriLGU Assistant</h2>
      <button class="assistant-clear" type="button" data-assistant-clear>New chat</button>
      <button class="assistant-icon-button" type="button" data-assistant-close aria-label="Close Assistant">×</button>
    </header>
    <div class="assistant-body" data-assistant-body tabindex="0" role="region" aria-label="Conversation messages">
      <div class="assistant-welcome" data-assistant-welcome>
        <h3>How can I help?</h3>
        <p>Ask about AgriLGU features or your permitted office records. Continue with “Explain more” or “Show me the steps” after a system guide.</p>
        <div class="assistant-suggestions" aria-label="Suggested questions">
          @foreach($assistantSuggestions as $suggestion)
            <button type="button" data-assistant-suggestion>{{ $suggestion }}<span aria-hidden="true">→</span></button>
          @endforeach
        </div>
      </div>
      <div class="assistant-messages" role="log" aria-label="Assistant conversation" aria-live="polite" aria-relevant="additions text" data-assistant-messages></div>
    </div>
    <button class="assistant-jump" type="button" data-assistant-jump hidden>New messages ↓</button>
    <div class="assistant-screen-reader" role="status" aria-live="polite" data-assistant-status></div>
    <form class="assistant-compose" data-assistant-form novalidate>
      <label for="officeAssistantQuestion">Your message</label>
      <textarea id="officeAssistantQuestion" name="message" maxlength="800" rows="2" required placeholder="Ask a question or follow up…" data-assistant-input aria-describedby="officeAssistantPrivacy officeAssistantCount"></textarea>
      <div class="assistant-actions">
        <span class="assistant-keyboard-hint">Enter to send · Shift + Enter for a new line</span>
        <span id="officeAssistantCount" class="assistant-count" data-assistant-count>0 / 800</span>
        <button class="assistant-send" type="submit" data-assistant-send>Send <span aria-hidden="true">↑</span></button>
      </div>
      <p id="officeAssistantPrivacy" class="assistant-privacy">Office records stay in AgriLGU. AI receives only general help topics. Never share passwords or keys.</p>
    </form>
  </dialog>
</div>
