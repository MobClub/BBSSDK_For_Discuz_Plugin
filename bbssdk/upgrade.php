<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}
@include_once libfile('cache/setting', 'function');
build_cache_setting();
upgrade();

global $_G;

$appkey = $_G['cache']['plugin']['bbssdk']['appkey'];
$appsecret = $_G['cache']['plugin']['bbssdk']['appsecret'];

if(!$appkey || !$appsecret){
    $plugin = C::t('common_plugin')->fetch_by_identifier('bbssdk');
    if(!$plugin) {
            cpmsg('plugin_not_found', '', 'error');
    }
    $dir = substr($plugin['directory'], 0, -1);
    $modules = dunserialize($plugin['modules']);
    $installtype = $modules['extra']['installtype'] ? $modules['extra']['installtype'] : '';
    dheader('location: '."admin.php?action=plugins&operation=plugininstall&dir=".$dir."&installtype=".$installtype."&pluginid=".$plugin['pluginid']."&step=install&modetype=1");
}

$mob_setting_url = trim($_G['setting']['discuzurl'],'/').'/api/mobile/remote.php';

$appInfo = json_decode(utf8_encode(file_get_contents($mob_setting_url."?check=check")),true);

if(!$appInfo['plugin_info']['bbssdk']['enabled']){
        cpmsg($installlang['discuzurl_error'], "", 'error');
}

$mob_request_url = "http://www.mob.com/api/dz/getUrl?appkey=$appkey&url=".urlencode($mob_setting_url);

$result = json_decode(utf8_encode(file_get_contents($mob_request_url)),true);

//write_log('upgrade query url ==>'.$mob_request_url."\t response ==>".json_encode($result));

if($result['status'] == 200 || $result['status'] == 502){
        $pluginid = intval($_GET['pluginid']);
        C::t('common_pluginvar')->update_by_variable($pluginid, 'appkey', array('value' => $appkey));
        C::t('common_pluginvar')->update_by_variable($pluginid, 'appsecret', array('value' => $appsecret));
        updatecache(array('plugin', 'setting', 'styles'));
        cleartemplatecache();
        $finish = TRUE;
}else{
        $msg = $result['status'] == 503 ? $installlang['address_msg'] : $installlang['errmsg'] ;
        cpmsg_error($msg, '', diconv($result['msg'], 'UTF-8', CHARSET));
}

function upgrade(){
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
            INSERT INTO `".DB::table('bbssdk_member_sync')."`(uid,modifytime,creattime,synctime,flag) VALUES(@uid,@currtime,@currtime,0,2);
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
    /* 1.5 触发器 fix */
    $sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_member;";
    DB::query($sql);

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
    /* 1.5 触发器 fix结束 */
}