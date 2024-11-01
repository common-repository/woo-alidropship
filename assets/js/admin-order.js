'use strict';
jQuery(document).ready(function ($) {
    $('.vi-wad-item-actions-edit').on('click', function () {
        let $button = $(this);
        $button.addClass('vi-wad-hidden');
        let $item_container = $button.closest('.vi-wad-item-details');
        $item_container.addClass('vi-wad-ali-order-id-editing');
        let $ali_order_id = $item_container.find('.vi-wad-ali-order-id');
        let $ali_order_id_input = $ali_order_id.find('.vi-wad-ali-order-id-input');
        $ali_order_id_input.prop('readonly', false).focus();
        $item_container.find('.vi-wad-item-actions-save').removeClass('vi-wad-hidden');
        $item_container.find('.vi-wad-item-actions-cancel').removeClass('vi-wad-hidden');
    });
    $('.vi-wad-item-actions-cancel').on('click', function () {
        let $button = $(this);
        $button.addClass('vi-wad-hidden');
        let $item_container = $button.closest('.vi-wad-item-details');
        $item_container.removeClass('vi-wad-ali-order-id-editing');
        let $ali_order_id = $item_container.find('.vi-wad-ali-order-id');
        let $ali_order_id_input = $ali_order_id.find('.vi-wad-ali-order-id-input');
        $ali_order_id_input.prop('readonly', true).val($ali_order_id.data('old_ali_order_id'));
        $item_container.find('.vi-wad-item-actions-edit').removeClass('vi-wad-hidden');
        $item_container.find('.vi-wad-item-actions-save').addClass('vi-wad-hidden');
    });
    $('.vi-wad-item-actions-save').on('click', function () {
        let $button = $(this);
        let $td = $(this).closest('td');
        let $container = $button.closest('.vi-wad-container');
        let $orders_tracking_container = $td.find('.woo-orders-tracking-container');
        let $item_container = $button.closest('.vi-wad-item-details');
        let item_id = $item_container.data('product_item_id');
        let $overlay = $container.find('.vi-wad-item-value-overlay');
        let $ali_order_id = $item_container.find('.vi-wad-ali-order-id');
        let $ali_order_id_input = $ali_order_id.find('.vi-wad-ali-order-id-input');
        let $tracking_number = $container.find('.vi-wad-ali-tracking-number');
        let $tracking_number_input = $container.find('.vi-wad-ali-tracking-number-input');
        let $get_tracking = $container.find('.vi-wad-item-actions-get-tracking');
        let ali_order_id = $ali_order_id_input.val();
        let old_ali_order_id = $ali_order_id.data('old_ali_order_id');
        if (ali_order_id == old_ali_order_id) {
            $('.vi-wad-item-actions-cancel').click();
        } else {
            $overlay.removeClass('vi-wad-hidden');
            $.ajax({
                url: vi_wad_edit_order.url,
                type: 'POST',
                dataType: 'JSON',
                data: {
                    action: 'vi_wad_manually_update_ali_order_id',
                    _vi_wad_ajax_nonce: vi_wad_edit_order._vi_wad_ajax_nonce,
                    item_id: item_id,
                    ali_order_id: ali_order_id,
                },
                success: function (response) {
                    if (response.status === 'success') {
                        $button.addClass('vi-wad-hidden');
                        $ali_order_id_input.prop('readonly', true);
                        $item_container.find('.vi-wad-item-actions-edit').removeClass('vi-wad-hidden');
                        $item_container.find('.vi-wad-item-actions-cancel').addClass('vi-wad-hidden');
                        $overlay.addClass('vi-wad-hidden');
                        $tracking_number_input.val('');
                        let href = '';
                        if (ali_order_id) {
                            $tracking_number.attr('href', 'http://track.aliexpress.com/logisticsdetail.htm?tradeId=ali_order_id');
                            $get_tracking.removeClass('vi-wad-hidden');
                            href = 'https://trade.aliexpress.com/order_detail.htm?orderId=' + ali_order_id;
                        } else {
                            $tracking_number.attr('href', '');
                            $get_tracking.addClass('vi-wad-hidden');
                        }
                        $ali_order_id.data('old_ali_order_id', ali_order_id).attr('href', href);
                        $item_container.removeClass('vi-wad-ali-order-id-editing');
                        if (response.delete_tracking === 'yes') {
                            $tracking_number_input.val('');
                            $orders_tracking_container.find('.woo-orders-tracking-item-tracking-code-value a').html('');
                            let $button_edit_tracking=$orders_tracking_container.find('.woo-orders-tracking-button-edit');
                            $button_edit_tracking.data('tracking_code','');
                            $button_edit_tracking.data('tracking_url','');
                            $button_edit_tracking.data('carrier_name','');
                            $button_edit_tracking.data('carrier_id','');
                            $orders_tracking_container.find('.woo-orders-tracking-item-tracking-button-add-to-paypal-container').removeClass('woo-orders-tracking-paypal-active').addClass('woo-orders-tracking-paypal-inactive');
                        }
                    } else {
                        alert(response.message);
                    }
                    $overlay.addClass('vi-wad-hidden');
                },
                error: function (err) {
                    console.log(err);
                    $overlay.addClass('vi-wad-hidden');
                },
            });
        }

    });
    $('.vi-wad-ali-tracking-number').on('click', function (e) {
        if (!$(this).attr('href')) {
            e.preventDefault();
            return false;
        }
    });
    $('.vi-wad-ali-order-id').on('click', function (e) {
        let $ali_order_id = $(this);
        let $item_container = $ali_order_id.closest('.vi-wad-item-details');
        let $button_edit = $item_container.find('.vi-wad-item-actions-edit');
        if (!$(this).attr('href') || $button_edit.hasClass('vi-wad-hidden')) {
            e.preventDefault();
            return false;
        }
    })
});