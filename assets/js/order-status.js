'use strict';
jQuery(document).ready(function ($) {

    $('.column-vi_wad_ali_order').on('click', function (e) {
        e.stopPropagation();
    })
    $('.wad-fulfill-button').on('click', function (e) {
        e.stopImmediatePropagation();
        let $this = $(this);
        setTimeout(function () {
            $this.attr({'href': 'javascript:void(0)', 'target': ''}).css('color', '#aaa');
        }, 1)
    });

    // $('.wad-fulfill-button').on('mouseenter', function () {
    //     console.log($(this).position());
    // });

    const orderDisplay = {
        run: function () {
            this.display();
            this.changeColspan();
        },

        display: function () {
            $('.wad-show-detail').on('click', function (e) {
                e.stopImmediatePropagation();
                let $this = $(this),
                    tr = $this.closest('tr'),
                    tdColspan = $('#adv-settings input[type="checkbox"]:checked').length + 1,
                    id = $this.attr('data-id'),
                    arrowIcon = $this.find('.wad-icon'),
                    running = $this.hasClass('running'),
                    check = $('#wad-detail-' + id);

                if (check.length) {
                    check.remove();
                } else {
                    if (!running) {
                        $this.addClass('running');
                        $.ajax({
                            url: orderStt.ajaxUrl,
                            type: 'post',
                            data: {
                                action: 'vi_wad_ali_order_detail',
                                _vi_wad_ajax_nonce: orderStt._vi_wad_ajax_nonce,
                                id: id
                            },
                            success: function (res) {
                                if (res.success) {
                                    let html = res.data;
                                    tr.after(`<tr id="wad-detail-${id}" class="wad-order-detail-row" style="display: none"><td class="wad-colspan" colspan="${tdColspan}"><div class="wad-ali-order-detail">${html}</div></td></tr>`);
                                    $('#wad-detail-' + id).show("slow");
                                    orderAction.run();
                                }
                            },
                            error: function (res) {
                                console.log(res);
                            },
                            beforeSend: function () {
                                $this.find('.wad-spinner').addClass('spinner is-active');
                            },
                            complete: function () {
                                $this.removeClass('running');
                                $this.find('.wad-spinner').removeClass('spinner is-active');
                            }
                        });
                    }
                }
                arrowIcon.toggleClass('dashicons-arrow-up dashicons-arrow-down');
            });
        },

        changeColspan: function () {
            $('#adv-settings input[type=checkbox]').on('change', function () {
                let tdColspan = $('#adv-settings input[type="checkbox"]:checked').length + 1;
                $('.wad-colspan').attr('colspan', tdColspan);
            });
        }
    };


    const orderAction = {
        currentOrderID: '',
        run: function () {
            this.editOrderID();
            this.saveOrderID();
        },
        editOrderID: function () {
            $('body').on('click', '.wad-icon.dashicons.dashicons-edit', function () {
                let $this = $(this);
                $this.hide();
                let td = $this.closest('td');
                this.currentOrderID = td.find('.wad-ali-order-id').val();
                td.find('.wad-icon.dashicons.dashicons-yes, .wad-ali-order-id').show();
                td.find('.wad-ali-product-link').hide();
            });
        },
        saveOrderID: function () {
            $('body').on('click', '.wad-icon.dashicons.dashicons-yes', function () {
                let table = $(this).closest('table'),
                    data = {
                        action: 'vi_wad_manually_update_ali_order_id',
                        _vi_wad_ajax_nonce: orderStt._vi_wad_ajax_nonce,
                        item_id: table.attr('item-id'),
                        ali_order_id: table.find('.wad-ali-order-id').val()
                    };

                if (this.currentOrderID !== data.ali_order_id) {
                    $.ajax({
                        url: orderStt.ajaxUrl,
                        type: 'post',
                        data: data,
                        success: function (res) {
                            if (res.status === 'success') {
                                table.find('.wad-status').text(res.text[0]).css('background-color', res.text[1]);
                                table.find('.wad-ali-product-link').attr('href', `https://trade.aliexpress.com/order_detail.htm?orderId=${data.ali_order_id}`);
                                table.find('.wad-ali-product-link').text(data.ali_order_id);
                                table.find('.wad-get-tracking-code-manual').attr('href', `https://trade.aliexpress.com/orderList.htm?tradeId=${data.ali_order_id}&getTracking=manual`);
                            }
                        },
                        error: function (res) {
                        }
                    });
                }

                table.find('.wad-icon.dashicons.dashicons-edit, .wad-ali-product-link').show();
                table.find('.wad-icon.dashicons.dashicons-yes, .wad-ali-order-id').hide();
            });
        },
    };

    orderDisplay.run();
});