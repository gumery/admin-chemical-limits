define('page/inventory/reagent-refresh-global', ['jquery'], function($) {
    function refresh($element) {
        var url = 'ajax/inventory/reagent/get-subs';
        var $container = $element.parents('tr');
        $.get(url, {
            cas_no: $element.attr('data-type')
            ,type: 'type'
            ,_t: (new Date()).getTime()
        }, function(data) {
            var $next;
            while (true) {
                $next = $container.next();
                if ($next.hasClass('board-content-reagent-type-li-row')) {
                    $next.remove();
                    continue;
                }
                break;
            }
            $container.find('').remove();
            if (data) {
                var $newEle = $(data);
                $container.after($newEle);
            }
        });
    }

    return {
        refresh: refresh
    };
});

