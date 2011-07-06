<?php
/**
 *
 * License, TERMS and CONDITIONS
 *
 * This software is lisensed under the GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * Please read the license here : http://www.gnu.org/licenses/lgpl-3.0.txt
 *
 *  Redistribution and use in source and binary forms, with or without
 *  modification, are permitted provided that the following conditions are met:
 * 1. Redistributions of source code must retain the above copyright
 *    notice, this list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright
 *    notice, this list of conditions and the following disclaimer in the
 *    documentation and/or other materials provided with the distribution.
 * 3. The name of the author may not be used to endorse or promote products
 *    derived from this software without specific prior written permission.
 *
 * ATTRIBUTION REQUIRED
 * 4. All web pages generated by the use of this software, or at least
 * 	  the page that lists the recent questions (usually home page) must include
 *    a link to the http://www.lampcms.com and text of the link must indicate that
 *    the website\'s Questions/Answers functionality is powered by lampcms.com
 *    An example of acceptable link would be "Powered by <a href="http://www.lampcms.com">LampCMS</a>"
 *    The location of the link is not important, it can be in the footer of the page
 *    but it must not be hidden by style attibutes
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR "AS IS" AND ANY EXPRESS OR IMPLIED
 * WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF
 * MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED.
 * IN NO EVENT SHALL THE FREEBSD PROJECT OR CONTRIBUTORS BE LIABLE FOR ANY
 * DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF
 * THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * This product includes GeoLite data created by MaxMind,
 *  available from http://www.maxmind.com/
 *
 *
 * @author     Dmitri Snytkine <cms@lampcms.com>
 * @copyright  2005-2011 (or current year) ExamNotes.net inc.
 * @license    http://www.gnu.org/licenses/lgpl-3.0.txt GNU LESSER GENERAL PUBLIC LICENSE (LGPL) version 3
 * @link       http://www.lampcms.com   Lampcms.com project
 * @version    Release: @package_version@
 *
 *
 */


namespace Lampcms\I18n;

/**
 * Class for parsing XLIFF XML file
 * and converting the file to array
 * and setting that array into $this->aMessages
 * Enter description here ...
 * @author admin
 *
 */
class Xliff implements \Lampcms\Interfaces\Translator
{
	/**
	 * Name of locate for which
	 * the messages are translated
	 *
	 * @var string
	 */
	protected $locale = null;

	/**
	 * Array of messages
	 * keys are strings to be translated
	 * values are translated values
	 *
	 * @var array
	 */
	protected $aMessages = array();


	/**
	 * 
	 * Constructor
	 * @param string $file
	 * @param string $locale
	 */
	public function __construct($file, $locale){
		d('cp');
		$this->locale = $locale;

		/**
		 * If File $file does not exist or not
		 * readable there will not be any errors
		 * Simply the loading of file will be skipped and
		 * the object will have only the default empty
		 * array of $this->aMessages;
		 */
		if(is_readable($file)){
			$this->parseFile($file);
		} else {
			d('XLIFF file '.$file.' does not exist or not readable');
		}
	}


	/**
	 * Load Xliff File, validate it
	 * and create DOMDocument object from it
	 *
	 * @param string $file full path to XLIFF xml file
	 * @throws \Lampcms\DevException
	 * @throws \Exception
	 */
	protected function parseFile($file){
		d('$file: '.$file);
		$oDom = new \DOMDocument();
		$current = libxml_use_internal_errors(true);
		if (!@$oDom->load($file, LIBXML_COMPACT)) {
			$err = implode("\n", $this->getXmlErrors());
			exit($err);
			throw new \Lampcms\DevException($err);
		}

		$location = \str_replace('\\', '/', __DIR__).'/schema/xml.xsd';
		d('$location: '.$location);

		$parts = explode('/', $location);

		$drive = '\\' === DIRECTORY_SEPARATOR ? array_shift($parts).'/' : '';
		$location = 'file:///'.$drive.implode('/', array_map('rawurlencode', $parts));
		d('$location: '.$location);

		$source = \file_get_contents(__DIR__.'/schema/xliff-core-1.2-strict.xsd');
		$source = \str_replace('http://www.w3.org/2001/xml.xsd', $location, $source);

		if (!@$oDom->schemaValidateSource($source)) {
			d('schemaValidateSource() failed');

			throw new \Lampcms\DevException(implode("\n", $this->getXmlErrors()));
		}

		$oDom->validateOnParse = true;
		d('cp');
		$oDom->normalizeDocument();
		d('cp');
		libxml_use_internal_errors($current);

		$this->xml2array($oDom);
	}


	protected function xml2array(\DOMDocument $o){

		$xp = new \DOMXPath($o);
		$xp->registerNamespace('xliff', 'urn:oasis:names:tc:xliff:document:1.2');
		$elements = $xp->query('//xliff:trans-unit');

		foreach($elements as $element){
			$s = $element->getElementsByTagName('source')->item(0)->nodeValue;
			$v = $element->getElementsByTagName('target')->item(0)->nodeValue;
			$this->aMessages[$s] = $v;
		}

		return $this;
	}


	/**
	 * Returns the XML errors of the internal XML parser
	 *
	 * @author Symfony project
	 *
	 * @return array  An array of errors
	 */
	protected function getXmlErrors(){
		$errors = array();
		foreach (libxml_get_errors() as $error) {
			$errors[] = sprintf('[%s %s] %s (in %s - line %d, column %d)',
			LIBXML_ERR_WARNING == $error->level ? 'WARNING' : 'ERROR',
			$error->code,
			trim($error->message),
			$error->file ? $error->file : 'n/a',
			$error->line,
			$error->column
			);
		}

		libxml_clear_errors();
		libxml_use_internal_errors(false);

		return $errors;
	}


	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.Translator::getMessages()
	 */
	public function getMessages(){
		return $this->aMessages;
	}

	
	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.Translator::getLocale()
	 */
	public function getLocale(){
		return $this->locale;
	}

	
	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.Translator::get()
	 */
	public function get($string, array $vars = null, $default = null){
		$str = (!empty($this->aMessages[$string])) ? $this->aMessages[$string] : (is_string($default) ? $default : $string);

		return (null === $vars) ? $str : strtr($str, $vars);
	}

	
	/**
	 * (non-PHPdoc)
	 * @see Lampcms\Interfaces.Translator::has()
	 */
	public function has($string){
		return array_key_exists($string, $this->aMessages);
	}


	/**
	 * (non-PHPdoc)
	 * @see Serializable::serialize()
	 */
	public function serialize(){
		return \json_encode(array('messages' => $this->aMessages, 'locale' => $this->locale));
	}


	/**
	 * (non-PHPdoc)
	 * @see Serializable::unserialize()
	 */
	public function unserialize($serialized){
		$a = \json_decode($serialized, true);
		$this->aMessages = $a['messages'];
		$this->locale = $a['locale'];
	}

}
