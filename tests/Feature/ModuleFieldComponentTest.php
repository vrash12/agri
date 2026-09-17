<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * The contract for <x-module.field>.
 *
 * The operations forms wrote this wrapper by hand 95 times, which is how the error
 * element became a <div> in some fields and a <span> in others, and how several
 * fields lost the association between an input and its message. These cases fix the
 * shape so the remaining forms can be migrated onto it without drifting again.
 */
class ModuleFieldComponentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        DB::purge('sqlite');

        // ShareErrorsFromSession does not run for a bare blade() render, so the
        // component's @error directive needs a bag to look in.
        view()->share('errors', new ViewErrorBag);
    }

    public function test_a_required_field_is_labelled_and_marked(): void
    {
        $html = $this->blade('<x-module.field name="chairperson" label="Chairperson" :required="true">
            <input id="chairperson" name="chairperson">
        </x-module.field>');

        $this->assertStringContainsString('<label for="chairperson">Chairperson', $html);
        $this->assertStringContainsString('<span class="module-required">*</span>', $html);
        $this->assertStringContainsString('class="module-form-field"', $html);
    }

    public function test_an_optional_field_carries_no_required_marker(): void
    {
        $html = $this->blade('<x-module.field name="address" label="Address"><input id="address" name="address"></x-module.field>');

        $this->assertStringContainsString('<label for="address">Address</label>', $html);
        $this->assertStringNotContainsString('module-required', $html);
    }

    public function test_the_full_width_modifier_is_applied_only_when_asked(): void
    {
        $narrow = $this->blade('<x-module.field name="a" label="A"><input id="a"></x-module.field>');
        $wide = $this->blade('<x-module.field name="a" label="A" :full="true"><input id="a"></x-module.field>');

        $this->assertStringNotContainsString('module-form-field-full', $narrow);
        $this->assertStringContainsString('module-form-field-full', $wide);
    }

    public function test_a_hint_is_rendered_with_the_id_a_control_can_reference(): void
    {
        $html = $this->blade('<x-module.field name="description" label="Description" hint="Keep this concise.">
            <textarea id="description" name="description" aria-describedby="description_hint"></textarea>
        </x-module.field>');

        $this->assertStringContainsString('id="description_hint"', $html);
        $this->assertStringContainsString('Keep this concise.', $html);
    }

    public function test_a_validation_message_is_rendered_against_the_conventional_id(): void
    {
        $html = $this->withViewErrors(['contact_number' => 'Enter a valid contact number.'])
            ->blade('<x-module.field name="contact_number" label="Contact number">
                <input id="contact_number" name="contact_number" aria-describedby="contact_number_error">
            </x-module.field>');

        // The control points at this id, so the message has to carry it.
        $this->assertStringContainsString('id="contact_number_error"', $html);
        $this->assertStringContainsString('Enter a valid contact number.', $html);
        $this->assertStringContainsString('<span class="module-hint module-required"', $html);
    }

    public function test_no_message_element_appears_while_the_field_is_valid(): void
    {
        $html = $this->withViewErrors(['something_else' => 'Unrelated.'])
            ->blade('<x-module.field name="contact_number" label="Contact number">
                <input id="contact_number" name="contact_number">
            </x-module.field>');

        $this->assertStringNotContainsString('contact_number_error', $html);
        $this->assertStringNotContainsString('Unrelated.', $html);
    }

    public function test_an_explicit_id_overrides_the_field_name_for_every_association(): void
    {
        $html = $this->withViewErrors(['municipality_id' => 'Choose a municipality.'])
            ->blade('<x-module.field name="municipality_id" label="Municipality" id="workspace">
                <select id="workspace" name="municipality_id"></select>
            </x-module.field>');

        $this->assertStringContainsString('<label for="workspace">', $html);
        $this->assertStringContainsString('id="workspace_error"', $html);
    }

    public function test_the_control_in_the_slot_is_rendered_untouched(): void
    {
        $html = $this->blade('<x-module.field name="quantity" label="Quantity">
            <input id="quantity" name="quantity" type="number" step="0.01" required data-unit="kg">
        </x-module.field>');

        foreach (['type="number"', 'step="0.01"', 'required', 'data-unit="kg"'] as $attribute) {
            $this->assertStringContainsString($attribute, $html);
        }
    }
}
