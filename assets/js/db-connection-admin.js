(function ($) {
    $(function () {
        var settings = window.DBConnectionAdmin || {};
        var strings = settings.strings || {};
        var statusLabels = strings.statuses || {};

        if (!settings.ajaxUrl || !settings.nonce) {
            return;
        }

        function updateLastStatus(statusKey, message, timestamp) {
            var $text = $('#db-connection-last-status-text');
            var $message = $('#db-connection-last-status-message');
            var $updated = $('#db-connection-last-status-updated');

            $text.removeClass('status-success status-error');

            if (statusKey) {
                var label = statusLabels[statusKey] || (statusKey.charAt(0).toUpperCase() + statusKey.slice(1));
                $text.text(label);
                $text.addClass('status-' + statusKey);
            } else {
                $text.text(strings.notChecked || 'Not checked yet.');
            }

            if (message) {
                $message.text(' — ' + message);
            } else {
                $message.text('');
            }

            if (timestamp) {
                var date = new Date(timestamp * 1000);
                if (!isNaN(date.getTime())) {
                    var formatted = date.toLocaleString();
                    if (strings.lastChecked) {
                        formatted = strings.lastChecked.replace('%s', formatted);
                    }
                    $updated.text(formatted);
                } else {
                    $updated.text('');
                }
            } else {
                $updated.text('');
            }
        }

        $('#db-connection-test').on('click', function () {
            var $button = $(this);
            var postId = $button.data('post-id');
            var $result = $('#db-connection-test-result');
            var credentials = {
                db_type: $('#db_type').val(),
                host: $('#db_host').val(),
                port: $('#db_port').val(),
                username: $('#db_username').val(),
                password: $('#db_password').val(),
                database: $('#db_database').val(),
                options: $('#db_options').val()
            };

            $result.text(strings.checking || 'Checking...').css('color', '');
            $button.prop('disabled', true);

            $.post(
                settings.ajaxUrl,
                {
                    action: 'dbcm_test_connection',
                    nonce: settings.nonce,
                    post_id: postId,
                    credentials: credentials
                }
            )
                .done(function (response) {
                    if (response && response.data && typeof response.data.status !== 'undefined') {
                        updateLastStatus(response.data.status, response.data.message, response.data.checked_at);
                    }

                    if (response.success) {
                        $result.text(response.data.message || strings.success || 'Connection established.').css('color', 'green');
                    } else {
                        $result.text(response.data.message || strings.failure || 'Connection failed.').css('color', 'red');
                    }
                })
                .fail(function () {
                    $result.text(strings.failure || 'Connection failed.').css('color', 'red');
                })
                .always(function () {
                    $button.prop('disabled', false);
                });
        });
    });
})(jQuery);
