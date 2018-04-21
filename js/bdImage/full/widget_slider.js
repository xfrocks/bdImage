/* global jQuery, XenForo */
/* jslint -W030 */
!function ($) {
    'use strict';

    XenForo.bdImage_Widget_Slider_Container = function ($container) {
        this.__construct($container);
    };

    XenForo.bdImage_Widget_Slider_Container.prototype = {
        __construct: function ($container) {
            var $items = $container.find('.bdImage_Widget_Slider_Items'),
                layout = $container.data('layout'),
                layoutOptions = $container.data('layoutOptions'),
                thumbnailWidth = $container.data('thumbnailWidth');

            if (layout === 'bxslider') {
                var bxsliderOptions = this.buildOptions({
                    controls: false,
                    infiniteLoop: true,
                    pager: true
                }, layoutOptions);

                // noinspection JSUnresolvedFunction
                $items.bxSlider(bxsliderOptions);
            }

            if (layout === 'owlcarousel') {
                var owlcarouselOptions = this.buildOptions({
                    dots: true,
                    items: 1,
                    loop: true,
                    margin: 10,
                    responsiveClass: true,
                    responsive: {}
                }, layoutOptions);

                if (owlcarouselOptions.responsiveClass === true &&
                    JSON.stringify(owlcarouselOptions.responsive) === '{}') {
                    owlcarouselOptions.responsive[0] = {
                        items: 1,
                        autoWidth: false
                    };
                    owlcarouselOptions.responsive[thumbnailWidth] = {
                        items: 2,
                        autoWidth: false
                    };

                    owlcarouselOptions.responsive[thumbnailWidth * 2] = {
                        items: 2,
                        autoWidth: true
                    };
                }

                $items.addClass('owl-carousel owl-theme')
                    .owlCarousel(owlcarouselOptions);
            }
        },

        buildOptions: function (defaultOptions, layoutOptions) {
            // noinspection JSUnresolvedFunction
            var merged = $.extend({}, defaultOptions);

            for (var k in layoutOptions) {
                if (!layoutOptions.hasOwnProperty(k)) {
                    continue;
                }

                var v = layoutOptions[k];
                if (typeof v === 'string') {
                    if (v === 'false') {
                        v = false;
                    } else if (v === 'true') {
                        v = true;
                    } else if (v.match(/^[0-9]+$/)) {
                        v = parseInt(v);
                    }
                }

                merged[k] = v;
            }

            return merged;
        }
    };

    // *********************************************************************

    XenForo.register('.bdImage_Widget_Slider_Container', 'XenForo.bdImage_Widget_Slider_Container');

}(jQuery, this, document);
