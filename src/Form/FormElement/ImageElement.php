<?php

namespace vxPHP\Form\FormElement;

use vxPHP\Form\FormElement\InputElement;

class ImageElement extends InputElement {
	public function __construct($name, $value = NULL, $src) {
		parent::__construct($name, $value);
		$this->setAttribute('alt', pathinfo($src, PATHINFO_FILENAME));
	}
	
	public function render($force = FALSE) {
		$this->attributes['type'] = 'image';
		return parent::render($force);
	}
}