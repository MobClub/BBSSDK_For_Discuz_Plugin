<?php
if(!defined('DISABLEDEFENSE'))  exit('Access Denied!');
require_once 'table/table_bbssdk_menu.php';

class Menu extends BaseCore
{
	function __construct()
	{
		parent::__construct();
	}

	public function get_setting()
	{
		global $_G;
		$actset = $this->setting;
		$iconlevels = array();
		foreach (array_values($actset['heatthread']['iconlevels']) as $item) {
			array_push($iconlevels, intval($item));
		}
		sort($iconlevels);
		$setting = array(
			'iconlevels' => $iconlevels,
			'censoruser'=>$actset['censoruser'],
			'floodctrl'=>$actset['floodctrl'],
			'need_email'=>$actset['need_email'],
			'need_avatar'=>$actset['need_avatar'],
			'strongpw'=>is_array($actset['strongpw']) ? $actset['strongpw'] : ( $actset['strongpw'] > 0 ? array($actset['strongpw']) : array() ),
			'regverify'=>$actset['regverify'],
			'charset'=>$_G['charset']
		);
		$this->success_result($setting);
	}

	public function get_list()
	{
		$fup = intval($_REQUEST['fup']);		

		$pagesize = intval($_REQUEST['pagesize']);
		$pagesize = $pagesize ? $pagesize : 10;
		if( $pagesize > 50) $pagesize = 10;
		
		$page = intval($_REQUEST['page'])>0 ? intval($_REQUEST['page']) : 1;
		$start = ($page - 1) * $pagesize;
		
		$data['total_count'] =  (int) c::t('bbssdk_menu')->count_by_fup($fup);
		$data['pagesize'] = $pagesize;
		$data['currpage'] = $page;
		$total_page = ceil($data['total_count']/$pagesize);
		$data['nextpage'] = $page+1 <= $total_page ? $page+1 : $total_page;
		$data['prepage'] = $page-1>0 ? $page-1 : 1;

		$list = array();
		if($data['currpage'] <= $data['nextpage']){
			$menus = c::t('bbssdk_menu')->fetch_all_forum($fup,$start,$pagesize);
			foreach ($menus as $key => $item) {
				$list[] = $this->relation_item($item);
			}
		}
		
		$data['list'] = $list;

		$this->success_result($data);
	}

	public function get_item()
	{
		$fid = intval($_REQUEST['fid']);
		if(!$fid) return_status(403);

		$data = c::t('bbssdk_menu')->fetch_all_by_fid($fid);

		$data = $this->relation_item($data[0]);

		$this->success_result($data);
	}

	public function relation_item($item)
	{
		$newItem = array();
		if(is_array($item))
		{
			$newItem = array(
				'name' => $item['name'],
				'fid' => (int)$item['fid'],
				'fup' => (int)$item['fup'],
				'status' => empty($item['redirect']) ? (int) $item['status'] : 0,
				'displayorder' => (int)$item['displayorder'],
				'type' => $item['type'],
				'description' => $item['description']
			);
		}
		return $newItem;
	}
}