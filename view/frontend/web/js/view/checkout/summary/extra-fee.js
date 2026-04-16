define([
    'Magento_Checkout/js/view/summary/abstract-total',
    'Magento_Checkout/js/model/totals',
    'ko'
], function (Component, totals, ko) {
    'use strict';

    return Component.extend({
        defaults: {
            template: 'Panth_ExtraFee/checkout/summary/extra-fee'
        },

        /**
         * Get all extra fee segments as an observable array.
         */
        getExtraFees: function () {
            var segments = totals.totals().total_segments || [];
            var fees = [];

            for (var i = 0; i < segments.length; i++) {
                if (segments[i].code &&
                    segments[i].code.indexOf('panth_extra_fee_') === 0 &&
                    parseFloat(segments[i].value) > 0) {
                    fees.push({
                        title: segments[i].title || 'Extra Fee',
                        value: this.getFormattedPrice(parseFloat(segments[i].value))
                    });
                }
            }

            return fees;
        },

        /**
         * Check if any extra fees exist.
         */
        isDisplayed: function () {
            return this.getExtraFees().length > 0;
        }
    });
});
