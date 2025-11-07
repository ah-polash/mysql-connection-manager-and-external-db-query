(function ($) {
    $(function () {
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

            var strings = DBConnectionAdmin.strings || {};

            $result.text(strings.checking || 'Checking...').css('color', '');
            $button.prop('disabled', true);

            $.post(
                DBConnectionAdmin.ajaxUrl,
                {
                    action: 'dbcm_test_connection',
                    nonce: DBConnectionAdmin.nonce,
                    post_id: postId,
                    credentials: credentials
                }
            )
                .done(function (response) {
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
