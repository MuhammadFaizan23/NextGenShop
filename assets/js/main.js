/**
 * NextGenShop – Main JavaScript
 */

document.addEventListener('DOMContentLoaded', function () {

    // ----- Toggle password visibility -----
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = btn.dataset.target;
            var input = document.getElementById(targetId);
            if (!input) return;
            var icon = btn.querySelector('i');
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    });

    // ----- Auto-dismiss flash alerts after 5 s -----
    document.querySelectorAll('.alert.alert-dismissible').forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 5000);
    });

    // ----- Quantity input: prevent non-numeric typing -----
    document.querySelectorAll('input[type="number"]').forEach(function (input) {
        input.addEventListener('wheel', function (e) {
            e.preventDefault(); // prevent scroll changing the value
        });
    });

    // ----- Payment method radio card highlight -----
    document.querySelectorAll('input[name="payment_method"]').forEach(function (radio) {
        radio.addEventListener('change', function () {
            document.querySelectorAll('.form-check.border').forEach(function (el) {
                el.classList.remove('border-primary', 'bg-primary-subtle');
            });
            radio.closest('.form-check.border').classList.add('border-primary', 'bg-primary-subtle');
        });
    });

    // ----- Product image gallery main swap -----
    document.querySelectorAll('.product-thumb').forEach(function (thumb) {
        thumb.addEventListener('click', function () {
            var main = document.getElementById('mainProductImage');
            if (main) main.src = thumb.src;
            document.querySelectorAll('.product-thumb').forEach(function (t) {
                t.style.borderColor = 'transparent';
            });
            thumb.style.borderColor = 'var(--primary)';
        });
    });

    // ----- Admin: check-all checkbox -----
    var checkAll = document.getElementById('checkAll');
    if (checkAll) {
        checkAll.addEventListener('change', function () {
            document.querySelectorAll('.row-check').forEach(function (cb) {
                cb.checked = checkAll.checked;
            });
        });
        // Uncheck "check all" if any individual checkbox is unchecked
        document.querySelectorAll('.row-check').forEach(function (cb) {
            cb.addEventListener('change', function () {
                if (!cb.checked) checkAll.checked = false;
            });
        });
    }

    // ----- Confirm form submits with data-confirm -----
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm(form.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});
