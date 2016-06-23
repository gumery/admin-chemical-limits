define('page/inventory/reagent-append-chemical', ['jquery', 'bootstrap', 'bootbox', 'bootstrap-select', 'ajax-bootstrap-select'], function($, Bootstrap, Bootbox) {
    $('body').on('click', '.app-reagent-append-chemical-handler', function() {
        var url = 'ajax/inventory/reagent/get-reagent-append-chemical-modal';
        $.get(url, function(result) {
            if (!result) return;
            $(result).modal({
                show: true
                ,backdrop: 'static'
            });
        });
    });
    $('body').on('submit', '.form-reagent-append-chemical-volume', function() {
        var $form = $(this);
        var action = $form.attr('action');
        var casVal = $form.find('select[name=cas]').val();
        if (casVal === '' || casVal === null || casVal === undefined) return false;
        $.post(action, $form.serialize(), function(result) {
            result = result || {};
            if (result.code) {
                Bootbox.alert(result.message);
                return;
            }
            window.location.reload();
        });
        return false;
    });

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
        $(".chemical-selectpicker").each(function(index, el) {
            $(el).ajaxSelectPicker(initOptions('ajax/inventory/reagent/search-chemical'));
        });
    }

    return {
        loopMe: loopMe
    };
});

