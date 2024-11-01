jQuery(document).ready(function ($) {
    'use strict';
    let _vi_wad_ajax_nonce = vi_wad_imported_list_params._vi_wad_ajax_nonce;
    let queue = [];
    let is_deleting = false;
    /*Set paged to 1 before submitting*/
    let is_current_page_focus = false;
    $('.tablenav-pages').find('.current-page').on('focus', function (e) {
        is_current_page_focus = true;
    }).on('blur', function (e) {
        is_current_page_focus = false;
    });
    $('.search-box').find('input[type="submit"]').on('click', function () {
        let $form = $(this).closest('form');
        if (!is_current_page_focus) {
            $form.find('.current-page').val(1);
        }
    });
    $('.vi-ui.tabular.menu .item').vi_tab();
    $('.vi-ui.accordion').vi_accordion('refresh');
    $('.vi-ui.checkbox').checkbox();
    $('select.vi-ui.dropdown').dropdown();
    let $imported_list_count = $('#toplevel_page_woo-alidropship').find('.current').find('.vi-wad-imported-list-count');
    $('.vi-wad-button-view-and-edit').on('click', function (e) {
        e.stopPropagation();
    });
    add_keyboard_event();

    function add_keyboard_event() {
        $(document).on('keydown', function (e) {
            if (!$('.vi-wad-delete-product-options-container').hasClass('vi-wad-hidden')) {
                if (e.keyCode == 13) {
                    if (!$('.vi-wad-delete-product-options-button-override').hasClass('vi-wad-hidden')) {
                        $('.vi-wad-delete-product-options-button-override').click();
                        $('.vi-wad-delete-product-options-override-product').focus();
                    } else if (!$('.vi-wad-delete-product-options-button-delete').hasClass('vi-wad-hidden')) {
                        $('.vi-wad-delete-product-options-button-delete').click();
                    }
                } else if (e.keyCode == 27) {
                    $('.vi-wad-overlay').click();
                }
            }
        });
    }

    $('.vi-wad-button-trash').on('click', function () {
        let $button = $(this);
        let $trash_count = $('.vi-wad-imported-products-count-trash');
        let trash_count = parseInt($trash_count.html());
        let $publish_count = $('.vi-wad-imported-products-count-publish');
        let publish_count = parseInt($publish_count.html());
        let data = {
            action: 'vi_wad_trash_product',
            _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
            product_id: $(this).data('product_id'),
        };
        let $product_container = $('#vi-wad-product-item-id-' + data.product_id);
        $button.addClass('loading');
        $.ajax({
            url: vi_wad_imported_list_params.url,
            type: 'post',
            dataType: 'JSON',
            data: data,
            success: function (res) {
                if (res.status === 'success') {
                    let imported_list_count_value = parseInt($imported_list_count.html());
                    if (imported_list_count_value > 0) {
                        let current_count = imported_list_count_value - 1;
                        $imported_list_count.html(current_count);
                        $imported_list_count.parent().attr('class', 'update-plugins count-' + current_count);
                    }
                    trash_count++;
                    publish_count--;
                    $product_container.fadeOut(300);
                    setTimeout(function () {
                        $trash_count.html(trash_count);
                        $publish_count.html(publish_count);
                        $product_container.remove();
                    }, 300)
                }
            },
            error: function (res) {
                console.log(res);
            },
            complete: function () {
                $button.removeClass('loading');
            }

        });
    });

    $('.vi-wad-button-restore').on('click', function () {
        let $button = $(this);
        let $trash_count = $('.vi-wad-imported-products-count-trash');
        let trash_count = parseInt($trash_count.html());
        let $publish_count = $('.vi-wad-imported-products-count-publish');
        let publish_count = parseInt($publish_count.html());
        let data = {
            action: 'vi_wad_restore_product',
            _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
            product_id: $(this).data('product_id'),
        };
        let $product_container = $('#vi-wad-product-item-id-' + data.product_id);
        $button.addClass('loading');
        $.ajax({
            url: vi_wad_imported_list_params.url,
            type: 'post',
            dataType: 'JSON',
            data: data,
            success: function (res) {
                console.log(res);
                if (res.status === 'success') {
                    let imported_list_count_value = parseInt($imported_list_count.html());
                    if (imported_list_count_value > 0) {
                        let current_count = imported_list_count_value + 1;
                        $imported_list_count.html(current_count);
                        $imported_list_count.parent().attr('class', 'update-plugins count-' + current_count);
                    }
                    trash_count--;
                    publish_count++;
                    $product_container.fadeOut(300);
                    setTimeout(function () {
                        $trash_count.html(trash_count);
                        $publish_count.html(publish_count);
                        $product_container.remove();
                    }, 300)
                }
            },
            error: function (res) {
                console.log(res);
            },
            complete: function () {
                $button.removeClass('loading');
            }
        });
    });

    $('.vi-wad-button-delete').on('click', function () {
        let $button_delete = $(this);
        if (!$button_delete.hasClass('loading')) {
            let product_title = $button_delete.data()['product_title'];
            let product_id = $button_delete.data()['product_id'];
            let woo_product_id = $button_delete.data()['woo_product_id'];
            $('.vi-wad-delete-product-options-product-title').html(product_title);
            $('.vi-wad-delete-product-options-button-delete').data('product_id', product_id).data('woo_product_id', woo_product_id);
            vi_wad_delete_product_options_show_delete();
        }
    });


    $('.vi-wad-delete-product-options-button-delete').on('click', function () {
        let $button = $(this);
        let product_id = $button.data()['product_id'];
        let woo_product_id = $button.data()['woo_product_id'];
        let $button_delete = $(`.vi-wad-button-delete[data-product_id="${product_id}"]`);
        $button_delete.addClass('loading');
        let $product_container = $(`#vi-wad-product-item-id-${product_id}`);
        $product_container.addClass('vi-wad-accordion-deleting').vi_accordion('close', 0);
        let delete_woo_product = $('.vi-wad-delete-product-options-delete-woo-product').prop('checked') ? 1 : 0;
        vi_wad_delete_product_options_hide();
        if (is_deleting) {
            queue.push({
                product_id: product_id,
                woo_product_id: woo_product_id,
                delete_woo_product: delete_woo_product,
            });
        } else {
            is_deleting = true;
            vi_wad_delete_product(product_id, woo_product_id, delete_woo_product);
        }
    });

    function vi_wad_delete_product(product_id, woo_product_id, delete_woo_product) {
        let $button_delete = $(`.vi-wad-button-delete[data-product_id="${product_id}"]`);
        let $product_container = $(`#vi-wad-product-item-id-${product_id}`);
        hide_message($product_container);
        $.ajax({
            url: vi_wad_imported_list_params.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'vi_wad_delete_product',
                _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                product_id: product_id,
                woo_product_id: woo_product_id,
                delete_woo_product: delete_woo_product,
            },
            success: function (response) {
                if (response.status === 'success') {
                    let imported_list_count_value = parseInt($imported_list_count.html());
                    if (imported_list_count_value > 0) {
                        let current_count = parseInt(imported_list_count_value - 1);
                        $imported_list_count.html(current_count);
                        $imported_list_count.parent().attr('class', 'update-plugins count-' + current_count);
                    }

                    $product_container.fadeOut(300);
                    setTimeout(function () {
                        $product_container.remove();
                        maybe_reload_page();
                    }, 300)
                } else {
                    show_message($product_container, 'negative', response.message);
                    $product_container.removeClass('vi-wad-accordion-deleting').vi_accordion('open', 0);
                }
            },
            error: function (err) {
                show_message($product_container, 'negative', err.statusText);
                $product_container.removeClass('vi-wad-accordion-deleting').vi_accordion('open', 0);
            },
            complete: function () {
                is_deleting = false;
                $button_delete.removeClass('loading');
                if (queue.length > 0) {
                    let current = queue.shift();
                    vi_wad_delete_product(current.product_id, current.woo_product_id, current.delete_woo_product);
                }
            }
        })

    }

    $('.vi-wad-button-override').on('click', function () {
        let button_override = $(this);
        let product_id = button_override.data()['product_id'];
        let product_title = button_override.data()['product_title'];
        let woo_product_id = button_override.data()['woo_product_id'];
        $('.vi-wad-delete-product-options-product-title').html(product_title);
        $('.vi-wad-delete-product-options-button-override').data('product_id', product_id).data('woo_product_id', woo_product_id);
        $('.vi-wad-delete-product-options-override-product-message').addClass('vi-wad-hidden');
        vi_wad_delete_product_options_show_override();
    });
    let override_product_data, override_product_id;
    $('.vi-wad-delete-product-options-override-product-new-close').on('click', function () {
        $('.vi-wad-delete-product-options-override-product-message').addClass('vi-wad-hidden').html('');
        $('.vi-wad-delete-product-options-override-product-new-wrap').addClass('vi-wad-hidden');
        $('.vi-wad-delete-product-options-button-override').html('Check').removeClass('vi-wad-checked');
        $('.vi-wad-delete-product-options-override-product').val('').focus();
        override_product_data = '';
        override_product_id = '';
    });

    $('.vi-wad-delete-product-options-button-override').on('click', function () {
        let $button_override = $(this);
        let override_product_url = $('#vi-wad-delete-product-options-override-product').val();
        let product_id = $button_override.data('product_id');
        let woo_product_id = $button_override.data('woo_product_id');
        let $current_button_override = $('.vi-wad-button-override[data-product_id="' + product_id + '"]');
        let step = 'check';
        if ($button_override.hasClass('vi-wad-checked')) {
            step = 'override';
        }
        if (step === 'check') {
            if (!override_product_url) {
                alert('Please enter url or ID of product you want to use to override current product with');
                return;
            }
        } else {
            if (!override_product_data && !override_product_id) {
                alert('Please enter product url to check.');
                return;
            }
        }
        $('.vi-wad-delete-product-options-override-product-message').addClass('vi-wad-hidden');
        $current_button_override.addClass('loading');
        $button_override.addClass('loading');
        $.ajax({
            url: vi_wad_imported_list_params.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'vi_wad_override_product',
                _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                product_id: product_id,
                woo_product_id: woo_product_id,
                override_product_url: override_product_url,
                override_product_data: override_product_data,
                override_product_id: override_product_id,
                step: step,
                replace_description: $('.vi-wad-delete-product-options-override-product-replace-description').prop('checked') ? 1 : 0,
            },
            success: function (response) {
                if (step === 'check') {
                    if (response.status === 'error') {
                        $('.vi-wad-delete-product-options-override-product-message').removeClass('vi-wad-hidden').html(response.message);
                    } else {
                        override_product_data = response.data;
                        override_product_id = response.exist_product_id;
                        $('.vi-wad-delete-product-options-override-product-new-wrap').removeClass('vi-wad-hidden');
                        $('.vi-wad-delete-product-options-override-product-new-image').find('img').attr('src', response.image);
                        $('.vi-wad-delete-product-options-override-product-new-title').html(response.title);
                        if (response.message) {
                            $('.vi-wad-delete-product-options-override-product-message').removeClass('vi-wad-hidden').html(response.message);
                        }
                        if (response.status === 'success') {
                            $button_override.html(vi_wad_imported_list_params.override).addClass('vi-wad-checked');
                        }
                    }
                } else {
                    if (response.status === 'success') {
                        let $product_container = $('#vi-wad-product-item-id-' + product_id);
                        $product_container.find('div.content').eq(0).prepend(response.data);
                        // $product_container.vi_accordion('close', 0);
                        $current_button_override.remove();
                        $product_container.find('.vi-wad-button-override-container').append(response.button_override_html);
                        vi_wad_delete_product_options_hide();
                    } else {
                        $button_override.html(vi_wad_imported_list_params.check).removeClass('vi-wad-checked');
                        if (response.message) {
                            $('.vi-wad-delete-product-options-override-product-message').removeClass('vi-wad-hidden').html(response.message);
                        }
                    }
                }
            },
            error: function (err) {
                console.log(err)
            },
            complete: function () {
                $current_button_override.removeClass('loading');
                $button_override.removeClass('loading');
            }
        })
    });

    $('.vi-wad-overlay').on('click', function () {
        vi_wad_delete_product_options_hide();
    });
    $('.vi-wad-delete-product-options-close').on('click', function () {
        vi_wad_delete_product_options_hide();
    });
    $('.vi-wad-delete-product-options-button-cancel').on('click', function () {
        vi_wad_delete_product_options_hide();
    });
    $('.vi-wad-accordion-store-url').on('click', function (e) {
        e.stopPropagation();
    });

    function vi_wad_delete_product_options_hide() {
        $('.vi-wad-delete-product-options-content-header-delete').addClass('vi-wad-hidden');
        $('.vi-wad-delete-product-options-button-delete').addClass('vi-wad-hidden').data('product_id', '').data('woo_product_id', '');
        $('.vi-wad-delete-product-options-delete-woo-product-wrap').addClass('vi-wad-hidden');
        $('.vi-wad-delete-product-options').addClass('vi-wad-delete-product-options-editing');
        $('.vi-wad-delete-product-options-container').addClass('vi-wad-hidden');
        vi_wad_enable_scroll();
        $('.vi-wad-delete-product-options-content-header-override').addClass('vi-wad-hidden');
        $('.vi-wad-delete-product-options-button-override').addClass('vi-wad-hidden').data('product_id', '').data('woo_product_id', '');
        $('.vi-wad-delete-product-options-override-product-wrap').addClass('vi-wad-hidden');
    }

    function vi_wad_delete_product_options_show_override() {
        $('.vi-wad-delete-product-options-content-header-override').removeClass('vi-wad-hidden');
        $('.vi-wad-delete-product-options-button-override').removeClass('vi-wad-hidden vi-wad-checked').html(vi_wad_imported_list_params.check);
        $('.vi-wad-delete-product-options-override-product-wrap').removeClass('vi-wad-hidden');
        $('.vi-wad-delete-product-options-override-product-new-image').find('img').attr('src', '');
        $('.vi-wad-delete-product-options-override-product-new-title').html('');
        $('.vi-wad-delete-product-options-override-product-new-wrap').addClass('vi-wad-hidden');
        vi_wad_delete_product_options_show();
        $('.vi-wad-delete-product-options-override-product').val('').focus();
    }

    function vi_wad_delete_product_options_show_delete() {
        $('.vi-wad-delete-product-options-content-header-delete').removeClass('vi-wad-hidden');
        $('.vi-wad-delete-product-options-button-delete').removeClass('vi-wad-hidden');
        $('.vi-wad-delete-product-options-delete-woo-product-wrap').removeClass('vi-wad-hidden');
        vi_wad_delete_product_options_show();
    }

    function vi_wad_delete_product_options_show() {
        $('.vi-wad-delete-product-options-container').removeClass('vi-wad-hidden');

        vi_wad_disable_scroll();
    }

    function vi_wad_enable_scroll() {
        let scrollTop = parseInt($('html').css('top'));
        $('html').removeClass('vi_wad-noscroll');
        $('html,body').scrollTop(-scrollTop);
    }

    function vi_wad_disable_scroll() {
        if ($(document).height() > $(window).height()) {
            let scrollTop = ($('html').scrollTop()) ? $('html').scrollTop() : $('body').scrollTop(); // Works for Chrome, Firefox, IE...
            $('html').addClass('vi_wad-noscroll').css('top', -scrollTop);
        }
    }

    function hide_message($parent) {
        $parent.find('.vi-wad-message').html('')
    }

    function show_message($parent, type, message) {
        $parent.find('.vi-wad-message').html(`<div class="vi-ui message ${type}"><div>${message}</div></div>`)
    }

    function maybe_reload_page() {
        if ($('.vi-wad-accordion').length === 0) {
            let url = new URL(document.location.href);
            url.searchParams.delete('vi_wad_search_woo_id');
            url.searchParams.delete('vi_wad_search_id');
            url.searchParams.delete('vi_wad_search');
            url.searchParams.delete('paged');
            document.location.href = url.href;
        }
    }
});
