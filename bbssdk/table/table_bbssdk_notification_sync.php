<?php
if(!defined('IN_DISCUZ')) {
	exit('Access Denied');
}
require_once 'bbssdk_common_sync.php';
class table_bbssdk_notification_sync extends bbssdk_common_sync
{
	public function __construct()
	{
		$this->_table = "bbssdk_notification_sync";
		$this->_pk = "syncid";

		parent::__construct();
	}
}