define('page/admin/chemical/limits/operate-request', ['jquery', 'bootbox'], function($, Bootbox) {

    var $modalLoading;
    function showLoading() {
        if ($modalLoading) {
            $modalLoading.modal({
                'backdrop': 'static',
                'show': true
            });
            return;
        }
        var loading = [
            '<div class="modal"><div class="modal-content"><div class="modal-body">',
                '<div class="text-center">', 
                    '<i class="fa fa-2x fa-spinner fa-spin"/>', 
                '</div>',
            '</div></div></div>'
        ].join('\n');
        $modalLoading = $(loading);
        showLoading();
    }

    function hideLoading() {
        if ($modalLoading) {
            $modalLoading.modal('hide');
        }
    }

    function op(type, id) {
        showLoading();
        var url = 'ajax/inventory/request/get-op-dialog';
        $.get(url, {
            'type': type,
            'id': id
        }, function (result) {
            hideLoading();
            $(result).modal({
                'backdrop': 'static',
                'show': true
            });
        });
    }

    $(document).on('submit', '.form-op-volume-request', function() {
        var $form = $(this);
        var url = 'ajax/inventory/request/post-op-request';
        showLoading();
        $.post(url, $form.serialize(), function(response) {
            hideLoading();
            response = response || {};
            if (response.code) {
                Bootbox.alert(response.message);
                return;
            }
            if (response.id) {
                $(['.app-handler-container[data-id=', response.id, ']'].join('')).text(response.text);
            }
        });
    });

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

