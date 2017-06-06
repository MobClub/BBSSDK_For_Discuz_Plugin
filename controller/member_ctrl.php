<?php
if(!defined('DISABLEDEFENSE'))  exit('Access Denied!');
require_once libfile('function/misc');
require_once libfile('function/mail');
require_once libfile('function/member');
require_once libfile('class/member');

class Member extends BaseCore
{
	function __construct()
	{
		parent::__construct();
	}
	public function get_item()
	{
		global $_G;
		$uid = intval($this->uid);

		if(!$uid) return_status(403);

		$final = array();
		$member = getuserbyuid($uid);
		if($uid == $member['uid']){
			$userinfo =  C::t('common_member')->fetch_all_stat_memberlist($member['username']);
			$userinfo = array_merge($userinfo[$uid],$member);
			$final = $this->relation_item($userinfo);
		}
		$this->success_result($final);
	}
	public function get_login()
	{
		global $_G;
		$username = urldecode($this->username);
		$email = urldecode($this->email);
		$password = urldecode($this->password);
		$answer = urldecode($this->answer);
		$clientip = urldecode($_REQUEST['clientip']);
		$questionid = empty($_REQUEST['questionid']) ? 0 : intval($_REQUEST['questionid']);

		if( ( empty($username) && empty($email) ) || empty($password) || empty($clientip)){
			return_status(403);
		}

		if(!preg_match('%utf%is', $this->charset)){
			if(function_exists('iconv')){
				$username = iconv('UTF-8', $this->charset . '//ignore', $username);
				$answer = iconv('UTF-8', $this->charset . '//ignore', $answer);
			}else{
				$username = mb_convert_encoding($username, $this->charset, 'UTF-8');
				$answer = mb_convert_encoding($answer, $this->charset, 'UTF-8');
			}
		}

		$clientip = '';

		if(empty($email)){
			$result = userlogin($username, $password, $questionid, $answer, 'username', $clientip);
		}else{
			$result = userlogin($email,$password,$questionid,$answer,'email',$clientip);
		}

		if((int)$result['ucresult']['uid'] < 0)
		{
			switch ($result['ucresult']['uid']) {
				case -1:
					return_status(30103);
					break;
				case -2:
					return_status(30105);
					break;
				case -3:
					$lang = lang('template');
					$question = array();
					for($i=1;$i<=7;$i++)
					{
						if(isset($lang['security_question_'.$i])){
							$question[$i-1]['questionid'] = $i;
							$question[$i-1]['question'] = $lang['security_question_'.$i];
						}
					}
					return_status(30101,array('data'=>$question));
					break;
			}
		}

		$uid = $_G['uid'] = $result['ucresult']['uid'];

		$ctlObj = new logging_ctl();
		$ctlObj->setting = $_G['setting'];
		if($result['status'] == -1) {
			if(!$ctlObj->setting['fastactivation']) {
				return_status(30102);
			}
			$init_arr = explode(',', $ctlObj->setting['initcredits']);
			$groupid = $ctlObj->setting['regverify'] ? 8 : $ctlObj->setting['newusergroupid'];
			C::t('common_member')->insert($uid, $result['ucresult']['username'], md5(random(10)), $result['ucresult']['email'], $clientip, $groupid, $init_arr);
			$result['member'] = getuserbyuid($uid);
			$result['status'] = 1;
		}
		if($result['status'] > 0) {
			if($ctlObj->extrafile && file_exists($ctlObj->extrafile)) {
				require_once $ctlObj->extrafile;
			}
			C::t('common_member_status')->update($uid, array('lastip' => $clientip, 'lastvisit' =>TIMESTAMP, 'lastactivity' => TIMESTAMP));
			if(isset($result['member']['password'])){
				unset($result['member']['password']);
			}
			if(isset($result['member']['credits'])){
				unset($result['member']['credits']);
			}
			//登录成功				
			$userinfo =  C::t('common_member')->fetch_all_stat_memberlist($result['member']['username']);
			$userinfo = array_merge($userinfo[$uid],$result['member']);
			$final = $this->relation_item($userinfo);
			$this->success_result($final);
		}
		if($_G['member_loginperm'] > 1) {
			//登录失败
			return_status(30104);
		}elseif($_G['member_loginperm'] == -1) {
			//密码错误
			return_status(30105);
		}else{
			return_status(30106);
		}
	}

	public function post_register()
	{
		global $_G;
		$username = urldecode($this->username);
		$email = urldecode($this->email);
		$clientip = urldecode($this->clientip);
		$password = urldecode($this->password);

		if(function_exists('iconv')){
			$username = iconv('UTF-8', $this->charset . '//ignore', $username);
		}else{
			$username = mb_convert_encoding($username, $this->charset, 'UTF-8');
		}

		if(isset($email) && !empty($email)){
			$email = strtolower($email);
		}

		if(empty($username) || empty($email) || empty($clientip) || empty($password)){
			return_status(403);
		}

		$usernameLen = dstrlen($username);
		if($usernameLen < 3){
			return_status(302011,'用户名过短');
		}
		if($usernameLen > 15){
			return_status(302012,'用户名过长');
		}

		$ctlObj = new register_ctl();
		$ctlObj->setting = $_G['setting'];
		if(isset($ctlObj->setting['pwlength']) && $ctlObj->setting['pwlength']) {
			if(strlen($password) < $ctlObj->setting['pwlength']) {
				return_status(302013,'密码长度请大于或等于'.$ctlObj->setting['pwlength']);
			}
		}
		if(isset($ctlObj->setting['strongpw']) && $ctlObj->setting['strongpw']) {
			$strongpw_str = array();
			if(in_array(1, $ctlObj->setting['strongpw']) && !preg_match("/\d+/", $password)) {
				$strongpw_str[] = '数字';
			}
			if(in_array(2, $ctlObj->setting['strongpw']) && !preg_match("/[a-z]+/", $password)) {
				$strongpw_str[] = '小写字母';
			}
			if(in_array(3, $ctlObj->setting['strongpw']) && !preg_match("/[A-Z]+/", $password)) {
				$strongpw_str[] = '大写字母';
			}
			if(in_array(4, $ctlObj->setting['strongpw']) && !preg_match("/[^a-zA-Z0-9]+/", $password)) {
				$strongpw_str[] = '字母和数字';
			}
			if($strongpw_str) {
				return_status(302014,'密码复杂度不符合要求，必须包含('.join(',',$strongpw_str).')');
			}
		}
		// if(!isset($_G['setting']['mobile']['mobileregister']) || !$_G['setting']['mobile']['mobileregister']){
		// 	return_status(302015,'手机端暂时不允许注册');
		// }

		if(!$ctlObj->setting['regclosed'] && (!$ctlObj->setting['regstatus'] || !$ctlObj->setting['ucactivation'])) {
			if(!$ctlObj->setting['regstatus']) {
				return_status(302016,'系统暂时不允许注册');
			}
		}
		if($ctlObj->setting['regverify']) {
			if($ctlObj->setting['areaverifywhite']) {
				$location = $whitearea = '';
				$location = trim(convertip($clientip, "./"));
				if($location) {
					$whitearea = preg_quote(trim($ctlObj->setting['areaverifywhite']), '/');
					$whitearea = str_replace(array("\\*"), array('.*'), $whitearea);
					$whitearea = '.*'.$whitearea.'.*';
					$whitearea = '/^('.str_replace(array("\r\n", ' '), array('.*|.*', ''), $whitearea).')$/i';
					if(@preg_match($whitearea, $location)) {
						$ctlObj->setting['regverify'] = 0;
					}
				}
			}
		
			if($_G['cache']['ipctrl']['ipverifywhite']) {
				foreach(explode("\n", $_G['cache']['ipctrl']['ipverifywhite']) as $ctrlip) {
					if(preg_match("/^(".preg_quote(($ctrlip = trim($ctrlip)), '/').")/", $clientip)) {
						$ctlObj->setting['regverify'] = 0;
						break;
					}
				}
			}
		}
		if($ctlObj->setting['regverify']) {
			$groupinfo['groupid'] = 8;
		} else {
			$groupinfo['groupid'] = $ctlObj->setting['newusergroupid'];
		}
		if(!$password || $password != addslashes($password)) {
			return_status(302017,'密码有非法字符');
		}
		$censorexp = '/^('.str_replace(array('\\*', "\r\n", ' '), array('.*', '|', ''), 
				preg_quote(($ctlObj->setting['censoruser'] = trim($ctlObj->setting['censoruser'])), '/')).')$/i';
		if($ctlObj->setting['censoruser'] && @preg_match($censorexp, $username)) {
			return_status(302018,'不允许的用户名，请更换');
		}

		if($ctlObj->setting['regctrl']) {
			if(C::t('common_regip')->count_by_ip_dateline($clientip, $_G['timestamp']-$ctlObj->setting['regctrl']*3600)) {
				return_status(30201901,'该IP被封');
			}
		}
		
		$setregip = null;
		if($ctlObj->setting['regfloodctrl']) {
			$regip = C::t('common_regip')->fetch_by_ip_dateline($clientip, $_G['timestamp']-86400);
			if($regip) {
				if($regip['count'] >= $ctlObj->setting['regfloodctrl']) {
					return_status(30201902,'该IP被封，请明日再试');
				} else {
					$setregip = 1;
				}
			} else {
				$setregip = 2;
			}
		}
		$uid = uc_user_register($username, $password, $email, '', '', $clientip);
		if($uid <= 0) {
			if($uid == -1) {
				return_status(302101,'用户名不合法');
			} elseif($uid == -2) {
				return_status(302102,'用户名包含非法字符');
			} elseif($uid == -3) {
				return_status(302103,'用户名已经存在');
			} elseif($uid == -4) {
				return_status(302104,'Email格式有误');
			} elseif($uid == -5) {
				return_status(302105,'Email不允许注册');
			} elseif($uid == -6) {
				return_status(302106,'该Email已经被注册');
			}
		}
		$_G['username'] = $username;
		$password = md5(random(10));
		if($setregip !== null) {
			if($setregip == 1) {
				C::t('common_regip')->update_count_by_ip($clientip);
			} else {
				C::t('common_regip')->insert(array('ip' => $clientip, 'count' => 1, 'dateline' => $_G['timestamp']));
			}
		}
		$profile = $verifyarr = array ();
		$emailstatus = 0;
		$init_arr = array('credits' => explode(',', $ctlObj->setting['initcredits']), 'profile'=>$profile, 'emailstatus' => $emailstatus);
		C::t('common_member')->insert($uid, $username, $password, $email, $clientip, $groupinfo['groupid'], $init_arr);
		if($ctlObj->setting['regctrl'] || $ctlObj->setting['regfloodctrl']) {
			C::t('common_regip')->delete_by_dateline($_G['timestamp']-($ctlObj->setting['regctrl'] > 72 ? $ctlObj->setting['regctrl'] : 72)*3600);
			if($ctlObj->setting['regctrl']) {
				C::t('common_regip')->insert(array('ip' => $clientip, 'count' => -1, 'dateline' => $_G['timestamp']));
			}
		}
		if($ctlObj->setting['regverify'] == 1) {
			// $hashstr = urlencode(authcode("$email\t$_G[timestamp]", 'ENCODE', $_G['config']['security']['authkey']));
			// $registerurl = "{$_G[siteurl]}member.php?mod=".$this->setting['regname']."&amp;hash={$hashstr}&amp;email={$email}";
			// $idstring = random(6);
			// $authstr = $ctlObj->setting['regverify'] == 1 ? "$_G[timestamp]\t2\t$idstring" : '';
			// C::t('common_member_field_forum')->update($uid, array('authstr' => $authstr));
			// $verifyurl = "{$_G['setting']['siteurl']}member.php?mod=activate&amp;uid=$uid&amp;id=$idstring";

			$hash = authcode("$uid\t$email\t$_G[timestamp]", 'ENCODE', md5(substr(md5($_G['config']['security']['authkey']), 0, 16)));
			$verifyurl = $_G['setting']['siteurl'].'home.php?mod=misc&amp;ac=emailcheck&amp;hash='.urlencode($hash);

			$email_verify_message = lang('email', 'email_verify_message', array(
						'username' => $username,
						'bbname' => $ctlObj->setting['bbname'],
						'siteurl' => $_G['setting']['siteurl'],
						'url' => $verifyurl
						));
			if(!sendmail("$username <$email>", lang('email', 'email_verify_subject'), $email_verify_message)) {
				runlog('sendmail', "$email sendmail failed.");
				return_status(3021102,'邮件发送失败');
			}else{
				return_status(3021101,'邮件已发送，请登录邮箱验证');
			}
		}
		if($ctlObj->setting['regverify'] == 2) {
			return_status(3021103,'信息提交成功还需要人工审核，请联系管理员');
		}
		require_once libfile('cache/userstats', 'function');
		build_cache_userstats();
		$regmessage = dhtmlspecialchars('from bbssdk client');
		if($ctlObj->setting['regverify'] == 2) {
			C::t('common_member_validate')->insert(array(
						'uid' => $uid,
						'submitdate' => $_G['timestamp'],
						'moddate' => 0,
						'admin' => '',
						'submittimes' => 1,
						'status' => 0,
						'message' => $regmessage,
						'remark' => '',
						), false, true);
			manage_addnotify('verifyuser');
		}
		setloginstatus(array(
					'uid' => $uid,
					'username' => $_G['username'],
					'password' => $password,
					'groupid' => $groupinfo['groupid'],
					), 0);
		include_once libfile('function/stat');
		updatestat('register');
		checkfollowfeed();
		C::t('common_member_status')->update($uid, array('lastip' => $clientip, 'lastvisit' =>TIMESTAMP, 'lastactivity' => TIMESTAMP));
		//注册成功
		$userinfo =  C::t('common_member')->fetch_all_stat_memberlist($username);
		$member = C::t('common_member')->fetch_by_username($username);
		$userinfo = array_merge($userinfo[$uid],$member);
		$final = $this->relation_item($userinfo);
		$this->success_result($final);
	}

	public function get_lostpasswd()
	{
		global $_G;
		$username = urldecode($this->username);
		$email = strtolower(trim(urldecode($this->email)));
		$clientip = urldecode($this->clientip);

		if(empty($username) || empty($email) || empty($clientip) ){
			return_status(403);
		}

		loaducenter();
		if($username) {
			list($tmp['uid'], , $tmp['email']) = uc_get_user(addslashes($username));
			$tmp['email'] = strtolower(trim($tmp['email']));
			if($email != $tmp['email']) {
				return_status(301101,'找回密码，提交的用户信息错误');
			}
			$member = getuserbyuid($tmp['uid'], 1);
		} else {
			$emailcount = C::t('common_member')->count_by_email($email, 1);
			if(!$emailcount) {
				return_status(301102,'邮箱不存在');
			}
			if($emailcount > 1) {
				return_status(301103,'提交的邮箱存在多用户使用，不能发送邮件');
			}
			$member = C::t('common_member')->fetch_by_email($email, 1);
			list($tmp['uid'], , $tmp['email']) = uc_get_user(addslashes($member['username']));
			$tmp['email'] = strtolower(trim($tmp['email']));
		}
		if(!$member) {
			return_status(301101,'找回密码，提交的用户信息错误');
		} elseif($member['adminid'] == 1 || $member['adminid'] == 2) {
			return_status(301104,'管理员用户不能通过手机端找回密码');
		}

		$table_ext = $member['_inarchive'] ? '_archive' : '';
		if($member['email'] != $tmp['email']) {
			C::t('common_member'.$table_ext)->update($tmp['uid'], array('email' => $tmp['email']));
		}

		$idstring = random(6);
		C::t('common_member_field_forum'.$table_ext)->update($member['uid'], array('authstr' => "$_G[timestamp]\t1\t$idstring"));
		require_once libfile('function/mail');
		$_G['siteurl'] = $_G['setting']['siteurl'];
		$get_passwd_subject = lang('email', 'get_passwd_subject');
		$get_passwd_message = lang(
			'email',
			'get_passwd_message',
			array(
				'username' => $member['username'],
				'bbname' => $_G['setting']['bbname'],
				'siteurl' => $_G['setting']['siteurl'],
				'uid' => $member['uid'],
				'idstring' => $idstring,
				'clientip' => $clientip,
				'sign' => make_getpws_sign($member['uid'], $idstring),
			)
		);
		if(!sendmail("$username <$tmp[email]>", $get_passwd_subject, $get_passwd_message)) {
			return_status(3021102,'邮件发送失败');
		}
		return_status(200,'邮件发送成功');
	}

	public function get_resendmail()
	{
		global $_G;
		$username = urldecode($this->username);
		$email = strtolower(trim(urldecode($this->email)));
		$clientip = urldecode($this->clientip);

		if(empty($username) || empty($email) || empty($clientip) ){
			return_status(403);
		}

		loaducenter();
		if($username) {
			list($tmp['uid'], , $tmp['email']) = uc_get_user(addslashes($username));
			$tmp['email'] = strtolower(trim($tmp['email']));
			if($email != $tmp['email']) {
				return_status(301101,'重发邮件，提交的用户信息错误');
			}
			$member = getuserbyuid($tmp['uid'], 1);
		} else {
			$emailcount = C::t('common_member')->count_by_email($email, 1);
			if(!$emailcount) {
				return_status(301102,'邮箱不存在');
			}
			if($emailcount > 1) {
				return_status(301103,'提交的邮箱存在多用户使用，不能发送邮件');
			}
			$member = C::t('common_member')->fetch_by_email($email, 1);
			list($tmp['uid'], , $tmp['email']) = uc_get_user(addslashes($member['username']));
			$tmp['email'] = strtolower(trim($tmp['email']));
		}
		if(!$member) {
			return_status(301101,'重发邮件，提交的用户信息错误');
		}

		$uid = $tmp['uid'];
		$ctlObj = new register_ctl();
		$ctlObj->setting = $_G['setting'];
		
		// $idstring = random(6);
		// $authstr = $ctlObj->setting['regverify'] == 1 ? "$_G[timestamp]\t2\t$idstring" : '';
		// C::t('common_member_field_forum')->update($uid, array('authstr' => $authstr));
		// $verifyurl = "{$_G['setting']['siteurl']}member.php?mod=activate&amp;uid=$uid&amp;id=$idstring";

		$hash = authcode("$uid\t$email\t$_G[timestamp]", 'ENCODE', md5(substr(md5($_G['config']['security']['authkey']), 0, 16)));
		$verifyurl = $_G['setting']['siteurl'].'home.php?mod=misc&amp;ac=emailcheck&amp;hash='.urlencode($hash);

		$email_verify_message = lang('email', 'email_verify_message', array(
					'username' => $username,
					'bbname' => $ctlObj->setting['bbname'],
					'siteurl' => $_G['setting']['siteurl'],
					'url' => $verifyurl
					));
		if(!sendmail("$username <$email>", lang('email', 'email_verify_subject'), $email_verify_message)) {
			runlog('sendmail', "$email sendmail failed.");
			return_status(3021102,'邮件发送失败');
		}else{
			return_status(3021101,'邮件已发送，请登录邮箱验证');
		}
	}

	public function put_profile()
	{
		$uid = intval($this->uid);
		$clientip = urldecode($this->clientip);
		$gender = $this->gender;
		$avatar_big = urldecode($this->avatar_big);
		$avatar_middle = urldecode($this->avatar_middle);
		$avatar_small = urldecode($this->avatar_small);

		if(!$uid || !$clientip || $gender>2)
			return_status(403);

		if(!isset($gender) && !(!empty($avatar_big) && !empty($avatar_middle) && !empty($avatar_small)))
			return_status(403);

		loaducenter();
		$member = getuserbyuid($uid, 1);

		if(!$member){
			return_status(405,'不存在的用户');
		}

		$error_arr = array();
		$success_msg = array();

		if(isset($gender))
		{
			$gender = intval($gender)>2 ? 0 : intval($gender);
			if(DB::query("update ".DB::table('common_member_profile')." set gender=$gender where uid=$uid")){
				array_push($success_msg, '性别更新成功');
			}
		}
		if(!empty($avatar_big) && !empty($avatar_middle) && !empty($avatar_small))
		{
			$uc_input = uc_api_input("uid=$uid");
			$uc_avatarurl = UC_API.'/index.php?m=user&inajax=1&a=rectavatar&appid='.UC_APPID.'&input='.$uc_input.'&agent='.md5($_SERVER['HTTP_USER_AGENT']).'&avatartype=virtual';
			$post_data = array(
				'urlReaderTS' => (int) microtime(true)*1000,
				'avatar1' => flashdata_encode(file_get_contents($avatar_big)),
				'avatar2' => flashdata_encode(file_get_contents($avatar_middle)),
				'avatar3' => flashdata_encode(file_get_contents($avatar_small))
			);
			$response = push_http_query($uc_avatarurl,$post_data,'rectavatar');
			if(!preg_match("%success=\"1\"%is", $response)){
				write_log($uc_avatarurl.'###post_data=>##'.json_encode($post_data).'###response=>##'.$response);
				array_push($error_arr, '更新头像失败');
			}else{
				array_push($success_msg, '更新头像成功');
			}
		}
		if(count($error_arr)>0) {
			return_status(405,join(',',$error_arr));
		}else{
			$username = $member['username'];
			$userinfo =  C::t('common_member')->fetch_all_stat_memberlist($username);
			$member = C::t('common_member')->fetch_by_username($username);
			$userinfo = array_merge($userinfo[$uid],$member);
			$final = $this->relation_item($userinfo);
			$this->success_result($final,join(',',$success_msg));
		}
	}

	private function relation_item($item)
	{
		return array(
			'uid' => (int)$item['uid'],
			'gender' => (int) $item['gender'],
			'email' => $item['email'],
			'username' => $item['username'],
			'password' => $item['password'],
			'avatar' => avatar($item['uid'],'big',1),
			// 'avatar' => array(avatar($item['uid'],'big',1),avatar($item['uid'],'middle',1),avatar($item['uid'],'small',1)),
			'status' => (int)$item['status'],
			'emailstatus' => (int)$item['emailstatus'],
			'avatarstatus' => (int)$item['avatarstatus'],
			'videophotostatus' => (int)$item['videophotostatus'],
			'adminid' => (int)$item['adminid'],
			'groupid' => (int)$item['groupid'],
			'groupexpiry' => (int)$item['groupexpiry'],
			'extgroupids' => $item['extgroupids'],
			'regdate' => (int)$item['regdate'],
			'credits' => (int)$item['credits'],
			'notifysound' => (int)$item['notifysound'],
			'timeoffset' => (int)$item['timeoffset'],
			'newpm' => (int) $item['newpm'],
			'newprompt' => (int) $item['newprompt'],
			'accessmasks' => (int) $item['accessmasks'],
			'allowadmincp' => (int) $item['allowadmincp'],
			'onlyacceptfriendpm' => (int) $item['onlyacceptfriendpm'],
			'conisbind' => (int) $item['conisbind']
		);
	}
}