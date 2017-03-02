define('page/manager/request', ['jquery', 'bootbox', 'board', 'utils/preview'], function($, Bootbox, Board) {
    $('body').on('click', '.app-op-per-handler', function() {
        var key = $(this).attr('data-key');
        var id = $(this).attr('data-id');
        $.get('ajax/inventory/request/get-op-dialog', {key : key, id : id}, function(data){
            $(data).modal({
                show:true,
                backdrop: 'static',
            });
        });
    });

    $('body').on('click', '.app-op-submit-handler', function(){
        var $modal = $(this).parents('.modal');
        $form = $modal.find('form');
        $.post('ajax/inventory/request/post-op-request', $form.serialize(), function(response){
            response = response || {};
            var code = response.code;
            var message = response.message;
            var id = response.id;
            if (code) {
                Bootbox.alert(response.message);
                return;
            }
            var $oph = $(['[data-id=', id, ']'].join(''));
            $oph.hide();
            $modal.modal('hide');
        });
    });


    $(document).on('click', '.app-pager-li-handler', function() {
        var page = $(this).attr('data-page');
        var type = $(this).attr('data-type');
        var $searchHandler = $('.app-q-search-handler');
        var q = '';
        if ($searchHandler.length) {
            q = $searchHandler.parents('form').find('[name=q]').val();
        }
        var url = ['ajax/inventory/request/more', page, type].join('/');
        search({
            url: url
            ,q: q
        });
    });

    function search(params) {
        $.get(params.url, params, function(html) {
            $('.board-content').html(html);
            Board.resize();
        });
    }
});
