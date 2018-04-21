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
                    auto: false,
                    autoControls: false,
                    autoControlsCombine: false,
                    autoHover: false,
                    captions: false,
                    controls: false,
                    infiniteLoop: true,
                    pager: false
                }, layoutOptions);

                if (bxsliderOptions.auto) {
                    if (bxsliderOptions.controls) {
                        bxsliderOptions.autoControls = true;
                        bxsliderOptions.autoControlsCombine = true;
                    }

                    bxsliderOptions.autoHover = true;
                }

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
            return $.extend({}, defaultOptions, layoutOptions);
        }
    };

    // *********************************************************************

    XenForo.register('.bdImage_Widget_Slider_Container', 'XenForo.bdImage_Widget_Slider_Container');

}(jQuery, this, document);
