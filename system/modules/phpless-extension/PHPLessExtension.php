<?php if (!defined('TL_ROOT')) die('You can not access this file directly!');

/**
 * PHP Less Extension
 * Copyright (C) 2010,2011 InfinitySoft <http://www.infinitysoft.de>
 *
 * Extension for:
 * Contao Open Source CMS
 * Copyright (C) 2005-2011 Leo Feyer
 *
 * Formerly known as TYPOlight Open Source CMS.
 *
 * This program is free software: you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation, either
 * version 3 of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this program. If not, please visit the Free
 * Software Foundation website at <http://www.gnu.org/licenses/>.
 *
 * PHP version 5
 * @copyright  2010,2011 InfinitySoft <http://www.infinitysoft.de>
 * @author     Tristan Lins <tristan.lins@infinitysoft.de>
 * @package    PHP Less Extension
 * @license    LGPL
 */


/**
 * Class PHPLessExtension
 * 
 * Extension functions to phpless compiler.
 */
class PHPLessExtension
{
	protected function parseARGB($argv, lessc $c)
	{
		if ($argv[0] == 'color')
		{
			if (count($argv) == 5)
			{
				$out = '#';
				
				// the alpha value
				$argv[4] *= 255;
				$out .= strtoupper(($argv[4] < 16 ? '0' : '').dechex($argv[4]));
				// the rgb values
				foreach (array(1, 2, 3) as $i)
				{
					$out .= strtoupper(($argv[$i] < 16 ? '0' : '').dechex($argv[$i]));
				}
				
				return $out;
			}
			return $c->compileValue($argv);
		}
		return '';
	}
	
	protected function appendPrefixed($key, $value, lessc $c)
	{
		foreach (array('', '-moz-', '-webkit-', '-o-', '-khtml-', '-ms-', '-pie-') as $strPrefix)
		{
			$c->append($strPrefix . $key, $value);
		}
	}
	
	protected function prependTags($prefix, $rtags)
	{
		if (!is_array($prefix))
		{
			$prefix = array($prefix);
		}
		
		$tags = array();
		foreach ($prefix as $pre)
		{
			list($pre_element, $pre_class) = explode('.', $pre, 2);
			foreach ($rtags as $tag)
			{
				$path = explode(' ', $tag);
				list($tag_element, $tag_class) = explode('.', $path[0], 2);
				if ($pre_element == $tag_element)
				{
					$path[0] = $pre_element . '.' . $pre_class . '.' . $tag_class;
				}
				else
				{
					array_unshift($path, $pre);
				}
				$tags[] = implode(' ', $path);
			}
		}
		
		return $tags;
	}
	
	public function hookLesscssHandleProperty($key, $value, lessc $c)
	{
		switch ($key)
		{
		case 'border-radius':
		case 'border-image':
		case 'box-shadow':
			$this->appendPrefixed($key, $value, $c);
			$c->append('behavior', array('keyword', 'url(' . $GLOBALS['TL_CONFIG']['websitePath'] . '/plugins/css3pie/PIE.htc)'));
			return true;
			
		case 'opacity':
			$c->append($key, $value);
			$c->append('-ms-filter', array('keyword', sprintf('progid:DXImageTransform.Microsoft.Alpha(Opacity=%d)', $value[1]*100)));
			$c->append('filter', array('keyword', sprintf('alpha(opacity=%d)', $value[1]*100)));
			return true;
		}
		return false;
	}
	
	public function hookLesscssHandleFunction($key, $argv, lessc $c)
	{
		switch ($key)
		{
		case 'border-box':
			$this->appendPrefixed('box-sizing', array('keyword', 'border-box'), $c);
			return true;
			
		case 'gradient':
		case 'hgradient':
			$tags = $c->multiplyTags();
			
			if ($argv[0] == 'list')
			{
				$argv = $argv[2];
			}
			
			$usePIE = true;
			$useFilter = true;
			$images = array();
			$colors = array();
			$n = 0;
			while ($n < count($argv))
			{
				if ($argv[$n][0] == 'function')
				{
					$argv[$n] = $c->funcToColor($argv[$n]);
				}
				if ($argv[$n][0] == 'color')
				{
					$color = $argv[$n++];
					
					if ($argv[$n][0] == 'number')
					{
						$position = doubleval($argv[$n++][1]);
					}
					else
					{
						$position = -1;
					}
					
					$colors[] = array($color, $position);
				}
				else if ($argv[$n][0] == 'keyword')
				{
					if ($argv[$n][1] == '!nopie')
					{
						$usePIE = false;
					}
					else if ($argv[$n][1] == '!nofilter')
					{
						$useFilter = false;
					}
					$n ++;
				}
				else
				{
					$images[] = $c->compileValue($argv[$n++]);
				}
			}
			
			$n = count($colors);
			$m = 1 / ($n-1);
			
			if ($n < 2)
			{
				return '';
			}
			
			$return = '';
			
			// add old webkit gradients
			$out = '';
			foreach ($images as $image)
			{
				$out .= $image . ', ';
			}
			$out .= '-webkit-gradient(linear, ';
			switch ($key) {
			case 'gradient':
				$out .= 'left top, left bottom';
				break;
			case 'hgradient':
				$out .= 'left top, right top';
				break;
			}
			foreach ($colors as $i => $color)
			{
				$out .= sprintf(', color-stop(%f, %s)', $color[1] >= 0 ? $color[1] : $i * $m, $c->compileValue($color[0]));
			}
			$out .= ')';
			$return .= $c->compileBlock($this->prependTags(array('body.safari', 'body.chrome'), $tags), array('background' => array(array('keyword', $out))));
			
			// add linear-gradients for various browsers
			$out = '';
			foreach ($images as $image)
			{
				$out .= $image . ', ';
			}
			$out .= 'linear-gradient(';
			switch ($key) {
			case 'gradient':
				$out .= 'top';
				break;
			case 'hgradient':
				$out .= 'left';
				break;
			}
			foreach ($colors as $i => $color)
			{
				$out .= sprintf(', %s %d%%', $c->compileValue($color[0]), ($color[1] >= 0 ? $color[1] : $i * $m) * 100);
			}
			$out .= ')';
			
			$c->append('background', array('keyword', $out));
			foreach (array
				(
					'-moz-'    => 'body.firefox',
					'-webkit-' => array('body.safari', 'body.chrome'),
					'-o-'      => 'body.opera',
					'-khtml-'  => 'body.konqueror',
					'-ms-'     => 'body.ie'
				) as $strPrefix => $arrClass)
			{
				$return .= $c->compileBlock($this->prependTags($arrClass, $tags), array('background' => array(array('keyword', str_replace('linear-gradient', $strPrefix . 'linear-gradient', $out)))));
			}
			
			// add css pie
			if ($usePIE)
			{
				$return .= $c->compileBlock($this->prependTags('body.ie', $tags), array
				(
					'-pie-background' => array(array('keyword', $out)),
					'behavior' => array(array('keyword', 'url(' . $GLOBALS['TL_CONFIG']['websitePath'] . '/plugins/css3pie/PIE.htc)'))
				));
			}
			
			// ms filter
			else if ($useFilter)
			{
				$out = 'progid:DXImageTransform.Microsoft.Gradient(enabled=true' . ($key == 'hgradient' ? ', GradientType=1' : '') . ', StartColorStr=' . $this->parseARGB($colors[0][0], $c) . ', EndColorStr=' . $this->parseARGB($colors[$n-1][0], $c) . ')';
				$return .= $c->compileBlock($this->prependTags(array('body.ie6', 'body.ie7', 'body.ie8'), $tags), array('filter' => array(array('keyword', $out))));
				// -ms-filter does NOT work in IE8!!! $return .= $c->compileBlock($this->prependTags('body.ie8', $tags), array('-ms-filter' => array(array('keyword', $out))));
				
				// IE9 svg gradient
				$out = '<?xml version="1.0" ?>
<svg xmlns="http://www.w3.org/2000/svg" preserveAspectRatio="none" version="1.0" width="100%" height="100%" xmlns:xlink="http://www.w3.org/1999/xlink">
	<defs>';
				switch ($key) {
				case 'gradient':
					$out .= '
		<linearGradient id="myLinearGradient1" x1="0%" y1="0%" x2="0%" y2="100%" spreadMethod="pad">';
					break;
				case 'hgradient':
					$out .= '
		<linearGradient id="myLinearGradient1" x1="0%" y1="0%" x2="100%" y2="0%" spreadMethod="pad">';
					break;
				}
				foreach ($colors as $i => $color)
				{
					$opacity = 1;
					if (count($color[0]) == 5)
					{
						$opacity = $color[0][4];
						unset($color[0][4]);
					}
					$out .= sprintf('
			<stop offset="%d%%" stop-color="%s" stop-opacity="%f"/>',
						($color[1] >= 0 ? $color[1] : $i * $m) * 100,
						$c->compileValue($color[0]),
						$opacity);
				}
				$out .= '
		</linearGradient>
	</defs>
	<rect width="100%" height="100%" style="fill:url(#myLinearGradient1);" />
</svg>';
				$strSVG = 'system/html/ie9_gradient_' . substr(md5($out), 0, 8) . '.svg';
				if (!file_exists(TL_ROOT . '/' . $strSVG))
				{
					$objFile = new File($strSVG);
					$objFile->write($out);
					$objFile->close();
				}
				
				// ms background
				if (count($images))
				{
					$return .= $c->compileBlock($this->prependTags(array('body.ie6', 'body.ie7', 'body.ie8'), $tags), array('background' => array(array('keyword', $images[0]))));
					
					$out = '';
					foreach ($images as $image)
					{
						$out .= $image . ', ';
					}
					$out .= 'url(../../' . $strSVG . ')';
					$return .= $c->compileBlock($this->prependTags('body.ie9', $tags), array('background' => array(array('keyword', $out))));
				}
				else
				{
					$out = 'url(../../' . $strSVG . ')';
					$return .= $c->compileBlock($this->prependTags('body.ie9', $tags), array('background' => array(array('keyword', $out))));
				}
			}
			
			return $return;
		}
		return false;
	}
	
	public function hookLesscssCompileFunction($key, $argv, lessc $c)
	{
		switch ($key)
		{
		case 'argb':
			return $this->parseARGB($argv, $c);
		}
		return false;
	}
}
