<?php
if((!isset($_POST['bbssdk_check'])||$_POST['bbssdk_check']!='checked')&&!isset($_POST['formhash'])){
    require_once 'check.php';
    $final = getCheckJson();
    $is_allow_dz_v = (in_array($final['discuzversion'], array('X3','X3.0', 'X3.1', 'X3.2','X3.3', 'X3.4')));

    preg_match("/^\d+\.\d+/", $final['phpversion'],$php);
    $is_allow_php_v =  $php[0]>=5.3?true:false;

    preg_match("/^\d+\.\d+/", $final['mysqlversion'],$mysql);
    $is_allow_mysql_v =  $mysql[0]>=5.0?true:false;

    $destFile = dirname(dirname(dirname(dirname(__FILE__)))) . '/api/mobile/';
    $is_w = is_writable($destFile);
    $is_e = file_exists($destFile.'remote.php');

    $grants = DB::fetch_all('show grants');
    $grants = (array_values($grants[0]));
    preg_match("/GRANT (.*) ON/", strtoupper($grants[0]),$g);
    $p = explode(', ',$g[1]);
    $nop = '';

    if(strpos($p,'ALL PRIVILEGES')===false){
        foreach (array('SELECT','INSERT','UPDATE','DELETE','DROP','TRIGGER','CREATE') as $i){
            if(!in_array($i, $p)){
                $nop.= $i.'<br/>';
            }
        }
    }
    
    $logs = DB::fetch_all('show variables like "log_bin%"');
    $log_bin = $log_bin_c = false;
    if($logs){
        foreach ($logs as $l){
            if($l['Variable_name']=='log_bin'){
                $log_bin = strtoupper($l['Value'])=='ON'?true:false;
            }
            if($l['Variable_name']=='log_bin_trust_function_creators'){
                $log_bin_c = strtoupper($l['Value'])=='ON'?true:false;
            }
        }
    }
    
    if($_G['charset']=='gbk'){
        require_once "template/check_gbk.html"; 
    }else{
        require_once "template/check.html"; 
    }
    exit;
}

