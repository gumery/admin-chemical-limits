define('page/inventory/reagent-add-group', ['jquery', 'bootstrap'], function($, bootstrap) {

    //点击添加的个别课题组
    $('body').on('click', '.btn-add-group-volume-handler', function() {

        var $that = $(this);
        var tRID = (new Date()).getTime();
        $(this).attr('data-handler-id', tRID);
        $.get('ajax/inventory/reagent/get-edit-group-volume', {
            type: $that.attr('data-type')
            ,cas_no: $that.attr('data-cas-no')
            ,tid: tRID
        }, function(data) {
            $(data).modal({
                show: true
                ,backdrop: 'static'
            });
        });
    });
});

