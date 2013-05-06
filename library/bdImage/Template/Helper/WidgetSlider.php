<?php

class bdImage_Template_Helper_WidgetSlider
{
	public static function getCssClass(array $widget)
	{
		return 'bdImage_Widget_Slider-' . $widget['widget_id'];
	}
}