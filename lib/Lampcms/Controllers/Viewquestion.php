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


namespace Lampcms\Controllers;

use Lampcms\Request;

use \Lampcms\WebPage;
use \Lampcms\Paginator;
use \Lampcms\CacheHeaders;
use \Lampcms\Badwords;
use \Lampcms\Question;
use \Lampcms\Answers;
use \Lampcms\Template\Urhere;
use \Lampcms\Forms\Answerform;
use \Lampcms\QuestionInfo;
use \Lampcms\Responder;
use \Lampcms\SocialCheckboxes;

/**
 * Controller for displaying
 * a Question, Answers to a question (paginated)
 * and an Answer form
 * as well as adding Question Info block
 *
 *
 * @author Dmitri Snytkine
 *
 */
class Viewquestion extends WebPage
{

	protected $aRequired = array('qid');


	/**
	 * Array with question data
	 * The oQuestion is created from this array
	 * but even though we can use oQuestion to
	 * get all the data, it may be faster to
	 * get data from array, so why not have
	 * them both in this object at a tiny
	 * overhead
	 *
	 * @var array
	 */
	protected $aQuestion;

	/**
	 * Object representing the question
	 * that we currently viewing
	 *
	 * @var object of type Question
	 */
	protected $oQuestion;

	/**
	 * The "Answer" form object
	 *
	 * @var object of type Answerform which is a Form
	 */
	protected $oForm;

	protected $aTplVars = array();

	protected $pageID = 1;


	/**
	 * Flag indicates that comments have
	 * been disabled, causing a special 'nocomments' css
	 * class to be added to answer template, hiding
	 * "comments" div, which also hides the "add comments" link
	 *
	 * @var bool
	 */
	protected $noComments = false;

	/**
	 * Sort by: i_lm_ts, i_ts or i_score
	 * this value is used to generate cache headers
	 * as each sort condition generates
	 * different page content, thus should be
	 * included in generating the "etag"
	 *
	 * @var string
	 */
	protected $tab = 'i_lm_ts';

	/**
	 * html of parsed answers
	 *
	 *
	 * @var string
	 */
	protected $answers = '';


	/**
	 * Number of total answers for this question
	 *
	 *
	 * @var int
	 */
	protected $numAnswers = 0;


	/**
	 * Main entry point
	 * (non-PHPdoc)
	 * @see WebPage::main()
	 */
	protected function main(){
		if(Request::isAjax()){
			$this->getQuestion()->getAnswers();
			Responder::sendJSON(array('paginated' => $this->answers));
		}



		$this->pageID = $this->oRegistry->Request->get('pageID', 'i', 1);
		$this->tab = $this->oRegistry->Request->get('sort', 's', 'i_lm_ts');
		$this->oRegistry->registerObservers();

		$this->getQuestion()
		->addMetas()
		->sendCacheHeaders()
		->configureEditor()
		->setTitle()
		->addMetaTags()
		->setAnswersHeader()
		->getAnswers()
		->setAnswers()
		->setSimilar()
		->makeForm()
		->setAnswerForm()
		->makeFollowButton()
		->setFollowersBlock()
		->setQuestionInfo()
		->setFooter()
		->increaseView()
		->makeTopTabs();

		$this->oRegistry->Dispatcher->post($this->oQuestion, 'onQuestionView');
	}

	
	/**
	 * Add extra meta tags to indicate
	 * that user has or does not have
	 * blogger and tumblr Oauth keys
	 *
	 * @return object $this
	 */
	protected function addMetas(){
		$this->addMetaTag('tm', (null !== $this->oRegistry->Viewer->getTumblrToken()));
		$this->addMetaTag('blgr', (null !== $this->oRegistry->Viewer->getBloggerToken()));

		return $this;
	}

	/**
	 * Adds block with info about
	 * this question to the
	 * $this->aPageVars['side']
	 *
	 * @return object $this
	 */
	protected function setQuestionInfo(){

		$this->aPageVars['side'] .= QuestionInfo::factory($this->oRegistry)->getHtml($this->oQuestion);

		return $this;
	}


	/**
	 * If this question has any followers
	 * then add block with
	 * some followers' avatars
	 *
	 * @return object $this
	 */
	protected function setFollowersBlock(){
		$aFlwrs = $this->oQuestion['a_flwrs'];
		$count = count($aFlwrs);
		if($count > 0){
			$s = \Lampcms\ShowFollowers::factory($this->oRegistry)->getQuestionFollowers($aFlwrs, $count);
			d('followers: '.$s);
			$this->aPageVars['side'] .= '<div class="fr cb w90 lg rounded3 pl10 mb10">'.$s.'</div>';
		}

		return $this;
	}


	/**
	 * Add meta tags 'qid' and 'lmts' for
	 * last modified timestamp
	 * The javascript will be able to query the server
	 * to check for the new answers for this qid since that lmts
	 * Some of these meta tags will be used by JavaScript
	 * to determine if viewer has permissions to comment
	 *
	 * @return object $this
	 */
	protected function addMetaTags(){
		$this->addMetaTag('lmts', $this->oQuestion['i_lm_ts']);
		$this->addMetaTag('qid', $this->oQuestion['_id']);
		$this->addMetaTag('asker_id', $this->oQuestion->getOwnerId());
		$this->addMetaTag('etag', $this->oQuestion['i_etag']);
		$this->addMetaTag('min_com_rep', \Lampcms\Points::COMMENT);
		$this->addMetaTag('comment', $this->oRegistry->Viewer->isAllowed('comment'));

		return $this;
	}


	/**
	 * Get array for this one question,
	 * set $this->oQuestion
	 * and also set $this->aTplVars['body']
	 * with parsed tplQuestion block
	 *
	 *
	 * @throws Lampcms404Exception if question not found
	 *
	 * @return object $this
	 */
	protected function getQuestion(){
		$isModerator = $this->oRegistry->Viewer->isModerator();
		$this->noComments = (false === (bool)$this->oRegistry->Ini->MAX_COMMENTS);
		d('no comments: '.$this->noComments);
		$aFields = ($this->noComments || false === (bool)$this->oRegistry->Ini->SHOW_COMMENTS) ? array('comments' => 0) : array();

		$this->aQuestion = $this->oRegistry->Mongo->QUESTIONS->findOne(array('_id' => (int)$this->oRequest['qid']), $aFields);

		/**
		 * @todo Translate string
		 */
		if(empty($this->aQuestion)){
			throw new \Lampcms\Lampcms404Exception('Question not found');
		}
		d('$this->aQuestion: '.print_r($this->aQuestion, 1));

		/**
		 * Only moderators can see ip address
		 */
		if(!$isModerator && !empty($this->aQuestion['ip'])){
			unset($this->aQuestion['ip']);
		}

		$deleted = (!empty($this->aQuestion['i_del_ts'])) ?  ' deleted' : false;

		/**
		 * Guests will have to see filtered
		 * content
		 */
		if(!$this->isLoggedIn()){
			$this->aQuestion['b'] = Badwords::filter($this->aQuestion['b'], true);
		}

		$this->oQuestion = new Question($this->oRegistry, $this->aQuestion);

		/**
		 * @todo Translate string
		 */
		if($deleted){
			if(!$isModerator && !\Lampcms\isOwner($this->oRegistry->Viewer, $this->oQuestion)){
				throw new \Lampcms\Lampcms404Exception('Question was deleted on '.date('F j Y', $this->aQuestion['i_del_ts']));
			}

			/**
			 * If Viewer is moderator, then viewer
			 * will be able to view deleted item
			 * This will add 'deleted' class to question table
			 */

			$this->aQuestion['deleted'] = $deleted;
			if(!empty($this->aQuestion['a_deleted'])){
				$this->aQuestion['deletedby'] = \tplDeletedby::parse($this->aQuestion['a_deleted'], false);
				d('deletedby: '.$this->aQuestion['deletedby']);
			}
		}

		if($this->noComments){
			$this->aQuestion['nocomments'] = ' nocomments';
		}

		$this->aPageVars['body'] = \tplQuestion::parse($this->aQuestion);

		return $this;
	}


	/**
	 * Create header div for answers block.
	 * This div is independant of answers
	 * block and contains word "Answers",
	 * count of answers and some 'sort by'
	 * tabs
	 *
	 * @todo translate word 'Answer'
	 *
	 * @return object $this
	 */
	protected function setAnswersHeader(){
		$tabs = '';

		/**
		 * Does not make sense to show
		 * any type of 'sort by' when there is
		 * only 1 answer or no answers at all
		 */
		if($this->oQuestion['i_ans'] > 1){
			$cond = $this->oRegistry->Request->get('sort', 's', 'i_lm_ts');
			$tabs = Urhere::factory($this->oRegistry)->get('tplAnstypes', $cond);
		}

		$aVars = array(
		$this->oQuestion['i_ans'],
		'Answer'.$this->oQuestion['ans_s'],
		$tabs
		);

		$this->aPageVars['body'] .= \tplAnswersheader::parse($aVars, false);

		return $this;
	}


	/**
	 * Set page title meta and h2 tag
	 *
	 * @return object $this
	 */
	protected function setTitle(){
		$title = $this->oQuestion['title'];
		$title = (!$this->isLoggedIn()) ? Badwords::filter($title) : $title;

		/**
		 * @todo Translate string "closed"
		 */
		if(!empty($this->aQuestion['a_closed'])){
			$title .= ' [closed]';
		}
		$this->aPageVars['title'] = $title;
		$this->aPageVars['qheader'] = '<h1>'.$title.'</h1>';

		return $this;
	}


	/**
	 * Send out HTTP Cache control Headers
	 *
	 */
	protected function sendCacheHeaders(){

		/**
		 * In case there is a login_error
		 * the user is redirected to this question
		 * via redirect header and if we don't do this check
		 * then the browser will display the cached version
		 * and user will never see login error
		 * in login form
		 */
		if(!empty($_SESSION['login_error'])){
			return $this;
		}

		$latestReplyTime = $this->oQuestion->getEtag();
		$userHash = $this->oRegistry->Viewer->hashCode();
		d('user Hash: '.$userHash);
		$etag = '"'.hash('md5', $this->oRequest['qid'].'-'.$this->pageID.$this->tab.'-'.$latestReplyTime.'-' .$userHash).'"';
		//$lastModified = gmdate("D, d M Y H:i:s", $latestReplyTime)." GMT";

		CacheHeaders::processCacheHeaders($etag); //, $lastModified

		return $this;
	}


	/**
	 * Set answers title bar with
	 * sort tags
	 * and under it add div id="answers"
	 * and insert answers html into it
	 * use Answers::get(oQuestion, $sort, $pageID)
	 * it should return html with all answers
	 *
	 * OR if there are no answers, then a text saying
	 * "Be the first to answer this question"
	 * inside a special div with special id so that
	 * when same user posts and gets json reply
	 * we can remove that special div from page
	 *
	 * @return object $this
	 *
	 */
	protected function setAnswers(){

		$tpl = '<div id="answers" class="sortable paginated fl cb w100" lampcms:total="%1$s" lampcms:perpage="%2$s">%3$s</div><!-- // answers -->';
		$this->aPageVars['body'] .= vsprintf($tpl, array($this->numAnswers, $this->oRegistry->Ini->PER_PAGE_ANSWERS, $this->answers));

		return $this;
	}


	/**
	 * Uses the Answers class to get the
	 * block of parsed answers (in html)
	 * It will automatically apply pagination if necessary,
	 * add pagination links and return the content of just one page
	 * of answers. It's just that smart!
	 *
	 * Sets values of $this->answers
	 * and $this->numAnswers
	 *
	 * @return object $this
	 */
	protected function getAnswers(){
		$this->answers = '';
		$this->numAnswers = $this->oQuestion['i_ans'];
		if($this->numAnswers > 0 || $this->oRegistry->Viewer->isModerator()){
			$this->answers = Answers::factory($this->oRegistry)->getAnswers($this->oQuestion);
		}

		/**
		 * Every page with question must have 'answers' div,
		 * even if there are currently no answers and the div
		 * is empty.
		 *
		 * This is so that new answer could be added via ajax,
		 * via "answer" form OR when we periodically call server
		 * via ajax to check for new answers we may display a message
		 * like '2 answers... click to load', similar
		 * to Twitter timeline
		 *
		 *
		 *
		 */

		if(!empty($this->answers) && !$this->isLoggedIn()){
			$this->answers = Badwords::filter($this->answers, true);
		}

		return $this;
	}


	/**
	 * Set similars:
	 * similar questions block in
	 * right column
	 *
	 * @return object $this
	 */
	protected function setSimilar(){

		if(!empty($this->aQuestion['sim_q'])){
			$sim = \tplBoxrecent::parse(array('Similar questions', 'recent-tags', $this->aQuestion['sim_q']), false);
			$this->aPageVars['tags'] = $sim;
		}

		return $this;
	}


	/**
	 * Add 'Answer' form to template's body
	 *
	 * @return object $this
	 */
	protected function setAnswerForm(){

		$this->aPageVars['body'] .= $this->oForm->getAnswerForm($this->oQuestion);

		return $this;
	}


	/**
	 * Sets the $this->oForm object
	 * We use it to get html of answer form (or empty
	 * string if question is closed to new answers)
	 *
	 * Also in sub-class wwwAnswer we use it for
	 * parsing submitted answer form
	 *
	 * @return object $this
	 */
	protected function makeForm(){
		$this->oForm = Answerform::factory($this->oRegistry);
		$this->oForm->socials = SocialCheckboxes::get($this->oRegistry);

		return $this;
	}


	/**
	 * Adds small extra text to the bottom of
	 * answer form.
	 *
	 * @todo translate text
	 *
	 * @return object $this
	 */
	protected function setFooter(){

		if($this->oQuestion['i_ans'] > 0){
			$text = 'Explore other questions tagged %1$s or <a href="/ask/">ask your own question</a>';
		} else {
			$text = '';
		}

		return $this;
	}


	/**
	 * Must check if current user has already viewed this question,
	 * if not then update QUESTIONS_VIEWS per user collection
	 * and call increaseViews() on oQuestion
	 *
	 * For not-logged-in users use some other method like
	 * checking ip address or maybe just per-session
	 * Like use session_id() value in per user views
	 *
	 * This way even if user not logged in, then loggs in
	 * the view will still count only once!
	 *
	 */
	protected function increaseView(){
		$this->oQuestion->increaseViews($this->oRegistry->Viewer);

		return $this;
	}


	protected function makeTopTabs(){

		$tabs = Urhere::factory($this->oRegistry)->get('tplToptabs', 'questions');
		$this->aPageVars['topTabs'] = $tabs;

		return $this;
	}


	/**
	 * Makes the button to "Follow" or "Following"
	 * for this question and sets this html as value
	 * of $this->aPageVars['side']
	 *
	 * @return object $this
	 */
	protected function makeFollowButton(){

		$qid = $this->oQuestion->getResourceId();

		$aVars = array(
		'id' => $qid,
		'icon' => 'cplus',
		'label' => 'Follow this question',
		'class' => 'follow',
		'type' => 'q',
		'title' => 'Follow this question to be notified of new answers, comments and edits'
		);


		if(in_array($this->oRegistry->Viewer->getUid(), $this->oQuestion['a_flwrs'])){
			$aVars['label'] = 'Following';
			$aVars['class'] = 'following';
			$aVars['icon'] = 'check';
			$aVars['title'] = 'You are following this question';
		}

		$this->aPageVars['side'] = '<div class="fr cb w90 lg rounded3 pl10 mb10"><div class="follow_wrap">'.\tplFollowButton::parse($aVars, false).'</div></div>';

		return $this;
	}

}
