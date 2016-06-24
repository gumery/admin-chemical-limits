define('page/admin/chemical/limits/operate-request', ['jquery', 'bootbox'], function($, Bootbox) {
    function op(type, id) {
        var $content = $('.app-handler-content');
        var message, text, url;
        if (type == 'approve') {
            message = $content.attr('data-approve-message');
            text = $content.attr('data-approved-text');
            url = $content.attr('data-approve-url');
        } else {
            message = $content.attr('data-reject-message');
            text = $content.attr('data-rejected-text');
            url = $content.attr('data-reject-url');
        }
        Bootbox.confirm(message, function(bool) {
            if (!bool) return;
            $.post([url, id].join('/'), function(data) {
                data = data || {};
                if (data.code) {
                    Bootbox.alert(data.message);
                    return;
                }
                $(['.app-handler-container[data-id=', id, ']'].join('')).text(text);
            });
        });
    }
    $(document).on('click', '.app-handler-approve-request', function(data) {
        var id = $(this).parents('.app-handler-container').attr('data-id');
        op('approve', id);
        return false;
    });
    $(document).on('click', '.app-handler-reject-request', function(data) {
        var id = $(this).parents('.app-handler-container').attr('data-id');
        op('reject', id);
        return false;
    });
});

