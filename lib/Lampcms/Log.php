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
 *    the website's Questions/Answers functionality is powered by lampcms.com
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


namespace Lampcms;


/**
 * Public static logger class
 * This class is responsible for loggin
 * messages to a file
 *
 * @author Dmitri Snytkine
 *
 */
class Log
{
	/**
	 * Location of log file
	 * it must point to actual file
	 * and that file must be writable to php program
	 * (usually this means writable to apache server)
	 *
	 * @var string
	 */
	const LOG_FILE_PATH = '';


	const LAMPCMS_DEVELOPER_EMAIL = '';


	/**
	 * Format of timestamp
	 * @var string
	 */
	const TIME_FORMAT = "F j, Y H:i:s";


	/**
	 * String to be used as a subject line
	 * of email notification
	 *
	 * @var string
	 */
	const EMAIL_SUBJECT = 'Error on your website';


	/**
	 * Our main logging function
	 *
	 * @param string $message message to log
	 * @param int $traceLevel this is useful
	 * for extractint correct line from debug backtrace
	 * you should normally not worry about this
	 * This is useful in only some cases where you notice that
	 * line number/method name is not logged correctly
	 *
	 * @return string message that was just logged
	 */
	public static function l($message, $traceLevel = 0){
		$logPath = self::getLogPath();

		if(empty($logPath)){
			
			return;
		}

		/**
		 * automatically stringify array
		 * in case we want to just add array to log
		 */
		$str = (is_array($message)) ? print_r($message, true) : $message;

		$string = '';
		$line = 'unknown';

		/**
		 * Passing the false as param
		 * will reduce the size of backtrace objet, sometimes considerabelly
		 * because by default, this value is true and it means
		 * that each object of backtrace is dumped!
		 *
		 */
		$arrBacktrace = debug_backtrace(false);

		/**
		 * Special case: if the ->log() called from an object
		 * that does not directly extend this class but has the __call() method
		 * then the __call() would pass the log() to the upstream object (this object)
		 *
		 * in such case the level 1 will be the __call method itself
		 * and the level 2 will be the actual method that invoked the __call
		 *
		 * In a case like this we are interested in level2 of backtrace!
		 */
		if(array_key_exists(1, $arrBacktrace)){
			if('__call' === $arrBacktrace[1]['function']){
					
				$traceLevel += 2;
					
			} elseif('call_user_func_array' === $arrBacktrace[1]['function']){

				$traceLevel += 3;
			}
		}

		$level1 = $traceLevel + 1;
		if (!empty($arrBacktrace[$level1])) {
			if (!empty($arrBacktrace[$level1]['class'])) {
				$string .= $arrBacktrace[$level1]['class'];
				if (!empty($arrBacktrace[$level1]['type'])) {
					$string .= $arrBacktrace[$level1]['type'];
				}
			}

			if (!empty($arrBacktrace[$level1]['function'])) {
				$string .= $arrBacktrace[$level1]['function'].'() ';
			}
		}

		if (!empty($arrBacktrace[$traceLevel])) {
			if(!empty($arrBacktrace[$traceLevel]['file'])){
				$string .= PHP_EOL.$arrBacktrace[$traceLevel]['file'].' ';
			}

			if (!empty($arrBacktrace[$traceLevel]['line'])) {
				$line = $arrBacktrace[$traceLevel]['line'];
			}

			$string .= ' line: '.$line;
		}

		$string .= PHP_EOL.$str;

		$sMessage = PHP_EOL.self::getTimeStamp().$string;

		$ret = \file_put_contents($logPath, $sMessage, FILE_APPEND | LOCK_EX);
		
		return $sMessage;

	}


	/**
	 * Log debug message. The debug messages
	 * are NOT logged in normal production enviroment
	 * Debugging messages are logged
	 * ONLY when global constant LAMPCMS_DEBUG is set to true
	 *
	 *
	 * @param string $message message to log
	 */
	public static function d($message, $level = 1){

		/**
		 * Increase backtrace level to one
		 * to account to delegating from this
		 * method to log() method
		 */
		return self::l($message, $level);

	}


	/**
	 * Log error message. The main difference
	 * between using this method and normal log()
	 * is that email will also be sent to admin
	 *
	 * @param string $message message to log
	 */
	public static function e($message, $level = 1){
		/**
		 * Increase backtrace level to one
		 * to account to delegating from this
		 * method to log() method
		 */
		$message = self::l($message, $level);

		self::notifyDeveloper($message);

		return $message;
	}


	/**
	 * Get path to log file
	 * If global constant LOG_FILE_PATH is defined
	 * then use it, otherwise use
	 * this class's constant
	 *
	 * @return string a path to log file
	 *
	 */
	protected static function getLogPath(){
		if(defined('SPECIAL_LOG_FILE')){
			return SPECIAL_LOG_FILE;
		}

		return (defined('LOG_FILE_PATH')) ? LOG_FILE_PATH : self::LOG_FILE_PATH;
	}


	/**
	 * Sends email message to developer
	 * if message contains error pattern
	 */
	protected static function notifyDeveloper($message){
		$devEmail = self::getDevEmail();

		if (empty($devEmail)) {
			return;
		}
			
		$msg = '';

		$msg = $message;

		if(isset($_SERVER) && is_array($_SERVER)){
			$msg .= "\n".'-----------------------------------------------------';
			$msg .= "\n".'HTTP_HOST: '.self::getServerVar('HTTP_HOST');
			$msg .= "\n".'SCRIPT_NAME: '.self::getServerVar('SCRIPT_NAME');
			$msg .= "\n".'REQUEST_METHOD: '.self::getServerVar('REQUEST_METHOD');
			$msg .= "\n".'REQUEST_URI: '.self::getServerVar('REQUEST_URI');
			$msg .= "\n".'SCRIPT_FILENAME: '.self::getServerVar('SCRIPT_FILENAME');
			$msg .= "\n".'-----------------------------------------------------';
			$msg .= "\n".'HTTP_USER_AGENT: '.self::getServerVar('HTTP_USER_AGENT');
			$msg .= "\n".'HTTP_REFERER: '.self::getServerVar('HTTP_REFERER');
			$msg .= "\n".'-----------------------------------------------------';
			$msg .= "\n".'REMOTE_ADDR/IP: '.self::getServerVar('REMOTE_ADDR');
			$msg .= "\n".'-----------------------------------------------------';
			$msg .= "\n".'GEOIP_CITY: '.self::getServerVar('GEOIP_CITY');
			$msg .= "\n".'GEOIP_COUNTRY_CODE: '.self::getServerVar('GEOIP_COUNTRY_CODE');
			$msg .= "\n".'GEOIP_COUNTRY_NAME: '.self::getServerVar('GEOIP_COUNTRY_NAME');
			$msg .= "\n".'GEOIP_REGION: '.self::getServerVar('GEOIP_REGION');
		}

		/**
		 * Add hight priority to email headers
		 * for error messages of certain types (real errors, no notices)
		 */
		$headers = 'X-Mailer: LogObserver'."\n".'X-Priority: 1'."\n".'Importance: High'."\n".'X-MSMail-Priority: High';

		mail($devEmail, self::EMAIL_SUBJECT, $msg, $headers);

		return;
	}


	/**
	 * Get value from global $_SERVER array
	 * if it exists, otherwise return just an empty string
	 *
	 * @param string $var
	 *
	 * @return string value of $var or empty string
	 */
	protected static function getServerVar($var){
		return (array_key_exists($var, $_SERVER)) ? $_SERVER[$var] : '';
	}


	/**
	 * Get string representation of
	 * current timestamp
	 *
	 * @return string a formatted timestamp
	 */
	protected static function getTimeStamp(){
		return date(self::TIME_FORMAT).' ';
	}


	/**
	 * Get email address of developer
	 * if global constant LAMPCMS_DEVELOPER_EMAIL exists
	 * then return it, otherwise return this class's
	 * self::LAMPCMS_DEVELOPER_EMAIL
	 *
	 *
	 * @return string email address of developer
	 */
	protected static function getDevEmail(){
		return defined('LAMPCMS_DEVELOPER_EMAIL') ? LAMPCMS_DEVELOPER_EMAIL : self::LAMPCMS_DEVELOPER_EMAIL;
	}

}
