define('page/inventory/add-group-volume', ['jquery', 'bootbox', 'board', 'bootstrap-select', 'ajax-bootstrap-select'], function($, Bootbox) {

    $('body').on('submit', '.form-add-group-volume', function() {
        var $that = $(this);
        var selectVal = $that.find('select[name=group]').val();
        if (selectVal === '' || selectVal === null || selectVal === undefined) return false;
        $.post($that.attr('action'), $that.serialize(), function(data) {
            data = data || {};
            if (data.code) {
                Bootbox.alert(data.message);
                return;
            }
            var tid = $that.attr('data-handler-id');
            $that.parents('.modal').modal('hide');
            refreshLine(tid);
        });
        return false;
    });

    function refreshLine(tid) {
        var $el = $(['.btn-add-group-volume-handler[data-handler-id=', tid, ']'].join(''));
        var myjs = $el.attr('data-refresh');
        if (!myjs) return;
        require([myjs], function(MyJS) {
            MyJS && MyJS.refresh && MyJS.refresh($el);
        });
    }

    function initOptions(url) {
        var options = {
            ajax: {
                url: url
                ,type: 'POST'
                ,dataType: 'json'
                ,data: function() {
                    return {
                        q: '{{{q}}}'
                    };
                }
            }
            ,cache: false
            ,preserveSelected: false
            ,preprocessData: function(data) {
                var i, l = data.length,
                array = [];
                if (l) {
                    for (i = 0; i < l; i++) {
                        array.push($.extend(true, data[i], {
                            text: data[i].value
                            ,value: data[i].key
                        }));
                    }
                    array.unshift({
                        text: '--'
                        ,value: ''
                    });
                }
                return array;
            }
        };
        return options;
    }

    function loopMe() {
        $(".selectpicker").selectpicker({
            style: 'btn-blank'
        });
        $(".group-selectpicker").each(function(index, el) {
            $(el).ajaxSelectPicker(initOptions('ajax/inventory/reagent/search-group'));
        });
    }

    return {
        loopMe: loopMe
    };

});

