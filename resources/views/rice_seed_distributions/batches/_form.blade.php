@php
    $editing = isset($batch);
    $value = fn (string $field, $fallback = null) => old($field, $editing ? ($batch->{$field} ?? $fallback) : $fallback);
@endphp

@include('rice_seed_distributions.batches._styles')

<form
    method="POST"
    action="{{ $editing ? route('rice-distribution-batches.update', $batch) : route('rice-distribution-batches.store') }}"
    class="module-form-shell"
    data-sheet-form
>
    @csrf
    @if($editing)
        @method('PUT')
        @include('partials.record-version', ['record' => $batch])
    @endif

    <div class="module-form-main">
        <section class="module-form-section">
            <div class="module-form-section-head">
                <span class="module-step">1</span>
                <div>
                    <h2>Which office and season</h2>
                    <p>A sheet groups releases that were already recorded. It does not create a second set of totals.</p>
                </div>
            </div>

            <div class="module-form-body">
                <div class="module-form-grid">
                    @if($canChooseMunicipality ?? false)
                        <x-module.field
                            name="municipality_id"
                            label="Municipality"
                            :required="true"
                            :full="true"
                            hint="Only offices you are authorized to work in are listed."
                        >
                            <select class="module-input" id="municipality_id" name="municipality_id" required
                                aria-describedby="municipality_id_hint municipality_id_error">
                                <option value="">Select municipality</option>
                                @foreach(($municipalities ?? []) as $municipality)
                                    <option value="{{ $municipality->id }}"
                                        @selected((string) old('municipality_id', $selectedMunicipalityId ?? '') === (string) $municipality->id)>
                                        {{ $municipality->name }}
                                    </option>
                                @endforeach
                            </select>
                        </x-module.field>
                    @else
                        {{-- A municipal account always writes to its own office; the value is
                             taken from the account on the server and is shown here for confidence. --}}
                        <x-module.field name="municipality_office" label="Municipality" :full="true"
                            hint="Taken from your account. Sheets you create belong to this office.">
                            <div class="locked-value" id="municipality_office">
                                {{ optional(auth()->user()->municipality)->name ?? 'Not assigned' }}
                            </div>
                        </x-module.field>
                    @endif

                    <x-module.field name="reference" label="Program or reference" :required="true" :full="true"
                        hint="How this distribution is identified on paper, for example the program name and batch.">
                        <input class="module-input" id="reference" type="text" name="reference"
                            value="{{ $value('reference') }}" maxlength="120" required
                            placeholder="e.g. Rice Seed Program - Batch 1"
                            aria-describedby="reference_hint reference_error">
                    </x-module.field>

                    <x-module.field name="planting_season" label="Planting season" :required="true">
                        <select class="module-input" id="planting_season" name="planting_season" required
                            aria-describedby="planting_season_error">
                            <option value="">Select season</option>
                            @foreach(($seasonOptions ?? []) as $seasonKey => $seasonLabel)
                                <option value="{{ $seasonKey }}" @selected((string) $value('planting_season') === (string) $seasonKey)>
                                    {{ $seasonLabel }}
                                </option>
                            @endforeach
                        </select>
                    </x-module.field>

                    <x-module.field name="planting_year" label="Planting year" :required="true">
                        <input class="module-input" id="planting_year" type="number" name="planting_year"
                            value="{{ $value('planting_year', now()->year) }}" min="1990" max="{{ now()->year + 1 }}"
                            step="1" required aria-describedby="planting_year_error">
                    </x-module.field>
                </div>
            </div>
        </section>

        <section class="module-form-section rice-sheet-section-harvest">
            <div class="module-form-section-head">
                <span class="module-step">2</span>
                <div>
                    <h2>Harvest reporting</h2>
                    <p>Recorded separately and left empty until the harvest is reported. It is never assumed from the planting season.</p>
                </div>
            </div>

            <div class="module-form-body">
                <div class="module-form-grid">
                    <x-module.field name="harvest_season" label="Harvest season"
                        hint="Leave blank while the harvest has not been reported.">
                        <select class="module-input" id="harvest_season" name="harvest_season"
                            aria-describedby="harvest_season_hint harvest_season_error">
                            <option value="">Not yet reported</option>
                            @foreach(($seasonOptions ?? []) as $seasonKey => $seasonLabel)
                                <option value="{{ $seasonKey }}" @selected((string) $value('harvest_season') === (string) $seasonKey)>
                                    {{ $seasonLabel }}
                                </option>
                            @endforeach
                        </select>
                    </x-module.field>

                    <x-module.field name="harvest_year" label="Harvest year">
                        <input class="module-input" id="harvest_year" type="number" name="harvest_year"
                            value="{{ $value('harvest_year') }}" min="1990" max="{{ now()->year + 1 }}" step="1"
                            aria-describedby="harvest_year_error">
                    </x-module.field>
                </div>
            </div>
        </section>

        <details class="module-more" @if(filled($value('default_seed_bag_kg')) || filled($value('notes')) || $errors->hasAny(['default_seed_bag_kg', 'notes'])) open @endif>
            <summary>Defaults and notes <span class="module-hint">Optional</span></summary>
            <div class="module-form-body">
                <div class="module-form-grid">
                    <x-module.field name="default_seed_bag_kg" label="Default seed bag weight (kg)"
                        hint="Offered when adding a release. This is the seed bag, not the harvest bag.">
                        <input class="module-input rice-sheet-measure" id="default_seed_bag_kg" type="number"
                            name="default_seed_bag_kg" value="{{ $value('default_seed_bag_kg') }}" min="0" step="0.01"
                            aria-describedby="default_seed_bag_kg_hint default_seed_bag_kg_error">
                    </x-module.field>

                    <x-module.field name="notes" label="Notes" :full="true"
                        hint="Shown on the printed sheet under the title.">
                        <textarea class="module-input" id="notes" name="notes" rows="3" maxlength="1000"
                            aria-describedby="notes_hint notes_error">{{ $value('notes') }}</textarea>
                    </x-module.field>
                </div>
            </div>
        </details>

        <div class="module-form-actions">
            <a class="module-button" href="{{ route('rice-distribution-batches.index') }}">Cancel</a>
            <button class="module-button module-button-primary rice-sheet-submit" type="submit">
                {{ $editing ? 'Save changes' : 'Create sheet' }}
            </button>
            <span class="module-hint" data-sheet-form-status role="status" aria-live="polite"></span>
        </div>
    </div>
</form>

@include('rice_seed_distributions.batches._submit-guard')
