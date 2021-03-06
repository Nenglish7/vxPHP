<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


namespace vxPHP\Image;

use vxPHP\Image\Exception\ImageModifierException;

/**
 * wraps some image manipulation functionality
 *
 * @author Gregor Kofler
 * @version 0.5.2 2014-04-06
 */
abstract class ImageModifier {

	protected	$queue,
				$mimeType,
				$supportedFormats = array('jpeg', 'gif', 'png'),
				$file,
				$path,
				$srcWidth,
				$srcHeight;

	/**
	 * adds a crop-"command" to queue
	 * parameters are validated
	 * 
	 * @param float $aspectRatio
	 * or
	 * @param int $width
	 * @param int $height
	 * or
	 * @param int $top
	 * @param int $left
	 * @param int $bottom
	 * @param int $right
	 * 
	 * @throws ImageModifierException
	 */
	public function crop() {

		$args = func_get_args();
		
		$srcAspectRatio = $this->srcWidth / $this->srcHeight;
		
		// single float value given, represents aspect ratio of cropped image

		if(count($args) == 1) {

			if(!is_numeric($args[0]) || $args[0] <= 0) {
				throw new ImageModifierException('Invalid dimension(s) for cropping: ' . $args[0] . '.');
			}
		
			if($srcAspectRatio <= $args[0]) {
		
				// width determines

				$left = $right = 0;
		
				// choose upper portion

				$top	= round(($this->srcHeight - $this->srcWidth / $args[0]) / 3);
				$bottom	= round(($this->srcHeight - $this->srcWidth / $args[0]) * 2 / 3);
			}

			else {
		
				// height determines

				$top	= $bottom	= 0;
				$left	= $right	= round(($this->srcWidth - $this->srcHeight * $args[0]) / 2);
			}
		}
		
		// width and height given
		
		else if(count($args) === 2) {

			$width	= (int) $args[0];
			$height	= (int) $args[1];
		
			if($width > 0 && $height > 0) {
				$left = $right = round(($this->srcWidth - $width) / 2);
		
				if($srcAspectRatio >= 1) {

					// landscape

					$top = $bottom = round(($this->srcHeight - $height) / 2);
				}

				else {

					// portrait

					$top	= round(($this->srcHeight - $height) / 3);
					$bottom	= round(($this->srcHeight - $height) * 2 / 3);
				}
			}
		
			else {
				throw new ImageModifierException('Invalid dimension(s) for cropping: ' . $width . ', ' . $height . '.');
			}
		}
		
		// top, left, bottom, right
		
		else if(count($args) == 4) {

			$top	= (int) $args[0];
			$left	= (int) $args[1];
			$bottom	= (int) $args[2];
			$right	= (int) $args[3];

		}

		else {
			throw new ImageModifierException('Insufficient arguments for cropping.');
		}
		
		// skip queuing when there is nothing to crop
		
		if(!$top && !$bottom && !$left && !$right) {
			return;
		}

		$todo = new \stdClass();
		
		$todo->method		= __FUNCTION__;
		$todo->parameters	= array($top, $right, $bottom, $left);

		$this->queue[] = $todo;
		
		$this->srcWidth		= $this->srcWidth - $left - $right;
		$this->srcHeight	= $this->srcHeight - $top - $bottom;

	}

	/**
	 * adds a resize-"command" to queue
	 * paramaters are "normalized" and validated
	 * when supplying $width and $height a prefixed "max_" will keep the respective dimension flexible within this value
	 * 
	 * @param float $percentage
	 * or
	 * @param mixed $width 
	 * @param mixed $height
	 * 
	 * @throws ImageModifierException
	 */
	public function resize() {
		
		$args = func_get_args();
		
		// width and/or height given
		
		if(count($args) >= 2) {
		
			// max limit for width?
		
			if(preg_match('/max_([1-9]\d*)/i', $args[0], $matches)) {
		
				$maxWidth	= $matches[1];
				$height		= (int) $args[1];
				$width		= round($height / $this->srcHeight * $this->srcWidth);
		
				if($width > $maxWidth) {
					$width	= $maxWidth;
					$height	= round($width / $this->srcWidth * $this->srcHeight);
				}
			}
		
			// max limit for height?
		
			else if(preg_match('/max_([1-9]\d*)/i', $args[1], $matches)) {
		
				$maxHeight	= $matches[1];
				$width		= (int) $args[0];
				$height		= round($width / $this->srcWidth * $this->srcHeight);
		
				if($height > $maxHeight) {
					$height	= $maxHeight;
					$width = round($height / $this->srcHeight * $this->srcWidth);
				}
			}
		
			// no limit
		
			else {

				$width	= (int) $args[0];
				$height	= (int) $args[1];
		
				if($width != 0 || $height != 0) {

					if($height == 0) {
						$height = round($width / $this->srcWidth * $this->srcHeight);
					}

					if($width == 0) {
						$width = round($height / $this->srcHeight * $this->srcWidth);
					}
				}
		
				else {
					throw new ImageModifierException('Invalid dimension(s) for resizing: ' . $width . ', ' . $height . '.');
				}
			}
		}
		
		// single float value given
		
		else if(count($args) == 1) {

			if(!is_numeric($args[0]) || $args[0] == 0) {
				throw new ImageModifierException('Invalid dimension(s) for resizing: ' . $args[0] . '.');
			}

			$width	= round($this->srcWidth * $args[0]);
			$height	= round($this->srcHeight * $args[0]);
		}

		else {
			throw new ImageModifierException('Insufficient arguments for resizing.');
		}
		
		// skip queueing when original size equals resized size

		if($width == $this->srcWidth && $height == $this->srcHeight) {
			return;
		}
		
		
		$todo = new \stdClass();

		$todo->method		= __FUNCTION__;
		$todo->parameters	= array($width, $height);

		$this->queue[] = $todo;
		
		$this->srcWidth		= $width;
		$this->srcHeight	= $height;

	}

	/**
	 * adds a watermark-"command" to queue
	 * 
	 * @param string $filename
	 * 
	 * @throws ImageModifierException
	 */
	public function watermark() {
		
		$args = func_get_args();

		if(!count($args)) {
			throw new ImageModifierException('Insufficient arguments for watermarking.');
		}
		
		if(!file_exists(realpath($args[0]))) {
			throw new ImageModifierException('Watermark file not found.');
		}
		
		$todo = new \stdClass();

		$todo->method		= __FUNCTION__;
		$todo->parameters	= array($args[0]);

		$this->queue[] = $todo;

	}

	/**
	 * turns image into b/w
	 */
	public function greyscale() {

		$todo = new \stdClass();

		$todo->method		= __FUNCTION__;
		$todo->parameters	= array();

		$this->queue[] = $todo;

	}

	/**
	 * performs crop-"command"
	 * 
	 * @param stdClass $src
	 * @param int $top
	 * @param int $left
	 * @param int $bottom
	 * @param int $right
	 */
	abstract protected function do_crop(\stdClass $src, $top, $left, $bottom, $right);

	/**
	 * performs resize-"command"
	 * 
	 * @param stdClass $src
	 * @param int $width
	 * @param int $height
	 */
	abstract protected function do_resize(\stdClass $src, $width, $height);
		
	/**
	 * performs "watermark"-command
	 * 
	 * @param stdClass $src
	 * @param string $watermarkFile
	 */
	abstract protected function do_watermark(\stdClass $src, $watermarkFile);
	
	/**
	 * performs "bw"-command
	 * 
	 * @param stdClass $src
	 */
	abstract protected function do_greyscale(\stdClass $src);

	/**
	 * exports resulting image
	 * all queued commands are applied to the source image
	 *
	 * file is then stored to $path, format $mimetype
	 *
	 * @param string $path, output filename, defaults to source filename
	 * @param string $mimetype, output file mimetype, defaults to source mime type
	 */
	abstract public function export($path = NULL, $mimetype = NULL);

}
