<?php
if(!defined('DISABLEDEFENSE'))  exit('Access Denied!');
require_once 'table/table_bbssdk_favorite_sync.php';
class Ping extends BaseCore
{
	function __construct()
	{
		parent::__construct();
	}
	public function get_new()
	{
            $t = intval($_GET['t']);
            $favorites = $syncids = [];
            $favs = c::t('bbssdk_favorite_sync')->unsync_list_by_time($t,100);
            if($favs){
                foreach ($favs as $fav){
                    array_push($syncids, $fav['syncid']);
                    
                    $fav['favid']*=$fav['flag']==3?-1:1;
                    array_push($favorites, $fav['favid']);
                }
                c::t('bbssdk_favorite_sync')->change_status($syncids);
            }
            
            $data['time'] = DB::fetch_first('select UNIX_TIMESTAMP(NOW()) as timestamp')['timestamp'];
            $data['favorites'] = $favorites;
            $this->success_result($data);
	}
}