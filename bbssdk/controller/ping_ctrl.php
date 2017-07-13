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
            $favorites = [];
            $favs = c::t('bbssdk_favorite_sync')->unsync_list_by_time($t);
            if($favs){
                foreach ($favs as $fav){
                    $fav['favid']*=$fav['flag']==3?-1:1;
                    array_push($favorites, $fav['favid']);
                }
            }
            $data['time'] = $t;
            $data['favorites'] = $favorites;
            $this->success_result($data);
	}
}