document.addEventListener('DOMContentLoaded', () => {
    const $customerGroup = document.getElementById('input-customerGroup');
    const $to = document.getElementById('input-to');
    const $submit = document.querySelector('button[form="form-module"]');
    const $write = $submit.previousElementSibling;

    $('#writeSms').on('shown.bs.collapse', () => {
        $submit.style.display = 'inline-block';

        $write.style.display = 'none';

        window.Sms77Counter.setStyle(document.querySelector('form textarea'));
    });

    $submit.addEventListener('click', () => {
        $submit.style.display = 'none';

        $write.style.display = 'inline-block';
    });

    $customerGroup.addEventListener('change',
        () => $to.disabled = '' !== $customerGroup.value);
});