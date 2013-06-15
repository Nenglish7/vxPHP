<?php

namespace vxPHP\Webpage\MenuEntry;

use vxPHP\Webpage\Menu\Menu;
use vxPHP\Webpage\MenuEntry\MenuEntryInterface;
use vxPHP\Request\NiceURI;

/**
 * MenuEntry class
 * manages a single menu entry
 *
 * @version 0.2.0 2013-06-15
 */
class MenuEntry implements MenuEntryInterface {
	private static		$href, $admin;
	protected static	$count = 1;
	protected			$menu,
						$auth,
						$authParameters,
						$attributes,
						$id,
						$page,
						$subMenu,
						$localPage;

	public function __construct($page, $attributes, $localPage = TRUE) {
		$this->id			= self::$count++;
		$this->page			= $page;
		$this->localPage	= (boolean) $localPage;
		$this->attributes	= new \StdClass();

		foreach($attributes as $attr => $value) {
			$attr = strtolower($attr);
			$this->attributes->$attr = (string) $value;
		}
	}

	// purge dynamically generated submenus

	public function __destruct() {
		if($this->subMenu && $this->subMenu->getType() == 'dynamic') {
			$this->subMenu->__destruct();
		}
	}

	public function __toString() {
		return $this->page;
	}

	public function appendMenu(Menu $menu) {
		$menu->setParentEntry($this);
		$this->subMenu = $menu;
	}

	public function setMenu(Menu $menu) {
		$this->menu = $menu;
	}

	public function getMenu() {
		return $this->menu;
	}

	public function getAuth() {
		return $this->auth;
	}

	public function setAuth($auth) {
		$this->auth = $auth;
	}

	public function getAuthParameters() {
		return $this->authParameters;
	}

	public function setAuthParameters($authParameters) {
		$this->authParameters = $authParameters;
	}

	public function isAuthenticatedBy($privilege) {
		return isset($this->auth) && $privilege >= $this->auth;
	}

	public function refersLocalPage() {
		return $this->localPage;
	}

	public function select() {
		$this->menu->setSelectedEntry($this);
	}

	public function getSubMenu() {
		return $this->subMenu;
	}

	public function getPage() {
		return $this->page;
	}

	public function getAttributes() {
		return $this->attributes;
	}

	public function setAttribute($attr, $value) {
		$this->attributes->$attr = $value;
	}

	public function render() {

		// check display attribute

		if(isset($this->attributes->display) && $this->attributes->display == 'none') {
			return FALSE;
		}

		if(!isset(self::$href)) {
			self::$href = "{$this->menu->getScript()}?page=";
		}

		$sel = $this->menu->getSelectedEntry();

		if(isset($this->attributes->text)) {
			if(!isset($sel) || $sel !== $this) {
				$markup = sprintf(
					'<li class="%s"><a href="%s">%s</a>',
					preg_replace('~[^\w]~', '_', $this->page),
					$this->localPage ? NiceURI::autoConvert(self::$href.$this->page) : $this->page,
					htmlspecialchars($this->attributes->text)
				);
			}
			else {
				if((!isset($this->subMenu) || is_null($this->subMenu->getSelectedEntry())) && !$this->menu->getForceActive()) {
					$markup = sprintf(
						'<li class="active %s"><span>%s</span>',
						preg_replace('~[^\w]~', '_', $this->page),
						htmlspecialchars($this->attributes->text)
					);
				}
				else {
					$markup = sprintf(
						'<li class="active %s"><a href="%s">%s</a>',
						preg_replace('~[^\w]~', '_', $this->page),
						$this->localPage ? NiceURI::autoConvert(self::$href.$this->page) : $this->page,
						htmlspecialchars($this->attributes->text)
					);
				}

				if(isset($this->subMenu) && $this->menu->getShowSubmenus()) {
					$markup .= $this->subMenu->render($this->menu->getShowSubmenus(), $this->menu->getForceActive());
				}
			}
			return $markup.'</li>';
		}

		return '';
	}
}
