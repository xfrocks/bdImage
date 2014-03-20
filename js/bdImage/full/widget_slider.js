/** @param {jQuery} $ jQuery Object */! function($, window, document, _undefined)
{
	XenForo.bdImage_Widget_Slider_Container = function($container)
	{
		this.__construct($container);
	};
	XenForo.bdImage_Widget_Slider_Container.prototype =
	{
		__construct: function($container)
		{
			var lib = $container.data('lib');

			if (lib == 'bxslider')
			{
				$container.find('ul').bxSlider(
				{
					auto: XenForo.isPositive($container.data('auto')),
					autoHover: true,
					captions: true,
					mode: 'fade',
					pager: XenForo.isPositive($container.data('pager'))
				});
			}
			else
			{
				$container.find('ul').jcarousel(
				{
					animation: 'slow',
					auto: 3,
					scroll: 1,
					wrap: 'circular',
					initCallback: function(carousel)
					{
						carousel.clip.hover(function()
						{
							carousel.stopAuto();
						}, function()
						{
							carousel.startAuto();
						});
					}
				});
			}
		}
	};

	// *********************************************************************

	XenForo.register('.bdImage_Widget_Slider_Container', 'XenForo.bdImage_Widget_Slider_Container');

}(jQuery, this, document);
