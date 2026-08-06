<script>
    document.addEventListener('DOMContentLoaded', function () {
        var SAFE_CHARS = 'abcdefghjkmnpqrstuvwxyz23456789ABCDEFGHJKMNPQRSTUVWXYZ';

        function randomString(length) {
            var out = '';
            for (var i = 0; i < length; i++) {
                out += SAFE_CHARS[Math.floor(Math.random() * SAFE_CHARS.length)];
            }
            return out;
        }

        document.querySelectorAll('[data-password-generator]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var length = parseInt(btn.getAttribute('data-length') || '10', 10);
                var value = randomString(length);
                var password = document.getElementById(btn.getAttribute('data-target'));
                var confirmation = btn.getAttribute('data-confirmation')
                    ? document.getElementById(btn.getAttribute('data-confirmation'))
                    : null;

                if (password) password.value = value;
                if (confirmation) confirmation.value = value;
            });
        });

        document.querySelectorAll('[data-username-generator]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var input = document.getElementById(btn.getAttribute('data-target'));
                if (input) input.value = randomString(10 + Math.floor(Math.random() * 6));
            });
        });

        document.querySelectorAll('[data-username-auto]').forEach(function (input) {
            if (!input.value) {
                input.value = randomString(10 + Math.floor(Math.random() * 6));
            }
        });
    });
</script>
