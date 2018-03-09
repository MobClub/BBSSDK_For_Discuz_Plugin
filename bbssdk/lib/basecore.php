<?php
if(!defined('DISABLEDEFENSE'))  exit('Access Denied!');
/**
* BaseCore
*/
require_once 'function.php';

class BaseCore
{
	protected $bbcode = 0;
	protected $charset = 'UTF-8';
	protected $setting;
	private $params;
        var $cachelist = array();
	protected $sync_mods = array(
		'forum'   => array('bbssdk_forum_sync','dateline',array('fid','tid')),
		'comment' => array('bbssdk_comment_sync','dateline',array('fid','tid','pid')),
		'member'  => array('bbssdk_member_sync','regdate',array('uid'))
	);
	function __construct()
	{
		global $_G;
		$this->initParams();
		if(!( BBSSDK_DEBUG && isset($_REQUEST['debug']))) $this->check_sign();
                
                $this->_initUser();
		$this->bbcode = isset($_G['setting']['bbclosed']) && $_G['setting']['bbclosed'] ? 1 : 0;
		$this->charset = strtoupper($_G['charset']);
		$this->setting = C::app()->var['setting'];
		$this->mod = strtolower($this->mod);
	}

	private function check_sign()
	{
		global $_G;
                $setting = C::t('common_setting')->fetch_all(array('bbssdk_setting','portalstatus'));
                $bbssdk = (array)unserialize($setting['bbssdk_setting']);
                
		$sign = !empty($_REQUEST['token']) ? $_REQUEST['token'] : $this->sign;
		$time = intval($this->time);
		$random = $this->random;
		if(!preg_match('%[a-zA-Z\d]{8,}%is', $random) || $time + 60*5 < time())
			return_status(109);

		if(empty($bbssdk['appkey']) || empty($bbssdk['appsecret']))
			return_status(110);

		$params = array(
			"appkey" => $bbssdk['appkey'],
			"appsecret" => $bbssdk['appsecret'],
			"random" => $random,
			"time" => $time
		);

		if($sign != md5(join('',$params)))
			return_status(111);
	}

	private function initParams()
	{
		$params = array();
		$keyVal = function($act) use(&$params){
			$a = explode('=', $act);
			$item = array();
			if(preg_match('%^\d+$%is', $a[1])){
				$item[$a[0]] = intval($a[1]);
			}else{
				$item[$a[0]] = urldecode($a[1]);
			}
			$params = array_merge($params,$item);
		};
		$request = file_get_contents("php://input");
		array_map($keyVal, explode('&',$request));
		$_REQUEST = $params = array_merge($_REQUEST,$params);		
		foreach ($params as $key => $value) {
			$this->params[$key] = $value;
		}
	}
        private function _initUser(){
            global $_G;
            if($this->uid){
                $user = getuserbyuid($this->uid, 1);
                if($user){
                    $_G['groupid'] = $user['groupid'];
                    $this->cachelist[] = 'usergroup_'.$user['groupid'];
                    if($user['adminid'] > 0 && $user['groupid'] != $user['adminid']) {
                        $this->cachelist[] = 'admingroup_'.$user['adminid'];
                    }
                    !empty($this->cachelist) && loadcache($this->cachelist);
                    if($_G['group']['radminid'] == 0 && $user['adminid'] > 0 && $user['groupid'] != $user['adminid']&& !empty($_G['cache']['admingroup_'.$user['adminid']])) {
                            $_G['group'] = array_merge($_G['group'], $_G['cache']['admingroup_'.$user['adminid']]);
                    }
                }
            }
        }

        public function bbcode_encode($message)
	{
		require_once libfile('class/bbcode');
		require_once libfile('function/editor');
		$bbcode = new bbcode();
		$message = urldecode($message);
		$html_s_str = array('<div>', '</div>','<p>','</p>','<span>','</span>');
		$html_r_str = array('[div]', '[/div]','[p]','[/p]','[span]','[/span]');
		// @$message = str_replace($html_s_str, $html_r_str,$message);
		// return $bbcode->html2bbcode($message);
		return html2bbcode($message);
	}
	public function success_result($params,$message="SUCCESS")
	{
	    global $_G;
	    $final = array('code'=>201,'message'=>'无返回');
	    if(!empty($params) && !( isset($params['list']) && empty($params['list']) ) )
	    {
	        $final['code'] = 200;

			if($this->mod == 'comment' && !empty($params['tid'])){
				require_once dirname(dirname(__FILE__)).'/controller/forum_ctrl.php';
				$forum = new Forum();
				$params['thread'] = $forum->getItem($params['fid'],$params['tid']);
			}

	        $final['data'] = $params;
	        $final['message'] = $message;

			$sync_params = $this->sync_mods[$this->mod];
	        if(isset($sync_params) && method_exists($this, 'common_sync')){
	        	$this->common_sync($params,$sync_params);
	        }

	        if($_SERVER['REQUEST_METHOD'] != 'GET')
				write_log (
					'method=>'.$_SERVER['REQUEST_METHOD']
					. "\t Request=>".json_encode($this->params)
					. "\t Response=>".json_encode($final)
					,'debug'
				);
	    }
	    header("Content-type:application/json;charset=".$_G['charset']);
	    if(preg_match('%^gb%is',$_G['charset'])){
                $final = gbkToUtf8($final,$_G['charset']);
	        echo json_encode_new($final,true);
	    }else{
	        echo json_encode($final);
	    }
	    exit;
	}

	public function common_sync($params,$sync_params)
	{
		$method = strtolower($_SERVER['REQUEST_METHOD']);
		$final = is_array($params) ? array_merge($this->params,$params) : $this->params;
		$a = array_search($method, array('post','put','delete'));
		if( $a>-1 && isset($sync_params))
		{
			$a = $a + 1;
			$table = $sync_params[0];
			$needKey = $sync_params[1];
			$dateline = isset($final[$needKey]) && $a == 1 ? $final[$needKey] : time();
			$search = array();
			$where = array();
			foreach ($sync_params[2] as $key) {
				$search[$key] = intval($final[$key]);
				array_push($where , $key.'='.intval($final[$key]));
			}
			$where = implode(' and ', $where);
			$item = DB::fetch_first("select * from ".DB::table($table).' where '.$where);
			if($item){
				$sql = 'update '.DB::table($table)." set synctime=$dateline,modifytime=$dateline,flag=$a where syncid=".$item['syncid'];
			}else{
				$sqlKey = implode(',', array_keys($search));
				$values = implode(',', array_values($search));
				$sql = 'insert into '.DB::table($table).'('.$sqlKey.',synctime,modifytime,creattime,flag) value('.$values.",$dateline,$dateline,$dateline,$a)";
			}
			write_log('common sync method=>'.$this->method.' where=>'.$where.' sql=>'.$sql,'debug');
			DB::query($sql);
		}
	}

	public function __get($name){
		return $this->params[$name];
	}
	public function __set($name,$value){
		if(!empty($value)){
			$this->params[$name] = $value;
		}
	}
}

class model_forum_newpost extends model_forum_post
{
	public function showmessage(){
		$p = func_get_args();
		isset($p[0]) && $message = $p[0];
		isset($p[1]) && $url_forward = $p[1];
		isset($p[2]) && $values = $p[2];
		isset($p[3]) && $extraparam = $p[3];
		isset($p[4]) && $custom = $p[4];
		global $_G, $show_message;

		$navtitle = lang('core', 'title_board_message');

		if($custom) {
			$alerttype = 'alert_info';
			$show_message = $message;
			return_status(405,$showmessage);
		}

		$vars = explode(':', $message);
		if(count($vars) == 2) {
			$show_message = lang('plugin/'.$vars[0], $vars[1], $values);
		} else {
			$show_message = lang('message', $message, $values);
		}

		if($_G['connectguest']) {
			$param['login'] = false;
			$param['alert'] = 'info';
			if (defined('IN_MOBILE')) {
				if ($message == 'postperm_login_nopermission_mobile') {
					$show_message = lang('plugin/qqconnect', 'connect_register_mobile_bind_error');
				}
				$show_message = str_replace(lang('forum/misc', 'connectguest_message_mobile_search'), lang('forum/misc', 'connectguest_message_mobile_replace'), $show_message);
			} else {
				$show_message = str_replace(lang('forum/misc', 'connectguest_message_search'), lang('forum/misc', 'connectguest_message_replace'), $show_message);
			}
			if ($message == 'group_nopermission') {
				$show_message = lang('plugin/qqconnect', 'connectguest_message_complete_or_bind');
			}
		}
		return_status(405,$show_message);
	}
}

class model_forum_newthread extends model_forum_thread
{
	public function showmessage(){
		$p = func_get_args();
		isset($p[0]) && $message = $p[0];
		isset($p[1]) && $url_forward = $p[1];
		isset($p[2]) && $values = $p[2];
		isset($p[3]) && $extraparam = $p[3];
		isset($p[4]) && $custom = $p[4];
		global $_G, $show_message;

		$navtitle = lang('core', 'title_board_message');

		if($custom) {
			$alerttype = 'alert_info';
			$show_message = $message;
			return_status(405,$showmessage);
		}

		$vars = explode(':', $message);
		if(count($vars) == 2) {
			$show_message = lang('plugin/'.$vars[0], $vars[1], $values);
		} else {
			$show_message = lang('message', $message, $values);
		}

		if($_G['connectguest']) {
			$param['login'] = false;
			$param['alert'] = 'info';
			if (defined('IN_MOBILE')) {
				if ($message == 'postperm_login_nopermission_mobile') {
					$show_message = lang('plugin/qqconnect', 'connect_register_mobile_bind_error');
				}
				$show_message = str_replace(lang('forum/misc', 'connectguest_message_mobile_search'), lang('forum/misc', 'connectguest_message_mobile_replace'), $show_message);
			} else {
				$show_message = str_replace(lang('forum/misc', 'connectguest_message_search'), lang('forum/misc', 'connectguest_message_replace'), $show_message);
			}
			if ($message == 'group_nopermission') {
				$show_message = lang('plugin/qqconnect', 'connectguest_message_complete_or_bind');
			}
		}
		return_status(405,$show_message);
	}
}
