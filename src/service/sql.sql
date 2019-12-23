CREATE TABLE `SystemMessageHistory` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `mid` bigint(20) unsigned DEFAULT '0' COMMENT '会员ID',
  `region` varchar(255) DEFAULT '0' COMMENT '国家编号',
  `phone` varchar(100) DEFAULT '' COMMENT '目标手机',
  `content` varchar(512) DEFAULT '' COMMENT '短信内容',
  `result` varchar(100) DEFAULT '' COMMENT '返回结果',
  `create_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
  PRIMARY KEY (`id`) USING BTREE,
  KEY `index_store_member_sms_history_phone` (`phone`) USING BTREE
) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COMMENT='短信-记录';