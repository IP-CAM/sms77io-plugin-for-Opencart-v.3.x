document.addEventListener('DOMContentLoaded', function () {
    var $form = document.querySelector('form');
    var $customerGroup = document.getElementById('input-customerGroup');
    var $to = document.getElementById('input-to');
    var $submit = document.querySelector('button[form="form-module"]');
    var $write = document.querySelector('button[data-target="#writeMessage"]');

    $('#writeMessage').on('shown.bs.collapse', function () {
        $submit.style.display = 'inline-block';

        $write.style.display = 'none';
    });

    $submit.addEventListener('click', function () {
        $submit.style.display = 'none';

        $write.style.display = 'inline-block';
    });

    $customerGroup.addEventListener('change', function () {
        $to.disabled = '' !== $customerGroup.value;
    });

    $form.addEventListener('submit', function () {
        if ('' !== $to.value) {
            $customerGroup.value = '';
        }
    });
});