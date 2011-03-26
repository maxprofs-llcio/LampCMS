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


namespace Lampcms;

use Lampcms\Interfaces\LampcmsResource;

/**
 *
 * This class is used for
 * adding or deleting a comment
 * as well as to modifying (editing)
 * the comment
 *
 * @todo finish deleting and modifying methods
 *
 * @author Dmitri Snytkine
 *
 */
class CommentParser extends LampcmsObject
{

	/**
	 * Resource for which this comment
	 * is being processed
	 * this will be either \Lampcms\Answer
	 * or \Lampcms\Question
	 * object
	 *
	 * @var object of type Lampcms\Answer
	 * or \Lampcms\Question but will implement Lampcms\LampcmsObject
	 */
	protected $oResource = null;

	/**
	 * Array of data for this one comment
	 *
	 * @var array
	 */
	protected $aComment;

	/**
	 * Array of one row in
	 * either QUESTIONS or ANSWERS collection
	 * The $this->oResource is created from this array
	 *
	 * @var array
	 */
	protected $aResource;

	/**
	 * Flag indicates that comment has been deleted
	 *
	 * @var bool
	 */
	protected $bDeleted;

	/**
	 * Object of SubmittedComment
	 * usually this is SubmittedCommentWWW object
	 * but could be any object implementing
	 * \Lampcms\Interfaces\SubmittedComment
	 *
	 * @var object implementing \Lampcms\Interfaces\SubmittedComment
	 */
	protected $oComment;


	public function __construct(Registry $oRegistry){
		$this->oRegistry = $oRegistry;
	}


	public function getArrayCopy(){
		if(empty($this->aComment) || empty($this->aComment['_id'])){
			throw new \LogicException('The comment data has not been set yet');
		}

		return $this->aComment;
	}


	/**
	 * Update i_lm_ts value
	 * as well as update i_lm_ts in affected
	 * Question
	 * This method will be used when
	 * comment is edited
	 *
	 * @throws \LogicException
	 */
	public function touch(){
		if(empty($this->aComment) || empty($this->aComment['_id'])){
			throw new \LogicException('The comment data has not been set yet');
		}

		$this->aComment['i_lm_ts'] = time();
		$this->touchQuestion();

		return $this;
	}


	/**
	 * Process submitted comment
	 * and add it to Resource
	 * and also insert meta data into COMMENTS collection
	 * meta includes id, resourceID, questionID, parentID,
	 * collection name
	 *
	 * @todo limit length of body to about 600 chars of plain text
	 *
	 * @param \Lampcms\Interfaces\SubmittedComment $oComment
	 * @throws \Lampcms\Exception
	 */
	public function add(\Lampcms\Interfaces\SubmittedComment $oComment){

		$this->oComment = $oComment;
		$this->oResource = $this->oComment->getResource();
		$this->checkCommentsLimit();

		$oCommentor = $this->oComment->getUserObject();
		$res_id = $this->oResource->getResourceId();
		$body = $this->oComment->getBody();
		$uid = $this->oComment->getOwnerId();

		$this->aComment['_id'] = $this->oComment->getResourceId();
		$this->aComment['i_res'] = $res_id;
		$this->aComment['i_qid'] = $this->oComment->getQuestionId();
		$this->aComment['b'] = $body;
		$this->aComment['username'] = $oCommentor->getDisplayName();
		$this->aComment['ip'] = $this->oComment->getIP();
		$this->aComment['i_uid'] = $uid;
		$this->aComment['i_prnt'] = $this->oComment->getParentId();
		$this->aComment['coll'] = $this->oComment->getCollectionName();
		$this->aComment['hash'] = hash('md5', $uid.$res_id.$body);
		$this->aComment['i_ts'] = time();
		$this->aComment['ts'] = date('r');
		$this->aComment['t'] = date('M j \'y \a\\t G:i'); // must escape t with 2 backslashes because \t means tab
		$this->aComment['avtr'] = $oCommentor->getAvatarSrc();

		/**
		 * If comment is made by the same user as Question owner
		 * we must add special flag so that
		 * later in template we can add special css class
		 * to indicate commentor is also question asker
		 */
		if($uid == $this->oResource->getQuestionOwnerId()){
			$this->aComment['b_owner'] = true;
		}


		/**
		 * Submitted comment object may provide
		 * extra data, for example Geo Location data
		 * and possibly name and url of API client
		 * that was used for submitting comment
		 * We add extra data to array here
		 */
		$this->aComments = array_merge($this->aComment, $this->oComment->getExtraData());

		d('$aComment '.print_r($this->aComment, 1));

		$this->oRegistry->Dispatcher->post($this->oComment, 'onBeforeNewComment', $this->aComment);

		$coll = $this->oRegistry->Mongo->COMMENTS;
		$coll->ensureIndex(array('hash' => 1), array('unique' => true));
		$coll->ensureIndex(array('i_uid' => 1));


		try{
			/**
			 * Remove unnecessary elements from $this->aComment array
			 * and keep on the the keys we need in COMMENTS collection
			 */
			$aKeys = array('_id', 'hash', 'i_res', 'i_qid', 'i_uid', 'ip', 'i_ts', 'i_prnt', 'coll');
			$aData = array_intersect_key($this->aComment, array_flip($aKeys));
			$coll->insert($aData, array('fsync' => true));
		} catch(\MongoException $e){
			e('unable to created record in COMMENTS collection '.$e->getMessage());
			throw new \Lampcms\Exception('It looks like you have already posted this comment');
		}

		$this->oResource->addComment($this)->save();
		$this->touchQuestion();

		$this->oRegistry->Dispatcher->post($this->oComment, 'onNewComment', $this->aComment);


		return $this;
	}


	/**
	 * Usually there is a limit to how many comments an item
	 * can have. This is to prevent run-away discussion
	 *
	 * @return object $this
	 *
	 * @throws \Lampcms\Exception if Resource already
	 * has reached the comments limit
	 */
	protected function checkCommentsLimit(){
		if(0 !== $limit = (int)$this->oRegistry->Ini->MAX_COMMENTS){
			if($this->oResource->getCommentsCount() > $limit){
				throw new \Lampcms\Exception('Unable to add comment because the limit of '.$limit.' comments per item has been reached.<br>Consider adding another answer instead');
			}
		} else {
			throw new \Lampcms\Exception('Comments feature has been disabled by administrator');
		}

		return $this;
	}


	/**
	 *
	 * Process edited comment
	 * It will: get comment record from COMMENTS
	 * check that comment is not older than 5 minutes (in !config.ini)
	 * Check permission IF $uid is passed here
	 *
	 * Get Resource, replace the value of 'b'
	 * in the corresponding comment,
	 * add editor and i_editor values IF edits not by owner
	 * add edit_ts value of date/time
	 *
	 * updates COMMENTS collection with i_edit_ts value
	 *
	 * touchQuestion()
	 *
	 * @todo check timeDiff, post onBefore and onEdit events
	 *
	 * @todo Update COMMENTS collection
	 *
	 * @todo better permissions check. Right now deleted or suspender
	 * user may be able to edit own comment is this OK?
	 *
	 * @param \Lampcms\Interfaces\SubmittedComment $oComment
	 * @param mixed (int|null) $viewerID userID of editor IF NOT by moderator
	 * so that we can check the ownership of comment here
	 *
	 * @return object $this
	 */
	public function edit(\Lampcms\Interfaces\SubmittedComment $oComment, $viewerID = null){
		$this->oComment = $oComment;
		$id = $oComment->getResourceId();

		$this->findCommentRecord($id)
		->checkIsOwner($viewerID)
		->getResourceArray()
		->checkEditTimeout($viewerID)
		->makeResourceObject();


		$body = $oComment->getBody();

		$aComments = $this->oResource->getComments();
		$bEdited = false;
		if(!empty($aComments)){
			for($i = 0; $i<count($aComments); $i+=1){
				if($aComments[$i]['_id'] == $id){
					$oEditor =  $this->oComment->getUserObject();
					$date = date('r');
					$editor =$oEditor->getDisplayName();
					$editor_url = $oEditor->getProfileUrl();
					$uid = $oEditor->getUid();

					d('comment found: '.$i);
					$aComments[$i]['b'] = $body;
					$aComments[$i]['e'] = '<a class="ce" href="'.$editor_url.'"><span class="ico edited tu" title="This comment was edited by '.$editor.' on '.$date.'"></span></a>';
					$bEdited = true;
					break;
				}
			}
		}

		/**
		 * Need to add 'b' and 'username' and 'hts' and 't'
		 * to $this->aComment.
		 * This values are needed by template
		 * These values are present only inside
		 * the comment array in Resource and not
		 * in the COMMENTS collection, so we need
		 * to merge these now so that the result array
		 * will have all required values
		 *
		 */
		$this->aComment = array_merge($this->aComment, $aComments[$i]);
		$this->oRegistry->Dispatcher->post($this->oResource, 'onBeforeCommentEdit', $aComments[$i]);

		if($bEdited){
			d('changes made');
			$this->oRegistry->Mongo->COMMENTS->update(array('_id' => $id),
			array('$set' => array('i_lm_ts' => time(), 'i_editor' => $viewerID)));

			$this->oResource['comments'] = $aComments;
			$this->touchQuestion();
			$this->oResource->save();
			d('changes saved to resource');
			$this->oRegistry->Dispatcher->post($this->oResource, 'onCommentEdit', $aComments[$i]);

		}


		return $this;
	}



	protected function checkEditTimeout($viewerID){
		if(null === $viewerID){
			d('Timeout does not apply to user with edit_comment permission');

			return $this;
		}

		$timeout = $this->oRegistry->Ini->COMMENT_EDIT_TIME;
		if(empty($timeout)){
			d('edit timeout disabled in !config.ini');

			return $this;
		}


		if((time() -  $this->aComment['i_ts']) > ($timeout * 60)){
			throw new \Lampcms\Exception('You cannot edit comments that are older than '.$timeout.' minutes');
		}

		return $this;
	}


	/**
	 * Update i_lm_ts (Last modified timestamp) of
	 * the question that is affected by this comment
	 * The Question is affected when comment is
	 * added to one of the questions's answer
	 * in such case we update i_lm_ts value
	 * directly in the QUESTIONS collection
	 *
	 * If the comment is added to a Question resource
	 * then we already have the $this->oResource
	 * and can just call touch() on it
	 *
	 * @return object $this
	 */
	protected function touchQuestion(){

		if($this->oResource instanceof \Lampcms\Question){
			$this->oResource->touch();
		} elseif($this->oResource instanceof \Lampcms\Answer){
			try{
				$this->oRegistry->Mongo->QUESTIONS
				->update(array('_id' => $this->oResource['i_qid']), array('$set' => array('i_lm_ts' => time())));
			} catch(\MongoException $e){
				e('Unable to update question '.$e->getMessage());
			}
		}

		return $this;
	}


	/**
	 *
	 * Removes record from COMMENTS
	 * and removes comment from array of comments
	 * from one resource
	 * Posts onBeforeDeleteComment
	 * and onDeleteComment events
	 *
	 * @param int id id of comment
	 *
	 * @param int $viewerID id of Viewer
	 * If passed then a check is performed to make
	 * sure that comment is owned by this userID and if not,
	 * the exception is thrown.
	 * If it's determined that viewer already has the permission
	 * to delete a comment then don't pass any value for this param
	 * and the check of ownership will not be performed
	 *
	 * @return object $this
	 *
	 * @throws \Lampcms\Exception if comment not found by id
	 * of if resource that this comment belongs to is not found
	 */
	public function delete($id, $viewerID = null){
		$id = (int)$id;
		$this->findCommentRecord($id)
		->checkIsOwner($viewerID)
		->getResourceArray()
		->makeResourceObject();

		$this->oRegistry->Dispatcher->post($this->oResource, 'onBeforeDeleteComment', $this->aComment);

		$this->oRegistry->Mongo->COMMENTS->remove(array('_id' => $id));

		$this->oResource->deleteComment($id);
		$this->touchQuestion();

		$this->oRegistry->Dispatcher->post($this->oResource, 'onDeleteComment', $this->aComment);

		return $this;
	}


	/**
	 * Instantiate resource object from the $this->aResource array
	 *
	 * @return object $this
	 */
	protected function makeResourceObject(){

		$class = ('QUESTIONS' === $this->aComment['coll']) ? '\\Lampcms\\Question' : '\\Lampcms\\Answer';

		$this->oResource = new $class($this->oRegistry, $this->aResource);
		d('$this->oResource: '.$this->oResource->getClass());

		return $this;
	}


	protected function getResourceArray(){
		$this->aResource = $this->oRegistry->Mongo->getCollection($this->aComment['coll'])
		->findOne(array('_id' => $this->aComment['i_res']));

		if(empty($this->aResource)){
			throw new \Lampcms\Exception('Unable to delete comment because commented item not found');
		}

		return $this;
	}


	/**
	 * Check that id of $viewerID param
	 * is the id of comment owner (from the COMMENTS i_uid)
	 *
	 *
	 * @param int $viewerID value of userid of Viewer
	 * @throws AccessException
	 *
	 * @return object $this
	 */
	protected function checkIsOwner($viewerID){
		d('$viewerID: '.var_export($viewerID, true));

		if( (null !== $viewerID) && ((int)$viewerID !== $this->aComment['i_uid'])){
			throw new AccessException('Action failed because you are not the author of this comment.');
		}

		return $this;
	}


	/**
	 * Find and set array of one comment record
	 * $this->aComments
	 * from the COMMENTS collection
	 *
	 * @param int $id value of comment id
	 *
	 * @throws \Lampcms\Exception if record not found
	 *
	 * @return object $this
	 */
	protected function findCommentRecord($id){
		$this->aComment = $this->oRegistry->Mongo->COMMENTS->findOne(array('_id' => $id));
		if(empty($this->aComment)){
			throw new \Lampcms\Exception('Unable to delete comment because comment not found');
		}

		return $this;
	}


	/**
	 * Get Resource from COMMENTS by rid
	 * postOnBefore,
	 * insert like into VOTES
	 * update i_likes in Resource[comments][$i]
	 * post onCommentLike
	 *
	 * @todo this can be done without creating
	 * Resource object by using in-place update
	 * of nested array element right in Mongo
	 * @see http://www.mongodb.org/display/DOCS/Updating#Updating-The%24positionaloperator
	 *
	 *
	 *
	 * Enter description here ...
	 */
	public function addLike(\Lampcms\Interfaces\SubmittedComment $oComment){
		$this->oComment = $oComment;
		$id = $oComment->getResourceId();
		$this->oRegistry->Dispatcher->post($oComment, 'onBeforeCommentLike');

		/**
		 * In case of duplicate vote
		 * OR vote for own comment
		 * the \LogicException will be thrown
		 * in which case we just don't record
		 * the "Like" but it will not
		 * generate any errors to the user
		 */
		try{
			$this->findCommentRecord($id)
			->getResourceArray()
			->addCommentLike($id)
			->makeResourceObject();
		} catch (\LogicException $e){
			d($e->getMessage());
			return;
		}

		$bEdited = false;
		$aComments = $this->oResource->getComments();

		if(!empty($aComments)){
			for($i = 0; $i<count($aComments); $i+=1){
				if($aComments[$i]['_id'] == $id){
					d('comment found: '.$i);
					if(empty($aComments[$i]['i_likes'])){
						$aComments[$i]['i_likes'] = 1;
					} else {
						$aComments[$i]['i_likes'] += 1;
					}

					$bEdited = true;
					break;
				}
			}
		}

		if($bEdited){
			$this->oResource['comments'] = $aComments;
			$this->touchQuestion();
			$this->oResource->save();
			d('changes saved to resource');
			$this->oRegistry->Dispatcher->post($this->oResource, 'onCommentLike', $aComments[$i]);
		}

		return $this;
	}


	/**
	 * Insert record into COMMENTS_LIKES collection
	 * also serves as a check for duplicate likes
	 * since uid,i_res is unique
	 *
	 *
	 * @param int $id id of comment
	 *
	 * @return object $this
	 *
	 */
	protected function addCommentLike($resID){

		$uid = $this->oComment->getUserObject()->getUid();
		$ownerID = $this->aComment['i_uid'];

		if($uid == $ownerID){
			throw new \LogicException('Likes of own comment do not cound');
		}

		$coll = $this->oRegistry->Mongo->getCollection('COMMENTS_LIKES');
		$coll->ensureIndex(array('i_uid' => 1));
		$coll->ensureIndex(array('i_owner' => 1));

		$id = $uid.'.'.$resID;

		$aData = array(
			'_id' => $id,
			'i_uid' => $uid,
			'i_res' => $resID,
			'i_ts' => time(),
			'i_owner' => $ownerID
		);

		d('aData: '.print_r($aData, 1));

		try{
			$coll->insert($aData, array('safe' => true));
		} catch(\MongoException $e){
			d('Unable to add record to COMMENTS_LIKES collection: '.$e->getMessage());
			throw new \LogicException('Duplicate Like detected');
		}

		return $this;
	}

}
