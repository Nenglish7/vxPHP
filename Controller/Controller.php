<?php

namespace vxPHP\Controller;

use vxPHP\Http\Response;
use vxPHP\Http\JsonResponse;
use vxPHP\Http\ParameterBag;
use vxPHP\Http\Request;
/**
 * Abstract parent class for all controllers
 *
 * @author Gregor Kofler
 *
 * @version 0.1.0 2013-10-24
 *
 */
abstract class Controller {

	/**
	 * @var Request
	 */
	protected $request;

	/**
	 * @var string
	 * the current script name (e.g. index.php, admin.php, ...)
	 */
	protected $currentDocument = NULL;

	/**
	 * @var \vxPHP\Http\Route
	 */
	protected $route;

	/**
	 * @var array
	* path segments stripped from (beautified) document (e.g. admin/...) and locale
	 */
	protected $pathSegments = array();

	/**
	 * @var Config
	 */
	protected $config;


	/**
	 * @var boolean
	 */
	protected $isXhr;

	/**
	 * @var ParameterBag
	 */
	protected $xhrBag;

	/**
	 *
	 *
	 */
	function __construct() {

		// set up references required in controllers

		$this->config			= Application::getInstance()->getConfig();
		$this->request			= Request::createFromGlobals();
		$this->route			= Router::getRouteFromPathInfo();
		$this->currentDocument	= basename($this->request->getScriptName());
		$this->pathSegments		= explode('/', trim($this->request->getPathInfo(), '/'));

		// skip script name

		if($this->config->site->use_nice_uris && $this->currentDocument != 'index.php') {
			array_shift($this->pathSegments);
		}

		// skip locale if one found

		if(count($this->pathSegments) && in_array($this->pathSegments[0], LocalesFactory::getAllowedLocales())) {
			array_shift($this->pathSegments);
		}

		$this->prepareForXhr();
		$this->execute()->send();
	}

	/**
	 * prepares and executes a Route::redirect
	 *
	 * @param string destination page id
	 * @param string $document
	 * @param array query
	 * @param int $statusCode
	 *
	 */
	protected function redirect($path = NULL, $document = NULL, $queryParams = array(), $statusCode = 303) {

		if(is_null($path)) {
			$this->route->redirect($queryParams, $statusCode);
		}

		if(is_null($document)) {
			$document = $this->currentDocument;
		}

		$urlSegments = array(
				$this->request->getSchemeAndHttpHost()
		);

		if(Application::getInstance()->getConfig()->site->use_nice_uris == 1) {
			if($document !== 'index.php') {
				$urlSegments[] = basename($document, '.php');
			}
		}
		else {
			$urlSegments[] = $document;
		}

		$urlSegments[] = trim($path, '/');

		if($queryParams) {
			$query = '?' . http_build_query($queryParams);
		}

		else {
			$query = '';
		}

		$response = new Response();
		$response->headers->set('Location', implode('/', $urlSegments) . $query);
		$response->setStatusCode($statusCode)->sendHeaders();
		exit();

	}

	/**
	 * generate error and (optional) error page content
	 *
	 * @param integer $errorCode
	 */
	protected function generateHttpError($errorCode = 404) {

		$content =
				'<h1>' .
				$errorCode .
				' ' .
				Response::$statusTexts[$errorCode] .
				'</h1>';

		Response::create($content, $errorCode)->send();
		exit();

	}

	/**
	 * add an echo property to a JsonResponse
	 * useful with vxJS.xhr based widgets
	 *
	 * @param JsonResponse $r
	 * @return JsonResponse
	 */
	protected function addEchoToJsonResponse(JsonResponse $r) {

		if($this->isXhr && $this->xhrBag && $this->xhrBag->get('echo') == 1) {

			// echo is the original xmlHttpRequest sans echo property

			$echo = json_decode($this->xhrBag->get('xmlHttpRequest'));
			unset($echo->echo);

			$r->setContent(array(
				'echo'		=> $echo,
				'response'	=> $r->getContent()
			));
		}

		return $r;

	}

	/**
	 * check whether a an XMLHttpRequest was submitted
	 * this will look for a key 'xmlHttpRequest' in both GET and POST and
	 * set the Controller::isXhr flag  and
	 * decode the parameters accordingly into their ParameterBages
 	 * in addition the presence of ifuRequest in GET is checked for handling IFRAME uploads
	 *
	 * this method is geared to fully support the vxJS.widget.xhrForm()
	 */
	private function prepareForXhr() {

		$parameters = array();

		// do we have a GET XHR?

		if($this->request->getMethod() === 'GET' && $this->request->query->get('xmlHttpRequest')) {

			$this->xhrBag = $this->request->query;

			foreach(json_decode($this->xhrBag->get('xmlHttpRequest'), TRUE) as $key => $value) {
				$this->xhrBag->set($key, $value);
			}

		}

		// do we have a POST XHR?

		else if($this->request->getMethod() === 'POST' && $this->request->request->get('xmlHttpRequest')) {

			$this->xhrBag = $this->request->request;

			foreach(json_decode($this->xhrBag->get('xmlHttpRequest'), TRUE) as $key => $value) {
				$this->xhrBag->set($key, $value);
			}

		}

		// do we have an iframe upload?

		else if($this->request->query->get('ifuRequest')) {

			// POST already contains all the parameters

			$this->request->request->set('httpRequest', 'ifuSubmit');

		}

		// otherwise no XHR according to the above rules was detected

		else {
			$this->isXhr = FALSE;
			return;
		}

		$this->isXhr = TRUE;

		// handle request for apc upload poll, this will not be left to individual controller

		if($this->xhrBag->get('httpRequest') === 'apcPoll') {

			$id = $this->xhrBag->get('id');
			if($this->config->server['apc_on'] && $id) {
				$apcData = apc_fetch('upload_' . $id);
			}
			if(isset($apcData['done']) && $apcData['done'] == 1) {
				apc_clear_cache('user');
			}

			JsonResponse::create($apcData)->send();
			exit();

		}
	}

	/**
	 * the actual controller functionality implemented in the individual controllers
	 *
	 * returns a Response or JsonResponse object
	 */
	abstract protected function execute();
}
