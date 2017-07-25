<?php
if(!defined('DISABLEDEFENSE'))  exit('Access Denied!');
require_once 'table/table_bbssdk_thread.php';
require_once 'table/table_bbssdk_forum.php';

class Forum extends BaseCore
{
	private $MarkdownToHtml;
	function __construct()
	{
		$this->MarkdownToHtml = new Markdown();
		parent::__construct();
	}

	public function get_list()
	{
		global $_G;
		$data = array();
		
		$fid = intval($_REQUEST['fid']);
		if(!$fid) return_status(403);

		$pagesize = intval($_REQUEST['pagesize']);
		$pagesize = $pagesize ? $pagesize : 10;
		if( $pagesize > 20) $pagesize = 10;
		
		$page = intval($_REQUEST['page'])>0 ? intval($_REQUEST['page']) : 1;
		$start = ($page - 1) * $pagesize;

		
		$data['total_count'] =  c::t('bbssdk_thread')->count_by_fid($fid);
		$data['pagesize'] = $pagesize;
		$data['currpage'] = $page;
		$total_page = ceil($data['total_count']/$pagesize);
		$data['nextpage'] = $page+1 <= $total_page ? $page+1 : $total_page;
		$data['prepage'] = $page-1>0 ? $page-1 : 1;

		$list = array();

		if($data['currpage'] <= $data['nextpage']){

			$where = 'fid = '. $fid;
			try{
				$thread_list = c::t('bbssdk_thread')->range_list($where , $start, $pagesize);
				$tids = array();
				foreach ($thread_list as $k=>$item) {
					array_push($tids, $item['tid']);
				}

				if(count($tids) > 0 ){
					$query_message = c::t('bbssdk_forum')->fetch_threadpost_by_tid($tids);

					$messages = array();
					foreach ($query_message as $item) {
						$messages[$item['tid']] = $item;
					}
					
					foreach ($thread_list as $k=>$item) {
						$list[$k] = $this->relation_item($item, $messages[$item['tid']]);
					}

				}
			}catch(\Exception $e){
				write_log('Forum Error:'.$e);
				return_status(500);
			}
		}

		$data['list'] = $list;

		$this->success_result($data);
	}

	public function get_item()
	{
		$fid = intval($_REQUEST['fid']);
		$tid = intval($_REQUEST['tid']);
		if(!$fid || !$tid) return_status(403);

		$item = c::t('forum_thread')->fetch_by_tid_displayorder($tid);
		
		$current = c::t('forum_post')->fetch_threadpost_by_tid_invisible($item['tid']);

		$data = $this->relation_item($item,$current);

		if($data['fid'] != $fid || $data['tid'] != $tid) $data = null;

		$this->success_result($data);
	}

	public function getItem($fid,$tid)
	{
		$fid = intval($fid);
		$tid = intval($tid);
		if(!$fid || !$tid) return_status(403);

		$item = c::t('forum_thread')->fetch_by_tid_displayorder($tid);
		
		$current = c::t('forum_post')->fetch_threadpost_by_tid_invisible($item['tid']);

		$data = $this->relation_item($item,$current);

		if($data['fid'] != $fid || $data['tid'] != $tid) $data = null;

		return $data;
	}

	public function post_item()
	{
		global $_G;
		$fid = intval($this->fid);
		$uid = intval($this->uid);
		$clientip = $this->clientip;
		$subject = urldecode($this->subject);
		$message = htmlspecialchars_decode($this->bbcode_encode($this->message));

		if(!$fid || !$uid || empty($clientip) || empty($subject) || empty($message)){
			return_status(403);
		}

		$_G['uid'] = $uid;

		$member = getuserbyuid($uid, 1);
		C::app()->var['member'] = $member;
		$_G['groupid'] = $groupid = $member['groupid'];
		$groupid > 0 && $authAll = DB::fetch_all("select * from ".DB::table('common_usergroup')." a LEFT JOIN ".DB::table('common_usergroup_field')." b on a.groupid=b.groupid where a.groupid in($groupid)");
		count($authAll)>0 && C::app()->var['group'] = $authAll[0];

		$authForum = C::t('forum_forum')->fetch_all_info_by_fids($fid);
		if(count($authForum) > 0 ) {
			$tmpForum = $authForum[$fid];
			if(!empty($tmpForum['threadtypes'])) $tmpForum['threadtypes'] = unserialize($tmpForum['threadtypes']);
			if(!empty($tmpForum['formulaperm'])) $tmpForum['formulaperm'] = unserialize($tmpForum['formulaperm']);
			C::app()->var['forum'] = $tmpForum;
		}

		if($_G['setting']['connect']['allow'] && $_G['setting']['accountguard']['postqqonly'] && !$_G['member']['conisbind']) {
			return_status(405,'为避免您的帐号被盗用，请您绑定QQ帐号后发帖，绑定后请使用QQ帐号登录');
		}

		if(!$_G['uid'] && !((!$_G['forum']['postperm'] && $_G['group']['allowpost']) || ($_G['forum']['postperm'] && forumperm($_G['forum']['postperm'])))) {
				return_status(405,'抱歉，您尚未登录，没有权限在该版块发帖');
		} elseif(empty($_G['forum']['allowpost'])) {
			if(!$_G['forum']['postperm'] && !$_G['group']['allowpost']) {
				return_status(405,'抱歉，您没有权限在该版块发帖');
			} elseif($_G['forum']['postperm'] && !forumperm($_G['forum']['postperm'])) {
				return_status(405,'抱歉，您没有权限在该版块发帖');
			}
		} elseif($_G['forum']['allowpost'] == -1) {
			return_status(405,'post_forum_newthread_nopermission', NULL);
		}

		if(!$_G['uid'] && ($_G['setting']['need_avatar'] || $_G['setting']['need_email'] || $_G['setting']['need_friendnum'])) {
			return_status(405,'抱歉，您尚未登录，没有权限在该版块发帖');
		}

		loadcache(array('bbcodes_display', 'bbcodes', 'smileycodes', 'smilies', 'smileytypes', 'domainwhitelist', 'albumcategory'));

		$modthread = C::m('forum_newthread',$fid);
		$bfmethods = $afmethods = array();

		$params = array(
			'subject' => $subject,
			'message' => $message,
			'typeid' => 0, //主题分类id
			'sortid' => 0, //分类信息id
			'special' => 0,	//特殊主题
			'clientip' => $clientip
		);

		$params['publishdate'] = $_G['timestamp'];

		$params['digest'] = 0;

		$params['tags'] = '';
		$params['bbcodeoff'] = 0;
		$params['smileyoff'] = 0;
		$params['htmlon'] = 0;

		$threadsorts = $modthread->forum('threadsorts');

		if(!is_array($threadsorts)){
			$threadsorts = array(
				'expiration'=>array()
			);
			$modthread->forum('threadsorts',$threadsorts);
		}

		$return = $modthread->newthread($params);
		$tid = $modthread->tid;
		$pid = $modthread->pid;

		$item = c::t('forum_thread')->fetch_by_tid_displayorder($tid);
		
		$current = c::t('forum_post')->fetch_threadpost_by_tid_invisible($item['tid']);

		$data = $this->relation_item($item,$current);

		$this->success_result($data);
	}

	public function put_item()
	{
		global $_G;
		$fid = intval($this->fid);
		$uid = intval($this->uid);
		$tid = intval($this->tid);
		$clientip = $this->clientip;
		$subject = urldecode($this->subject);
		$message = htmlspecialchars_decode($this->bbcode_encode($this->message));

		if(!$fid || !$uid || !$tid || empty($clientip) || empty($subject) || empty($message)){
			return_status(403);
		}

		$_G['uid'] = $uid;

		$member = getuserbyuid($uid, 1);
		C::app()->var['member'] = $member;
		$_G['groupid'] = $groupid = $member['groupid'];
		$groupid > 0 && $authAll = DB::fetch_all("select * from ".DB::table('common_usergroup')." a LEFT JOIN ".DB::table('common_usergroup_field')." b on a.groupid=b.groupid where a.groupid in($groupid)");
		count($authAll)>0 && C::app()->var['group'] = $authAll[0];

		$authForum = C::t('forum_forum')->fetch_all_info_by_fids($fid);
		if(count($authForum) > 0 ) {
			$tmpForum = $authForum[$fid];
			if(!empty($tmpForum['threadtypes'])) $tmpForum['threadtypes'] = unserialize($tmpForum['threadtypes']);
			if(!empty($tmpForum['formulaperm'])) $tmpForum['formulaperm'] = unserialize($tmpForum['formulaperm']);
			C::app()->var['forum'] = $tmpForum;
		}

		if($_G['setting']['connect']['allow'] && $_G['setting']['accountguard']['postqqonly'] && !$_G['member']['conisbind']) {
			return_status(405,'为避免您的帐号被盗用，请您绑定QQ帐号后发帖，绑定后请使用QQ帐号登录');
		}

		if(!$_G['uid'] && !((!$_G['forum']['postperm'] && $_G['group']['allowpost']) || ($_G['forum']['postperm'] && forumperm($_G['forum']['postperm'])))) {
				return_status(405,'抱歉，您尚未登录，没有权限在该版块发帖');
		} elseif(empty($_G['forum']['allowpost'])) {
			if(!$_G['forum']['postperm'] && !$_G['group']['allowpost']) {
				return_status(405,'抱歉，您没有权限在该版块发帖');
			} elseif($_G['forum']['postperm'] && !forumperm($_G['forum']['postperm'])) {
				return_status(405,'抱歉，您没有权限在该版块发帖');
			}
		} elseif($_G['forum']['allowpost'] == -1) {
			return_status(405,'抱歉，本版块只有特定用户组可以发新主题', NULL);
		}

		if(!$_G['uid'] && ($_G['setting']['need_avatar'] || $_G['setting']['need_email'] || $_G['setting']['need_friendnum'])) {
			return_status(405,'抱歉，您尚未登录，没有权限在该版块发帖');
		}
		
		loadcache(array('bbcodes_display', 'bbcodes', 'smileycodes', 'smilies', 'smileytypes', 'domainwhitelist', 'albumcategory'));

		$thread = c::t('forum_post')->fetch_threadpost_by_tid_invisible($tid);
		$modpost = C::m('forum_newpost', $tid, $thread['pid']);


		$params = array(
			'subject' => $subject,
			'message' => $message,
			'typeid' => 0, //主题分类id
			'sortid' => 0, //分类信息id
			'special' => 0,	//特殊主题
			'clientip' => $clientip
		);

		$params['publishdate'] = $_G['timestamp'];

		$params['digest'] = 0;

		$params['tags'] = '';
		$params['bbcodeoff'] = 0;
		$params['smileyoff'] = 0;
		$params['htmlon'] = 0;

		$return = $modpost->editpost($params);
		C::t('forum_thread')->update($tid,array('subject'=>$subject));
		$pid = $modpost->pid;

		$item = c::t('forum_thread')->fetch_by_tid_displayorder($tid);

		$data = $this->relation_item($item,$thread);

		$this->success_result($data);
	}

	public function delete_item()
	{
		$fid = intval($this->fid);
		$tid = intval($this->tid);
		$uid = intval($this->uid);
		$clientip = $this->clientip;

		if(!$fid || !$tid || !$uid || empty($clientip)){
			return_status(403);
		}


		$thread = c::t('forum_post')->fetch_threadpost_by_tid_invisible($tid);

		if(empty($thread)){
			return_status(405,'帖子已删除');
		}

		if($thread['authorid'] != $uid){
			return_status(405,'抱歉，用户不能删除其他人的帖子');
		}

		$pid = $thread['pid'];
		$modpost = C::m('forum_newpost', $tid, $pid);
		
		$param = array('fid' => $fid, 'tid' => $tid, 'pid' => $pid);	
		
		$result = $modpost->deletepost($param);

		$this->success_result('删除成功');
	}
	protected function relation_item($item, $current)
	{
		global $_G;
		try{
			require_once libfile('function/discuzcode');
			$actItem = array();
			if(is_array($item)){
				$actItem = array(
					'tid' => (int)$item['tid'],
					'fid' => (int)$item['fid'],
					'subject' => $item['subject'],
					'author' => $item['author'],
					'authorid' => (int)$item['authorid'],
					'dateline' => (int)$item['dateline'],
					'useip' => $current['useip'],
					'views' => (int)$item['views'],
					'heats' => (int)$item['heats'],
					'replies' => (int) $item['replies'],
					'avatar' => avatar($item['authorid'],'middle',1),
					'displayorder' => (int) $item['displayorder'],
					'digest' => (int) $item['digest'],
					'highlight' => (int) $item['highlight'],
					'lastpost' => (int) $item['lastpost'],
					'lastposter' => $item['lastposter'],
                                        'favtimes' => (int) $item['favtimes'],
                                        'recommend_add' => (int) $item['recommend_add'],
					'message' => isset($current['mdtype']) && $current['mdtype'] == 1 ? $this->MarkdownToHtml->transform($current['message']) : discuzcode($current['message'], $current['smileyoff'], $current['bbcodeoff'], $current['htmlon']),
				);
				$attachment = array();

				preg_match_all("%\[attach\]\s*(\d*)\[%is", $current['message'],$matches);
				foreach ($matches[1] as $it) {
					$thisItem = C::t('forum_attachment_n')->fetch('tid:'.$item['tid'], $it);
					if(!empty($thisItem)) $attachment[$it] = $thisItem;
				}

				$files = C::t('forum_attachment_n')->fetch_all_by_id('tid:'.$item['tid'], 'tid', $item['tid'], 'dateline', 0);

				if(count($files) > 0){
					foreach ($files as $key => $itm) {
						if(empty($attachment[$key]))
							$attachment[$key] = $itm;
					}
				}

				foreach ($attachment as $j => $obj) {
					$isused = false;
					$actItem['message'] = preg_replace_callback("%\[attach\]{$j}\[\/attach\]%is", 
						function($matches) use ( $obj, &$isused ,&$actItem){
							if((int)$obj['isimage'] == 1 || preg_match('%\.(gif|jpg|png|jpeg)$%is', $obj['filename'])){
								$isused = true;
								return "<img src='".check_url($obj['attachment'])."'>";
							}else{
								return '';
							}
						}, $actItem['message']);
					
					// 假如不是图片
					if ( !$isused && (int)$obj['isimage'] == 0 ){
						$actItem['attachment'][] = array(
								'aid' => (int)$obj['aid'],
								'filesize' => (int)$obj['filesize'],
								'filename' => $obj['filename'],
								'dateline' => (int)$obj['dateline'],
								'readperm' => (int)$obj['readperm'],
								'isimage' => (int)$obj['isimage'],
								'width' => (int)$obj['width'],
								'price' => (int)$obj['price'],
								'uid' => (int)$obj['uid'],
								'url' => check_url($obj['attachment'])
						);
					}
				}

				try{
					$actItem['message'] = message_filter($actItem['message']);
				}catch(Exception $e){
					try{
						$actItem['message'] = message_filter($actItem['message']);
					}catch(\Exception $e){
						throw new Exception('relation Markdown Error:'.$e,1);
					}
				}
			}
			return $actItem;
		}catch(\Exception $e){
			write_log('relation_item Error:'.$e);
			return_status(500);
		}
	}	
}