@once
<style>
  .form-field-error { display: block; margin: 8px 0 0; color: var(--ui-danger, #a6504a); font-size: 14px; line-height: 1.5; }
  .form-error-summary { margin-bottom: 16px; padding: 16px; border: 1px solid var(--ui-danger, #a6504a); border-radius: 8px; background: var(--ui-danger-soft, #faefed); color: var(--ui-danger, #a6504a); }
  .form-error-summary a { color: inherit; text-decoration: underline; }
  [aria-invalid="true"]:is(input, select, textarea) { border-color: var(--ui-danger, #a6504a); }
</style>
<script>
(() => {
    const serverErrors = @json(isset($errors) ? $errors->getMessages() : []);

    function reveal(control) {
        for (let ancestor = control.parentElement; ancestor; ancestor = ancestor.parentElement) {
            if (ancestor instanceof HTMLDetailsElement) ancestor.open = true;
        }
    }

    // Native validation fires before the browser tries to focus a required field.
    document.addEventListener('invalid', event => {
        reveal(event.target);
        event.target.setAttribute('aria-invalid', 'true');
    }, true);

    function initialize() {
        const controls = Array.from(document.querySelectorAll('input[name], select[name], textarea[name]'));
        let firstInvalid = null;
        let errorIndex = 0;

        Object.entries(serverErrors).forEach(([key, messages]) => {
            const matchingControls = controls.filter(control => {
                const normalizedName = control.name.replace(/\[([^\]]*)\]/g, '.$1').replace(/\.$/, '');
                return normalizedName === key && control.type !== 'hidden' && !control.disabled;
            });

            matchingControls.forEach(control => {
                reveal(control);
                control.setAttribute('aria-invalid', 'true');
                if (!control.id) control.id = `form-error-field-${++errorIndex}`;
                firstInvalid ??= control;

                const field = control.closest('.module-form-field, .module-field, .field, .form-group') || control.parentElement;
                const message = Array.isArray(messages) ? messages.join(' ') : String(messages);
                let hint = Array.from(field.querySelectorAll('.module-hint, .invalid-feedback, .form-field-error, [role="alert"]'))
                    .find(element => element.textContent.trim() === message);
                if (!hint) {
                    hint = document.createElement('span');
                    hint.className = 'form-field-error';
                    hint.textContent = message;
                    field.append(hint);
                }
                if (!hint.id) hint.id = `form-error-message-${++errorIndex}`;
                const describedBy = new Set((control.getAttribute('aria-describedby') || '').split(/\s+/).filter(Boolean));
                describedBy.add(hint.id);
                control.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
                if (control.tomselect) {
                    control.tomselect.control_input.setAttribute('aria-invalid', 'true');
                    control.tomselect.control_input.setAttribute('aria-describedby', Array.from(describedBy).join(' '));
                }
            });
        });

        if (Object.keys(serverErrors).length) {
            let summary = document.querySelector('[data-error-summary], .module-alert-danger, .module-alert-error, .error-box');
            if (!summary) {
                summary = document.createElement('div');
                summary.className = 'form-error-summary';
                const heading = document.createElement('strong');
                heading.textContent = 'Please check the information below.';
                const list = document.createElement('ul');
                Object.values(serverErrors).flat().forEach(message => {
                    const item = document.createElement('li');
                    item.textContent = message;
                    list.append(item);
                });
                summary.append(heading, list);
                (document.querySelector('.container, .login-form') || document.body).prepend(summary);
            }
            summary.setAttribute('role', 'alert');
            summary.tabIndex = -1;
            reveal(summary);
            if (firstInvalid) {
                const link = document.createElement('a');
                link.href = `#${firstInvalid.id}`;
                link.className = 'form-field-error';
                link.textContent = 'Go to the first field to check';
                link.addEventListener('click', event => {
                    event.preventDefault();
                    reveal(firstInvalid);
                    if (firstInvalid.tomselect) firstInvalid.tomselect.focus();
                    else firstInvalid.focus();
                    firstInvalid.scrollIntoView({ block: 'center' });
                });
                summary.append(link);
            }
            summary.focus();
        }
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initialize);
    else initialize();
})();
</script>
@endonce
