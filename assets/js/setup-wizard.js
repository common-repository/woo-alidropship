jQuery(document).ready(function ($) {
    'use strict';
    $('.vi-wad-copy-secretkey').on('click', function () {
        let $container = $(this).closest('td');
        $container.find('.vi-wad-secret-key').select();
        $container.find('.vi-wad-copy-secretkey-success').remove();
        document.execCommand('copy');
        let $result_icon = $('<span class="vi-wad-copy-secretkey-success dashicons dashicons-yes" title="Copied to Clipboard"></span>');
        $container.append($result_icon);
        $result_icon.fadeOut(10000);
        setTimeout(function () {
            $result_icon.remove();
        }, 5000);
    });
    $('.vi-wad-secret-key').on('click', function () {
        $(this).closest('.input').find('.vi-wad-copy-secretkey').click();
    });
    $('select.vi-ui.dropdown').dropdown();
    /*Add row*/
    $('.vi-wad-price-rule-add').on('click', function () {
        let $rows = $('.vi-wad-price-rule-row'),
            $lastRow = $rows.last(),
            $newRow = $lastRow.clone();
        $newRow.find('.vi-wad-price-from').val('');
        $newRow.find('.vi-wad-price-to').val('');
        $newRow.find('.vi-wad-plus-value-type').dropdown();
        $('.vi-wad-price-rule-container').append($newRow);
    });

    /*remove last row*/
    $(document).on('click', '.vi-wad-price-rule-remove', function () {
        let $button = $(this), $rows = $('.vi-wad-price-rule-row'),
            $row = $button.closest('.vi-wad-price-rule-row');
        if ($rows.length > 1) {
            if (confirm('Do you want to remove this row?')) {
                $row.fadeOut(300);
                setTimeout(function () {
                    $row.remove();
                }, 300)
            }
        }
    });

    let search_params = new URLSearchParams(window.location.href), setup_step = search_params.get('step');
    $('.vi-ui.button.primary').on('click', function () {
        if (setup_step == 2) {
            if (!$('#vi-wad-import-currency-rate').val()) {
                alert('Please enter Import products currency exchange rate');
                return false;
            }
        }
    });
    $(document).on('change', 'select[name="wad_plus_value_type[]"]', function () {
        change_price_label($(this));
    });
    $(document).on('change', 'select[name="wad_price_default[plus_value_type]"]', function () {
        change_price_label($(this));
    });

    function change_price_label($select) {
        let $current = $select.closest('tr');
        switch ($select.val()) {
            case 'fixed':
                $current.find('.vi-wad-value-label-left').html('+');
                $current.find('.vi-wad-value-label-right').html('$');
                break;
            case 'percent':
                $current.find('.vi-wad-value-label-left').html('+');
                $current.find('.vi-wad-value-label-right').html('%');
                break;
            case 'multiply':
                $current.find('.vi-wad-value-label-left').html('x');
                $current.find('.vi-wad-value-label-right').html('');
                break;
            default:
                $current.find('.vi-wad-value-label-left').html('=');
                $current.find('.vi-wad-value-label-right').html('$');
        }
    }

    $('#vi-wad-show-shipping-option').on('change', function () {
        let $dependency = $('#vi-wad-shipping-cost-after-price-rules').closest('tr');
        if ($(this).prop('checked')) {
            $dependency.fadeIn(200);
        } else {
            $dependency.fadeOut(200);
        }
    }).trigger('change');
});