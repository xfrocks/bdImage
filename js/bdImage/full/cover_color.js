/* global ColorThief, jQuery, XenForo */
/* jshint -W030 */
!function ($, window) {
    'use strict';

    XenForo.bdImage_CoverColorAutoPicker = function ($container) {
        this.__construct($container);
    };

    XenForo.bdImage_CoverColorAutoPicker.prototype = {

        __construct: function ($container) {
            this.$container = $container;
            this.$demo = $container.find($container.data('coverColorDemoSelector'));
            this.$input = $container.find($container.data('coverColorInputSelector'));

            this.colorThief = null;
            if (typeof ColorThief === 'function') {
                this.colorThief = new ColorThief();
            }

            $container.find('input[type=radio]').change($.context(this, 'pickColor'));
        },

        pickColor: function () {
            var self = this,
                $radio = this.$container.find('input[name=bdimage_image]:checked'),
                $img = $radio.siblings('img'),
                callback = function ($img) {
                    if (self.colorThief) {
                        var color = self.colorThief.getColor($img[0]);
                        if (color && color[0] && color[1] && color[2]) {
                            var rgb = 'rgb(' + color[0] + ',' + color[1] + ',' + color[2] + ')';
                            self.$demo.css('background', rgb);
                            self.$input.val(rgb);
                        }
                    }
                };

            if ($img.length === 1) {
                callback($img);
            }
        }
    };

    // *********************************************************************

    XenForo.register('.bdImagePicker', 'XenForo.bdImage_CoverColorAutoPicker');

}(jQuery, this);