jQuery(document).ready(function ($) {
    'use strict';
    let _vi_wad_ajax_nonce = vi_wad_params_admin_error_images._vi_wad_ajax_nonce;
    let is_current_page_focus = false;
    /*Set paged to 1 before submitting*/
    $('.tablenav-pages').find('.current-page').on('focus', function (e) {
        is_current_page_focus = true;
    }).on('blur', function (e) {
        is_current_page_focus = false;
    });
    $('select[name="vi_wad_search_product_id"]').on('change', function () {
        let $form = $(this).closest('form');
        if (!is_current_page_focus) {
            $form.find('.current-page').val(1);
        }
        $form.submit();
    });
    $('.vi-wad-search-product-id').select2({
        placeholder: 'Filter by product',
        allowClear: true,
    });
    $('.vi-wad-search-product-id-ajax').select2({
        closeOnSelect: true,
        allowClear: true,
        placeholder: "Please enter product title to search",
        ajax: {
            url: "admin-ajax.php?action=wad_search_product_failed_images&_vi_wad_ajax_nonce="+_vi_wad_ajax_nonce,
            dataType: 'json',
            type: "GET",
            quietMillis: 50,
            delay: 250,
            data: function (params) {
                return {
                    keyword: params.term,
                    p_id: $(this).closest('td').data('id')
                };
            },
            processResults: function (data) {
                return {
                    results: data
                };
            }
        },
        escapeMarkup: function (markup) {
            return markup;
        }, // let our custom formatter work
        minimumInputLength: 1
    });
    let $button_download = $('.vi-wad-action-download');
    let $button_download_all = $('.vi-wad-action-download-all');
    let $button_delete = $('.vi-wad-action-delete');
    let $button_delete_all = $('.vi-wad-action-delete-all');
    let queue = [];
    let queue_delete = [];
    let is_bulk_delete = false;
    $button_download_all.on('click', function () {
        if ($('.vi-wad-button-all-container').find('.loading').length === 0) {
            $('.vi-wad-action-download').not('.loading').map(function () {
                if ($(this).closest('.vi-wad-actions-container').find('.loading').length === 0) {
                    queue.push($(this));
                }
            });
            if (queue.length > 0) {
                queue.shift().click();
                $button_download_all.addClass('loading');
            }
        }
    });
    $button_delete_all.on('click', function () {
        if ($('.vi-wad-button-all-container').find('.loading').length === 0) {
            if (confirm(vi_wad_params_admin_error_images.i18n_confirm_delete_all)) {
                $('.vi-wad-action-delete').not('.loading').map(function () {
                    if ($(this).closest('.vi-wad-actions-container').find('.loading').length === 0) {
                        queue_delete.push($(this));
                    }
                });
                console.log(queue_delete)
                if (queue_delete.length > 0) {
                    is_bulk_delete = true;
                    queue_delete.shift().click();
                    $button_delete_all.addClass('loading');
                }
            }

        }
    });
    $button_delete.on('click', function () {
        let $button = $(this);
        let $row = $button.closest('tr');
        let item_id = $button.data('item_id');
        if ($button.hasClass('loading')) {
            return;
        }
        if (is_bulk_delete || confirm(vi_wad_params_admin_error_images.i18n_confirm_delete)) {
            $button.addClass('loading');
            $button.find('.vi-wad-delete-image-error').remove();
            $.ajax({
                url: vi_wad_params_admin_error_images.url,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'vi_wad_delete_error_product_images',
                    item_id: item_id,
                    _vi_wad_ajax_nonce: _vi_wad_ajax_nonce
                },
                success: function (response) {
                    $button.removeClass('loading');
                    if (response.status === 'success') {
                        $row.remove();
                        if ($('.vi-wad-action-download').length === 0) {
                            $('.vi-wad-button-all-container').remove();
                        }
                    } else {
                        let $result_icon = $('<span class="vi-wad-delete-image-error dashicons dashicons-no" title="' + response.message + '"></span>');
                        $button.append($result_icon);
                    }
                },
                error: function (err) {
                    console.log(err);
                    $button.removeClass('loading');
                },
                complete: function () {
                    if (queue_delete.length > 0) {
                        queue_delete.shift().click();
                    } else {
                        if ($('.vi-wad-action-delete-all').hasClass('loading')) {
                            $('.vi-wad-action-delete-all').removeClass('loading')
                        }
                        is_bulk_delete = false;
                    }
                }
            })
        }
    });
    $button_download.on('click', function () {
        let $button = $(this);
        let $row = $button.closest('tr');
        let item_id = $button.data('item_id');
        if ($button.hasClass('loading')) {
            return;
        }
        $button.addClass('loading');
        $button.find('.vi-wad-download-image-error').remove();
        $.ajax({
            url: vi_wad_params_admin_error_images.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'vi_wad_download_error_product_images',
                item_id: item_id,
                _vi_wad_ajax_nonce: _vi_wad_ajax_nonce
            },
            success: function (response) {
                $button.removeClass('loading');
                if (response.status === 'success') {
                    $row.remove();
                    if ($('.vi-wad-action-download').length === 0) {
                        $('.vi-wad-button-all-container').remove();
                    }
                } else {
                    let $result_icon = $('<span class="vi-wad-download-image-error dashicons dashicons-no" title="' + response.message + '"></span>');
                    $button.append($result_icon);
                }
            },
            error: function (err) {
                console.log(err);
                $button.removeClass('loading');
            },
            complete: function () {
                if (queue.length > 0) {
                    queue.shift().click();
                } else if ($('.vi-wad-action-download-all').hasClass('loading')) {
                    $('.vi-wad-action-download-all').removeClass('loading')
                }
            }
        })
    })
});
