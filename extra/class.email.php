<?php
/**
 * Einfache Klasse zum verschicken von Mails per mail()
 * @version 0.2.4 2011-01-10
 */

if(!defined('MAIL_CRLF')) {
	define('MAIL_CRLF', "\n");
	
}

class Email {
	private $sender;
	private $subject;
	private $bcc;
	private $receiver;
	private $mailText;
	private $sig;
	private $htmlMail;
	private $headers;
	private $attachments = array();
	private $debug = false;


	public function __construct($receiver = null, $subject = '(Kein Betreff)', $mailText = '', $sender = null, $bcc = '', $sig = '', $htmlMail = false) {
		$this->receiver	= $receiver;
		$this->subject	= $subject;
		$this->mailText	= $mailText;
		$this->sender	= !empty($sender) ? $sender : (defined('DEFAULT_MAIL_SENDER') ? DEFAULT_MAIL_SENDER : 'mail@net.invalid');
		$this->bcc		= $bcc;
		$this->sig		= $sig;
		$this->htmlMail	= $htmlMail;
		
		$this->encoding	= defined('DEFAULT_ENCODING') ? strtoupper(DEFAULT_ENCODING) : 'iso-8859-15'; 
	}
	
	public function setDebug($state) {
		$this->debug = (boolean) $state;
	}

	public function setReceiver($parm)	{ $this->receiver = $parm; }
	public function setSender($parm)	{ $this->sender = $parm; }
	public function setMailText($parm)	{ $this->mailText = $parm; }
	public function setSig($parm)		{ $this->sig = $parm; }
	public function setSubject($parm)	{ $this->subject = $parm; }
	public function setBcc($parm)		{ $this->bcc = $parm; }
	public function setHtmlMail($parm)	{ $this->htmlMail = $parm; }
	
	public function addAttachment($file) {
		if(file_exists($file)) {
			$this->attachments[] = $file;
		}
	}

	public function send()	{
		if(!$this->buildMsg()) { return false; }
		
		if (is_array($this->receiver)) {
			foreach ($this->receiver as $r) {
				if($this->debug) {
					echo '<div style="border: solid 2px #888; background:#efe; font-family: monospace; font-size: 1em; padding: 1em; margin: 1em;">';
					echo implode(', ', $this->receiver), '<hr>', nl2br($this->headers), '<hr>', nl2br($this->msg);
					echo '</div>';
				}
				else {
					mail($r, $this->subject, $this->msg, $this->headers);
				}
			}
		}
		else {
				if($this->debug) {
					echo '<div style="border: solid 2px #888; background:#efe; font-family: monospace; font-size: 1em; padding: 1em; margin: 1em;">';
					echo $this->receiver.'<hr>'.nl2br($this->headers).'<hr>'.nl2br(htmlspecialchars($this->msg));
					echo '</div>';
				}
				else {
					mail($this->receiver, $this->subject, $this->msg, $this->headers);
				}
		}
		return true;
	}

	private function buildMsg() {
		if (empty($this->receiver) || empty($this->sender) || empty ($this->mailText)) { return false; }

		$this->headers =	'From: '.$this->sender.MAIL_CRLF;
		$this->headers .=	'Reply-To: '.$this->sender.MAIL_CRLF;

		if(!empty($this->bcc)) {
			$this->headers .= 'BCC: '.(is_array($this->bcc) ? implode(', ', $this->bcc) : $this->bcc).MAIL_CRLF;
		}

		$this->headers .= 'MIME-Version: 1.0'.MAIL_CRLF;

		if(count($this->attachments) > 0) {
			$boundary = '!!!@snip@here@!!!';
			$this->headers .= 'Content-type: multipart/mixed; boundary="'.$boundary.'"'.MAIL_CRLF;
		}

		if(isset($boundary)) {
			$this->msg = '--'.$boundary.MAIL_CRLF;
			$this->msg .= 'Content-type: text/'.($this->htmlMail ? 'html' : 'plain')."; charset={$this->encoding}".MAIL_CRLF;
			$this->msg .= 'Content-Transfer-Encoding: 8bit'.MAIL_CRLF.MAIL_CRLF;
		}
		else {
			$this->headers .= 'Content-type: text/'.($this->htmlMail ? 'html' : 'plain')."; charset={$this->encoding}".MAIL_CRLF;
			$this->msg = '';
		}

		$this->msg .=	$this->mailText.MAIL_CRLF;
		$this->msg .=	empty($this->sig) || $this->htmlMail ? MAIL_CRLF : MAIL_CRLF.MAIL_CRLF.'-- '.MAIL_CRLF.$this->sig.MAIL_CRLF;

		foreach($this->attachments as $f) {
			$this->msg .= '--'.$boundary.MAIL_CRLF;
			$this->msg .= 'Content-Type: application/octet-stream; name="'.basename($f).'"'.MAIL_CRLF;
			$this->msg .= 'Content-Disposition: attachment; filename="'.basename($f).'"'.MAIL_CRLF;
			$this->msg .= 'Content-Transfer-Encoding: base64'.MAIL_CRLF.MAIL_CRLF;
			$this->msg .= rtrim(chunk_split(base64_encode(file_get_contents($f)),72,MAIL_CRLF));
		}

		return true;
	}
}
?>