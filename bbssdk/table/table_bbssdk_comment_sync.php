<?php
if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}

class table_bbssdk_comment_sync extends discuz_table
{
	public function __construct()
	{
		$this->_table = "bbssdk_comment_sync";
		$this->_pk = "syncid";

		parent::__construct();
	}

	public function count_by_unsync()
	{
		return (int) DB::result_first("SELECT COUNT(*) FROM %t WHERE synctime = 0", array($this->_table));
	}

	public function unsync_list($start = 0, $limit = 0, $sort = 'desc')
	{
		if($sort){
			$this->checkpk();
		}
		return DB::fetch_all("select * from ".DB::table($this->_table) . " where synctime = 0 order by ".DB::order($this->_pk,$sort) . DB::limit($start, $limit));
	}

	public function change_status($ids)
	{
		$idstirng = is_array($ids) ? join(',',$ids) : $ids;
		if(!preg_match("%[\d\,]+%is", $idstirng))
			return false;
		return DB::query('update '.DB::table($this->_table).' SET synctime=unix_timestamp(now()) where syncid in('.$idstirng.')');
	}
}