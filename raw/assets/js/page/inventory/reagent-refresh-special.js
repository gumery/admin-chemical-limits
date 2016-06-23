define('page/inventory/reagent-refresh-special', ['jquery'], function($) {
    function refresh($element) {
        var url = 'ajax/inventory/reagent/get-subs';
        var $container = $element.parents('.board-content-reagent-li-row');
        $.get(url, {
            cas_no: $element.attr('data-cas-no')
            ,type: 'cas'
            ,_t: (new Date()).getTime()
        }, function(data) {
            $container.find('.board-content-reagent-li-row').remove();
            if (data) {
                $container.append(data);
            }
        });
    }
    return {
        refresh: refresh
    };
});

