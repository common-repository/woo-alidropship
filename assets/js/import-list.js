jQuery(document).ready(function ($) {
    'use strict';

    let body = $('body');
    let _vi_wad_ajax_nonce = vi_wad_import_list_params._vi_wad_ajax_nonce;
    let queue = [];
    let is_importing = false;
    let is_current_page_focus = false;

    /*Set paged to 1 before submitting*/
    function setPagedTo1() {
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
    }

    function init() {
        setPagedTo1();
        $('.vi-ui.tabular.menu .item').vi_tab();
        $('.vi-ui.accordion').vi_accordion('refresh');
        $('.vi-ui.checkbox').checkbox();
        $('.ui-sortable').sortable();
        $('select.vi-ui.dropdown').not('.vi-wad-accordion-bulk-actions,.vi-wad-modal-popup-set-shipping-class-select,.vi-wad-import-data-shipping-class,.vi-wad-import-data-tags,.vi-wad-modal-popup-set-tags-select').dropdown();
        $('.vi-wad-accordion-bulk-actions').dropdown({placeholder: 'auto'});
        $('.vi-wad-modal-popup-set-shipping-class-select,.vi-wad-import-data-shipping-class').dropdown({
            placeholder: '',
            fullTextSearch: true
        });
        $('.vi-wad-import-data-tags,.vi-wad-modal-popup-set-tags-select').dropdown({
            allowAdditions: true,
            fullTextSearch: true
        });

        /*Set default categories*/
        $('.vi-wad-import-data-categories,.vi-wad-modal-popup-set-categories-select').dropdown({
            fullTextSearch: true,
            onAdd: function (value, text, $choice) {
                $(this).find('a.ui.label').map(function () {
                    let $option = $(this);
                    $option.html($option.html().replace(/&nbsp;/g, ''));
                })
            }
        });
        if (vi_wad_import_list_params.product_categories) {
            $('.vi-wad-import-data-categories').dropdown('set exactly', vi_wad_import_list_params.product_categories).trigger('change');
        }

        $(".search-product").select2({
            closeOnSelect: true,
            allowClear: true,
            placeholder: "Please enter product title to search",
            ajax: {
                url: `admin-ajax.php?action=wad_search_product&exclude_ali_products=1&_vi_wad_ajax_nonce=${_vi_wad_ajax_nonce}`,
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
        $('.vi-wad-override-product-options-override-keep-product').on('change', function () {
            let $button = $(this),
                $message = $button.closest('.vi-wad-override-product-options-container').find('.vi-wad-override-product-remove-warning'),
                $override_find_in_orders = $('.vi-wad-override-product-options-content-body-row-override-find-in-orders');
            if ($button.prop('checked')) {
                $message.fadeOut(100);
                $override_find_in_orders.hide();
            } else {
                $message.fadeIn(100);
                $override_find_in_orders.show();
            }
        }).trigger('change');

    }

    init();

    /**
     * Filter product attributes
     */
    body.on('click', '.vi-wad-attribute-filter-item', function (e) {
        let $button = $(this);
        let selected = [];
        let $container = $button.closest('table');
        let $attribute_filters = $container.find('.vi-wad-attribute-filter-list');
        let $attribute_filter = $attribute_filters.eq(0);
        let current_filter_slug = $attribute_filter.data('attribute_slug');
        if ($button.hasClass('vi-wad-attribute-filter-item-active')) {
            $button.removeClass('vi-wad-attribute-filter-item-active');
        } else {
            $button.addClass('vi-wad-attribute-filter-item-active');
        }
        let $variations_rows = $container.find('.vi-wad-product-variation-row');
        let $active_filters = $attribute_filter.find('.vi-wad-attribute-filter-item-active');
        let active_variations = [];
        if ($active_filters.length > 0) {
            $active_filters.map(function () {
                selected.push($(this).data('attribute_value'));
            });
            for (let $i = 0; $i < $variations_rows.length; $i++) {
                let $current_attribute = $variations_rows.eq($i).find('.vi-wad-import-data-variation-attribute[data-attribute_slug="' + current_filter_slug + '"]');
                if (selected.indexOf($current_attribute.data('attribute_value')) > -1) {
                    active_variations[$i] = 1;
                } else {
                    active_variations[$i] = 0;
                }
            }
        } else {
            for (let $i = 0; $i < $variations_rows.length; $i++) {
                active_variations[$i] = 1;
            }
        }

        if ($attribute_filters.length > 1) {
            for (let $j = 1; $j < $attribute_filters.length; $j++) {
                $attribute_filter = $attribute_filters.eq($j);
                current_filter_slug = $attribute_filter.data('attribute_slug');
                $active_filters = $attribute_filter.find('.vi-wad-attribute-filter-item-active');
                if ($active_filters.length > 0) {
                    $active_filters.map(function () {
                        selected.push($(this).data('attribute_value'));
                    });
                    for (let $i = 0; $i < $variations_rows.length; $i++) {
                        let $current_attribute = $variations_rows.eq($i).find('.vi-wad-import-data-variation-attribute[data-attribute_slug="' + current_filter_slug + '"]');
                        if (selected.indexOf($current_attribute.data('attribute_value')) < 0) {
                            active_variations[$i] = 0;
                        }
                    }
                }
            }
        }
        let variations_count = 0;
        for (let $i = 0; $i < $variations_rows.length; $i++) {
            let $current_variation = $variations_rows.eq($i);
            if (active_variations[$i] == 1) {
                $current_variation.removeClass('vi-wad-variation-filter-inactive');
                if ($current_variation.find('.vi-wad-variation-enable').prop('checked')) {
                    variations_count++;
                }
            } else {
                $current_variation.addClass('vi-wad-variation-filter-inactive');
            }
        }
        let $current_container = $button.closest('form');
        $current_container.find('.vi-wad-selected-variation-count').html(variations_count);
    });
    /**
     * Set product featured image
     */
    body.on('click', '.vi-wad-set-product-image', function (e) {
        e.stopPropagation();
        let $button = $(this);
        let container = $button.closest('form');
        let $product_image_container = container.find('.vi-wad-product-image');
        let $gallery_item = $button.closest('.vi-wad-product-gallery-item');
        let $product_gallery = $button.closest('.vi-wad-product-gallery');
        if ($gallery_item.hasClass('vi-wad-is-product-image')) {
            $gallery_item.removeClass('vi-wad-is-product-image');
            $product_image_container.removeClass('vi-wad-selected-item');
            $product_image_container.find('input[type="hidden"]').val('');
        } else {
            if (!$gallery_item.hasClass('vi-wad-selected-item')) {
                $gallery_item.click();
            }

            if (!$product_image_container.hasClass('vi-wad-selected-item')) {
                $product_image_container.addClass('vi-wad-selected-item');
            }
            $product_gallery.find('.vi-wad-product-gallery-item').removeClass('vi-wad-is-product-image');
            $gallery_item.addClass('vi-wad-is-product-image');
            let product_image_url = $gallery_item.find('img').data('image_src');

            $(this).closest('.vi-wad-accordion').find('.vi-wad-accordion-product-image').attr('src', product_image_url);
            $product_image_container.find('img').attr('src', product_image_url);
            $product_image_container.find('input[type="hidden"]').val(product_image_url);
        }

    });

    add_keyboard_event();

    /**
     * Support ESC(cancel) and Enter(OK) key
     */
    function add_keyboard_event() {
        $(document).on('keydown', function (e) {
            if (!$('.vi-wad-set-price-container').hasClass('vi-wad-hidden')) {
                if (e.keyCode == 13) {
                    $('.vi-wad-set-price-button-set').click();
                } else if (e.keyCode == 27) {
                    $('.vi-wad-overlay').click();
                }
            } else if (!$('.vi-wad-override-product-options-container').hasClass('vi-wad-hidden')) {
                if (e.keyCode == 13) {
                    $('.vi-wad-override-product-options-button-override').click();
                } else if (e.keyCode == 27) {
                    $('.vi-wad-override-product-overlay').click();
                }
            }
        });
    }

    count_selected_variations();
    let current_focus_checkbox;

    /**
     * Count currently selected variations
     */
    function count_selected_variations() {
        body.on('click', '.vi-wad-variations-bulk-enable', function () {
            let $current_container = $(this).closest('form');
            let selected = 0;
            if ($(this).prop('checked')) {
                selected = $current_container.find('.vi-wad-product-variation-row').length - $current_container.find('.vi-wad-variation-filter-inactive').length;
                $current_container.find('.vi-wad-variations-bulk-select-image').prop('checked', true).trigger('change');
            } else {
                $current_container.find('.vi-wad-import-data-variation-default').prop('checked', false);
                $current_container.find('.vi-wad-variations-bulk-select-image').prop('checked', false).trigger('change');
            }
            $current_container.find('.vi-wad-selected-variation-count').html(selected);
        });
        body.on('click', '.vi-wad-variation-enable', function (e) {
            let $current_select = $(this);
            let $current_container = $current_select.closest('form');
            let prev_select = $current_container.find('.vi-wad-variation-enable').index(current_focus_checkbox);
            let selected = 0;
            if (e.shiftKey) {
                let current_index = $current_container.find('.vi-wad-variation-enable').index($current_select);
                if ($current_select.prop('checked')) {
                    if (prev_select < current_index) {
                        for (let i = prev_select; i <= current_index; i++) {
                            $current_container.find('.vi-wad-variation-enable').eq(i).prop('checked', true)
                        }
                    } else {
                        for (let i = current_index; i <= prev_select; i++) {
                            $current_container.find('.vi-wad-variation-enable').eq(i).prop('checked', true)
                        }
                    }
                } else {
                    if (prev_select < current_index) {
                        for (let i = prev_select; i <= current_index; i++) {
                            $current_container.find('.vi-wad-variation-enable').eq(i).prop('checked', false)
                        }
                    } else {
                        for (let i = current_index; i <= prev_select; i++) {
                            $current_container.find('.vi-wad-variation-enable').eq(i).prop('checked', false)
                        }
                    }
                }
            }
            $current_container.find('.vi-wad-variation-enable').map(function () {
                let $current_row = $(this).closest('tr');
                if ($(this).prop('checked') && !$current_row.hasClass('vi-wad-variation-filter-inactive')) {
                    selected++;
                    $current_row.find('.vi-wad-variation-image').removeClass('vi-wad-selected-item').click();
                } else {
                    $current_row.find('.vi-wad-variation-image').addClass('vi-wad-selected-item').click();
                    $current_row.find('.vi-wad-import-data-variation-default').prop('checked', false);
                }
            });

            $current_container.find('.vi-wad-selected-variation-count').html(selected);
            current_focus_checkbox = $(this);
        })
    }

    /**
     * Bulk select variations
     */
    body.on('change', '.vi-wad-variations-bulk-enable', function () {
        let product = $(this).closest('form');
        product.find('.vi-wad-product-variation-row:not(.vi-wad-variation-filter-inactive) .vi-wad-variation-enable').prop('checked', $(this).prop('checked'));
    });

    /**
     * Bulk select images
     */
    body.on('change', '.vi-wad-variations-bulk-select-image', function () {
        let button_bulk = $(this);
        let product = button_bulk.closest('form');
        let image_wrap = product.find('.vi-wad-variation-image');
        if (button_bulk.prop('checked')) {
            image_wrap.addClass('vi-wad-selected-item');
        } else {
            image_wrap.removeClass('vi-wad-selected-item');
        }
        image_wrap.map(function () {
            let current = $(this);
            if (button_bulk.prop('checked')) {
                current.find('input[type="hidden"]').val(current.find('.vi-wad-import-data-variation-image').attr('src'));
            } else {
                current.find('input[type="hidden"]').val('');
            }
        })

    });

    function hide_message($parent) {
        $parent.find('.vi-wad-message').html('')
    }

    function show_message($parent, type, message) {
        $parent.find('.vi-wad-message').html(`<div class="vi-ui message ${type}"><div>${message}</div></div>`)
    }

    let $import_list_count = $('#toplevel_page_woo-alidropship').find('.current').find('.vi-wad-import-list-count');
    let $imported_list_count = $('.vi-wad-imported-list-count');

    /**
     * Empty import list
     */
    body.on('click', '.vi-wad-button-empty-import-list', function (e) {
        if (!confirm('Do you want to delete all products(except overriding products) from your Import list?')) {
            e.preventDefault();
            return false;
        }
    });

    let is_bulk_remove = false;
    /**
     * Remove product
     */
    body.on('click', '.vi-wad-button-remove', function (e) {
        e.stopPropagation();
        let $button_remove = $(this);
        let product_id = $button_remove.data('product_id');
        let $product_container = $('#vi-wad-product-item-id-' + product_id);

        if ($button_remove.closest('.vi-wad-button-view-and-edit').find('.loading').length === 0 && (is_bulk_remove || confirm(vi_wad_import_list_params.i18n_remove_product_confirm))) {
            $product_container.vi_accordion('close', 0).addClass('vi-wad-accordion-removing');
            $button_remove.addClass('loading');
            hide_message($product_container);
            $.ajax({
                url: vi_wad_import_list_params.url,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'vi_wad_remove',
                    product_id: product_id,
                    _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                },
                success: function (response) {
                    if (response.status === 'success') {
                        let import_list_count_value = parseInt($import_list_count.html());
                        if (import_list_count_value > 0) {
                            let current_count = parseInt(import_list_count_value - 1);
                            $import_list_count.html(current_count);
                            $import_list_count.parent().attr('class', 'update-plugins count-' + current_count);
                        }
                        $product_container.fadeOut(300);
                        setTimeout(function () {
                            $product_container.remove();
                            maybe_reload_page();
                            maybe_hide_bulk_actions();
                        }, 300)
                    } else {
                        $product_container.vi_accordion('open', 0).removeClass('vi-wad-accordion-removing');
                        show_message($product_container, 'negative', response.message ? response.message : 'Error');
                    }
                },
                error: function (err) {
                    console.log(err);
                    $product_container.vi_accordion('open', 0).removeClass('vi-wad-accordion-removing');
                    show_message($product_container, 'negative', err.statusText);
                },
                complete: function () {
                    $button_remove.removeClass('loading');
                }
            })
        }
    });

    /**
     * Import product
     */
    body.on('click', '.vi-wad-button-import', function (e) {
        e.stopPropagation();
        let $button_import = $(this);
        let $button_container = $button_import.closest('.vi-wad-button-view-and-edit');
        let product_id = $button_import.data('product_id');
        let $product_container = $('#vi-wad-product-item-id-' + product_id);
        if ($product_container.hasClass('vi-wad-accordion-importing') || $product_container.hasClass('vi-wad-accordion-removing') || $product_container.hasClass('vi-wad-accordion-splitting')) {
            return;
        }
        let $form = $product_container.find('.vi-wad-product-container');
        // let data = $form.serializeArray();
        let form_data = $form.find('.vi-ui.tab').not('.vi-wad-variations-tab').find('input,select,textarea').serializeArray();
        let description = $('#wp-vi-wad-product-description-' + product_id + '-wrap').hasClass('tmce-active') ? tinyMCE.get('vi-wad-product-description-' + product_id).getContent() : $('#vi-wad-product-description-' + product_id).val();
        form_data.push({name: 'vi_wad_product[' + product_id + '][description]', value: description});
        let selected = {};
        if ($form.find('.vi-wad-variation-enable').length > 0) {
            let each_selected = [];
            let selected_key = 0;
            $form.find('.vi-wad-variation-enable').map(function () {
                let $row = $(this).closest('.vi-wad-product-variation-row');
                if ($(this).prop('checked') && !$row.hasClass('vi-wad-variation-filter-inactive')) {
                    each_selected.push(selected_key);
                    let variation_data = $row.find('input,select,textarea').serializeArray();
                    if (variation_data.length > 0) {
                        /*only send data of selected variations*/
                        for (let v_i = 0; v_i < variation_data.length; v_i++) {
                            form_data.push(variation_data[v_i]);
                        }
                    }
                }
                selected_key++;
            });
            selected[product_id] = each_selected;
        } else {
            selected[product_id] = [0];
        }
        form_data.push({name: 'z_check_max_input_vars', value: 1});
        form_data = $.param(form_data);
        if (selected[product_id].length === 0) {
            alert(vi_wad_import_list_params.i18n_empty_variation_error);
            return;
        }
        let empty_price_error = false, sale_price_error = false;
        $form.find('.vi-wad-import-data-variation-sale-price').removeClass('vi-wad-price-error');
        $form.find('.vi-wad-import-data-variation-regular-price').removeClass('vi-wad-price-error');
        for (let i = 0; i < $form.find('.vi-wad-import-data-variation-sale-price').length; i++) {
            let sale_price = $form.find('.vi-wad-import-data-variation-sale-price').eq(i);
            let regular_price = $form.find('.vi-wad-import-data-variation-regular-price').eq(i);
            if (!parseFloat(regular_price.val())) {
                empty_price_error = true;
                regular_price.addClass('vi-wad-price-error')
            } else if (parseFloat(sale_price.val()) > parseFloat(regular_price.val())) {
                sale_price_error = true;
                sale_price.addClass('vi-wad-price-error')
            }
        }
        if (empty_price_error) {
            alert(vi_wad_import_list_params.i18n_empty_price_error);
            return;
        } else if (sale_price_error) {
            alert(vi_wad_import_list_params.i18n_sale_price_error);
            return;
        }
        $button_import.addClass('loading');
        if (!is_importing) {
            $product_container.vi_accordion('close', 0).addClass('vi-wad-accordion-importing');
            is_importing = true;
            $.ajax({
                url: vi_wad_import_list_params.url,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'vi_wad_import',
                    form_data: form_data,
                    _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                    selected: JSON.stringify(selected),
                },
                success: function (response) {
                    if (response.status === 'success') {
                        let import_list_count_value = parseInt($import_list_count.html());
                        if (import_list_count_value > 0) {
                            import_list_count_value--;
                            $import_list_count.html(import_list_count_value);
                            $import_list_count.parent().attr('class', 'update-plugins count-' + import_list_count_value);
                        } else {
                            $import_list_count.html(0);
                            $import_list_count.parent().attr('class', 'update-plugins count-' + 0);
                        }
                        let imported_list_count_value = parseInt($imported_list_count.html());
                        imported_list_count_value++;
                        $imported_list_count.html(imported_list_count_value);
                        $imported_list_count.parent().attr('class', 'update-plugins count-' + imported_list_count_value);
                        if ($('.vi-wad-button-import').length === 0) {
                            $('.vi-wad-button-import-all').remove();
                        }
                        $button_container.append(response.button_html);
                        $button_container.find('.vi-wad-button-remove').remove();
                        $button_import.remove();
                        $product_container.find('.content').remove();
                        $product_container.find('.vi-wad-accordion-title-icon').attr('class', 'icon check green');
                        maybe_hide_bulk_actions();
                    } else {
                        $button_import.removeClass('loading');
                        show_message($product_container, 'negative', response.message ? response.message : 'Error');
                    }
                },
                error: function (err) {
                    console.log(err)
                    $button_import.removeClass('loading');
                    show_message($product_container, 'negative', err.statusText);
                },
                complete: function () {
                    is_importing = false;
                    $product_container.vi_accordion('open', 0).removeClass('vi-wad-accordion-importing');
                    if (queue.length > 0) {
                        queue.shift().click();
                    } else if ($('.vi-wad-button-import-all').hasClass('loading')) {
                        $('.vi-wad-button-import-all').removeClass('loading')
                    }
                }
            })
        } else {
            queue.push($button_import);
        }
    });
    /**
     * Bulk import
     */
    body.on('click', '.vi-wad-button-import-all', function () {
        let $button_import = $(this);
        if ($button_import.hasClass('loading')) {
            return;
        }
        if (!confirm(vi_wad_import_list_params.i18n_import_all_confirm)) {
            return;
        }
        $('.vi-wad-button-import').not('.loading').map(function () {
            if ($(this).closest('.vi-wad-button-view-and-edit').find('.loading').length === 0) {
                queue.push($(this));
                $(this).addClass('loading');
            }
        });
        if (queue.length > 0) {
            if (!is_importing) {
                queue.shift().click();
            }
            $button_import.addClass('loading');
        } else {
            alert(vi_wad_import_list_params.i18n_not_found_error);
        }
    });

    let found_items, check_orders;
    /**
     * Override product
     */
    body.on('click', '.vi-wad-button-override', function (e) {
        e.stopPropagation();
        let $button_import = $(this);
        let product_id = $button_import.data('product_id');
        let form = $button_import.closest('.vi-wad-accordion').find('.vi-wad-product-container');
        let selected = {};
        if (form.find('.vi-wad-variation-enable').length > 0) {
            let each_selected = [];
            let selected_key = 0;
            form.find('.vi-wad-variation-enable').map(function () {
                let $row = $(this).closest('.vi-wad-product-variation-row');
                if ($(this).prop('checked') && !$row.hasClass('vi-wad-variation-filter-inactive')) {
                    each_selected.push(selected_key);
                }
                selected_key++;
            });
            selected[product_id] = each_selected;
        } else {
            selected[product_id] = [0];
        }
        if (selected[product_id].length === 0) {
            alert(vi_wad_import_list_params.i18n_empty_variation_error);
            return;
        }
        let empty_price_error = false, sale_price_error = false;
        let $container = $button_import.closest('.vi-wad-accordion').find('.vi-wad-product-container');
        $container.find('.vi-wad-import-data-variation-sale-price').removeClass('vi-wad-price-error');
        $container.find('.vi-wad-import-data-variation-regular-price').removeClass('vi-wad-price-error');
        for (let i = 0; i < $container.find('.vi-wad-import-data-variation-sale-price').length; i++) {
            let sale_price = $container.find('.vi-wad-import-data-variation-sale-price').eq(i);
            let regular_price = $container.find('.vi-wad-import-data-variation-regular-price').eq(i);
            if (!parseFloat(regular_price.val())) {
                empty_price_error = true;
                regular_price.addClass('vi-wad-price-error')
            } else if (parseFloat(sale_price.val()) > parseFloat(regular_price.val())) {
                sale_price_error = true;
                sale_price.addClass('vi-wad-price-error')
            }
        }

        if (empty_price_error) {
            alert(vi_wad_import_list_params.i18n_empty_price_error);
            return;
        } else if (sale_price_error) {
            alert(vi_wad_import_list_params.i18n_sale_price_error);
            return;
        }
        let $override_woo_id = $container.find('.vi-wad-override-woo-id');
        if ($override_woo_id.val()) {
            $('.vi-wad-override-product-title').html($override_woo_id.find(':selected').html());
        } else {
            $('.vi-wad-override-product-title').html($button_import.closest('.vi-wad-accordion').find('.vi-wad-override-product-product-title').html());
        }
        $('.vi-wad-override-product-options-button-override').data('product_id', product_id).data('override_product_id', $button_import.data('override_product_id'));
        vi_wad_override_product_show($button_import);
    });
    body.on('change', '.vi-wad-override-woo-id', function () {
        let $override_woo_id = $(this), $container = $override_woo_id.closest('.vi-wad-accordion'),
            $button_import = $container.find('.vi-wad-button-import'),
            $button_override = $container.find('.vi-wad-button-override');
        if ($(this).val()) {
            $button_import.addClass('vi-wad-hidden');
            $button_override.removeClass('vi-wad-hidden');
        } else {
            $button_import.removeClass('vi-wad-hidden');
            $button_override.addClass('vi-wad-hidden');
        }
    });
    /**
     * Confirm Override product
     */
    body.on('click', '.vi-wad-override-product-options-button-override', function () {
        let $button = $(this);
        let product_id = $button.data('product_id');
        let override_product_id = $button.data('override_product_id');
        let $button_import = $('.vi-wad-button-override[data-product_id="' + product_id + '"]');
        let $button_container = $button_import.closest('.vi-wad-button-view-and-edit');
        let $product_container = $('#vi-wad-product-item-id-' + product_id);
        let $form = $product_container.find('.vi-wad-product-container');
        // let data = $form.serializeArray();
        let form_data = $form.find('.vi-ui.tab').not('.vi-wad-variations-tab').find('input,select,textarea').serializeArray();
        let description = $('#wp-vi-wad-product-description-' + product_id + '-wrap').hasClass('tmce-active') ? tinyMCE.get('vi-wad-product-description-' + product_id).getContent() : $('#vi-wad-product-description-' + product_id).val();
        form_data.push({name: 'vi_wad_product[' + product_id + '][description]', value: description});
        let selected = {};
        if ($form.find('.vi-wad-variation-enable').length > 0) {
            let each_selected = [];
            let selected_key = 0;
            $form.find('.vi-wad-variation-enable').map(function () {
                let $row = $(this).closest('.vi-wad-product-variation-row');
                if ($(this).prop('checked') && !$row.hasClass('vi-wad-variation-filter-inactive')) {
                    each_selected.push(selected_key);
                    let variation_data = $row.find('input,select,textarea').serializeArray();
                    if (variation_data.length > 0) {
                        /*only send data of selected variations*/
                        for (let v_i = 0; v_i < variation_data.length; v_i++) {
                            form_data.push(variation_data[v_i]);
                        }
                    }
                }
                selected_key++;
            });
            selected[product_id] = each_selected;
        } else {
            selected[product_id] = [0];
        }
        form_data.push({name: 'z_check_max_input_vars', value: 1});
        form_data = $.param(form_data);
        let replace_items = {};
        if (check_orders) {
            $('.vi-wad-override-order-container').map(function () {
                replace_items[$(this).data('replace_item_id')] = $(this).find('.vi-wad-override-with').val();
            })
        }
        $button_import.addClass('loading');
        $button.addClass('loading');
        let override_hide = $('.vi-wad-override-product-options-override-hide').prop('checked') ? 1 : 0;
        $.ajax({
            url: vi_wad_import_list_params.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'vi_wad_override',
                _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                form_data: form_data,
                selected: JSON.stringify(selected),
                override_product_id: override_product_id,
                check_orders: check_orders,
                replace_items: replace_items,
                found_items: found_items,
                override_woo_id: $product_container.find('.vi-wad-override-woo-id').val(),
                override_keep_product: $('.vi-wad-override-product-options-override-keep-product').prop('checked') ? 1 : 0,
                override_title: $('.vi-wad-override-product-options-override-title').prop('checked') ? 1 : 0,
                override_images: $('.vi-wad-override-product-options-override-images').prop('checked') ? 1 : 0,
                override_description: $('.vi-wad-override-product-options-override-description').prop('checked') ? 1 : 0,
                override_find_in_orders: $('.vi-wad-override-product-options-override-find-in-orders').prop('checked') ? 1 : 0,
                override_hide: override_hide,
            },
            success: function (response) {
                if (check_orders) {
                    if (response.status === 'success') {
                        vi_wad_override_product_hide();
                        $button_container.append(response.button_html);
                        $button_container.find('.vi-wad-button-remove').remove();
                        $button_import.remove();
                        $product_container.find('.content').remove();
                        $product_container.find('.vi-wad-accordion-bulk-item-check').remove();
                        $product_container.find('.vi-wad-accordion-title-icon').attr('class', 'icon check green');
                        maybe_hide_bulk_actions();
                    } else {
                        alert(response.message);
                    }

                } else {
                    if (response.status === 'checked') {
                        let $replace_order = $('.vi-wad-override-product-options-content-body-override-old');
                        $replace_order.removeClass('vi-wad-hidden').html(response.replace_order_html);
                        check_orders = 1;
                        found_items = response.found_items;
                        if (override_hide === 1) {
                            $('.vi-wad-override-product-options-content-body-option').remove();
                        } else {
                            $('.vi-wad-override-product-options-content-body-option').addClass('vi-wad-hidden');
                        }
                    } else if (response.status === 'success') {
                        vi_wad_override_product_hide();
                        $button_container.append(response.button_html);
                        $button_container.find('.vi-wad-button-remove').remove();
                        $button_import.remove();
                        $product_container.find('.content').remove();
                        $product_container.find('.vi-wad-accordion-bulk-item-check').remove();
                        $product_container.find('.vi-wad-accordion-title-icon').attr('class', 'icon check green');
                        maybe_hide_bulk_actions();
                    } else {
                        alert(response.message);
                    }
                }
            },
            error: function (err) {
                console.log(err)
            },
            complete: function () {
                $button_import.removeClass('loading');
                $button.removeClass('loading');
            }
        })
    });
    $(document).on('change', '.vi-wad-override-with-attributes>select', function (e) {
        if ($('.vi-wad-override-product-options-override-keep-product').prop('checked') || $('.vi-wad-override-product-options-container').hasClass('vi-wad-override-product-options-container-reimport') || $('.vi-wad-override-product-options-container').hasClass('vi-wad-override-product-options-container-map-existing')) {
            let $current = $(this);
            let selected = $current.val();
            let prev_value = $current.data('prev_value');
            $('.vi-wad-override-with-attributes>select').not($(this)).map(function () {
                let $current = $(this);
                if (selected) {
                    $current.find(`option[value="${selected}"]`).prop('disabled', true);
                }
                if (prev_value) {
                    $current.find(`option[value="${prev_value}"]`).prop('disabled', false);
                }
            });
            $current.data('prev_value', selected);
        }
    });
    /**
     * Bulk set sale price
     */
    body.on('change', '.vi-wad-import-data-variation-sale-price', function () {
        let button = $(this);
        let container_row = button.closest('tr');
        let current_value = parseFloat(button.val());
        let profit = container_row.find('.vi-wad-import-data-variation-profit');
        let cost = container_row.find('.vi-wad-import-data-variation-cost');
        let profit_value = 0;
        if (current_value) {
            profit_value = current_value - parseFloat(cost.html());
        } else {
            profit_value = parseFloat(container_row.find('.vi-wad-import-data-variation-regular-price').val()) - parseFloat(cost.html());
        }
        profit.html(roundResult(profit_value));
    });

    /**
     * Bulk set regular price
     */
    body.on('change', '.vi-wad-import-data-variation-regular-price', function () {
        let button = $(this);
        let container_row = button.closest('tr');
        let sale_price = parseFloat(container_row.find('.vi-wad-import-data-variation-sale-price').val());
        let profit = container_row.find('.vi-wad-import-data-variation-profit');
        let cost = container_row.find('.vi-wad-import-data-variation-cost');
        let profit_value = 0;
        if (!sale_price) {
            profit_value = parseFloat(button.val()) - parseFloat(cost.html());
            profit.html(roundResult(profit_value));
        }
    });

    /**
     * Bulk set price confirm
     */
    body.on('click', '.vi-wad-set-price', function () {
        let $button = $(this);
        $button.addClass('vi-wad-set-price-editing');
        let $container = $('.vi-wad-modal-popup-container');
        $container.attr('class', 'vi-wad-modal-popup-container vi-wad-modal-popup-container-set-price');
        let $content = $('.vi-wad-modal-popup-content-set-price');
        $content.find('.vi-wad-modal-popup-header').find('h2').html('Set ' + $button.data('set_price').replace(/_/g, ' '));
        vi_wad_set_price_show();
    });

    /**
     * Select gallery images
     */
    body.on('click', '.vi-wad-product-gallery-item', function () {
        let current = $(this);
        let image = current.find('.vi-wad-product-gallery-image');
        let container = current.closest('form');
        let gallery_container = container.find('.vi-wad-product-gallery');
        let $product_image_container = container.find('.vi-wad-product-image');
        if (current.hasClass('vi-wad-selected-item')) {
            if (current.hasClass('vi-wad-is-product-image')) {
                current.removeClass('vi-wad-is-product-image');
                current.find('vi-wad-set-product-image').click();
                $product_image_container.removeClass('vi-wad-selected-item').find('input[type="hidden"]').val('');
            }
            current.removeClass('vi-wad-selected-item').find('input[type="hidden"]').val('');
        } else {
            current.addClass('vi-wad-selected-item').find('input[type="hidden"]').val(image.data('image_src'));
        }
        container.find('.vi-wad-selected-gallery-count').html(gallery_container.find('.vi-wad-selected-item').length);
    });

    /**
     * Select product image
     */
    body.on('click', '.vi-wad-product-image', function () {
        let image_src = $(this).find('.vi-wad-import-data-image').attr('src');
        let $container = $(this).closest('form');
        if (image_src) {
            let $gallery_item = $container.find('.vi-wad-product-gallery-image[data-image_src="' + image_src + '"]').closest('.vi-wad-product-gallery-item');
            $gallery_item.find('.vi-wad-set-product-image').click();
        }
    });

    /**
     * Select default variation
     */
    body.on('click', '.vi-wad-import-data-variation-default', function () {
        let $current = $(this);
        if ($current.prop('checked')) {
            let $enable = $current.closest('tr').find('.vi-wad-variation-enable');
            if (!$enable.prop('checked')) {
                $enable.click();
            }
        }
    });

    /**
     * Select variation image
     */
    body.on('click', '.vi-wad-variation-image', function () {
        let $current = $(this);
        if ($current.hasClass('vi-wad-selected-item')) {
            $current.removeClass('vi-wad-selected-item').find('input[type="hidden"]').val('');
        } else {
            $current.addClass('vi-wad-selected-item').find('input[type="hidden"]').val($current.find('img').attr('src'));
            $current.closest('tr').find('.vi-wad-variation-enable').prop('checked', true);
        }
    });

    body.on('click', '.vi-wad-overlay', function () {
        vi_wad_set_price_hide()
    });
    body.on('click', '.vi-wad-modal-popup-close', function () {
        vi_wad_set_price_hide()
    });
    body.on('click', '.vi-wad-set-price-button-cancel', function () {
        vi_wad_set_price_hide()
    });

    body.on('change', '.vi-wad-set-price-amount', function () {
        let price = parseFloat($(this).val());
        if (isNaN(price)) {
            price = 0;
        }
        $(this).val(price);
    });
    body.on('click', '.vi-wad-set-price-button-set', function () {
        let button = $(this);
        let action = $('.vi-wad-set-price-action').val(),
            amount = parseFloat($('.vi-wad-set-price-amount').val());
        let editing = $('.vi-wad-set-price-editing');
        let container = editing.closest('table');
        let target_field;
        if (editing.data('set_price') === 'sale_price') {
            target_field = container.find('.vi-wad-import-data-variation-sale-price');
        } else {
            target_field = container.find('.vi-wad-import-data-variation-regular-price');
        }
        if (target_field.length > 0) {
            switch (action) {
                case 'set_new_value':
                    target_field.map(function () {
                        let $price = $(this), $row = $price.closest('.vi-wad-product-variation-row');
                        if (!$row.hasClass('vi-wad-variation-filter-inactive') && $row.find('.vi-wad-variation-enable').prop('checked')) {
                            $price.val(amount);
                        }
                    });
                    break;
                case 'increase_by_fixed_value':
                    target_field.map(function () {
                        let $price = $(this), $row = $price.closest('.vi-wad-product-variation-row'),
                            current_amount = parseFloat($price.val());
                        if (!$row.hasClass('vi-wad-variation-filter-inactive') && $row.find('.vi-wad-variation-enable').prop('checked')) {
                            $price.val(current_amount + amount);
                        }
                    });
                    break;
                case 'increase_by_percentage':
                    target_field.map(function () {
                        let $price = $(this), $row = $price.closest('.vi-wad-product-variation-row'),
                            current_amount = parseFloat($price.val());
                        if (!$row.hasClass('vi-wad-variation-filter-inactive') && $row.find('.vi-wad-variation-enable').prop('checked')) {
                            $price.val((1 + amount / 100) * current_amount);
                        }
                    });
                    break;
            }
        }
        container.find('.vi-wad-import-data-variation-profit').map(function () {
            let $profit = $(this), $row = $profit.closest('tr');
            if (!$row.hasClass('vi-wad-variation-filter-inactive') && $row.find('.vi-wad-variation-enable').prop('checked')) {
                let sale_price = $row.find('.vi-wad-import-data-variation-sale-price');
                let regular_price = $row.find('.vi-wad-import-data-variation-regular-price');
                let cost = $row.find('.vi-wad-import-data-variation-cost');
                let sale_price_v = parseFloat(sale_price.val()), regular_price_v = parseFloat(regular_price.val()),
                    cost_v = parseFloat(cost.html()), profit_v;
                if (sale_price_v) {
                    profit_v = roundResult(sale_price_v - cost_v);
                } else {
                    profit_v = roundResult(regular_price_v - cost_v);
                }
                $profit.html(profit_v);
            }
        });
        vi_wad_set_price_hide()
    });
    body.on('click', '.vi-wad-accordion-store-url', function (e) {
        e.stopPropagation();
    });
    body.on('click', '.vi-wad-lazy-load', function () {
        let $tab = $(this);
        let tab_data = $tab.data('tab');
        if (!$tab.hasClass('vi-wad-lazy-load-loaded')) {
            $tab.addClass('vi-wad-lazy-load-loaded');
            let $tab_data = $('.vi-wad-lazy-load-tab-data[data-tab="' + tab_data + '"]');
            $tab_data.find('img').map(function () {
                let image_src = $(this).data('image_src');
                if (image_src) {
                    $(this).attr('src', image_src);
                }
            })
        }
    });
    /**
     * Load variations dynamically
     */
    body.on('click', '.vi-wad-variations-tab-menu', function () {
        let $tab = $(this);
        let $overlay = $tab.closest('.vi-wad-accordion').find('.vi-wad-product-overlay');
        let tab_data = $tab.data('tab');
        let $tab_data = $('.vi-wad-variations-tab[data-tab="' + tab_data + '"]');
        let $variations_table = $tab_data.find('.vi-wad-variations-table');
        if (!$tab_data.hasClass('vi-wad-variations-tab-loaded')) {
            $overlay.removeClass('vi-wad-hidden');
            $.ajax({
                url: vi_wad_import_list_params.url,
                type: 'GET',
                dataType: 'JSON',
                data: {
                    action: 'vi_wad_load_variations_table',
                    _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                    product_id: $tab_data.data('product_id'),
                    product_index: tab_data.substr(11),
                },
                success: function (response) {
                    let variations_table;
                    if (response.status === 'success') {
                        $tab_data.addClass('vi-wad-variations-tab-loaded');
                        variations_table = response.data;
                        if (response.hasOwnProperty('split_option') && response.split_option) {
                            $variations_table.closest('.vi-wad-variations-tab').find('.vi-wad-button-split-container').html(response.split_option);
                        }
                    } else {
                        variations_table = `<div class="vi-ui negative message">${response.data}</div>`;
                    }
                    $variations_table.html(variations_table).find('.vi-ui.dropdown').dropdown({
                        fullTextSearch: true,
                        forceSelection: false,
                        selectOnKeydown: false
                    });
                },
                error: function (err) {
                    console.log(err);
                    $variations_table.html(`<div class="vi-ui negative message">ERROR</div>`);
                },
                complete: function () {
                    $overlay.addClass('vi-wad-hidden');
                }
            })
        }
    });

    function vi_wad_set_price_hide() {
        $('.vi-wad-set-price').removeClass('vi-wad-set-price-editing');
        $('.vi-wad-attributes-attribute-removing').removeClass('vi-wad-attributes-attribute-removing');
        $('.vi-wad-modal-popup-container').addClass('vi-wad-hidden');
        vi_wad_enable_scroll()
    }

    function vi_wad_set_price_show() {
        $('.vi-wad-modal-popup-container').removeClass('vi-wad-hidden');

        vi_wad_disable_scroll();
    }

    body.on('click', '.vi-wad-override-product-overlay', function () {
        vi_wad_override_product_hide()
    });
    body.on('click', '.vi-wad-override-product-options-close', function () {
        vi_wad_override_product_hide()
    });
    body.on('click', '.vi-wad-override-product-options-button-cancel', function () {
        vi_wad_override_product_hide()
    });

    function vi_wad_override_product_hide() {
        $('.vi-wad-override-product-options-container').addClass('vi-wad-hidden');
        found_items = [];
        check_orders = 0;
        vi_wad_enable_scroll()
    }

    function vi_wad_override_product_show($button_import) {
        let $container = $('.vi-wad-override-product-options-container');
        if ($button_import.hasClass('vi-wad-button-map-existing')) {
            $container.addClass('vi-wad-override-product-options-container-map-existing');
        } else {
            $container.removeClass('vi-wad-override-product-options-container-map-existing');
        }
        if ($button_import.hasClass('vi-wad-button-reimport')) {
            $container.addClass('vi-wad-override-product-options-container-reimport');
        } else {
            $container.removeClass('vi-wad-override-product-options-container-reimport');
        }
        $container.removeClass('vi-wad-hidden');
        $('.vi-wad-override-product-options-content-body-override-old').addClass('vi-wad-hidden');
        let $override_options = $('.vi-wad-override-product-options-content-body-option');
        if ($override_options.length > 0) {
            $override_options.removeClass('vi-wad-hidden');
        } else {
            $('.vi-wad-override-product-options-button-override').click();
        }
        found_items = [];
        check_orders = 0;
        vi_wad_disable_scroll();
    }

    function vi_wad_enable_scroll() {
        let scrollTop = parseInt($('html').css('top'));
        $('html').removeClass('vi_wad-noscroll');
        $('html,body').scrollTop(-scrollTop);
    }

    function vi_wad_disable_scroll() {
        if ($(document).height() > $(window).height()) {
            let scrollTop = ($('html').scrollTop()) ? $('html').scrollTop() : body.scrollTop(); // Works for Chrome, Firefox, IE...
            $('html').addClass('vi_wad-noscroll').css('top', -scrollTop);
        }
    }

    function roundResult(number) {
        let decNum = parseInt(vi_wad_import_list_params.decimals),
            temp = Math.pow(10, decNum);
        return Math.round(number * temp) / temp;
    }

    /**
     * Find replacements for current attributes values
     */
    $(document).on('click', '.vi-wad-switch-product-attributes-values', function () {
        let $button = $(this);
        let $container = $button.closest('.vi-wad-accordion');
        let $overlay = $container.find('.vi-wad-product-overlay');
        $overlay.removeClass('vi-wad-hidden');
        let product_id = $button.data('product_id');
        let product_index = $button.data('product_index');
        $button.addClass('loading');
        $.ajax({
            url: vi_wad_import_list_params.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'vi_wad_switch_product_attributes_values',
                _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                product_id: product_id,
                product_index: product_index,
            },
            success: function (response) {
                if (response.status === 'success' && response.data) {
                    $button.closest('.vi-wad-variations-tab').find('.vi-wad-table-fix-head').html(response.data).find('.vi-ui.dropdown').dropdown({
                        fullTextSearch: true,
                        forceSelection: false,
                        selectOnKeydown: false
                    });
                }
            },
            error: function (err) {
                console.log(err)
            },
            complete: function () {
                $button.removeClass('loading');
                $overlay.addClass('vi-wad-hidden');
            }
        })
    });
    /**
     * Get shipping info
     */
    $(document).on('change', 'select[name="vi_wad_shipping_info_company"]', function () {
        let $button = $(this);
        let $shipping_info = $button.closest('.vi-wad-shipping-info');
        let $container = $button.closest('.vi-wad-accordion');
        let $overlay = $container.find('.vi-wad-product-overlay');
        let product_type = $shipping_info.data('product_type');
        $overlay.removeClass('vi-wad-hidden');
        $.ajax({
            url: vi_wad_import_list_params.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'vi_wad_select_shipping',
                _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                country: '',
                company: $button.val(),
                product_id: $shipping_info.data('product_id'),
                product_index: $shipping_info.data('product_index'),
                product_type: product_type,
            },
            success: function (response) {
                if (response.status === 'success' && response.data) {
                    let $target_field;
                    if (product_type === 'variable') {
                        $target_field = $button.closest('.vi-wad-table-fix-head');
                    } else {
                        $target_field = $button.closest('.vi-wad-simple-product-price-field');
                    }
                    if ($target_field !== undefined) {
                        $target_field.html(response.data);
                        $target_field.find('.vi-ui.dropdown').dropdown({
                            fullTextSearch: true,
                            forceSelection: false,
                            selectOnKeydown: false
                        });
                    }
                }
            },
            error: function (err) {
                console.log(err)
            },
            complete: function () {
                $button.removeClass('loading');
                $overlay.addClass('vi-wad-hidden');
            }
        });
    });
    $(document).on('change', 'select[name="vi_wad_shipping_info_country"]', function () {
        let $button = $(this);
        let $shipping_info = $button.closest('.vi-wad-shipping-info');
        let $container = $button.closest('.vi-wad-accordion');
        let $overlay = $container.find('.vi-wad-product-overlay');
        let product_type = $shipping_info.data('product_type');
        $overlay.removeClass('vi-wad-hidden');
        $.ajax({
            url: vi_wad_import_list_params.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'vi_wad_select_shipping',
                _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                country: $button.val(),
                company: '',
                product_id: $shipping_info.data('product_id'),
                product_index: $shipping_info.data('product_index'),
                product_type: product_type,
            },
            success: function (response) {
                if (response.status === 'success' && response.data) {
                    let $target_field;
                    if (product_type === 'variable') {
                        $target_field = $button.closest('.vi-wad-table-fix-head');
                    } else {
                        $target_field = $button.closest('.vi-wad-simple-product-price-field');
                    }
                    if ($target_field !== undefined) {
                        $target_field.html(response.data);
                        $target_field.find('.vi-ui.dropdown').dropdown({
                            fullTextSearch: true,
                            forceSelection: false,
                            selectOnKeydown: false
                        });
                    }
                }
            },
            error: function (err) {
                console.log(err)
            },
            complete: function () {
                $button.removeClass('loading');
                $overlay.addClass('vi-wad-hidden');
            }
        });
    });

    function maybe_hide_bulk_actions() {
        let $check = $('.vi-wad-accordion-bulk-item-check'),
            $bulk_actions = $('.vi-wad-accordion-bulk-actions-container');
        if ($bulk_actions.css('display') !== 'none') {
            if ($check.length > 0) {
                let check = 0;
                $check.map(function () {
                    if ($(this).prop('checked')) {
                        check++;
                    }
                });
                if (check === 0) {
                    $bulk_actions.fadeOut(200);
                }
            } else {
                $bulk_actions.fadeOut(200);
            }
        }
    }

    function maybe_reload_page() {
        if ($('.vi-wad-accordion').length === 0) {
            let url = new URL(document.location.href);
            url.searchParams.delete('vi_wad_search_id');
            url.searchParams.delete('vi_wad_search');
            url.searchParams.delete('paged');
            document.location.href = url.href;
        }
    }

    /*Edit attributes*/
    $(document).on('click', '.vi-wad-attributes-button-save', function () {
        let $button = $(this),
            $container = $button.closest('.vi-wad-accordion'),
            $row = $button.closest('tr'),
            change = 0,
            $attribute_values = $row.find('.vi-wad-attributes-attribute-value'),
            $slug = $row.find('.vi-wad-attributes-attribute-slug'),
            $overlay = $container.find('.vi-wad-product-overlay'),
            $name = $row.find('.vi-wad-attributes-attribute-name');

        if (!$name.val()) {
            alert(vi_wad_import_list_params.i18n_empty_attribute_name);
            return;
        }

        if ($name.val() !== $name.data('attribute_name')) {
            change++;
        }

        let attribute_values = [];
        $attribute_values.map(function () {
            let attribute_value = $(this).val();
            if (attribute_value !== $(this).data('attribute_value')) {
                change++;
            }
            attribute_value = attribute_value.toLowerCase().trim();
            if (attribute_value && -1 === attribute_values.indexOf(attribute_value)) {
                attribute_values.push(attribute_value);
            }
        });

        if (attribute_values.length !== $attribute_values.length) {
            alert(vi_wad_import_list_params.i18n_invalid_attribute_values);
            return;
        }

        if (change > 0) {
            $overlay.removeClass('vi-wad-hidden');
            $.ajax({
                url: vi_wad_import_list_params.url,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'vi_wad_save_attributes',
                    _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                    form_data: $row.find('input').serialize(),
                },
                success: function (response) {
                    if (response.status === 'success') {
                        let need_update_variations = false;
                        if (response.new_slug) {
                            need_update_variations = true;
                            $slug.html(response.new_slug);
                            $name.data('attribute_name', $name.val());
                        }
                        if (response.change_value === true) {
                            need_update_variations = true;
                            $row.find('.vi-wad-attributes-attribute-value').map(function () {
                                let $attribute_value = $(this);
                                $attribute_value.data('attribute_value', $attribute_value.val());
                            });
                        }
                        if (need_update_variations) {
                            let $variations_tab = $container.find('.vi-wad-variations-tab');
                            $variations_tab.removeClass('vi-wad-variations-tab-loaded');
                            $variations_tab.find('.vi-wad-variations-table').html('');
                        }
                    }
                },
                error: function (err) {
                    console.log(err)
                },
                complete: function () {
                    $button.removeClass('loading');
                    $overlay.addClass('vi-wad-hidden');
                    // $row.removeClass('vi-wad-attributes-attribute-editing');
                    // $row.find('input').prop('readonly', true);
                }
            })
        } else {
            $row.find('.vi-wad-attributes-button-cancel').click();
        }
    });

    $(document).on('click', '.vi-wad-attributes-button-edit', function () {
        let $button = $(this), $column = $button.closest('td'), $row = $button.closest('tr');
        $row.addClass('vi-wad-attributes-attribute-editing');
        $row.find('input').prop('readonly', false);
        $row.find('.vi-wad-attributes-attribute-name').focus();
    });
    $(document).on('click', '.vi-wad-attributes-button-cancel', function () {
        let $button = $(this), $row = $button.closest('tr'),
            $name = $row.find('.vi-wad-attributes-attribute-name');
        $name.val($name.data('attribute_name'));
        $row.find('.vi-wad-attributes-attribute-value').map(function () {
            let $attribute_value = $(this);
            $attribute_value.val($attribute_value.data('attribute_value'));
        });
        $row.removeClass('vi-wad-attributes-attribute-editing');
        $button.closest('tr').find('input').prop('readonly', true);
    });
    /*Switch tmce when opening Description tab*/
    body.on('click', '.vi-wad-description-tab-menu', function () {
        $(`.vi-wad-description-tab[data-tab="${$(this).data('tab')}"]`).find('.switch-tmce').click();
    });
    /*Show/hide button set variation image*/
    body.on('click', '.vi-wad-gallery-tab-menu', function () {
        let $button = $(this),
            $container = $button.closest('.vi-wad-accordion'),
            $variations_tab = $container.find('.vi-wad-variations-tab'),
            $variation_count = $container.find('.vi-wad-selected-variation-count'),
            $product_gallery = $container.find('.vi-wad-product-gallery');
        if ($variation_count.length > 0 && $variations_tab.hasClass('vi-wad-variations-tab-loaded')) {
            if (parseInt($variation_count.html()) > 0) {
                $product_gallery.addClass('vi-wad-allow-set-variation-image');
            } else {
                $product_gallery.removeClass('vi-wad-allow-set-variation-image');
            }
        }
    });
    /*Set variation image*/
    body.on('click', '.vi-wad-set-variation-image', function (e) {
        e.stopPropagation();
        let $button = $(this),
            $container = $button.closest('.vi-wad-accordion'),
            $rows = $container.find('.vi-wad-product-variation-row').not('.vi-wad-variation-filter-inactive'),
            image_src = $button.closest('.vi-wad-product-gallery-item').find('.vi-wad-product-gallery-image').data('image_src');
        if (image_src && $rows.length > 0) {
            $rows.map(function () {
                let $row = $(this);
                if ($row.find('.vi-wad-variation-enable').prop('checked')) {
                    let $image_container = $row.find('.vi-wad-variation-image');
                    let $image_input = $image_container.find('input[type="hidden"]');
                    $image_container.find('.vi-wad-import-data-variation-image').attr('src', image_src).attr('image_src', image_src);
                    if ($image_input.val()) {
                        $image_input.val(image_src)
                    }
                }
            });
            villatheme_admin_show_message('Image is set for selected variations', 'success', '', false, 2000);
        }
    });
    /**
     * Remove an attribute
     */
    body.on('click', '.vi-wad-attributes-attribute-remove', function () {
        let $button = $(this);
        let $row = $button.closest('.vi-wad-attributes-attribute-row');
        $row.addClass('vi-wad-attributes-attribute-removing');
        let $container = $('.vi-wad-modal-popup-container');
        let $content = $('.vi-wad-modal-popup-select-attribute');
        $content.html($button.closest('.vi-wad-attributes-attribute-row').find('.vi-wad-attributes-attribute-values').html());
        $content.find('.vi-wad-attributes-attribute-value').addClass('vi-ui').addClass('button').addClass('mini');
        $container.attr('class', 'vi-wad-modal-popup-container vi-wad-modal-popup-container-remove-attribute');
        vi_wad_set_price_show();
        if ($content.find('.vi-wad-attributes-attribute-value').length === 1) {
            $content.find('.vi-wad-attributes-attribute-value').eq(0).click();
        }
    });
    body.on('click', '.vi-wad-modal-popup-select-attribute .vi-wad-attributes-attribute-value', function () {
        let $button = $(this),
            $overlay = $('.vi-wad-saving-overlay'),
            $row = $('.vi-wad-attributes-attribute-removing'),
            $container = $row.closest('.vi-wad-accordion'),
            $tab = $container.find('.vi-wad-product-tab'),
            tab_data = $tab.data('tab');
        $overlay.removeClass('vi-wad-hidden');
        $.ajax({
            url: vi_wad_import_list_params.url,
            type: 'POST',
            dataType: 'JSON',
            data: {
                action: 'vi_wad_remove_attribute',
                _vi_wad_ajax_nonce: _vi_wad_ajax_nonce,
                attribute_slug: $row.find('.vi-wad-attributes-attribute-slug').data('attribute_slug'),
                attribute_value: $button.data('attribute_value'),
                form_data: $row.find('input').serialize(),
                product_index: tab_data.substr(11),
            },
            success: function (response) {
                if (response.status === 'success') {
                    if ($container.find('.vi-wad-attributes-attribute-row').length > 1) {
                        $row.remove();
                        $container.find('.vi-wad-variations-tab').removeClass('vi-wad-variations-tab-loaded');
                    } else {
                        $container.find('.vi-wad-attributes-tab-menu').remove();
                        $container.find('.vi-wad-attributes-tab').remove();
                        $container.find('.vi-wad-variations-tab-menu').remove();
                        $container.find('.vi-wad-variations-tab').remove();
                        $container.find('.tabular.menu .item').eq(0).addClass('active');
                        $container.find('.vi-wad-product-tab').addClass('active');
                    }
                    if (response.html) {
                        $(response.html).insertAfter($container.find('.vi-wad-import-data-sku-status-visibility')).find('.vi-ui.dropdown').dropdown({
                            fullTextSearch: true,
                            forceSelection: false,
                            selectOnKeydown: false
                        });
                    }
                    villatheme_admin_show_message(response.message, response.status, '', false, 2000);
                } else {
                    villatheme_admin_show_message(response.message, response.status, '', false, 5000);
                }
            },
            error: function (err) {
                console.log(err);
                villatheme_admin_show_message('An error occurs', 'error', '', false, 5000);
            },
            complete: function () {
                $overlay.addClass('vi-wad-hidden');
                $('.vi-wad-attributes-attribute-editing').removeClass('vi-wad-attributes-attribute-editing');
                $('.vi-wad-overlay').click();
            }
        })
    });
    /*Bulk product*/
    body.on('click', '.vi-wad-accordion-bulk-item-check', function (e) {
        let $button = $(this), show_actions = false;
        e.stopPropagation();
        if ($button.prop('checked')) {
            show_actions = true;
        } else {
            let $checkbox = $('.vi-wad-accordion-bulk-item-check');
            if ($checkbox.length > 0) {
                for (let i = 0; i < $checkbox.length; i++) {
                    if ($checkbox.eq(i).prop('checked')) {
                        show_actions = true;
                        break;
                    }
                }
            }
        }
        if (show_actions) {
            $('.vi-wad-accordion-bulk-actions-container').fadeIn(200);
        } else {
            $('.vi-wad-accordion-bulk-actions-container').fadeOut(200);
            // $('select[name="vi_wad_bulk_actions"]').val('none').trigger('change');
            $('.vi-wad-accordion-bulk-actions').dropdown('clear');
        }
    });

    body.on('click', '.vi-wad-accordion-bulk-item-check-all', function (e) {
        let $button = $(this), $checkbox = $('.vi-wad-accordion-bulk-item-check');
        if ($button.prop('checked')) {
            if ($checkbox.length > 0) {
                $('.vi-wad-accordion-bulk-actions-container').fadeIn(200);
                $checkbox.prop('checked', true).trigger('change');
            }
        } else {
            $('.vi-wad-accordion-bulk-actions-container').fadeOut(200);
            // $('select[name="vi_wad_bulk_actions"]').val('none').trigger('change');
            $('.vi-wad-accordion-bulk-actions').dropdown('clear');
            $checkbox.prop('checked', false).trigger('change');
        }
    });

    $('select[name="vi_wad_bulk_actions"]').on('change', function () {
        let $action = $(this), action = $action.val(), $checkbox = $('.vi-wad-accordion-bulk-item-check');
        if ($checkbox.length > 0 && action !== '') {
            switch (action) {
                case 'set_status_publish':
                case 'set_status_pending':
                case 'set_status_draft':
                    let status = action.replace('set_status_', '');
                    $checkbox.map(function () {
                        let $button = $(this);
                        if ($button.prop('checked')) {
                            let $container = $button.closest('.vi-wad-accordion'),
                                $status = $container.find('.vi-wad-import-data-status');
                            if ($status.length > 0) {
                                $status.find('select').val(status).trigger('change');
                            }
                        }
                    });
                    break;
                case 'set_visibility_visible':
                case 'set_visibility_catalog':
                case 'set_visibility_search':
                case 'set_visibility_hidden':
                    let visibility = action.replace('set_visibility_', '');
                    $checkbox.map(function () {
                        let $button = $(this);
                        if ($button.prop('checked')) {
                            let $container = $button.closest('.vi-wad-accordion'),
                                $visibility = $container.find('.vi-wad-import-data-catalog-visibility');
                            if ($visibility.length > 0) {
                                $visibility.find('select').val(visibility).trigger('change');
                            }
                        }
                    });
                    break;
                case 'set_tags':
                case 'set_categories':
                    let taxonomy = action.replace('set_', '');
                    let $container = $('.vi-wad-modal-popup-container');
                    $container.attr('class', `vi-wad-modal-popup-container vi-wad-modal-popup-container-set-${taxonomy}`);
                    vi_wad_set_price_show();
                    break;
                case 'import':
                    if (confirm(vi_wad_import_list_params.i18n_bulk_import_product_confirm)) {
                        $checkbox.map(function () {
                            let $button = $(this);
                            if ($button.prop('checked')) {
                                let $container = $button.closest('.vi-wad-accordion');
                                $container.find('.vi-wad-button-import').not('.vi-wad-hidden').click();
                                // $container.find('.vi-wad-button-override').not('.vi-wad-hidden').click();
                            }
                        });
                    }
                    break;
                case 'remove':
                    if (confirm(vi_wad_import_list_params.i18n_bulk_remove_product_confirm)) {
                        is_bulk_remove = true;
                        $checkbox.map(function () {
                            let $button = $(this);
                            if ($button.prop('checked')) {
                                let $container = $button.closest('.vi-wad-accordion');
                                $container.find('.vi-wad-button-remove').click();
                            }
                        });
                        is_bulk_remove = false;
                    }
                    break;
            }
            $('.vi-wad-accordion-bulk-actions').dropdown('clear');
            // setTimeout(function () {
            //     $action.val('none').trigger('change');
            // }, 100)
        }
    });
    body.on('click', '.vi-wad-set-categories-button-set', function () {
        let $checkbox = $('.vi-wad-accordion-bulk-item-check'),
            $new_categories = $('select[name="vi_wad_bulk_set_categories"]'), new_categories = $new_categories.val();
        $checkbox.map(function () {
            let $button = $(this);
            if ($button.prop('checked')) {
                let $container = $button.closest('.vi-wad-accordion'),
                    $categories = $container.find('.vi-wad-import-data-categories');
                if ($categories.length > 0) {
                    $categories.dropdown('set exactly', new_categories);
                }
            }
        });
        vi_wad_set_price_hide();
    });
    body.on('click', '.vi-wad-set-categories-button-add', function () {
        let $checkbox = $('.vi-wad-accordion-bulk-item-check'),
            $new_categories = $('select[name="vi_wad_bulk_set_categories"]'), new_categories = $new_categories.val();
        if (new_categories.length > 0) {
            $checkbox.map(function () {
                let $button = $(this);
                if ($button.prop('checked')) {
                    let $container = $button.closest('.vi-wad-accordion'),
                        $categories = $container.find('.vi-wad-import-data-categories');
                    if ($categories.length > 0) {
                        $categories.dropdown('set exactly', [...new Set(new_categories.concat($categories.dropdown('get values')))]);
                    }
                }
            });
        }
        vi_wad_set_price_hide();
    });
    body.on('click', '.vi-wad-set-categories-button-cancel', function () {
        vi_wad_set_price_hide();
    });
    body.on('click', '.vi-wad-set-tags-button-set', function () {
        let $checkbox = $('.vi-wad-accordion-bulk-item-check'),
            $new_tags = $('select[name="vi_wad_bulk_set_tags"]'), new_tags = $new_tags.val();
        $checkbox.map(function () {
            let $button = $(this);
            if ($button.prop('checked')) {
                let $container = $button.closest('.vi-wad-accordion'),
                    $tags = $container.find('.vi-wad-import-data-tags');
                if ($tags.length > 0) {
                    $tags.dropdown('set exactly', new_tags);
                }
            }
        });
        vi_wad_set_price_hide();
    });
    body.on('click', '.vi-wad-set-tags-button-add', function () {
        let $checkbox = $('.vi-wad-accordion-bulk-item-check'),
            $new_tags = $('select[name="vi_wad_bulk_set_tags"]'), new_tags = $new_tags.val();
        if (new_tags.length > 0) {
            $checkbox.map(function () {
                let $button = $(this);
                if ($button.prop('checked')) {
                    let $container = $button.closest('.vi-wad-accordion'),
                        $tags = $container.find('.vi-wad-import-data-tags');
                    if ($tags.length > 0) {
                        $tags.dropdown('set exactly', [...new Set(new_tags.concat($tags.dropdown('get values')))]);
                    }
                }
            });
        }
        vi_wad_set_price_hide();
    });
    body.on('click', '.vi-wad-set-tags-button-cancel', function () {
        vi_wad_set_price_hide();
    });
    body.on('click', '.vi-wad-modal-popup-set-categories-clear', function () {
        $(this).parent().find('.vi-wad-modal-popup-set-categories-select').dropdown('clear')
    });
    body.on('click', '.vi-wad-modal-popup-set-tags-clear', function () {
        $(this).parent().find('.vi-wad-modal-popup-set-tags-select').dropdown('clear')
    });


    /*Find product to import via API*/
    let msgBoxes = $('<div class="ald-message-box"></div>');
    let findProductModal = $('#ald-find-product-modal').modal({autofocus: false,});

    body.append(msgBoxes);

    let importing = false;

    window.aldAddToImportList = function (product_id,country,$importBtn) {
        if (importing) return;
        importing = true;
        $.ajax({
            url: vi_wad_import_list_params.url,
            type: 'post',
            dataType: 'json',
            data: {
                action: 'vi_wad_add_to_import_list',
                nonce: _vi_wad_ajax_nonce,
                product_id: product_id,
                country: country
            },
            success(response) {
                console.log(response)
                if (response?.message){
                    villatheme_admin_show_message(response.message, response.status, '', false, 5000);
                }
                if (response?.status === 'success') {
                    if (response?.import_list) {
                        tinymce.remove();
                        $('.wrap.woo-alidropship-import-list').replaceWith(response.import_list);
                        for (let id in response.mce_init) {
                            tinyMCEPreInit.mceInit[id] = (new Function('return ' + response.mce_init[id]))();
                            tinyMCEPreInit.qtInit[id] = (new Function('return ' + response.qt_init[id]))();
                        }
                        init();
                    }
                }
            },
            complete() {
                importing = false;
                $importBtn.find('.loading').removeClass('spinner loading');
                $importBtn.addClass('disabled');
            }
        });
    };
    $('.ald-ship-to-country').dropdown({
        onChange:function (val) {
            if (val ==='RU'){
                villatheme_admin_show_message('Notice', 'viwad_shipping_warning','Shipping information for the product will not be retrieved when you import here. If you wish to obtain shipping information, please visit AliExpress and use our Chrome extension to import.', '', false, 50000);
            }else {
                $('.villatheme-admin-show-message-message-viwad_shipping_warning .villatheme-admin-show-message-message-item-close').trigger('click');
            }
        }
    })
    body.on('click', '.ald-open-find-product-modal', function () {
        findProductModal.modal('show');
        findProductModal.find('.ald-keyword').trigger('focus');
        $('.villatheme-admin-show-message-message-viwad_shipping_warning .villatheme-admin-show-message-message-item-close').trigger('click');
        if ($('.ald-ship-to-country').dropdown('get value') ==='RU'){
            villatheme_admin_show_message('Notice', 'viwad_shipping_warning','Shipping information for the product will not be retrieved when you import here. If you wish to obtain shipping information, please visit AliExpress and use our Chrome extension to import.', '', false, 50000);
        }
    });

    body.on('submit', '.ald-search-product-form', function (e, data) {
        e.preventDefault();
        let keyWordField = $(this).find('input.ald-keyword'),
            keyWord = keyWordField.val(),
            keyWordErrorField = $('.ald-keyword-error');

        keyWordErrorField.hide();

        if (!keyWord) {
            keyWordErrorField.show();
            return;
        }

        let searchBtn = $('.ald-search-button'),
            formData = $(this).serialize();

        searchBtn.addClass('loading');
        let sendData = `action=ald_search_product&nonce=${_vi_wad_ajax_nonce}&` + formData;
        sendData += data?.paged ? `&paged=${data.paged}` : '';

        $.ajax({
            url: vi_wad_import_list_params.url,
            type: 'POST',
            // dataType: 'JSON',
            data: sendData,
            success: function (response) {
                if (response.success) {
                    $('.ald-search-result').html(response.data.products);
                    $('.ald-pagination-wrapper').html(response.data.pagination);
                }
            },
            error: function (err) {
                console.log(err);
            },
            complete: function () {
                searchBtn.removeClass('loading');
            }
        })

    });

    body.on('click', '.ald-add-to-import-list', function (e) {
        e.preventDefault();
        if (importing){
            villatheme_admin_show_message('Another product is being imported. Please wait then try again.', 'notice', '', false, 5000);
            return;
        }
        let $thisBtn = $(this);
        let pid = $thisBtn.data('product_id');
        $thisBtn.find('i.icon').addClass('spinner loading');
        aldAddToImportList(pid, $('.ald-ship-to-country').dropdown('get value'), $thisBtn);
    });

    body.on('click', '.ald-pagination a:not(.active)', function () {
        let paged = $(this).data('paged');
        $('.ald-search-product-form').trigger('submit', {paged});
    });

    if (location.hash === '#aldShowModal') {
        let currentURL = new URL(window.location);
        currentURL.hash = '';
        history.replaceState(null, document.title, currentURL);

        if (!$('div[id^=vi-wad-product-item-id-]').length) {
            $('.ald-open-find-product-modal').trigger('click');
        }
    }

});

