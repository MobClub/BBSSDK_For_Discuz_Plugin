<?php
if(!defined('DISABLEDEFENSE'))  exit('Access Denied!');

class Favorite extends BaseCore
{
	function __construct()
	{
		parent::__construct();
	}
        /**
         * 通过favid获取内容
         * @param mix(int|array) $favid 
         * @return array
         */
	private function _item($favid){
            global $_G;
            $list = array();
            $idtypes = array('thread'=>'tid', 'forum'=>'fid', 'blog'=>'blogid', 'group'=>'gid', 'album'=>'albumid', 'space'=>'uid', 'article'=>'aid');
            $favid = empty($favid)?0:dintval($favid, is_array($favid));
            $result = DB::fetch_all("SELECT * FROM %t WHERE favid in(%n)", ['home_favorite', $favid]);
            if($result){
                $icons = array(
                        'tid'=>'<img src="'.get_site_url().'static/image/feed/thread.gif" alt="thread" class="t" /> ',
                        'fid'=>'<img src="'.get_site_url().'static/image/feed/discuz.gif" alt="forum" class="t" /> ',
                        'blogid'=>'<img src="'.get_site_url().'static/image/feed/blog.gif" alt="blog" class="t" /> ',
                        'gid'=>'<img src="'.get_site_url().'static/image/feed/group.gif" alt="group" class="t" /> ',
                        'uid'=>'<img src="'.get_site_url().'static/image/feed/profile.gif" alt="space" class="t" /> ',
                        'albumid'=>'<img src="'.get_site_url().'static/image/feed/album.gif" alt="album" class="t" /> ',
                        'aid'=>'<img src="'.get_site_url().'static/image/feed/article.gif" alt="article" class="t" /> ',
                );
                foreach ($result as &$value){
                    $value['icon'] = isset($icons[$value['idtype']]) ? $icons[$value['idtype']] : '';
                    $value['url'] = makeurl($value['id'], $value['idtype'], $value['spaceuid']);
                    $value['description'] = !empty($value['description']) ? nl2br($value['description']) : '';
                    $value['type'] = array_flip($idtypes)[$value['idtype']];
                    $list[$value['favid']] = $value;
                    if($value['idtype'] == 'aid') {
                            $articles[$value['favid']] = $value['id'];
                    }
                }
                if(!empty($articles)) {
                            include_once libfile('function/portal');
                            $_urls = array();
                            foreach(C::t('portal_article_title')->fetch_all($articles) as $aid => $article) {
                                    $_urls[$aid] = fetch_article_url($article);
                            }
                            foreach ($articles as $favid => $aid) {
                                    $list[$favid]['url'] = $_urls[$aid];
                            }
                    }
            }
            return array_values($list);
        }
        public function get_add(){
            require_once libfile('function/home');
            global $_G;
            $_G['uid'] = intval($_GET['uid']);
            $_POST['description']=$this->description;
            if(empty($_G['uid'])) {
                return_status(601);
            }
            
            if(!in_array($_GET['type'], array("thread", "forum", "group", "blog", "album", "article", "all"))){
                return_status(602);
            }
//            cknewuser();

            $type = empty($_GET['type']) ? '' : $_GET['type'];
            $id = empty($_GET['id']) ? 0 : intval($_GET['id']);
            $spaceuid = empty($_GET['spaceuid']) ? 0 : intval($_GET['spaceuid']);
            $idtype = $title = $icon = '';
            switch($type) {
                    case 'thread':
                            $idtype = 'tid';
                            $thread = C::t('forum_thread')->fetch($id);
                            $title = $thread['subject'];
                            $icon = '<img src="'.get_site_url().'static/image/feed/thread.gif" alt="thread" class="vm" /> ';
                            break;
                    case 'forum':
                            $idtype = 'fid';
                            $foruminfo = C::t('forum_forum')->fetch($id);
                            loadcache('forums');
                            $forum = $_G['cache']['forums'][$id];
                            if(!$forum['viewperm'] || ($forum['viewperm'] && forumperm($forum['viewperm'])) || strstr($forum['users'], "\t$_G[uid]\t")) {
                                    $title = $foruminfo['status'] != 3 ? $foruminfo['name'] : '';
                                    $icon = '<img src="'.get_site_url().'static/image/feed/discuz.gif" alt="forum" class="vm" /> ';
                            }
                            break;
                    case 'blog':
                            $idtype = 'blogid';
                            $bloginfo = C::t('home_blog')->fetch($id);
                            $title = ($bloginfo['uid'] == $spaceuid) ? $bloginfo['subject'] : '';
                            $icon = '<img src="'.get_site_url().'static/image/feed/blog.gif" alt="blog" class="vm" /> ';
                            break;
                    case 'group':
                            $idtype = 'gid';
                            $foruminfo = C::t('forum_forum')->fetch($id);
                            $title = $foruminfo['status'] == 3 ? $foruminfo['name'] : '';
                            $icon = '<img src="'.get_site_url().'static/image/feed/group.gif" alt="group" class="vm" /> ';
                            break;
                    case 'album':
                            $idtype = 'albumid';
                            $result = C::t('home_album')->fetch($id, $spaceuid);
                            $title = $result['albumname'];
                            $icon = '<img src="'.get_site_url().'static/image/feed/album.gif" alt="album" class="vm" /> ';
                            break;
                    case 'space':
                            $idtype = 'uid';
                            $_member = getuserbyuid($id);
                            $title = $_member['username'];
                            $unset($_member);
                            $icon = '<img src="'.get_site_url().'static/image/feed/profile.gif" alt="space" class="vm" /> ';
                            break;
                    case 'article':
                            $idtype = 'aid';
                            $article = C::t('portal_article_title')->fetch($id);
                            $title = $article['title'];
                            $icon = '<img src="'.get_site_url().'static/image/feed/article.gif" alt="article" class="vm" /> ';
                            break;
            }
            if(empty($idtype) || empty($title)) {
                    return_status(605);
            }

            $fav = C::t('home_favorite')->fetch_by_id_idtype($id, $idtype, $_G['uid']);
            if($fav) {
                    return_status(606);
            }
            $description = '';
            
            $arr = array(
                    'uid' => intval($_G['uid']),
                    'idtype' => $idtype,
                    'id' => $id,
                    'spaceuid' => $spaceuid,
                    'title' => getstr($title, 255),
                    'description' => getstr($_POST['description'], '', 0, 0, 1),
                    'dateline' => TIMESTAMP
            );
            
            $favid = C::t('home_favorite')->insert($arr, true);
            
            if($_G['setting']['cloud_status']) {
                    $favoriteService = Cloud::loadClass('Service_Client_Favorite');
                    $favoriteService->add($arr['uid'], $favid, $arr['id'], $arr['idtype'], $arr['title'], $arr['description'], TIMESTAMP);
            }
            
            switch($type) {
                    case 'thread':
                            C::t('forum_thread')->increase($id, array('favtimes'=>1));
                            require_once libfile('function/forum');
                            update_threadpartake($id);
                            break;
                    case 'forum':
                            C::t('forum_forum')->update_forum_counter($id, 0, 0, 0, 0, 1);
                            break;
                    case 'blog':
                            C::t('home_blog')->increase($id, $spaceuid, array('favtimes' => 1));
                            break;
                    case 'group':
                            C::t('forum_forum')->update_forum_counter($id, 0, 0, 0, 0, 1);
                            break;
                    case 'album':
                            C::t('home_album')->update_num_by_albumid($id, 1, 'favtimes', $spaceuid);
                            break;
                    case 'space':
                            C::t('common_member_status')->increase($id, array('favtimes' => 1));
                            break;
                    case 'article':
                            C::t('portal_article_count')->increase($id, array('favtimes' => 1));
                            break;
            }
            $r = $this->_item($favid);
            return $this->success_result($r);       
        }
        public function get_get(){
            global $_G;
            $_G['uid'] = intval($_GET['uid']);
            if(empty($_G['uid'])) {
                return_status(601);
            }
            
            $page = empty($_GET['page'])?1:intval($_GET['page']);
            $perpage = !isset($_GET['pagesize'])||intval($_GET['pagesize'])<1?10:intval($_GET['pagesize']);

            $_G['disabledwidthauto'] = 0;

            $start = ($page-1)*$perpage;
            //ckstart($start, $perpage);

            $idtypes = array('thread'=>'tid', 'forum'=>'fid', 'blog'=>'blogid', 'group'=>'gid', 'album'=>'albumid', 'space'=>'uid', 'article'=>'aid');
            if(!isset($idtypes[$_GET['type']])&&$_GET['type']!='all'){
                return_status(602);
            }

            $data = $list = array();
            $favid = empty($_GET['favid'])?0:intval($_GET['favid']);
            $idtype = isset($idtypes[$_GET['type']]) ? $idtypes[$_GET['type']] : '';

            $count = C::t('home_favorite')->count_by_uid_idtype($_G['uid'], $idtype, $favid);
            if($count) {
                    $icons = array(
                            'tid'=>'<img src="'.get_site_url().'static/image/feed/thread.gif" alt="thread" class="t" /> ',
                            'fid'=>'<img src="'.get_site_url().'static/image/feed/discuz.gif" alt="forum" class="t" /> ',
                            'blogid'=>'<img src="'.get_site_url().'static/image/feed/blog.gif" alt="blog" class="t" /> ',
                            'gid'=>'<img src="'.get_site_url().'static/image/feed/group.gif" alt="group" class="t" /> ',
                            'uid'=>'<img src="'.get_site_url().'static/image/feed/profile.gif" alt="space" class="t" /> ',
                            'albumid'=>'<img src="'.get_site_url().'static/image/feed/album.gif" alt="album" class="t" /> ',
                            'aid'=>'<img src="'.get_site_url().'static/image/feed/article.gif" alt="article" class="t" /> ',
                    );
                    $articles = array();
                    foreach(C::t('home_favorite')->fetch_all_by_uid_idtype($_G['uid'], $idtype, $favid, $start,$perpage) as $value) {
                            $value['icon'] = isset($icons[$value['idtype']]) ? $icons[$value['idtype']] : '';
                            $value['url'] = makeurl($value['id'], $value['idtype'], $value['spaceuid']);
                            $value['description'] = !empty($value['description']) ? nl2br($value['description']) : '';
                            $value['type'] = array_flip($idtypes)[$value['idtype']];
                            $list[$value['favid']] = $value;
                            if($value['idtype'] == 'aid') {
                                    $articles[$value['favid']] = $value['id'];
                            }
                    }
                    if(!empty($articles)) {
                            include_once libfile('function/portal');
                            $_urls = array();
                            foreach(C::t('portal_article_title')->fetch_all($articles) as $aid => $article) {
                                    $_urls[$aid] = fetch_article_url($article);
                            }
                            foreach ($articles as $favid => $aid) {
                                    $list[$favid]['url'] = $_urls[$aid];
                            }
                    }
            }
            
            $data['total_count'] = $count;
            $data['pagesize'] = $perpage;
            $data['currpage'] = $_GET['page'];
            $data['nextpage'] = $_GET['page']+1;
            $data['prepage'] = $_GET['page']>1?$_GET['page']-1:1;
            
            $data['list'] = array_values($list);
            
            return $this->success_result($data);
        }
        public function get_item(){
            global $_G;
            $data = $list = array();
            $idtypes = array('thread'=>'tid', 'forum'=>'fid', 'blog'=>'blogid', 'group'=>'gid', 'album'=>'albumid', 'space'=>'uid', 'article'=>'aid');
            $favid = empty($_GET['favid'])?0:dintval($_GET['favid'], is_array($_GET['favid']));
            
            $data['list'] = $this->_item($favid);
            return $this->success_result($data);
        }
        public function post_delete(){
            global $_G;
            $_G['uid'] = intval($_GET['uid']);
            if(empty($_G['uid'])) {
                return_status(601);
            }
            if($_GET['checkall']) {
		if($_GET['favorite']) {
                    $_GET['favorite'] = dintval($_GET['favorite'], is_array($_GET['favorite']));
                    $deletecounter = array();
                    $data = C::t('home_favorite')->fetch_all($_GET['favorite']);
                    foreach($data as $dataone) {
                            switch($dataone['idtype']) {
                                    case 'fid':
                                            $deletecounter['fids'][] = $dataone['id'];
                                            break;
                                    default:
                                            break;
                            }
                    }
                    if($deletecounter['fids']) {
                            C::t('forum_forum')->update_forum_counter($deletecounter['fids'], 0, 0, 0, 0, -1);
                    }
                    C::t('home_favorite')->delete($_GET['favorite'], false, $_G['uid']);
                    if($_G['setting']['cloud_status']) {
                            $favoriteService = Cloud::loadClass('Service_Client_Favorite');
                            $favoriteService->remove($_G['uid'], $_GET['favorite'], TIMESTAMP);
                    }
                    return_status(200,'删除成功');
                }
                return_status(403);
            } else {
                    $favid = intval($_GET['favid']);
                    $thevalue = C::t('home_favorite')->fetch($favid);
                    if(empty($thevalue) || $thevalue['uid'] != $_G['uid']) {
                            return_status(611);
                    }

                    switch($thevalue['idtype']) {
                            case 'fid':
                                    C::t('forum_forum')->update_forum_counter($thevalue['id'], 0, 0, 0, 0, -1);
                                    break;
                            default:
                                    break;
                    }
                    C::t('home_favorite')->delete($favid);
                    if($_G['setting']['cloud_status']) {
                            $favoriteService = Cloud::loadClass('Service_Client_Favorite');
                            $favoriteService->remove($_G['uid'], $favid);
                    }
                    return_status(200,'删除成功');            
            }
        }
}
function makeurl($id, $idtype, $spaceuid=0) {
	$url = '';
	switch($idtype) {
		case 'tid':
			$url = get_site_url().'forum.php?mod=viewthread&tid='.$id;
			break;
		case 'fid':
			$url = get_site_url().'forum.php?mod=forumdisplay&fid='.$id;
			break;
		case 'blogid':
			$url = get_site_url().'home.php?mod=space&uid='.$spaceuid.'&do=blog&id='.$id;
			break;
		case 'gid':
			$url = get_site_url().'forum.php?mod=group&fid='.$id;
			break;
		case 'uid':
			$url = get_site_url().'home.php?mod=space&uid='.$id;
			break;
		case 'albumid':
			$url = get_site_url().'home.php?mod=space&uid='.$spaceuid.'&do=album&id='.$id;
			break;
		case 'aid':
			$url = get_site_url().'portal.php?mod=view&aid='.$id;
			break;
	}
	return $url;
}