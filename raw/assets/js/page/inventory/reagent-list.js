define('page/inventory/reagent-list', ['jquery', 'bootbox', 'board', 'more'], function($, Bootbox, Board, More) {
    // START 开始整个页面的滚动加载更多
    var condition;
    function init() {
        // MORE
        var classContainer = '.board-content-reagent-list';
        var options = {
            start: 0
            ,auto: false
            ,after: function() {
                Board.resize();
            }
            ,root: $(classContainer)
            ,data: function() {
                return condition || {};
            }
            ,url: function() {
                var url = ['ajax/inventory/reagent/more', this.start].join('/');
                return url;
            }
        };

        more = new More(options);
    }
    init();

    // 模糊搜索开始
    var searchFromClass = '.board-header-form';
    function initSearchForm(placeholder) {
        var $container = $(searchFromClass);
        var $input = $container.find('input');
        $input.attr('placeholder', placeholder);
        $input.val('');
    }
    $('body').on('click', '.board-header-form .btn', function() {
        var $container = $(this).parents(searchFromClass);
        var type = $container.find('select[name=hazardous_type]').val();
        var q = $container.find('input[name=q]').val();
        /*
        var $active = $('.board-header .board-header-ul li.active');
        var type = $active.attr('data-type');
        */

        loadContent(type, 0, q);
    });

    //类别搜索
    $('body').on('change', '.board-header-form .select', function() {
        var $container = $(this).parents(searchFromClass);
        var type = $container.find('select[name=hazardous_type]').val();
        var q = $container.find('input[name=q]').val();

        loadContent(type, 0, q);

    });
    $('body').on('keyup', '.board-header-form input', function(evt) {
        var key = evt.which || evt.keyCode;
        if (key == 13) {
            $(this).parents('.board-header-form').find('.btn').click();
        }
    });

    var loadContent = function(type, start, q) {
        start = start || 0;
        type = type || '';
        condition = {
            type: type
            ,q: q
        };
        more.reload(start);
    };

    // 修改单个cas号的存量上限
    $('body').on('click', '.btn-edit-cas-volume-handler', function() {
        var $myContainer = $(this).parent().find('.volume-value-view');
        var myValue = $myContainer.attr('data-value');
        var $input = $('<input type="text"/>');
        $input.addClass('form-control');
        $input.css({
            width: '100px'
            ,textAlign: 'right'
            ,display: 'inline-block'
            ,height: '25px'
            ,padding: '0 10px'
        });
        $input.val(myValue);
        $myContainer.html('');
        $myContainer.append($input);
        $(this).hide();
        $(this).parent().find('.btn-remove-cas-volume-handler').hide();
        $(this).parent().find('.btn-save-cas-volume-handler, .btn-cancel-cas-volume-handler').show();
        $input.focus();
    });

    $('body').on('click', '.btn-remove-cas-volume-handler', function() {
        var $myContainer = $(this).parent().find('.volume-value-view');
        var myCAS = $myContainer.attr('data-cas');
        var myGroup = $myContainer.attr('data-group');
        var myMessage = $(this).attr('data-message');
        Bootbox.confirm(myMessage, function(confirmed) {
            if (!confirmed) return;
            var myPostData = {
                cas: myCAS
            };
            if (myGroup) {
                myPostData.group = myGroup;
            }
            $.post('ajax/inventory/reagent/delete-volume', myPostData, function(data) {
                data = data || {};
                if (data.code) {
                    Bootbox.alert(data.message);
                    return;
                }
                $myContainer.parents('.board-content-reagent-li-row').first().remove();
            });
        });
    });

    $('body').on('click', '.btn-save-cas-volume-handler', function() {
        var $myContainer = $(this).parent().find('.volume-value-view');
        var $input = $myContainer.find('input');
        var newVal = $input.val();
        var myVal = $myContainer.attr('data-value');
        var myCAS = $myContainer.attr('data-cas');
        var myGroup = $myContainer.attr('data-group');
        var myPostData = {
            volume: newVal
            ,cas: myCAS
        };
        if (myGroup) {
            myPostData.group = myGroup;
        }
        $.post('ajax/inventory/reagent/edit-volume', myPostData, function(data) {
            data = data || {};
            if (data.code) {
                Bootbox.alert(data.message);
                return;
            }
            $myContainer.attr('data-value', newVal);
            $myContainer.parent().find('.btn-cancel-cas-volume-handler').click();
        });
    });

    $('body').on('click', '.btn-cancel-cas-volume-handler', function() {
        var $myContainer = $(this).parent().find('.volume-value-view');
        var value = $myContainer.attr('data-value');
        if (value) {
            $myContainer.text(value);
        } else {
            $myContainer.html('<span class="text-muted">未设置</span>');
        }
        $(this).parent().find('.btn-save-cas-volume-handler, .btn-cancel-cas-volume-handler').hide();
        $(this).parent().find('.btn-edit-cas-volume-handler, .btn-remove-cas-volume-handler').show();
    });

    $('body').on('click', '.radio-cart', function(event) {
        var v = $(this).val();
        $.post('ajax/inventory/reagent/set-conf', {
            enable: v
        }, function(data) {});
    });
});

