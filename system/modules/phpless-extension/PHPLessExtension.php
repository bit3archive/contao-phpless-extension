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
				
				foreach (array(4, 1, 2, 3) as $i)
				{
					$out .= strtoupper(($value[$i] < 16 ? '0' : '').dechex($value[$i]));
				}
				
				return $out;
			}
			return $c->compileValue($argv);
		}
		return '';
	}
	
	protected function appendPrefixed($key, $value, lessc $c)
	{
		foreach (array('', '-moz-', '-webkit-', '-o-', '-khtml-') as $strPrefix)
		{
			$c->append($strPrefix . $key, $value);
		}
	}
	
	public function hookLesscssHandleProperty($key, $value, lessc $c)
	{
		switch ($key)
		{
		case 'border-radius':
		case 'box-shadow':
			$this->appendPrefixed($key, $value, $c);
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
			if ($argv[0] == 'list')
			{
				$argv = $argv[2];
			}
			
			$colors = array();
			$n = 0;
			while ($n < count($argv))
			{
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
				else
				{
					return '';
				}
			}
			
			$n = count($colors);
			
			if ($n < 2)
			{
				return '';
			}
			
			// webkit gradients
			$out = '-webkit-gradient(linear, left top, left bottom';
			foreach ($colors as $i => $color)
			{
				$out .= sprintf(', color-stop(%f, %s)', $color[1] >= 0 ? $color[1] : $n / $i, $c->compileValue($color[0]));
			}
			$out .= ')';
			$c->append('background', array('keyword', $out));
			
			// mozilla gradients
			$out = '-moz-linear-gradient(center top';
			foreach ($colors as $i => $color)
			{
				$out .= sprintf(', %s %d%%', $c->compileValue($color[0]), ($color[1] >= 0 ? $color[1] : $n / $i) * 100);
			}
			$out .= ')';
			$c->append('background', array('keyword', $out));
			
			// opera gradients
			$out = '-o-linear-gradient(center top';
			foreach ($colors as $i => $color)
			{
				$out .= sprintf(', %s %d%%', $c->compileValue($color[0]), ($color[1] >= 0 ? $color[1] : $n / $i) * 100);
			}
			$out .= ')';
			$c->append('background', array('keyword', $out));
			
			// ms filter
			$out = 'progid:DXImageTransform.Microsoft.Gradient(enabled=true, StartColorStr=' . $this->parseARGB($colors[0][0], $c) . ', EndColorStr=' . $this->parseARGB($colors[$n-1][0], $c) . ')';
			$c->append('filter', array('keyword', $out));
			$out = 'progid:DXImageTransform.Microsoft.Gradient(enabled=true, StartColorStr=' . $this->parseARGB($colors[0][0], $c) . ', EndColorStr=' . $this->parseARGB($colors[$n-1][0], $c) . ')';
			$c->append('-ms-filter', array('keyword', $out));
			
			return true;
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
