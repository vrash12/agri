@php
  $assistantRoute = request()->route()?->getName() ?? '';
  $assistantModule = match(true) {
    str_starts_with($assistantRoute, 'farmers.') => 'farmers',
    str_starts_with($assistantRoute, 'farm-plots.satellite.') => 'satellite',
    str_starts_with($assistantRoute, 'farm-plots.') => 'crops',
    str_starts_with($assistantRoute, 'assistance-coverage.') => 'coverage',
    str_starts_with($assistantRoute, 'climate-risk.') => 'climate-risk',
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
  $assistantGuides = \App\Modules\Assistant\Application\AssistantGuide::all();
  $assistantGuide = \App\Modules\Assistant\Application\AssistantGuide::get();
  $assistantMascot = asset($assistantGuide['avatar']);
  $assistantPortrait = asset($assistantGuide['portrait']);
  $assistantSuggestions = $assistantVeterinary
    ? ['Summarize Animal Health services', 'How do I record multiple animals for one owner?', 'What can my veterinary account access?']
    : ['Who are you?', 'How do I use this page?', 'How many farmers do we have?'];
@endphp
<div class="office-assistant" data-office-assistant data-endpoint="{{ route('assistant.message') }}" data-module="{{ $assistantModule }}" data-guide="{{ $assistantGuide['id'] }}">
  <button class="assistant-launcher" type="button" data-assistant-open aria-label="Chat with {{ $assistantGuide['name'] }}" aria-haspopup="dialog" aria-controls="officeAssistantDialog" aria-expanded="false" hidden>
    <img class="assistant-launcher-mascot" src="{{ $assistantMascot }}" width="64" height="64" alt="" decoding="async" data-assistant-avatar>
    <span class="assistant-launcher-label"><strong data-assistant-launcher-name>Ask {{ $assistantGuide['name'] }}</strong><span>Your farm guide</span></span>
  </button>
  <dialog class="assistant-dialog" id="officeAssistantDialog" aria-labelledby="officeAssistantTitle" aria-describedby="officeAssistantPrivacy" data-assistant-dialog>
    <header class="assistant-header">
      <details class="assistant-guide-picker" data-assistant-guide-picker>
        <summary class="assistant-brand" aria-label="Choose your farm guide" title="Choose your farm guide">
          <img src="{{ $assistantMascot }}" width="52" height="52" alt="" decoding="async" data-assistant-avatar>
          <span class="assistant-guide-chevron" aria-hidden="true">⌄</span>
        </summary>
        <div class="assistant-guide-options">
          <p>Choose your guide</p>
          <div class="assistant-guide-choices" role="group" aria-label="Farm guide appearance">
            @foreach($assistantGuides as $guide)
              <button type="button" data-assistant-guide="{{ $guide['id'] }}" data-name="{{ $guide['name'] }}" data-avatar="{{ asset($guide['avatar']) }}" data-portrait="{{ asset($guide['portrait']) }}" data-alt="{{ $guide['alt'] }}" aria-label="Use {{ $guide['name'] }} as your guide" aria-pressed="{{ $guide['id'] === $assistantGuide['id'] ? 'true' : 'false' }}">
                <img src="{{ asset($guide['avatar']) }}" width="48" height="48" alt="" decoding="async">
                <span>{{ $guide['name'] }}</span>
              </button>
            @endforeach
          </div>
          <small>Same help and access, whichever guide you choose.</small>
        </div>
      </details>
      <div class="assistant-title">
        <h2 id="officeAssistantTitle" data-assistant-guide-name>{{ $assistantGuide['name'] }}</h2>
        <span class="assistant-presence">Your eAgriLGU guide</span>
      </div>
      <button class="assistant-clear" type="button" data-assistant-clear>New chat</button>
      <button class="assistant-icon-button" type="button" data-assistant-close aria-label="Close farm guide chat">×</button>
    </header>
    <div class="assistant-body" data-assistant-body tabindex="0" role="region" aria-label="Conversation messages">
      <div class="assistant-welcome" data-assistant-welcome>
        <img class="assistant-welcome-mascot" src="{{ $assistantPortrait }}" width="112" height="112" alt="{{ $assistantGuide['alt'] }}" decoding="async" data-assistant-portrait>
        <h3>Hi, I’m <span data-assistant-guide-name>{{ $assistantGuide['name'] }}</span>.</h3>
        <p>{{ $assistantVeterinary ? 'Need a hand with Animal Health records or this page? Ask me here.' : 'Need a hand finding records or using eAgriLGU? Ask me here.' }}</p>
        <div class="assistant-suggestions" aria-label="Suggested questions">
          @foreach($assistantSuggestions as $suggestion)
            <button type="button" data-assistant-suggestion>{{ $suggestion }}<span aria-hidden="true">→</span></button>
          @endforeach
        </div>
      </div>
      <div class="assistant-messages" role="log" aria-label="Conversation with {{ $assistantGuide['name'] }}" aria-live="polite" aria-relevant="additions text" data-assistant-messages></div>
    </div>
    <button class="assistant-jump" type="button" data-assistant-jump hidden>New messages ↓</button>
    <div class="assistant-screen-reader" role="status" aria-live="polite" data-assistant-status></div>
    <form class="assistant-compose" data-assistant-form novalidate>
      <label for="officeAssistantQuestion">Your message</label>
      <textarea id="officeAssistantQuestion" name="message" maxlength="800" rows="2" required placeholder="Ask {{ $assistantGuide['name'] }}…" data-assistant-input aria-describedby="officeAssistantPrivacy officeAssistantCount officeAssistantCooldown"></textarea>
      <div class="assistant-actions">
        <span class="assistant-keyboard-hint">Enter to send · Shift + Enter for a new line</span>
        <span id="officeAssistantCount" class="assistant-count" data-assistant-count>0 / 800</span>
        <button class="assistant-send" type="submit" data-assistant-send>Send <span aria-hidden="true">↑</span></button>
      </div>
      <p id="officeAssistantCooldown" class="assistant-cooldown" data-assistant-cooldown hidden></p>
      <p id="officeAssistantPrivacy" class="assistant-privacy">Digital guide · AI-assisted help. Keep passwords and keys private.</p>
      <details class="assistant-about">
        <summary>About your guide</summary>
        <p>Your chosen character is eAgriLGU’s digital farm guide, not a person or an official decision-maker. General help uses AI. Office records stay in eAgriLGU; only general help topics and reviewed guides are sent to the AI provider. Your guide cannot edit or approve records.</p>
      </details>
    </form>
  </dialog>
</div>
