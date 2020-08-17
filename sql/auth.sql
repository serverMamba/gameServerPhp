// 账号表  todo 账号应该手机号注册还是微信注册
CREATE TABLE `auth` (
  `aid` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '账号id',
  `account` char(20) NOT NULL DEFAULT '' COMMENT '账号: 手机号',
  `password` varchar(10) NOT NULL DEFAULT '' COMMENT '登陆密码',
  PRIMARY KEY (`aid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COMMENT='账号表';