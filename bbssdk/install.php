<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}
require_once 'vendor/autoload.php';
require_once 'lib/function.php';
$request_url = str_replace('&step='.$_GET['step'],'',$_SERVER['QUERY_STRING']);
$form_url = str_replace('action=','',$request_url);
showsubmenusteps($installlang['title'], array(
	array($installlang['check'], $_GET['step']==''),
	array($installlang['install'], $_GET['step']=='install'),
	array($installlang['succeed'], $_GET['step']=='ok')
));

$pluginName = 'bbssdk';
$final = false;
$delPlugin = rtrim($_G['siteurl'],'/').$_SERVER['PHP_SELF'].'?action=plugins&operation=delete&pluginid='.$_GET['pluginid'];
switch($_GET['step']){
	default:
		require_once 'check.php';
		$srcFile = dirname(__FILE__) . '/files/remote.php';
		$final = getCheckJson();
		if(floatval($final['phpversion']) < 5.3){
			cpmsg($installlang['phpversion_msg'], $delPlugin, 'error');
		}
		if(floatval($final['mysqlversion']) < 5){
			cpmsg($installlang['mysql_msg'],$delPlugin,'error');
		}
		if(!$final['mysqlgrants']){
			cpmsg($installlang['dbuser_msg'],$delPlugin,'error');
		}
		$destFile = dirname(dirname(dirname(dirname(__FILE__)))) . '/api/mobile/';
		$filemodel = decbin(file_mode_info($destFile));
		$modelNum = substr($filemodel, strlen($filemodel)-2);
		if(!file_exists($destFile.'remote.php') && $modelNum != '11'){
			cpmsg(dirname($destFile).$installlang['file_msg']."($modelNum,$filemodel)",$delPlugin,'error');
		}else{
			$destFile = $destFile.'remote.php';
			$input = @file_get_contents($srcFile);
			if(!file_exists($destFile) && !empty($input)){
				@file_put_contents($destFile, $input);
			}
		}
		install_action();
		C::t('common_plugin')->update($_GET['pluginid'], array('available' => '1'));
		updatecache(array('plugin', 'setting', 'styles'));
                dheader('location: '.$_SERVER['PHP_SELF']."?{$request_url}&step=install&modetype=1");
//		cpmsg($installlang['ifreg'], "{$request_url}&step=install&modetype=1", 'form', array(), '', TRUE, $delPlugin);
	case 'install':
		if(extension_loaded('curl')){
			if($_GET['modetype'] == '1'){
				if(!submitcheck('submit')){
					$mob_setting_url = trim($_G['setting']['discuzurl'],'/').'/api/mobile/remote.php';
					showtips($installlang['hd_tip1']);
					showformheader("{$form_url}&step=install&modetype=1");
					showtableheader($installlang['reg_header']);
					showsetting('AppKey', 'appkey', '', 'text', '', '', $installlang['must_fill']);
					showsetting('AppSecret', 'appsecret', '', 'text', '', '', $installlang['must_fill']);
					showsetting($installlang['address_key'], 'discuzurl', $mob_setting_url, 'text', '', '', $installlang['must_fill']);
					showsubmit('submit', 'submit');
					showtablefooter();
					showformfooter();
				}else{
					if(!$_GET['appkey'] || !$_GET['appsecret']) cpmsg($installlang['not_fill'], "", 'error');
					$appkey = (string) trim($_GET['appkey']);
					$appsecret = (string) trim($_GET['appsecret']);
					$mob_setting_url = empty($_GET['discuzurl']) ? trim($_G['setting']['discuzurl'],'/').'/api/mobile/remote.php' : trim($_GET['discuzurl']);

					$appInfo = json_decode(utf8_encode(file_get_contents($mob_setting_url."?check=check")),true);

					if(!$appInfo['plugin_info']['bbssdk']['enabled']){
						cpmsg($installlang['discuzurl_error'], "", 'error');
					}

					$mob_request_url = "http://admin.mob.com/api/bbs/info?appkey=$appkey&url=".urlencode($mob_setting_url);

					$result = json_decode(utf8_encode(file_get_contents($mob_request_url)),true);

					write_log('query url ==>'.$mob_request_url."\t response ==>".json_encode($result));

					if($result['status'] == 200 || $result['status'] == 502){
						$pluginid = intval($_GET['pluginid']);
					    C::t('common_pluginvar')->update_by_variable($pluginid, 'appkey', array('value' => $appkey));
					    C::t('common_pluginvar')->update_by_variable($pluginid, 'appsecret', array('value' => $appsecret));
						updatecache(array('plugin', 'setting', 'styles'));
						cleartemplatecache();
						cpmsg($installlang['install_succeed'], "{$request_url}&step=ok", 'loading', '');
					}else{
						$msg = $result['status'] == 503 ? $installlang['address_msg'] : $installlang['errmsg'] ;
						cpmsg_error($msg, '', diconv($result['msg'], 'UTF-8', CHARSET));
					}
				}
			}
		}else{
			cpmsg($installlang['curl_unsupported'], $delPlugin, 'error');
		}
		break;
	case 'ok':
		$finish = TRUE;
		break;
}

function install_action()
{
	@include_once libfile('cache/setting', 'function');
	build_cache_setting();

	$sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_comment_sync')."` (
	  `syncid` int(11) NOT NULL AUTO_INCREMENT,
	  `fid` int(11) DEFAULT NULL,
	  `tid` int(11) DEFAULT NULL,
	  `pid` int(11) DEFAULT NULL,
	  `creattime` int(11) DEFAULT NULL,
	  `modifytime` int(11) DEFAULT NULL,
	  `synctime` int(11) DEFAULT NULL,
	  `flag` tinyint(4) DEFAULT NULL,
	  PRIMARY KEY (`syncid`),
	  UNIQUE KEY `indexid` (`fid`,`tid`,`pid`),
	  KEY `fid` (`fid`),
	  KEY `tid` (`tid`),
	  KEY `pid` (`pid`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

	DB::query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_forum_sync')."` (
	  `syncid` int(11) NOT NULL AUTO_INCREMENT,
	  `fid` int(11) DEFAULT NULL,
	  `tid` int(11) DEFAULT NULL,
	  `creattime` int(11) DEFAULT NULL,
	  `modifytime` int(11) DEFAULT NULL,
	  `synctime` int(11) DEFAULT NULL,
	  `flag` tinyint(4) DEFAULT NULL,
	  PRIMARY KEY (`syncid`),
	  UNIQUE KEY `indexid` (`fid`,`tid`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

	DB::query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_menu_sync')."` (
	  `syncid` int(11) NOT NULL AUTO_INCREMENT,
	  `fid` int(11) DEFAULT NULL,
	  `creattime` int(11) DEFAULT NULL,
	  `modifytime` int(11) DEFAULT '0',
	  `synctime` int(11) DEFAULT '0',
	  `flag` tinyint(4) DEFAULT '0',
	  PRIMARY KEY (`syncid`),
	  UNIQUE KEY `fid` (`fid`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

	DB::query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_member_sync')."` (
	  `syncid` int(11) NOT NULL AUTO_INCREMENT,
	  `uid` int(11) DEFAULT NULL,
	  `creattime` int(11) DEFAULT NULL,
	  `modifytime` int(11) DEFAULT '0',
	  `synctime` int(11) DEFAULT '0',
	  `flag` tinyint(4) DEFAULT '0',
	  PRIMARY KEY (`syncid`),
	  UNIQUE KEY `uid` (`uid`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

	DB::query($sql);

	$sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_usergroup_sync')."` (
	  `syncid` int(11) NOT NULL AUTO_INCREMENT,
	  `groupid` int(11) DEFAULT NULL,
	  `creattime` int(11) DEFAULT NULL,
	  `modifytime` int(11) DEFAULT '0',
	  `synctime` int(11) DEFAULT '0',
	  `flag` tinyint(4) DEFAULT '0',
	  PRIMARY KEY (`syncid`),
	  UNIQUE KEY `groupid` (`groupid`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

	DB::query($sql);
        
        $sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_oauth')."` (
          `id` INT NOT NULL AUTO_INCREMENT , 
	  `uid` INT NULL DEFAULT NULL , 
          `wxOpenid` varchar(100) DEFAULT NULL,
          `wxUnionid` varchar(100) DEFAULT NULL,
          `qqOpenid` varchar(100) DEFAULT NULL,
          `qqUnionid` varchar(100) DEFAULT NULL,
          PRIMARY KEY (`id`),
          UNIQUE `uid` (`uid`), 
          index `wxOpenid` (`wxOpenid`), 
          index `wxUnionid` (`wxUnionid`), 
          index `qqOpenid` (`qqOpenid`), 
          index `qqUnionid` (`qqUnionid`)
          ) ENGINE = InnoDB DEFAULT CHARSET=utf8;";

	DB::query($sql);
        
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_menu;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_menu;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_menu;";
	DB::query($sql);


	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_forum;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_forum;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_forum;";
	DB::query($sql);


	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_comment;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_comment;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_comment;";
	DB::query($sql);

	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_usergroup;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_usergroup;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_usergroup;";
	DB::query($sql);

	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_member;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_member;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_member;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_memberprofile;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_memberprofile;";
	DB::query($sql);
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_memberprofile;";
	DB::query($sql);

	/* 用户模块开始 */
	$sql = "CREATE TRIGGER bbssdk_afterinsert_on_member AFTER INSERT ON `".DB::table('common_member')."` FOR EACH ROW \r\n
	BEGIN
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	SET @currtime = UNIX_TIMESTAMP(NOW());
        set @uid = new.uid;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_member_sync')."` WHERE uid=@uid;
	if @syncid = 0 THEN
		INSERT INTO `".DB::table('bbssdk_member_sync')."`(uid,modifytime,creattime,synctime,flag) VALUES(@uid,@currtime,@currtime,0,1);
	END IF;
	END;";
	DB::query($sql);
	
	$sql = "CREATE TRIGGER bbssdk_afterupdate_on_member AFTER UPDATE ON `".DB::table('common_member')."` FOR EACH ROW \r\n
	BEGIN
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	set @uid = old.uid;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_member_sync')."` WHERE uid=@uid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid > 0 THEN
		UPDATE `".DB::table('bbssdk_member_sync')."` SET modifytime=@currtime,synctime=0,flag=2 WHERE syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_member_sync')."`(uid,modifytime,creattime,synctime,flag) VALUES(@uid,@currtime,@currtime,0,2);
	END IF;
	END;";
	DB::query($sql);
	
	$sql = "CREATE TRIGGER bbssdk_afterdelete_on_member AFTER DELETE ON `".DB::table('common_member')."` FOR EACH ROW \r\n
	BEGIN
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	set @uid = old.uid;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_member_sync')."` WHERE uid=@uid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid > 0 THEN
		UPDATE `".DB::table('bbssdk_member_sync')."` SET modifytime=@currtime,synctime=0,flag=3 WHERE syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_member_sync')."`(uid,modifytime,creattime,synctime,flag) VALUES(@uid,@currtime,@currtime,0,3);
	END IF;
	END;";
	DB::query($sql);
	
	$sql = "CREATE TRIGGER bbssdk_afterupdate_on_memberprofile AFTER UPDATE ON `".DB::table('common_member_profile')."` FOR EACH ROW \r\n
	BEGIN
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	set @uid = old.uid;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_member_sync')."` WHERE uid=@uid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid > 0 THEN
		UPDATE `".DB::table('bbssdk_member_sync')."` SET modifytime=@currtime,synctime=0,flag=2 WHERE syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_member_sync')."`(uid,modifytime,creattime,synctime,flag) VALUES(@uid,@currtime,@currtime,0,2);
	END IF;
	END;";
	DB::query($sql);
	
	$sql = "CREATE TRIGGER bbssdk_afterdelete_on_memberprofile AFTER DELETE ON `".DB::table('common_member_profile')."` FOR EACH ROW \r\n
	BEGIN
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	set @uid = old.uid;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_member_sync')."` WHERE uid=@uid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid > 0 THEN
		UPDATE `".DB::table('bbssdk_member_sync')."` SET modifytime=@currtime,synctime=0,flag=3 WHERE syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_member_sync')."`(uid,modifytime,creattime,synctime,flag) VALUES(@uid,@currtime,@currtime,0,3);
	END IF;
	END;";
	DB::query($sql);	
	/* 用户模块结束 */

	/* 板块模块 */
	// 板块新增
	$sql = "CREATE TRIGGER bbssdk_afterinsert_on_menu AFTER INSERT ON `".DB::table('forum_forum')."` FOR EACH ROW \r\n
	BEGIN
	SET @currtime = UNIX_TIMESTAMP(NOW());
	INSERT INTO `".DB::table('bbssdk_menu_sync')."`(fid,modifytime,creattime,synctime,flag) VALUES(new.fid,@currtime,@currtime,0,1);
	END;";
	DB::query($sql);

	// 板块更新
	$sql = "CREATE TRIGGER bbssdk_afterupdate_on_menu AFTER UPDATE ON `".DB::table('forum_forum')."` FOR EACH ROW \r\n
	BEGIN
	set @fid = old.fid;
	set @syncid = (SELECT syncid FROM `".DB::table('bbssdk_menu_sync')."` WHERE fid=@fid);
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid > 0 THEN
		UPDATE `".DB::table('bbssdk_menu_sync')."` SET modifytime=@currtime,synctime=0,flag=2 WHERE syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_menu_sync')."`(fid,modifytime,creattime,synctime,flag) VALUES(@fid,@currtime,@currtime,0,2);
	END IF;
	END;";
	DB::query($sql);

	// 板块删除
	$sql = "CREATE TRIGGER bbssdk_afterdelete_on_menu AFTER DELETE ON `".DB::table('forum_forum')."` FOR EACH ROW \r\n
	BEGIN
	set @fid = old.fid;
	set @syncid = (SELECT syncid FROM `".DB::table('bbssdk_menu_sync')."` WHERE fid=@fid);
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid > 0 THEN
		UPDATE `".DB::table('bbssdk_menu_sync')."` SET modifytime=@currtime,synctime=0,flag=3 WHERE syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_menu_sync')."`(fid,modifytime,creattime,synctime,flag) VALUES(@fid,@currtime,@currtime,0,3);
	END IF;
	END;";
	DB::query($sql);
	/* 板块模块 */

	/* 帖子模块 */
	// 帖子新增
	$sql = "CREATE TRIGGER bbssdk_afterinsert_on_forum AFTER INSERT ON `".DB::table('forum_thread')."` FOR EACH ROW \n
	BEGIN
	set @fid = new.fid;
	set @tid = new.tid;
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_forum_sync')."` where fid=@fid and tid=@tid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid=0 THEN
		INSERT INTO `".DB::table('bbssdk_forum_sync')."`(fid,tid,creattime,modifytime,synctime,flag) VALUE(@fid,@tid,@currtime,@currtime,0,1);
	END IF;
	END;";
	DB::query($sql);

	// 帖子修改 
	$sql = "CREATE TRIGGER bbssdk_afterupdate_on_forum AFTER UPDATE ON `".DB::table('forum_thread')."` FOR EACH ROW \n
	BEGIN
	set @fid = old.fid;
	set @tid = old.tid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_forum_sync')."` where fid=@fid and tid=@tid;
	IF @syncid>0 THEN
		UPDATE `".DB::table('bbssdk_forum_sync')."` SET modifytime=@currtime,synctime=0,flag=2 where syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_forum_sync')."`(fid,tid,creattime,modifytime,synctime,flag) VALUE(@fid,@tid,@currtime,@currtime,0,2);
	END IF;
	END;";
	DB::query($sql);

	// 帖子删除
	$sql = "CREATE TRIGGER bbssdk_afterdelete_on_forum AFTER DELETE ON `".DB::table('forum_thread')."` FOR EACH ROW \n
	BEGIN
	set @fid = old.fid;
	set @tid = old.tid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_forum_sync')."` where fid=@fid and tid=@tid;
	IF @syncid>0 THEN
		UPDATE `".DB::table('bbssdk_forum_sync')."` SET modifytime=@currtime,synctime=0,flag=3 where syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_forum_sync')."`(fid,tid,creattime,modifytime,synctime,flag) VALUE(@fid,@tid,@currtime,@currtime,0,3);
	END IF;
	END;";
	DB::query($sql);
	/* 帖子模块结束 */

	// 评论创建
	$sql = "CREATE TRIGGER bbssdk_afterinsert_on_comment AFTER INSERT ON `".DB::table('forum_post')."` FOR EACH ROW \n
	BEGIN
	 set @fid = new.fid;
	 set @tid = new.tid;
	 set @pid = new.pid;
	 SET @currtime = UNIX_TIMESTAMP(NOW());
	 set @first = new.first;	 
	 set @syncid=0;
	 set @modifytime=0;
	 set @synctime=0;
	 IF @first = 1 THEN
		SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_forum_sync')."` where fid=@fid and tid=@tid;
		IF @syncid > 0 THEN
			UPDATE `".DB::table('bbssdk_forum_sync')."` SET modifytime=@currtime,synctime=0,flag=2 where syncid=@syncid;
		ELSE
			INSERT INTO `".DB::table('bbssdk_forum_sync')."`(fid,tid,creattime,modifytime,synctime,flag) VALUE(@fid,@tid,@currtime,@currtime,0,1);
		END IF;
	 ELSE
		SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_comment_sync')."` where fid=@fid and tid=@tid and pid=@pid;
		if @syncid = 0 THEN
			INSERT INTO `".DB::table('bbssdk_comment_sync')."`(fid,tid,pid,creattime,modifytime,synctime,flag) VALUE(@fid,@tid,@pid,@currtime,@currtime,0,1);
		END IF;
	 END IF;
	END;";
	DB::query($sql);

	// 评论更新
	$sql = "CREATE TRIGGER bbssdk_afterupdate_on_comment AFTER UPDATE ON `".DB::table('forum_post')."` FOR EACH ROW \n
	BEGIN
	 set @fid = new.fid;
	 set @tid = new.tid;
	 set @pid = new.pid;
	 SET @currtime = UNIX_TIMESTAMP(NOW());
	 set @syncid=0;
	 set @modifytime=0;
	 set @synctime=0;
	 set @first = new.first;
	 IF @first = 1 THEN
	 	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_forum_sync')."` where fid=@fid and tid=@tid;
		IF @syncid > 0 THEN
			UPDATE `".DB::table('bbssdk_forum_sync')."` SET modifytime=@currtime,synctime=0,flag=2 where syncid=@syncid;
		ELSE
			INSERT INTO `".DB::table('bbssdk_forum_sync')."`(fid,tid,creattime,modifytime,synctime,flag) VALUE(@fid,@tid,@currtime,@currtime,0,2);
		END IF;
	 ELSE
		SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_comment_sync')."` where fid=@fid and tid=@tid and pid=@pid;
		IF @syncid > 0 THEN
			UPDATE `".DB::table('bbssdk_comment_sync')."` SET modifytime=@currtime,synctime=0,flag=2 where syncid=@syncid;
	    ELSE
			INSERT INTO `".DB::table('bbssdk_comment_sync')."`(fid,tid,pid,creattime,modifytime,synctime,flag) VALUE(@fid,@tid,@pid,@currtime,@currtime,0,2);
		END IF;
	 END IF;
	END;";
	DB::query($sql);

	// 评论删除 
	$sql = "CREATE TRIGGER bbssdk_afterdelete_on_comment AFTER DELETE ON `".DB::table('forum_post')."` FOR EACH ROW \n
	BEGIN
	 set @fid = old.fid;
	 set @tid = old.tid;
	 set @pid = old.pid;
	 SET @currtime = UNIX_TIMESTAMP(NOW());
	 set @first = old.first;
	 set @syncid=0;
	 set @modifytime=0;
	 set @synctime=0;
	 IF @first = 1 THEN
	 	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_forum_sync')."` where fid=@fid and tid=@tid;
		IF @syncid > 0 THEN
			UPDATE `".DB::table('bbssdk_forum_sync')."` SET modifytime=@currtime,synctime=0,flag=3 where syncid=@syncid;
		ELSE
			INSERT INTO `".DB::table('bbssdk_forum_sync')."`(fid,tid,creattime,modifytime,synctime,flag) VALUE(@fid,@tid,@currtime,@currtime,0,3);
		END IF;
	 ELSE
		SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_comment_sync')."` where fid=@fid and tid=@tid and pid=@pid;
		IF @syncid > 0 THEN
			UPDATE `".DB::table('bbssdk_comment_sync')."` SET modifytime=@currtime,synctime=0,flag=3 where syncid=@syncid;
	  ELSE
			INSERT INTO `".DB::table('bbssdk_comment_sync')."`(fid,tid,pid,creattime,modifytime,synctime,flag) VALUE(@fid,@tid,@pid,@currtime,@currtime,0,3);
		END IF;
	 END IF;
	END;";

	DB::query($sql);
        
        /* 收藏模块开始 */

        $sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_favorite_sync')."` (
            `syncid` int(11) NOT NULL AUTO_INCREMENT,
            `favid` int(11) NOT NULL,
            `creattime` int(11) NOT NULL,
            `modifytime` int(11) NOT NULL,
            `synctime` int(11) NOT NULL,
            `flag` tinyint(4) NOT NULL,
            PRIMARY KEY (`syncid`),
            UNIQUE KEY `favid` (`favid`)
          ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

        DB::query($sql);

        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_homefavorite;";
        DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_homefavorite;";
        DB::query($sql);

        $sql = "CREATE TRIGGER bbssdk_afterinsert_on_homefavorite AFTER INSERT ON `".DB::table('home_favorite')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        set @favid = new.favid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_favorite_sync')."` WHERE favid=@favid;
        if @syncid = 0 THEN
                INSERT INTO `".DB::table('bbssdk_favorite_sync')."`(favid,modifytime,creattime,synctime,flag) VALUES(new.favid,@currtime,@currtime,0,1);
        END IF;
        END;";
        DB::query($sql);

        $sql = "CREATE TRIGGER bbssdk_afterdelete_on_homefavorite AFTER DELETE ON `".DB::table('home_favorite')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        set @favid = old.favid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_favorite_sync')."` WHERE favid=@favid;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        IF @syncid > 0 THEN
                UPDATE `".DB::table('bbssdk_favorite_sync')."` SET modifytime=@currtime,synctime=0,flag=3 WHERE syncid=@syncid;
        ELSE
                INSERT INTO `".DB::table('bbssdk_favorite_sync')."`(favid,modifytime,creattime,synctime,flag) VALUES(@favid,@currtime,@currtime,0,3);
        END IF;
        END;";
        DB::query($sql);
        /* 收藏模块结束 */

        /* 通知模块开始 */
        $sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_notification_sync')."` (
            `syncid` int(11) NOT NULL AUTO_INCREMENT,
            `noticeid` int(11) NOT NULL,
            `creattime` int(11) NOT NULL,
            `modifytime` int(11) NOT NULL,
            `synctime` int(11) NOT NULL,
            `flag` tinyint(4) NOT NULL,
            PRIMARY KEY (`syncid`),
            UNIQUE KEY `noticeid` (`noticeid`)
          ) ENGINE=MyISAM  DEFAULT CHARSET=utf8;";

        DB::query($sql);

        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_homenotification;";
        DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_homenotification;";
        DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_homenotification;";
        DB::query($sql);

        $sql = "CREATE TRIGGER bbssdk_afterinsert_on_homenotification AFTER INSERT ON `".DB::table('home_notification')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        set @noticeid = new.id;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_notification_sync')."` WHERE noticeid=@noticeid;
        if @syncid = 0 THEN
                INSERT INTO `".DB::table('bbssdk_notification_sync')."`(noticeid,modifytime,creattime,synctime,flag) VALUES(new.id,@currtime,@currtime,0,1);
        END IF;
        END;";
        DB::query($sql);

        $sql = "CREATE TRIGGER bbssdk_afterdelete_on_homenotification AFTER DELETE ON `".DB::table('home_notification')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        set @noticeid = old.id;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_notification_sync')."` WHERE noticeid=@noticeid;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        IF @syncid > 0 THEN
                UPDATE `".DB::table('bbssdk_notification_sync')."` SET modifytime=@currtime,synctime=0,flag=3 WHERE syncid=@syncid;
        ELSE
                INSERT INTO `".DB::table('bbssdk_notification_sync')."`(noticeid,modifytime,creattime,synctime,flag) VALUES(@noticeid,@currtime,@currtime,0,3);
        END IF;
        END;";
        DB::query($sql);

        $sql = "CREATE TRIGGER bbssdk_afterupdate_on_homenotification AFTER UPDATE ON `".DB::table('home_notification')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        set @noticeid = old.id;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_notification_sync')."` WHERE noticeid=@noticeid;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        IF @syncid > 0 THEN
                UPDATE `".DB::table('bbssdk_notification_sync')."` SET modifytime=@currtime,synctime=0,flag=2 WHERE syncid=@syncid;
        ELSE
                INSERT INTO `".DB::table('bbssdk_notification_sync')."`(noticeid,modifytime,creattime,synctime,flag) VALUES(@noticeid,@currtime,@currtime,0,2);
        END IF;
        END;";
        DB::query($sql);
        /* 通知模块结束 */

        /* 用户模块开始 */
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_memberfieldforum ;";
        DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_memberfieldforum ;";
        DB::query($sql);

        $sql = "CREATE TRIGGER bbssdk_afterinsert_on_memberfieldforum AFTER INSERT ON `".DB::table('common_member_field_forum')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        set @uid = new.uid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_member_sync')."` WHERE uid=@uid;
        if @syncid = 0 THEN
                INSERT INTO `".DB::table('bbssdk_member_sync')."`(uid,modifytime,creattime,synctime,flag) VALUES(new.uid,@currtime,@currtime,0,2);
        END IF;
        END;";
        DB::query($sql);

        $sql = "CREATE TRIGGER bbssdk_afterupdate_on_memberfieldforum AFTER UPDATE ON `".DB::table('common_member_field_forum')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        set @uid = old.uid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_member_sync')."` WHERE uid=@uid;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        IF @syncid > 0 THEN
                UPDATE `".DB::table('bbssdk_member_sync')."` SET modifytime=@currtime,synctime=0,flag=2 WHERE syncid=@syncid;
        ELSE
                INSERT INTO `".DB::table('bbssdk_member_sync')."`(uid,modifytime,creattime,synctime,flag) VALUES(@uid,@currtime,@currtime,0,2);
        END IF;
        END;";
        DB::query($sql);
        /* 用户模块结束 */
        //门户相关
        /* 门户文章模块开始 */
        $sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_portal_article_sync')."` (
	  `syncid` int(11) NOT NULL AUTO_INCREMENT,
	  `aid` int(11) DEFAULT NULL,
	  `creattime` int(11) DEFAULT NULL,
	  `modifytime` int(11) DEFAULT '0',
	  `synctime` int(11) DEFAULT '0',
	  `flag` tinyint(4) DEFAULT '0',
	  PRIMARY KEY (`syncid`),
	  UNIQUE KEY `aid` (`aid`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        DB::query($sql);
        
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_portalarticletitle;";
	DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_portalarticletitle;";
	DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_portalarticletitle;";
	DB::query($sql);
        
        $sql = "CREATE TRIGGER bbssdk_afterinsert_on_portalarticletitle AFTER INSERT ON `".DB::table('portal_article_title')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        set @aid = new.aid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_portal_article_sync')."` WHERE aid=@aid;
        if @syncid = 0 THEN
                INSERT INTO `".DB::table('bbssdk_portal_article_sync')."`(aid,modifytime,creattime,synctime,flag) VALUES(new.aid,@currtime,@currtime,0,1);
        END IF;
        END;";
        DB::query($sql);
        
        $sql = "CREATE TRIGGER bbssdk_afterupdate_on_portalarticletitle AFTER UPDATE ON `".DB::table('portal_article_title')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        set @aid = old.aid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_portal_article_sync')."` WHERE aid=@aid;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        IF @syncid > 0 THEN
                UPDATE `".DB::table('bbssdk_portal_article_sync')."` SET modifytime=@currtime,synctime=0,flag=2 WHERE syncid=@syncid;
        ELSE
                INSERT INTO `".DB::table('bbssdk_portal_article_sync')."`(aid,modifytime,creattime,synctime,flag) VALUES(@aid,@currtime,@currtime,0,2);
        END IF;
        END;";
        DB::query($sql);
        
        $sql = "CREATE TRIGGER bbssdk_afterdelete_on_portalarticletitle AFTER DELETE ON `".DB::table('portal_article_title')."` FOR EACH ROW \r\n
	BEGIN
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	set @aid = old.aid;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_portal_article_sync')."` WHERE aid=@aid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid > 0 THEN
		UPDATE `".DB::table('bbssdk_portal_article_sync')."` SET modifytime=@currtime,synctime=0,flag=3 WHERE syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_portal_article_sync')."`(aid,modifytime,creattime,synctime,flag) VALUES(@aid,@currtime,@currtime,0,3);
	END IF;
	END;";
	DB::query($sql);
        /* 门户文章模块结束 */

        /* 门户文章评论模块开始 */
        $sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_portal_comment_sync')."` (
	  `syncid` int(11) NOT NULL AUTO_INCREMENT,
	  `cid` int(11) DEFAULT NULL,
	  `creattime` int(11) DEFAULT NULL,
	  `modifytime` int(11) DEFAULT '0',
	  `synctime` int(11) DEFAULT '0',
	  `flag` tinyint(4) DEFAULT '0',
	  PRIMARY KEY (`syncid`),
	  UNIQUE KEY `cid` (`cid`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        DB::query($sql);
        
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_portalcomment;";
	DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_portalcomment;";
	DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_portalcomment;";
	DB::query($sql);
        
        $sql = "CREATE TRIGGER bbssdk_afterinsert_on_portalcomment AFTER INSERT ON `".DB::table('portal_comment')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        set @cid = new.cid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_portal_comment_sync')."` WHERE cid=@cid;
        if @syncid = 0 THEN
                INSERT INTO `".DB::table('bbssdk_portal_comment_sync')."`(cid,modifytime,creattime,synctime,flag) VALUES(new.cid,@currtime,@currtime,0,1);
        END IF;
        END;";
        DB::query($sql);
        
        $sql = "CREATE TRIGGER bbssdk_afterupdate_on_portalcomment AFTER UPDATE ON `".DB::table('portal_comment')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        set @cid = old.cid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_portal_comment_sync')."` WHERE cid=@cid;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        IF @syncid > 0 THEN
                UPDATE `".DB::table('bbssdk_portal_comment_sync')."` SET modifytime=@currtime,synctime=0,flag=2 WHERE syncid=@syncid;
        ELSE
                INSERT INTO `".DB::table('bbssdk_portal_comment_sync')."`(cid,modifytime,creattime,synctime,flag) VALUES(@cid,@currtime,@currtime,0,2);
        END IF;
        END;";
        DB::query($sql);
        
        $sql = "CREATE TRIGGER bbssdk_afterdelete_on_portalcomment AFTER DELETE ON `".DB::table('portal_comment')."` FOR EACH ROW \r\n
	BEGIN
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	set @cid = old.cid;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_portal_comment_sync')."` WHERE cid=@cid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid > 0 THEN
		UPDATE `".DB::table('bbssdk_portal_comment_sync')."` SET modifytime=@currtime,synctime=0,flag=3 WHERE syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_portal_comment_sync')."`(cid,modifytime,creattime,synctime,flag) VALUES(@cid,@currtime,@currtime,0,3);
	END IF;
	END;";
	DB::query($sql);
        /* 门户文章评论模块结束 */
        
        /* 门户栏目模块结束 */
        $sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_portal_category_sync')."` (
	  `syncid` int(11) NOT NULL AUTO_INCREMENT,
	  `catid` int(11) DEFAULT NULL,
	  `creattime` int(11) DEFAULT NULL,
	  `modifytime` int(11) DEFAULT '0',
	  `synctime` int(11) DEFAULT '0',
	  `flag` tinyint(4) DEFAULT '0',
	  PRIMARY KEY (`syncid`),
	  UNIQUE KEY `catid` (`catid`)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8;";
        DB::query($sql);
	
	$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_portalcategory;";
	DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_portalcategory;";
	DB::query($sql);
        $sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_portalcategory;";
	DB::query($sql);
        
        $sql = "CREATE TRIGGER bbssdk_afterinsert_on_portalcategory AFTER INSERT ON `".DB::table('portal_category')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        set @catid = new.catid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_portal_category_sync')."` WHERE catid=@catid;
        if @syncid = 0 THEN
                INSERT INTO `".DB::table('bbssdk_portal_category_sync')."`(catid,modifytime,creattime,synctime,flag) VALUES(new.catid,@currtime,@currtime,0,1);
        END IF;
        END;";
        DB::query($sql);
        
        $sql = "CREATE TRIGGER bbssdk_afterupdate_on_portalcategory AFTER UPDATE ON `".DB::table('portal_category')."` FOR EACH ROW \r\n
        BEGIN
        set @syncid=0;
        set @modifytime=0;
        set @synctime=0;
        set @catid = old.catid;
        SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_portal_category_sync')."` WHERE catid=@catid;
        SET @currtime = UNIX_TIMESTAMP(NOW());
        IF @syncid > 0 THEN
                UPDATE `".DB::table('bbssdk_portal_category_sync')."` SET modifytime=@currtime,synctime=0,flag=2 WHERE syncid=@syncid;
        ELSE
                INSERT INTO `".DB::table('bbssdk_portal_category_sync')."`(catid,modifytime,creattime,synctime,flag) VALUES(@catid,@currtime,@currtime,0,2);
        END IF;
        END;";
        DB::query($sql);
        
        $sql = "CREATE TRIGGER bbssdk_afterdelete_on_portalcategory AFTER DELETE ON `".DB::table('portal_category')."` FOR EACH ROW \r\n
	BEGIN
	set @syncid=0;
	set @modifytime=0;
	set @synctime=0;
	set @catid = old.catid;
	SELECT syncid,modifytime,synctime into @syncid,@modifytime,@synctime FROM `".DB::table('bbssdk_portal_category_sync')."` WHERE catid=@catid;
	SET @currtime = UNIX_TIMESTAMP(NOW());
	IF @syncid > 0 THEN
		UPDATE `".DB::table('bbssdk_portal_category_sync')."` SET modifytime=@currtime,synctime=0,flag=3 WHERE syncid=@syncid;
	ELSE
		INSERT INTO `".DB::table('bbssdk_portal_category_sync')."`(catid,modifytime,creattime,synctime,flag) VALUES(@catid,@currtime,@currtime,0,3);
	END IF;
	END;";
	DB::query($sql);
        /* 门户栏目模块结束 */
	for($i=0; $i < 60; $i++){
		$times = array();
		for($j=0;$j<12 && $i+$j<60;$j++){
			array_push($times , intval($i+$j));
		}
		$i = $i+$j-1;
		$sql = "INSERT INTO ".DB::table('common_cron')."(available,type,`name`,filename,weekday,`day`,`hour`,`minute`) value(1,'plugin','每日BBSSDK同步','bbssdk:cron_sync.php',-1,-1,-1,'".implode("\t",$times)."')";
		DB::query($sql);
	}

	return true;
}

function file_mode_info($file_path)
{
    /* 如果不存在，则不可读、不可写、不可改 */
    if (!file_exists($file_path))
    {
        return false;
    } 
    $mark = 0;
    if (strtoupper(substr(PHP_OS, 0, 3)) == 'WIN')
    {
        /* 测试文件 */
        $test_file = $file_path . '/cf_test.txt';
        /* 如果是目录 */
        if (is_dir($file_path))
        {
            /* 检查目录是否可读 */
            $dir = @opendir($file_path);
            if ($dir === false)
            {
                return $mark; //如果目录打开失败，直接返回目录不可修改、不可写、不可读
            }
            if (@readdir($dir) !== false)
            {
                $mark ^= 1; //目录可读 001，目录不可读 000
            }
            @closedir($dir);
 
            /* 检查目录是否可写 */
            $fp = @fopen($test_file, 'wb');
            if ($fp === false)
            {
                return $mark; //如果目录中的文件创建失败，返回不可写。
            }
            if (@fwrite($fp, 'directory access testing.') !== false)
            {
                $mark ^= 2; //目录可写可读011，目录可写不可读 010
            }
            @fclose($fp);
 
            @unlink($test_file);
 
            /* 检查目录是否可修改 */
            $fp = @fopen($test_file, 'ab+');
            if ($fp === false)
            {
                return $mark;
            }
            if (@fwrite($fp, "modify test.rn") !== false){
                $mark ^= 4;
            }
            @fclose($fp);
            if (@rename($test_file, $test_file) !== false){
                $mark ^= 8;
            }
            @unlink($test_file);
        }elseif (is_file($file_path)){
            $fp = @fopen($file_path, 'rb');
            if ($fp){
                $mark ^= 1; //可读 001
            }
            @fclose($fp);
            $fp = @fopen($file_path, 'ab+');
            if ($fp && @fwrite($fp, '') !== false){
                $mark ^= 6; //可修改可写可读 111，不可修改可写可读011...
            }
            @fclose($fp);
            if (@rename($test_file, $test_file) !== false){
                $mark ^= 8;
            }
        }
    }else{
        if (@is_readable($file_path)){
            $mark ^= 1;
        }
        if (@is_writable($file_path)){
            $mark ^= 14;
        }
    }
    return $mark;
}
