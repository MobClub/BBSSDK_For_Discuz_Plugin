<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}
$pluginName = 'bbssdk';

$sql = "drop table  if exists `" . DB::table('bbssdk_comment_sync') . "`";
DB::query($sql);

$sql = "drop table  if exists `" . DB::table('bbssdk_forum_sync') . "`";
DB::query($sql);

$sql = "drop table  if exists `" . DB::table('bbssdk_menu_sync') . "`";
DB::query($sql);

$sql = "drop table  if exists `" . DB::table('bbssdk_member_sync') . "`";
DB::query($sql);

$sql = "drop table  if exists `" . DB::table('bbssdk_usergroup_sync') . "`";
DB::query($sql);

$sql = "drop table  if exists `" . DB::table('bbssdk_favorite_sync') . "`";
DB::query($sql);

$sql = "drop table  if exists `" . DB::table('bbssdk_notification_sync') . "`";
DB::query($sql);

$sql = "drop table  if exists `" . DB::table('bbssdk_oauth') . "`";
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
$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_memberfieldforum;";
DB::query($sql);
$sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_memberfieldforum;";
DB::query($sql);

$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_homefavorite;";
DB::query($sql);
$sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_homefavorite;";
DB::query($sql);

$sql = "DROP TRIGGER IF EXISTS bbssdk_afterinsert_on_homenotification;";
DB::query($sql);
$sql = "DROP TRIGGER IF EXISTS bbssdk_afterdelete_on_homenotification;";
DB::query($sql);
$sql = "DROP TRIGGER IF EXISTS bbssdk_afterupdate_on_homenotification;";
DB::query($sql);


$sql = "delete from ". DB::table('common_cron') ." where filename like 'bbssdk%'";
DB::query($sql);

C::t('common_setting')->update_batch(array('bbssdk_setting'=>array()));

$destFile = dirname(dirname(dirname(dirname(__FILE__)))) . '/api/mobile/remote.php';
if(file_exists($destFile)){
	unlink($destFile);
}

$finish = TRUE;