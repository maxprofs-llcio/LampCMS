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

namespace Lampcms\Controllers;

use \Lampcms\WebPage;
use \Lampcms\Request;
use \Lampcms\Responder;



class Emailoptions extends WebPage
{
	protected $membersOnly = true;

	protected $permission = 'edit_profile';

	protected $layoutID = 1;

	/**
	 * @var object of type Form
	 */
	protected $oForm;


	/**
	 * @todo Translate strings used in form using the
	 * $this->oForm->name = val setting
	 *
	 * @todo maybe send email on save() notifying
	 * that email settings has been updated
	 *
	 * (non-PHPdoc)
	 * @see Lampcms.WebPage::main()
	 */
	protected function main(){

		$email = $this->oRegistry->Viewer->email;
		$this->oForm = new \Lampcms\Forms\EmailOptions($this->oRegistry);
		$this->oForm->formTitle = $this->aPageVars['title'] = 'Your Email Subscription Preferences';
		$this->oForm->your_email = $email;

		if($this->oForm->isSubmitted()){
			$this->oRegistry->Dispatcher->post($this->oForm, 'onBeforeEmailOptionsUpdate');
			$this->savePrefs();
			$this->oRegistry->Dispatcher->post($this->oForm, 'onEmailOptionsUpdate');
			$this->aPageVars['body'] = '<div id="tools"><h3>Your email subscription preferences have been updated.</h3><p><a href="/emailoptions/">Your email preferences</a></p></div>';

		} else {
			$this->setForm();
			$this->aPageVars['body'] = $this->oForm->getForm();
		}
	}


	/**
	 * Save Email preferences in
	 * Viewer object and call save() to store
	 * to Database right away.
	 *
	 * This will set values of e_fu, e_fq, e_ft in USERS to
	 * either true or false, so the value will not be null
	 * it may become false but it will exist - it will
	 * not be considered null anymore
	 *
	 * @return object $this
	 */
	protected function savePrefs(){

		$formVals = $this->oForm->getSubmittedValues();
		d('formVals: '.print_r($formVals, 1));
		$oViewer = $this->oRegistry->Viewer;

		$oViewer['ne_fu'] = (empty($formVals['e_fu']));
		$oViewer['ne_fq'] = (empty($formVals['e_fq']));
		$oViewer['ne_ft'] = (empty($formVals['e_ft']));
		$oViewer['ne_fa'] = (empty($formVals['e_fa']));
		$oViewer['ne_fc'] = (empty($formVals['e_fc']));
		$oViewer['ne_ok'] = (empty($formVals['e_ok']));

		$oViewer->save();

		return $this;
	}


	/**
	 * Set the "checked" values of check boxes
	 * to the ones in Viewer object
	 *
	 * Value is considered checked in it is
	 * not spefically set to false by user
	 * by default there is no value in USERS collection
	 * for these settings, so it will be returned
	 * as null (but not false) from Viewer object
	 *
	 * Enter description here ...
	 */
	protected function setForm(){

		$this->oForm->e_fu = (true !== $this->oRegistry->Viewer->ne_fu) ? 'checked' : '';
		$this->oForm->e_ft = (true !== $this->oRegistry->Viewer->ne_ft) ? 'checked' : '';
		$this->oForm->e_fq = (true !== $this->oRegistry->Viewer->ne_fq) ? 'checked' : '';
		$this->oForm->e_fa = (true !== $this->oRegistry->Viewer->ne_fa) ? 'checked' : '';
		$this->oForm->e_fc = (true !== $this->oRegistry->Viewer->ne_fc) ? 'checked' : '';
		$this->oForm->e_ok = (true !== $this->oRegistry->Viewer->ne_ok) ? 'checked' : '';

		return $this;
	}
}
