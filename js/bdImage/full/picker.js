/* global ColorThief, jQuery, XenForo */
/* jshint -W030 */
!function ($, window) {
    'use strict';

    XenForo.bdImage_Picker = function ($container) {
        this.__construct($container);
    };

    XenForo.bdImage_Picker.prototype = {

        __construct: function ($container) {
            this.$container = $container;
            this.$coverColorDemo = $container.find($container.data('coverColorDemoSelector'));
            this.$coverColorInput = $container.find($container.data('coverColorInputSelector'));
            this.$currentImage = $container.find($container.data('currentImageSelector'));
            this.$select = $container.find('select[name=bdimage_image]');
            this.$other = $container.find('input[name=bdimage_other]');

            this.colorThief = null;
            if (typeof ColorThief === 'function') {
                this.colorThief = new ColorThief();
            }

            this.$select.change($.context(this, 'onImageChange'));
            this.onImageChange();
        },

        onImageChange: function () {
            var self = this,
                selectValue = this.$select.val(),
                doColorThief = function ($img) {
                    if (self.colorThief) {
                        var color = null;
                        try {
                            color = self.colorThief.getColor($img[0]);
                        } catch (e) {
                            console.log(e);
                        }

                        if (color && color[0] && color[1] && color[2]) {
                            var rgb = 'rgb(' + color[0] + ',' + color[1] + ',' + color[2] + ')';
                            self.$coverColorDemo.css('background', rgb);
                            self.$coverColorInput.val(rgb);
                        }
                    }
                },
                handleImg = function ($img) {
                    if (self.$coverColorInput.length !== 1) {
                        return;
                    }

                    doColorThief($img);
                };

            if (!selectValue) {
                return;
            }

            var selectedImage = selectValue;
            if (selectedImage === 'other') {
                selectedImage = this.$other.val();
            }
            if (!selectedImage) {
                return;
            }

            var previewUrl = selectedImage;
            this.$select.children().each(function () {
                var $option = $(this);
                if ($option.attr('value') === selectedImage) {
                    var optionPreviewUrl = $option.data('previewUrl');
                    if (optionPreviewUrl) {
                        previewUrl = optionPreviewUrl;
                        return false;
                    }
                }
            });

            this.loadBlob(previewUrl, function (err, $blobImg) {
                if (!err) {
                    return handleImg($blobImg);
                }

                self.loadImg(previewUrl);
            });
        },

        loadBlob: function (imageUrl, callback) {
            var self = this,
                URL = window.URL || window.webkitURL,
                xhr = new XMLHttpRequest();

            if (!URL) {
                // https://developer.mozilla.org/en-US/docs/Web/API/Window/URL
                return callback('No window.URL');
            }

            xhr.open('GET', imageUrl, true);
            xhr.responseType = 'blob';

            xhr.onload = function () {
                if (this.status === 200) {
                    var blob = this.response,
                        objectUrl = URL.createObjectURL(blob),
                        $img = $('<img />');

                    $img.on('load', function () {
                        callback(null, $img);
                    }).attr('src', objectUrl);

                    self.setCurrentImage($img);
                } else {
                    callback('xhr.status = ' + this.status);
                }
            };

            xhr.onerror = function () {
                callback('xhr.onerror');
            };

            xhr.send();
        },

        loadImg: function (imageUrl) {
            var $img = $('<img />').attr('src', imageUrl);
            this.setCurrentImage($img);
        },

        setCurrentImage: function ($img) {
            var $c = this.$currentImage;

            $c.css('min-height', $c.height() + 'px');
            $c.empty();
            $img.appendTo($c);
        }
    };

    // *********************************************************************

    XenForo.register('.bdImagePicker', 'XenForo.bdImage_Picker');

}(jQuery, this);