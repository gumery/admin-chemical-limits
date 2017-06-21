define('page/manager/member', ['jquery', 'bootbox', 'board', 'more', 'url', 'utils/dragdrop'], function($, Bootbox, Board, More, U) {
    var $container = $('#manager-setting .access-members');
    var $bar = $container.find('.search-bar');
    var $results = $container.find('.search-results');
    var $addHandler = $('.app-add-member-handler');

    var form = $bar.find('form').data('form');
    $results.data('criteria', form);

    var more = new More({
        start: 0,
        root: $results,
        url: function() { return 'ajax/inventory/manager/more-members/' + this.start },
        data: function(){
            return $results.data('criteria');
        },
        after: function() {
            $addHandler.length && $results.append($addHandler);
        }
    });

    function _refresh() {
        U.changeURL(U.modifyURL(null, form));
        more.reload();
    }

    $bar.find('form').submit(function() {
        form['q']=$(this).find('[name="q"]').val();
        _refresh();
        return false;
    });

    $('body').on('click', '.permission-card-group-add-handler', function(){
        $.get('ajax/inventory/manager/add-group', function(data){
            if (data) {
                var $myDialog = $(data);
                $myDialog.modal({
                    show: true
                    ,backdrop: 'static'
                });
            }
        });
    });

    var $groupFormModal;
    function hideGroupFormModal($modal)
    {
        $groupFormModal && $groupFormModal.hide();
        $groupFormModal = $modal;
        $groupFormModal.modal({
            show: true,
            backdrop: 'static'
        });
    }
    $('body').on('submit', 'form.form-permission-card-group-edit', function() {
        var $myDialog = $(this).parents('.modal');
        var url = 'ajax/inventory/manager/submit-group';
        $.post(url, $(this).serialize(), function(data) {
            hideGroupFormModal($myDialog);
            if (true===data) {
                window.location.reload();
                return;
            }
            if (data) {
                if ($(data).hasClass('modal')) {
                    hideGroupFormModal($(data));
                } else {
                    Bootbox.alert(data);
                }
            }
        });
        return false;
    });

    $('body').on('click', '.permission-card-group-remove-handler', function(){
        var $card = $(this).parents('.permission-card');
        var group = $card.attr('data-permission');
        $.get('ajax/inventory/manager/get-dialog', {group : group}, function(data){
            if (data.success) {
                Bootbox.confirm({
                    message: data.success,
                    callback: function(result) {
                        if (result) {
                            $.post('ajax/inventory/manager/remove-group', {group : group}, function(data){
                                if (true===result) {
                                    window.location.reload();
                                    return;
                                } else {
                                    Bootbox.alert({
                                        message: data,
                                        callback: function(result) {
                                            return false;
                                        }
                                    });
                                }
                            });
                        }
                    }
                });
            } else {
                Bootbox.alert({
                    message: data.error,
                    callback: function(result) {
                        return false;
                    }
                });
            }
        });
    });

    $('body').on('click', '.permission-card-group-edit-handler', function(){
        var $card = $(this).parents('.permission-card');
        var group = $card.attr('data-permission');
        $.get('ajax/inventory/manager/edit-group', {group : group}, function(data){
            if (data) {
                var $myDialog = $(data);
                $myDialog.modal({
                    show: true
                    ,backdrop: 'static'
                });
            }
        });
    });

    //权限设置

    var getHelper = function(evt) {
		var $ele = $(evt.target);
		$ele = $ele.parents('.user-icon');

		var id = $ele.data('id');
		var name = $ele.data('name');
		$ele = $ele.clone();
		$ele.find('.user-name').remove();

		$ele.data('id', id);
		$ele.data('name', name);

		$ele.appendTo($('#manager-setting'));

		return $ele;
	};

	var listUserDraggableOpts = {
		helper: getHelper,
		appendTo: 'body',
		revert: false,
		containment: '#board',
		scroll: true,
		refreshPositions: true,
        cursor: 'move',
        cursorAt: { top: 56, left: 56 }
	};

    var $board = $('#manager-setting');
    var $results = $board.find('.access-members .search-results');
    $results.on('load.more', function(){
    	$results.find('.user-icon').draggable(listUserDraggableOpts);
    });

    $board.find('.permission-card-body').droppable({
		accept: '.user-icon',
		activeClass: 'drag-active',
		hoverClass: 'drag-hover',
		drop: function(evt, ui) {
			var $ele = $(ui.helper);
			var id = $ele.data('id');
			var name = $ele.data('name');

			$ele = $('<dd class="permission-user"/>');
			var $del = $('<i class="fa fa-times delete-member"/>');

			$ele.text(name + " ");
			$ele.append($del);

			$ele.data('id', id);
			$ele.data('name', name);

            var $body = $(this);
            var $card = $body.parents('.permission-card');

            $.post('ajax/inventory/manager/add-user/' + $card.data('permission'), {
                id: id
            }).done(function(data) {
                if (data === true) {
                    $body.append($ele);
                }
                else {
                    $ele.remove();
                }
            }).fail(function() {
                $ele.remove();
            });
		}
	});

    $('body').on('click', '.permission-card-body .delete-member', function(){
        var $user = $(this).parents('.permission-user');
        var $card = $(this).parents('.permission-card');

        $.post('ajax/inventory/manager/remove-user/' + $card.data('permission'), {
            id: $user.data('id')
        }).done(function(data) {
            if (data === true) {
                $user.remove();
            }
        });
    });

    $('body').ready(function(){
        var $searchResults = $('.access-members').find('.search-results');
        var $searchForm = $('.access-members').find('.search-form');
        var maxHeight = $(window).height();
        var searchHeight = $searchForm.height();
        $searchResults.css({
            'height' : maxHeight - searchHeight - 100 ,
            'overflow': 'scroll'
        });
    });
});
