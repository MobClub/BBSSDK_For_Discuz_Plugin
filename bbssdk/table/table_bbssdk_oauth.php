<?php
if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
require_once 'bbssdk_common_sync.php';
class table_bbssdk_oauth extends bbssdk_common_sync
{
	public function __construct()
	{
		$this->_table = "bbssdk_oauth";
		parent::__construct();
	}
        public function getOauthUser($wxOpenid,$wxUnionid,$qqOpenid,$qqUnionid){
            return DB::result_first("SELECT uid FROM %t WHERE wxOpenid=%s OR wxUnionid=%s OR qqOpenid=%s OR qqUnionid=%s", array($this->_table, $wxOpenid,$wxUnionid,$qqOpenid,$qqUnionid));
        }
        public function getOauthByUid($uid){
            return DB::fetch_first("SELECT * FROM %t WHERE uid=%d ", array($this->_table, $uid));
        }

        public function recordOauth($uid,$wxOpenid,$wxUnionid,$qqOpenid,$qqUnionid) {
            $res = $this->getOauthByUid($uid);
            try{
                if($res){
                    if($wxOpenid||$wxUnionid){
                        if($res['wxOpenid']||$res['wxUnionid']){
                            return array('code'=>701,'msg'=>'');
                        }
                        $wxOpenid  = $wxOpenid?$wxOpenid:"NULL";
                        $wxUnionid = $wxUnionid?$wxUnionid:"NULL";
                        DB::query("update ".DB::table('bbssdk_oauth')." set wxOpenid=$wxOpenid where uid=$uid");
                    }else if($qqOpenid||$qqUnionid){
                        if($res['qqOpenid']||$res['qqUnionid']){
                            return array('code'=>702,'msg'=>'');
                        }
                        $qqOpenid  = $qqOpenid?$qqOpenid:"NULL";
                        $qqUnionid = $qqUnionid?$qqUnionid:"NULL";
                        DB::query("update ".DB::table('bbssdk_oauth')." set qqOpenid=$qqOpenid ,qqUnionid = $qqUnionid where uid=$uid");
                    }else{
                        return array('code'=>403,'msg'=>'oAuth信息不能为空');
                    }
                }else{
                    $wxOpenid  = $wxOpenid?$wxOpenid:"NULL";
                    $wxUnionid = $wxUnionid?$wxUnionid:"NULL";
                    $qqOpenid  = $qqOpenid?$qqOpenid:"NULL";
                    $qqUnionid = $qqUnionid?$qqUnionid:"NULL";
                    DB::query("insert INTO ".DB::table('bbssdk_oauth')." (`uid`,`wxOpenid`,`wxUnionid`,`qqOpenid`,`qqUnionid`) VALUES ($uid,$wxOpenid,$wxUnionid,$qqOpenid,$qqUnionid)");
                }
            } catch (Exception $e){
                return array('code'=>$e->getCode(),'msg'=>$e->getMessage());
            }
            return array('code'=>200,'msg'=>'');
        }
}