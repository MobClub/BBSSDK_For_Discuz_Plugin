<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}
update_action();

function update_action()
{
	@include_once libfile('cache/setting', 'function');
	build_cache_setting();
        $sql = "CREATE TABLE IF NOT EXISTS `".DB::table('bbssdk_favorite_sync')."` (
	  `syncid` int(11) NOT NULL AUTO_INCREMENT,
	  `fid` int(11) DEFAULT NULL,
	  `tid` int(11) DEFAULT NULL,
	  `pid` int(11) DEFAULT NULL,
	  `creattime` int(11) DEFAULT NULL,
	  `modifytime` int(11) DEFAULT NULL,
	  `synctime` int(11) DEFAULT NULL,
	  `flag` tinyint(4) DEFAULT NULL,
	  PRIMARY KEY (`syncid`),
	  UNIQUE KEY `indexid` USING BTREE (`fid`,`tid`,`pid`),
	  KEY `fid` (`fid`),
	  KEY `tid` (`tid`),
	  KEY `pid` (`pid`)
	) ENGINE=MyISAM DEFAULT CHARSET=utf8;";

	DB::query($sql);
        
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

        /* 收藏模块开始 */
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

}
