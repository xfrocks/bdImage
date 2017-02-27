!function ($, window) {

    XenForo.bdImage_StylizedGrid_Thread = function ($container) {
        this.__construct($container);
    };

    XenForo.bdImage_StylizedGrid_Thread.prototype = {
        __construct: function ($container) {
            this.$container = $container;

            this.author = $container.data('author');
            if (!this.author) {
                return;
            }

            this.threadTitle = $container.find('.thread-title').text();
            if (!this.threadTitle) {
                return;
            }

            var snippet = $container.data('snippet');
            if (!snippet) {
                return;
            }
            snippet = snippet.trim();
            if (snippet.length === 0) {
                return;
            }

            var $snippet = $('<div />').html(snippet);
            var $iframe = $snippet.find('iframe');
            if ($iframe.length === 0) {
                $iframe = $snippet.find('div.iframe-container-compatible').children();
            }
            if ($iframe.length !== 1) {
                return;
            }
            this.iframe = $iframe.prop('outerHTML');
            this.text = $snippet.text();

            var $a = $container.find('a[href]');
            if ($a.length === 0) {
                return;
            }
            this.href = $($a.get(0)).attr('href');
            if (!this.href) {
                return;
            }

            var $grid = $container.parents('.bdImage_stylizedGrid');
            if ($grid.length !== 1) {
                return;
            }
            this.overlayTemplate = $grid.data('iframeTemplate');
            if (!this.overlayTemplate) {
                return;
            }

            this._attemptAutoPlay();

            $container.addClass('with-iframe');
            $container.click($.context(this, 'onContainerClick'));
        },

        onContainerClick: function (e) {
            if (e.altKey || e.ctrlKey || e.metaKey || e.shiftKey) {
                return;
            }

            e.preventDefault();

            var $overlayHtml = $(this.overlayTemplate);
            $overlayHtml.find('.author').text(this.author);
            $overlayHtml.find('.thread-title').text(this.threadTitle);
            $overlayHtml.find('.iframe-container').html(this.iframe);
            $overlayHtml.find('.href').attr('href', this.href);
            $overlayHtml.find('.snippet-text').text(this.text);

            $overlayHtml.find('.iframe-container').find('.LbTrigger,.OverlayTrigger').each(function () {
                $(this).attr('class', '');
            });

            var overlay = XenForo.createOverlay(null, $overlayHtml, {
                onClose: function () {
                    this.getOverlay().empty().remove();
                },
                mask: {
                    maskId: 'bdImage_stylizedGridIframeMask'
                }
            });
            overlay.load();
        },

        _attemptAutoPlay: function () {
            // youtube: append ?autoplay=1&
            this.iframe = this.iframe.replace(/(src="[^"]+youtu[^"]+embed[^"\?]+)\??/, '$1?autoplay=1&')
        }
    };

    // *********************************************************************

    if ($(window).width() >= 1024) {
        XenForo.register('.bdImage_stylizedGrid .thread', 'XenForo.bdImage_StylizedGrid_Thread');
    }

}(jQuery, this);
