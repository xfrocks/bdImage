//noinspection ThisExpressionReferencesGlobalObjectJS
!function ($) {

    XenForo.bdImage_Widget_Slider_Container = function ($container) {
        this.__construct($container);
    };

    XenForo.bdImage_Widget_Slider_Container.prototype =
    {
        __construct: function ($container) {
            var layout = $container.data('layout');
            var layoutOptions = $container.data('layoutOptions');

            if (layout === 'owlcarousel') {
                var owlcarouselOptions =
                {
                    autoplay: false,
                    autoplayHoverPause: false,
                    center: false,
                    dots: false,
                    items: 1,
                    lazyLoad: true,
                    loop: true,
                    margin: 10,
                    nav: false
                };

                for (var i in layoutOptions) {
                    if (!layoutOptions.hasOwnProperty(i)) {
                        continue;
                    }

                    var typeOf = typeof owlcarouselOptions[i];

                    switch (typeOf) {
                        case 'boolean':
                            owlcarouselOptions[i] = (layoutOptions[i] > 0);
                            break;
                        case 'number':
                            owlcarouselOptions[i] = parseInt(layoutOptions[i]);
                            break;
                        default:
                            console.log(i, owlcarouselOptions[i], typeOf);
                    }
                }

                if (owlcarouselOptions.autoplay) {
                    owlcarouselOptions.autoplayHoverPause = true;
                }

                if (owlcarouselOptions.items === 1
                    && typeof layoutOptions['dots'] === 'undefined') {
                    owlcarouselOptions.dots = true;
                }
                if (!owlcarouselOptions.dots && !owlcarouselOptions.nav) {
                    owlcarouselOptions.dots = true;
                }

                $container.find('.bdImage_Widget_Slider_Items').owlCarousel(owlcarouselOptions);
            }
        }
    };

    // *********************************************************************

    XenForo.register('.bdImage_Widget_Slider_Container', 'XenForo.bdImage_Widget_Slider_Container');

}(jQuery, this, document);
