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
                src = $img.prop('src'),
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

            if (!src) {
                return;
            }

            this.loadBlob(src, function (err, $blobImg) {
                if (!err) {
                    return callback($blobImg);
                }

                callback($img);
            });
        },

        loadBlob: function (thumbnailUrl, callback) {
            var self = this,
                URL = window.URL || window.webkitURL,
                xhr = new XMLHttpRequest();

            if (!URL) {
                // https://developer.mozilla.org/en-US/docs/Web/API/Window/URL
                return callback('No window.URL');
            }

            xhr.open('GET', thumbnailUrl, true);
            xhr.responseType = 'blob';

            xhr.onload = function () {
                if (this.status === 200) {
                    var blob = this.response,
                        objectUrl = URL.createObjectURL(blob),
                        $img = $('<img />');

                    $img.on('load', function () {
                        callback(null, $img);
                    }).attr('src', objectUrl);
                } else {
                    callback('xhr.status = ' + this.status);
                }
            };

            xhr.onerror = function () {
                callback('xhr.onerror');
            };

            xhr.send();
        }
    };

    // *********************************************************************

    XenForo.register('.bdImagePicker', 'XenForo.bdImage_CoverColorAutoPicker');

}(jQuery, this);