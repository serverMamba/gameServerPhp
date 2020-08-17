// 角色表
CREATE TABLE `role` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '角色id',
  `account_id` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '账号id',
  `name` varchar(10) NOT NULL DEFAULT '' COMMENT '角色名',
  `lv` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '角色等级',
  `exp` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '角色经验',
  `gold` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '角色金币',
  `silver` int(11) unsigned NOT NULL DEFAULT '0' COMMENT '角色银币',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='角色表'