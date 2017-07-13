<?php
if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

class table_bbssdk_favorite_sync extends discuz_table
{
	public function __construct()
	{
		$this->_table = "bbssdk_favorite_sync";
		$this->_pk = "syncid";

		parent::__construct();
	}

	public function count_by_unsync()
	{
		return (int) DB::result_first("SELECT COUNT(*) FROM %t WHERE synctime = 0", array($this->_table));
	}

	public function unsync_list_by_time($t = 0, $limit = 0, $sort = 'asc')
	{
		if($sort){
			$this->checkpk();
		}
		return DB::fetch_all("select * from ".DB::table($this->_table) . " where synctime = 0 or  synctime>= ".$t." order by ".DB::order($this->_pk,$sort) . DB::limit(0, $limit));
	}

	public function change_status($ids)
	{
		$idstirng = is_array($ids) ? join(',',$ids) : $ids;
		if(!preg_match("%[\d\,]+%is", $idstirng))
			return false;
		return DB::query('update '.DB::table($this->_table).' SET synctime=unix_timestamp(now()) where syncid in('.$idstirng.')');
	}
}