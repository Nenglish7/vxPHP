<?php
/*
 * This file is part of the vxPHP/vxWeb framework
 *
 * (c) Gregor Kofler <info@gregorkofler.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */


/*
 * with minor adaptations lifted from Symfony's HttpFoundation classes
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 */

namespace vxPHP\Http;

/**
 * StreamedResponse represents a streamed HTTP response.
 *
 * A StreamedResponse uses a callback for its content.
 *
 * The callback should use the standard PHP functions like echo
 * to stream the response back to the client. The flush() method
 * can also be used if needed.
 *
 * @see flush()
 *
 * @author Fabien Potencier <fabien@symfony.com>
 *
 * @api
 */
class StreamedResponse extends Response {

	protected $callback;
	protected $streamed;

	/**
	 * Constructor.
	 *
	 * @param mixed   $callback A valid PHP callback
	 * @param integer $status   The response status code
	 * @param array   $headers  An array of response headers
	 */
	public function __construct(callable $callback = NULL, $status = 200, $headers = []) {

		parent::__construct(NULL, $status, $headers);

		if (NULL !== $callback) {
				$this->setCallback($callback);
		}

		$this->streamed = FALSE;

	}

	/**
	 * {@inheritDoc}
	 */
	public static function create($callback = NULL, $status = 200, $headers = []) {

		return new static($callback, $status, $headers);

	}

	/**
	 * Sets the PHP callback associated with this Response.
	 *
	 * @param callable $callback
	 */
	public function setCallback(callable $callback) {

		$this->callback = $callback;

	}

	/**
	 * {@inheritdoc}
	 *
	 * This method only sends the content once.
	 */
	public function sendContent() {

		if ($this->streamed) {
			return;
		}

		$this->streamed = TRUE;

		if (NULL === $this->callback) {
			throw new \LogicException('The Response callback must not be null.');
		}

		call_user_func($this->callback);

	}

	/**
	 * {@inheritdoc}
	 *
	 * @throws \LogicException when the content is not null
	 */
	public function setContent($content) {

		if (NULL !== $content) {
			throw new \LogicException('The content cannot be set on a StreamedResponse instance.');
		}

	}

	/**
	 * {@inheritdoc}
	 *
	 * @return false
	 */
	public function getContent() {

		return FALSE;

	}
}
