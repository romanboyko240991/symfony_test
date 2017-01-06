
(function ($) {
    var menu_copy = $('.col-sm-2').html();
    $('<div class="toggle_menu">' + menu_copy + '<div>').insertBefore('.col-xs-10').css('display', 'none');

    $('#menu').click(function (event) {
        $('.toggle_menu').toggle();
        event.preventDefault();
    });

    $(window).resize(function () {
        if($(window).width() > 768) $('.toggle_menu').css('display', 'none');
    });

    $('.cat .save_upd_cat, .new_cat_toggle').hide();

    $('.show_insert_cat').click(function () {
        $('.new_cat_toggle').toggle();
    });

    function setUpdateBtnClickHandler() {
        //update click
        $('.upd_cat').off('click');

        $('.upd_cat').click(function (event) {
            var category = $(this).parent();
            $(category).children('.save_upd_cat').show();
            $(category).children('span').hide();
            $(category).children('.del_cat').hide();
            $(category).children('.upd_cat').hide();
            $(category).prepend('<input class="upd_cat_name" type="text" value="' + $(category).children('span').text() + '">');

            event.preventDefault();
        });

        //save updated category name
        $('.save_upd_cat').off('click');

        $('.save_upd_cat').click(function (event) {
            var category = $(this).parent();
            var newCatName = $(category).children('.upd_cat_name').val();

            $(this).hide();
            $(category).children('.upd_cat_name').hide();
            $(category).children('span').show();
            $(category).children('.del_cat').show();
            $(category).children('.upd_cat').show();

            $.ajax({
                type: 'POST',
                url: $(this).attr('href'),
                data: 'cat_id=' + $(this).attr('id') + '&cat_name=' + newCatName,
                success: function (data) {
                    if (data.success) {
                        $(category).children('span').text(newCatName);
                        alert('Category has been changed');
                    }
                    else {
                        alert('Flickr doesn\'t have this TAG');
                    }
                }
            });

            event.preventDefault();
        });
    }

    function setDeleteBtnClickHandler() {
        //delete
        $('.del_cat').off('click');

        $('.del_cat').click(function (event) {
            var category = $(this).parent();

            $.ajax({
                type: 'POST',
                url: $(this).attr('href'),
                data: 'cat_id=' + $(this).attr('id'),
                success: function (data) {
                    if (data.success) {
                        category.remove();
                        alert('Category has been deleted');
                    }
                    else {
                        alert('Delete error');
                    }
                }
            });

            event.preventDefault();
        });
    }

    setDeleteBtnClickHandler();
    setUpdateBtnClickHandler();

    //insert
    $('.save_new_cat').click(function (event) {
        var currentValue = $('.new_cat_name').val();

        if (currentValue.length > 0) {
            $.ajax({
                type: 'POST',
                url: $(this).attr('href'),
                data: 'cat_name=' + currentValue,
                success: function (data) {
                    if (data.success) {
                        alert('Category has been created');

                        $('.new_cat_toggle').toggle();
                        $('.new_cat_name').val('');

                        $('.cat_list').append('<p class="cat"><span class="alert alert-success fade in">' + currentValue + '</span> ' +
                            '<a href="/delcat" class="del_cat" id="' + data.success + '">Delete</a> ' +
                        '<a class="upd_cat" href="#">Update</a> ' +
                        '<a href="/updcat" class="save_upd_cat" id="' + data.success + '">Save</a>');

                        $('.cat .save_upd_cat').hide();

                        setDeleteBtnClickHandler();
                        setUpdateBtnClickHandler();
                    }
                    else alert('There is the same category or Flickr doesn\'t have this TAG');

                }
            });
        }
        else alert('Enter category name');

        event.preventDefault();
    });
})(jQuery);
