jQuery(document).ready(function ($) {
    'use strict';
    if (pagenow === 'edit-product') {
        $('.page-title-action:first').after(`<a href="${vi_wad_admin_product_params.import_page_url}#aldShowModal" class="page-title-action button-primary" style="margin-left: 4px;">Find AliExpress product to import</a>`);
        $('.woocommerce-BlankState-buttons').append(`<a href="${vi_wad_admin_product_params.import_page_url}#aldShowModal" class="woocommerce-BlankState-cta button" style="margin-left: 4px;background-color: #2271b1; color: white;">Find AliExpress product to import</a>`);
    } else {
        $(document).on('change', '#product-type', function () {
            let $simple_attributes = $('.vi-wad-original-attributes-simple');
            if ($(this).val() === 'variable') {
                $simple_attributes.fadeOut(200)
            } else {
                $simple_attributes.fadeIn(200)
            }
        }).trigger('change');
        $(document).on('change', '.vi-wad-original-attributes-select', function () {
            let $sku_attr = $(this);
            $sku_attr.closest('.vi-wad-original-attributes').find('.vi-wad-original-sku-id').val($sku_attr.find(`option[value="${$sku_attr.val()}"]`).data('vi_wad_sku_id'))
        });
    }
});