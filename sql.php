<?php

if (config('app.framework') == 'platform') {
	$create_dir = [
	    '/bootstrap/cache',
	    '/storage/framework/views',
	    '/storage/logs/error',
	    '/storage/logs/debug'
	];
} else {
	$create_dir = [
	    '../addons/yun_shop/bootstrap/cache',
	    '../addons/yun_shop/storage/framework/views',
	    '../addons/yun_shop/storage/logs/error',
	    '../addons/yun_shop/storage/logs/debug'
	];
}


foreach ($create_dir as $dir_path) {
    if (!is_dir($dir_path)) {
        @mkdir($dir_path, 0755, true);
    }
}

$sql = "
CREATE TABLE IF NOT EXISTS ims_yz_account_open_config (
  `config_id` int(11) NOT NULL,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `app_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `app_secret` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_address
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_address (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `areaname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `parentid` int(11) DEFAULT NULL,
  `level` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

 

# Dump of table ims_yz_balance
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_balance (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `old_money` decimal(14,2) DEFAULT NULL,
  `change_money` decimal(14,2) NOT NULL,
  `new_money` decimal(14,2) NOT NULL,
  `type` tinyint(3) NOT NULL,
  `service_type` tinyint(11) NOT NULL,
  `serial_number` varchar(45) NOT NULL DEFAULT '',
  `operator` int(11) NOT NULL,
  `operator_id` varchar(45) NOT NULL DEFAULT '',
  `remark` varchar(200) NOT NULL DEFAULT '',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  INDEX `idx_u_m_t`(`uniacid`, `member_id`, `type`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_balance_recharge
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_balance_recharge (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `old_money` decimal(14,2) DEFAULT NULL,
  `money` decimal(14,2) DEFAULT NULL,
  `new_money` decimal(14,2) DEFAULT NULL,
  `type` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `ordersn` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_balance_transfer
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_balance_transfer (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `transferor` int(11) DEFAULT NULL,
  `recipient` int(11) DEFAULT NULL,
  `money` decimal(14,2) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_brand
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_brand (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `alias` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `logo` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `desc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_brand
# ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS ims_mc_member_address (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(10) unsigned NOT NULL,
  `uid` int(50) unsigned NOT NULL COMMENT '会员ID',
  `username` varchar(20) NOT NULL COMMENT '用户名',
  `mobile` varchar(11) NOT NULL COMMENT '手机号',
  `zipcode` varchar(6) NOT NULL COMMENT '邮政编码',
  `province` varchar(32) NOT NULL COMMENT '省',
  `city` varchar(32) NOT NULL COMMENT '市',
  `district` varchar(32) NOT NULL COMMENT '区',
  `address` varchar(512) NOT NULL COMMENT '详细地址',
  `isdefault` tinyint(1) unsigned NOT NULL COMMENT '是否默认',
  PRIMARY KEY (`id`),
  KEY `idx_uinacid` (`uniacid`),
  KEY `idx_uid` (`uid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_mc_members
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_mc_members  (
  `uid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT COMMENT '会员id',
  `uniacid` int(10) UNSIGNED NOT NULL,
  `mobile` varchar(18) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '手机号',
  `email` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '邮箱',
  `password` varchar(32) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码',
  `salt` varchar(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '密码随机串',
  `groupid` int(11) NOT NULL COMMENT '分组id',
  `credit1` decimal(10, 2) UNSIGNED NOT NULL COMMENT '积分',
  `credit2` decimal(10, 2) UNSIGNED NOT NULL COMMENT '余额',
  `credit3` decimal(10, 2) UNSIGNED NOT NULL,
  `credit4` decimal(10, 2) UNSIGNED NOT NULL,
  `credit5` decimal(10, 2) UNSIGNED NOT NULL,
  `credit6` decimal(10, 2) NOT NULL,
  `createtime` int(10) UNSIGNED NOT NULL COMMENT '创建时间',
  `realname` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '真实名称',
  `nickname` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '昵称',
  `avatar` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '头像',
  `qq` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'qq',
  `vip` tinyint(3) UNSIGNED NOT NULL COMMENT '是否vip',
  `gender` tinyint(1) NOT NULL COMMENT '性别',
  `birthyear` smallint(6) UNSIGNED NOT NULL COMMENT '生日年',
  `birthmonth` tinyint(3) UNSIGNED NOT NULL COMMENT '生日月',
  `birthday` tinyint(3) UNSIGNED NOT NULL COMMENT '生日日',
  `constellation` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `zodiac` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `telephone` varchar(15) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `idcard` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '身份证',
  `studentid` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `grade` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `address` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '地址',
  `zipcode` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nationality` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `resideprovince` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `residecity` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `residedist` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `graduateschool` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `company` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `education` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `occupation` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `position` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `revenue` varchar(10) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `affectivestatus` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `lookingfor` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bloodtype` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `height` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `weight` varchar(5) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `alipay` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `msn` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `taobao` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `site` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `bio` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `interest` text CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `pay_password` varchar(30) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0' COMMENT '支付密码',
  PRIMARY KEY (`uid`) USING BTREE,
  INDEX `groupid`(`groupid`) USING BTREE,
  INDEX `uniacid`(`uniacid`) USING BTREE,
  INDEX `email`(`email`) USING BTREE,
  INDEX `mobile`(`mobile`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;




# Dump of table ims_mc_mapping_fans
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_mc_mapping_fans  (
  `fanid` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `acid` int(10) UNSIGNED NOT NULL,
  `uniacid` int(10) UNSIGNED NOT NULL,
  `uid` int(10) UNSIGNED NOT NULL,
  `openid` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `groupid` varchar(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `salt` char(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `follow` tinyint(1) UNSIGNED NOT NULL,
  `followtime` int(10) UNSIGNED NOT NULL,
  `unfollowtime` int(10) UNSIGNED NOT NULL,
  `tag` varchar(1000) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `updatetime` int(10) UNSIGNED NULL DEFAULT NULL,
  `unionid` varchar(64) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`fanid`) USING BTREE,
  UNIQUE INDEX `openid_2`(`openid`) USING BTREE,
  INDEX `acid`(`acid`) USING BTREE,
  INDEX `uniacid`(`uniacid`) USING BTREE,
  INDEX `nickname`(`nickname`) USING BTREE,
  INDEX `updatetime`(`updatetime`) USING BTREE,
  INDEX `uid`(`uid`) USING BTREE,
  INDEX `openid`(`openid`) USING BTREE
) ENGINE = InnoDB AUTO_INCREMENT = 1 CHARACTER SET = utf8mb4 COLLATE = utf8mb4_unicode_ci ROW_FORMAT = Dynamic;



# Dump of table ims_mc_fans_groups
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_mc_fans_groups (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(10) unsigned NOT NULL,
  `acid` int(10) unsigned NOT NULL,
  `groups` varchar(10000) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uniacid` (`uniacid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_mc_fans_tag_mapping
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_mc_fans_tag_mapping (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `fanid` int(11) unsigned NOT NULL,
  `tagid` tinyint(3) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `mapping` (`fanid`,`tagid`),
  KEY `fanid_index` (`fanid`),
  KEY `tagid_index` (`tagid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_mc_groups
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_mc_groups  (
  `groupid` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `title` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL,
  `credit` int(10) UNSIGNED NOT NULL,
  `isdefault` tinyint(4) NOT NULL,
  PRIMARY KEY (`groupid`) USING BTREE,
  INDEX `uniacid`(`uniacid`) USING BTREE
) ENGINE = InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci ROW_FORMAT = Dynamic;



# Dump of table ims_yz_category
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_category (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '名称',
  `thumb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '图片',
  `parent_id` int(11) DEFAULT '0' COMMENT '上级id',
  `description` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '描述',
  `display_order` tinyint(1) DEFAULT '0' COMMENT '排序',
  `enabled` tinyint(1) DEFAULT '1' COMMENT '是否开启',
  `is_home` tinyint(1) DEFAULT '0',
  `adv_img` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `adv_url` varchar(500) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `level` tinyint(1) DEFAULT '0' COMMENT '层级',
  `advimg_pc` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `advurl_pc` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_parentid` (`parent_id`),
  KEY `idx_displayorder` (`display_order`),
  KEY `idx_enabled` (`enabled`),
  KEY `idx_ishome` (`is_home`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--分类表';



# Dump of table ims_yz_comment
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_comment (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `order_id` int(11) DEFAULT '0' COMMENT '订单id',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品id',
  `uid` int(11) DEFAULT '0' COMMENT '会员id',
  `nick_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '昵称',
  `head_img_url` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '头像',
  `content` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '内容',
  `level` tinyint(1) DEFAULT '0' COMMENT '等级',
  `images` text COLLATE utf8mb4_unicode_ci COMMENT '图片',
  `comment_id` int(11) DEFAULT '0' COMMENT '内容id',
  `reply_id` int(11) DEFAULT '0' COMMENT '回复id',
  `reply_name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '回复名称',
  `type` tinyint(3) DEFAULT '1' COMMENT '类型',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_orderid` (`order_id`),
  KEY `idx_goodsid` (`goods_id`),
  KEY `idx_openid` (`uid`),
  KEY `idx_createtime` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--评论表';
 

# Dump of table ims_yz_coupon
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_coupon (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `cat_id` int(11) DEFAULT '0' COMMENT '分类ID',
  `name` varchar(255) DEFAULT '' COMMENT '优惠券名称',
  `get_type` tinyint(3) DEFAULT '0' COMMENT '获取优惠券的方式',
  `level_limit` int(11) NOT NULL DEFAULT '0' COMMENT '领取条件 - 会员等级',
  `get_max` int(11) DEFAULT '0' COMMENT '每个人的限领数量',
  `use_type` tinyint(3) unsigned DEFAULT '0' COMMENT '使用方式',
  `return_type` tinyint(3) DEFAULT '0',
  `bgcolor` varchar(255) DEFAULT '' COMMENT '背景颜色',
  `enough` int(11) NOT NULL DEFAULT '0' COMMENT '使用条件(消费金额前提)',
  `coupon_type` tinyint(3) DEFAULT '0' COMMENT '优惠券类型',
  `time_limit` tinyint(3) DEFAULT '0' COMMENT '使用时间限制',
  `time_days` int(11) DEFAULT '0' COMMENT '使用时间限制',
  `time_start` int(11) DEFAULT '0' COMMENT '起始使用时间',
  `time_end` int(11) DEFAULT '0' COMMENT '截止使用时间',
  `coupon_method` tinyint(4) DEFAULT NULL COMMENT '	优惠方式',
  `discount` decimal(10,2) DEFAULT '0.00' COMMENT '优惠立减',
  `deduct` decimal(10,2) DEFAULT '0.00' COMMENT '优惠打折',
  `back_type` tinyint(3) DEFAULT '0',
  `back_money` varchar(50) DEFAULT '',
  `back_credit` varchar(50) DEFAULT '',
  `back_redpack` varchar(50) DEFAULT '',
  `back_when` tinyint(3) DEFAULT '0',
  `thumb` varchar(255) DEFAULT '' COMMENT '缩略图',
  `desc` text COMMENT '描述说明',
  `total` int(11) DEFAULT '0' COMMENT '优惠券发放总数',
  `status` tinyint(3) DEFAULT '0' COMMENT '是否启用',
  `money` decimal(10,2) DEFAULT '0.00',
  `resp_desc` text COMMENT '推送说明',
  `resp_thumb` varchar(255) DEFAULT '' COMMENT '推送缩略图',
  `resp_title` varchar(255) DEFAULT '' COMMENT '推送标题',
  `resp_url` varchar(255) DEFAULT '' COMMENT '推送链接',
  `credit` int(11) DEFAULT '0' ,
  `usecredit2` tinyint(3) DEFAULT '0' ,
  `remark` varchar(1000) DEFAULT '' COMMENT '备注',
  `descnoset` tinyint(3) DEFAULT '0',
  `display_order` int(11) DEFAULT '0' COMMENT '排序',
  `supplier_uid` int(11) DEFAULT '0' COMMENT '供应商uid',
  `getcashier` tinyint(1) NOT NULL DEFAULT '0',
  `cashiersids` text COMMENT '收银台id',
  `cashiersnames` text COMMENT '收银台名称',
  `category_ids` text COMMENT '分类id',
  `categorynames` text COMMENT '分类名称',
  `goods_names` text COMMENT '商品名称',
  `goods_ids` text COMMENT '商品id',
  `storeids` text COMMENT '门店id',
  `storenames` text COMMENT '门店名称',
  `getstore` tinyint(1) NOT NULL DEFAULT '0',
  `getsupplier` tinyint(1) DEFAULT '0',
  `supplierids` text COMMENT '供应商ids',
  `suppliernames` text COMMENT '供应商名称',
  `createtime` int(11) DEFAULT '0',
  `created_at` int(10) unsigned DEFAULT NULL,
  `updated_at` int(10) unsigned DEFAULT NULL,
  `deleted_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_catid` (`cat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--优惠券主表';



# Dump of table ims_yz_coupon_category
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_coupon_category (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `name` varchar(255) DEFAULT '' COMMENT '分类名称',
  `display_order` int(11) DEFAULT '0' COMMENT '排序',
  `status` int(11) DEFAULT '0' COMMENT '是否启用 0 否 1 是',
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_displayorder` (`display_order`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='优惠券分类表';



# Dump of table ims_yz_coupon_log
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_coupon_log (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `logno` varchar(255) DEFAULT '',
  `member_id` varchar(255) DEFAULT '',
  `couponid` int(11) DEFAULT '0',
  `status` int(11) DEFAULT '0',
  `paystatus` tinyint(3) DEFAULT '0',
  `creditstatus` tinyint(3) DEFAULT '0',
  `createtime` int(11) DEFAULT '0',
  `paytype` tinyint(3) DEFAULT '0',
  `getfrom` tinyint(3) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_couponid` (`couponid`),
  KEY `idx_status` (`status`),
  KEY `idx_paystatus` (`paystatus`),
  KEY `idx_createtime` (`createtime`),
  KEY `idx_getfrom` (`getfrom`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


 

# Dump of table ims_yz_dispatch
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_dispatch (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `dispatch_name` varchar(50) DEFAULT NULL COMMENT '配送模板名称',
  `display_order` int(11) DEFAULT NULL COMMENT '排序',
  `enabled` tinyint(3) DEFAULT NULL COMMENT '是否显示（1：是；0：否）',
  `is_default` tinyint(3) DEFAULT NULL COMMENT '是否默认模板（1：是；0：否）',
  `calculate_type` tinyint(3) DEFAULT NULL COMMENT '计费方式',
  `areas` text COMMENT '配送区域',
  `first_weight` int(11) DEFAULT NULL COMMENT '首重克数',
  `another_weight` int(11) DEFAULT NULL COMMENT '续重克数',
  `first_weight_price` decimal(14,2) DEFAULT NULL COMMENT '首重价格',
  `another_weight_price` decimal(14,2) DEFAULT NULL COMMENT '续重价格',
  `first_piece` int(11) DEFAULT NULL COMMENT '首件个数',
  `another_piece` int(11) DEFAULT NULL COMMENT '续件个数',
  `first_piece_price` int(11) DEFAULT NULL COMMENT '首件价格',
  `another_piece_price` int(11) DEFAULT NULL COMMENT '续件价格',
  `weight_data` longtext COMMENT '按重量计费数据',
  `piece_data` longtext COMMENT '按数量计费数据',
  `is_plugin` tinyint(3) DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_dispatch_type
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_dispatch_type (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `plugin` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_goods
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0' COMMENT '公众号ID',
  `brand_id` int(11) NOT NULL COMMENT '品牌ID',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '类型1实体2虚拟',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态1上架0下架',
  `display_order` int(11) DEFAULT '0' COMMENT '排序',
  `title` varchar(100) NOT NULL DEFAULT '' COMMENT '名称',
  `thumb` varchar(255) NOT NULL DEFAULT '' COMMENT '主图',
  `thumb_url` text COMMENT '轮播图',
  `sku` varchar(5) DEFAULT '' COMMENT '单位',
  `description` varchar(1000) DEFAULT '',
  `content` text COMMENT '描述',
  `goods_sn` varchar(50) DEFAULT '' COMMENT '编号',
  `product_sn` varchar(50) DEFAULT '' COMMENT '条码',
  `market_price` decimal(14,2) DEFAULT '0.00' COMMENT '原价',
  `price` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT '现价',
  `cost_price` decimal(14,2) DEFAULT '0.00' COMMENT '成本价',
  `stock` int(10) NOT NULL DEFAULT '0' COMMENT '库存',
  `reduce_stock_method` int(11) DEFAULT '0' COMMENT '扣库存方式0下单1付款2永不',
  `show_sales` int(11) DEFAULT '0' COMMENT '销量',
  `real_sales` int(11) DEFAULT '0' COMMENT '销量',
  `weight` decimal(10,2) DEFAULT '0.00' COMMENT '重量',
  `has_option` int(11) DEFAULT '0' COMMENT '是否拥有规格',
  `is_new` tinyint(1) DEFAULT '0' COMMENT '新上',
  `is_hot` tinyint(1) DEFAULT '0' COMMENT '热卖',
  `is_discount` tinyint(1) DEFAULT '0' COMMENT '促销',
  `is_recommand` tinyint(1) DEFAULT '0' COMMENT '推荐',
  `is_comment` tinyint(1) DEFAULT '0',
  `is_deleted` tinyint(3) NOT NULL DEFAULT '0',
  `created_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `comment_num` int(11) NOT NULL DEFAULT '0' COMMENT '评论数量',
  `is_plugin` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`,`is_deleted`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_isnew` (`is_new`),
  KEY `idx_ishot` (`is_hot`),
  KEY `idx_isdiscount` (`is_discount`),
  KEY `idx_isrecommand` (`is_recommand`),
  KEY `idx_iscomment` (`is_comment`),
  KEY `idx_deleted` (`is_deleted`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--商品主表';



# Dump of table ims_yz_goods_area
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_area (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL,
  `area_id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_goodid` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--商品区域插件关联表';



# Dump of table ims_yz_goods_bonus
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_bonus (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL,
  `bonus_money` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_good_id` (`goods_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--分红关联表';



# Dump of table ims_yz_goods_category
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_category (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) DEFAULT NULL COMMENT '商品ID',
  `category_id` int(11) DEFAULT NULL COMMENT '分类id',
  `category_ids` varchar(255) DEFAULT NULL COMMENT '分类id逗号拼接',
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_goodid` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--分类关联';

 


# Dump of table ims_yz_goods_discount
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_discount (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `level_discount_type` tinyint(1) NOT NULL COMMENT '折扣类型：1会员等级',
  `discount_method` tinyint(1) NOT NULL COMMENT '折扣方式：1折扣2固定金额3成本比例',
  `level_id` int(11) NOT NULL COMMENT '会员等级id',
  `discount_value` decimal(14,2) NOT NULL COMMENT '折扣数值',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_goodid` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--折扣挂件';



# Dump of table ims_yz_goods_discount_detail
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_discount_detail (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `discount_id` int(11) NOT NULL,
  `level_id` int(11) DEFAULT NULL,
  `discount` decimal(3,2) DEFAULT NULL,
  `amount` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_discountid` (`discount_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--折扣与商品折扣明细关联表';



# Dump of table ims_yz_goods_dispatch
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_dispatch (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `dispatch_type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '运费配置:1统一邮费0运费模板',
  `dispatch_price` int(11) DEFAULT '0' COMMENT '运费金额',
  `dispatch_id` int(11) DEFAULT NULL COMMENT '运费模板ID',
  `is_cod` tinyint(1) NOT NULL DEFAULT '1',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_good_id` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--配送方式挂件';



# Dump of table ims_yz_goods_diyform
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_diyform (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `good_id` int(11) NOT NULL,
  `diyform_id` int(11) DEFAULT NULL,
  `diyform_enable` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_goodid` (`good_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--自定义表单关联表';



# Dump of table ims_yz_goods_level_returns
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_level_returns (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `good_return_id` int(11) NOT NULL,
  `level_type` tinyint(3) NOT NULL DEFAULT '1',
  `level_id` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_good_return_id` (`good_return_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--等级返现规则表';



# Dump of table ims_yz_goods_notices
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_notices (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL,
  `uid` int(11) DEFAULT NULL,
  `type` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_good_id` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--消息通知挂件';



# Dump of table ims_yz_goods_option
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_option (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `goods_id` int(10) DEFAULT '0' COMMENT '商品ID',
  `title` varchar(255) DEFAULT NULL COMMENT '规格名称',
  `thumb` varchar(60) DEFAULT NULL COMMENT '规格图片',
  `product_price` decimal(10,2) DEFAULT '0.00' COMMENT '  现价',
  `market_price` decimal(10,2) DEFAULT '0.00' COMMENT '原价',
  `cost_price` decimal(10,2) DEFAULT '0.00' COMMENT '成本价',
  `stock` int(11) DEFAULT '0' COMMENT '库存',
  `weight` decimal(10,2) DEFAULT '0.00' COMMENT '重量',
  `display_order` int(11) DEFAULT '0' COMMENT '排序',
  `specs` text  COMMENT '规格值ID组合',
  `skuId` varchar(255) DEFAULT '',
  `goods_sn` varchar(255) DEFAULT '' COMMENT '编号',
  `product_sn` varchar(255) DEFAULT '' COMMENT '条码',
  `virtual` int(11) DEFAULT '0',
  `red_price` varchar(50) DEFAULT '' COMMENT '红包价格',
  `created_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_goodsid` (`goods_id`),
  KEY `idx_displayorder` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--商品规格';



# Dump of table ims_yz_goods_param
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_param (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `goods_id` int(10) DEFAULT '0' COMMENT '商品ID',
  `title` varchar(50) DEFAULT NULL COMMENT '属性名称',
  `value` text COMMENT '属性值',
  `displayorder` int(11) DEFAULT '0' COMMENT '排序',
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_goodsid` (`goods_id`),
  KEY `idx_displayorder` (`displayorder`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--商品属性挂件';



# Dump of table ims_yz_goods_privilege
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_privilege (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `show_levels` text COLLATE utf8mb4_unicode_ci COMMENT '会员浏览等级',
  `show_groups` text COLLATE utf8mb4_unicode_ci COMMENT '会员浏览分组',
  `buy_levels` text COLLATE utf8mb4_unicode_ci COMMENT '会员购买等级',
  `buy_groups` text COLLATE utf8mb4_unicode_ci COMMENT '会员购买分组',
  `once_buy_limit` int(11) DEFAULT '0' COMMENT '单次购买限制',
  `total_buy_limit` int(11) DEFAULT '0' COMMENT '总购买限制',
  `day_buy_limit` int(11) DEFAULT '0' COMMENT '每天购买限制',
  `week_buy_limit` int(11) DEFAULT '0' COMMENT '每周购买限制',
  `month_buy_limit` int(11) DEFAULT '0' COMMENT '每月购买限制',
  `time_begin_limit` int(11) DEFAULT NULL COMMENT '限时起始时间',
  `time_end_limit` int(11) DEFAULT NULL COMMENT '限时结束时间',
  `enable_time_limit` tinyint(1) NOT NULL COMMENT '限时购，没用',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_goodid` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--权限挂件';



# Dump of table ims_core_sessions
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_core_sessions (
  `sid` char(32) NOT NULL primary key,
  `uniacid` int(10) NOT NULL,
  `openid` varchar(50) NOT NULL,
  `data` varchar(5000) NOT NULL,
  `expiretime` int(10) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_goods_sale
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_sale (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL COMMENT '商品ID',
  `max_point_deduct` int(11) DEFAULT '0' COMMENT '积分最高抵扣',
  `max_balance_deduct` int(11) DEFAULT '0' COMMENT '最高余额抵扣',
  `is_sendfree` int(11) DEFAULT '0',
  `ed_num` int(11) DEFAULT '0' COMMENT '单品满件包邮',
  `ed_money` int(11) DEFAULT '0' COMMENT '单品满额包邮',
  `ed_areas` text COLLATE utf8mb4_unicode_ci COMMENT '不参与单品包邮地区ID',
  `point` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0'  COMMENT '赠送积分',
  `bonus` int(11) DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_good_id` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--营销挂件表';



# Dump of table ims_yz_goods_share
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_share (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL,
  `need_follow` tinyint(1) DEFAULT NULL,
  `no_follow_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `follow_message` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `share_title` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `share_thumb` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `share_desc` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_goodid` (`goods_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_mc_chats_record
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_mc_chats_record (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(10) unsigned NOT NULL,
  `acid` int(10) unsigned NOT NULL,
  `flag` tinyint(3) unsigned NOT NULL,
  `openid` varchar(32) NOT NULL,
  `msgtype` varchar(15) NOT NULL,
  `content` varchar(10000) NOT NULL,
  `createtime` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `uniacid` (`uniacid`,`acid`),
  KEY `openid` (`openid`),
  KEY `createtime` (`createtime`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_goods_spec
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_spec (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `goods_id` int(11) DEFAULT '0' COMMENT '商品ID',
  `title` varchar(50) DEFAULT NULL COMMENT '规格项名称',
  `description` varchar(1000) DEFAULT NULL  COMMENT '无效',
  `display_type` tinyint(3) DEFAULT '0' COMMENT '无效',
  `content` text,
  `display_order` int(11) DEFAULT '0' COMMENT '排序',
  `propId` varchar(255) DEFAULT NULL COMMENT '无效',
  `created_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_goodsid` (`goods_id`),
  KEY `idx_displayorder` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--规格项';



# Dump of table ims_yz_goods_spec_item
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_goods_spec_item (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `specid` int(11) DEFAULT '0' COMMENT '规格项ID',
  `title` varchar(255) DEFAULT NULL COMMENT '值名称',
  `thumb` varchar(255) DEFAULT NULL COMMENT '图片',
  `show` int(11) DEFAULT '0' COMMENT '无效',
  `display_order` int(11) DEFAULT '0' COMMENT '排序',
  `valueId` varchar(255) DEFAULT NULL COMMENT '无效',
  `virtual` int(11) DEFAULT '0' COMMENT '无效',
  `created_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_specid` (`specid`),
  KEY `idx_show` (`show`),
  KEY `idx_displayorder` (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='商品--规格项值';



# Dump of table ims_yz_member
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member (
  `m_id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `uniacid` int(11) NOT NULL,
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `group_id` int(11) NOT NULL DEFAULT '0',
  `level_id` int(11) NOT NULL DEFAULT '0',
  `inviter` int(11) DEFAULT '0',
  `is_black` tinyint(1) NOT NULL DEFAULT '0',
  `province_name` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `city_name` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `area_name` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `province` int(11) DEFAULT '0',
  `city` int(11) DEFAULT '0',
  `area` int(11) DEFAULT '0',
  `address` text COLLATE utf8mb4_unicode_ci,
  `referralsn` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `is_agent` tinyint(1) DEFAULT '0',
  `alipayname` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `alipay` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `content` text COLLATE utf8mb4_unicode_ci,
  `status` int(11) DEFAULT '0',
  `child_time` int(11) DEFAULT '0',
  `agent_time` int(11) DEFAULT '0',
  `apply_time` int(11) DEFAULT '0',
  `relation` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`m_id`),
  UNIQUE INDEX `un_member_id`(`member_id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_parentid` (`parent_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_app_wechat
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_app_wechat (
  `app_wechat_id` int(11) NOT NULL,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `openid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`app_wechat_id`),
  UNIQUE INDEX `un_openid`(`openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_cart
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_cart (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `uniacid` int(11) NOT NULL,
  `goods_id` int(11) NOT NULL,
  `total` int(11) NOT NULL,
  `option_id` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_coupon
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_coupon (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT '0',
  `uid` varchar(255) DEFAULT '' COMMENT '会员ID',
  `coupon_id` int(11) DEFAULT '0' COMMENT '优惠券ID',
  `get_type` tinyint(3) DEFAULT '0' COMMENT '获取类型',
  `used` int(11) DEFAULT '0' COMMENT '是否已使用：1-已使用，0-否',
  `use_time` int(11) DEFAULT '0' COMMENT '使用时间',
  `get_time` int(11) DEFAULT '0' COMMENT '获取时间',
  `send_uid` int(11) DEFAULT '0' COMMENT '手动发放优惠券的操作人员的 uid',
  `order_sn` varchar(255) DEFAULT '' COMMENT '使用优惠券的订单号',
  `back` tinyint(3) DEFAULT '0' COMMENT '返现',
  `back_time` int(11) DEFAULT '0' COMMENT '返现时间',
  PRIMARY KEY (`id`),
  KEY `idx_couponid` (`coupon_id`),
  KEY `idx_gettype` (`get_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_favorite
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_favorite (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `uniacid` int(11) NOT NULL,
  `goods_id` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_group
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_group (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `group_name` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  `is_default` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_history
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_history (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_id` int(11) NOT NULL,
  `uniacid` int(11) NOT NULL,
  `goods_id` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_income
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_income (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL COMMENT '会员ID',
  `incometable_type` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '收入表类型',
  `incometable_id` int(11) DEFAULT NULL COMMENT '收入表id',
  `type_name` varchar(120) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '类型名称',
  `amount` decimal(14,2) NOT NULL DEFAULT '0.00' COMMENT '金额',
  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否提现状态:0-未提现，1-已提现',
  `pay_status` tinyint(3) NOT NULL DEFAULT '0' COMMENT '打款状态:-1无效，0-未审核，1-未打款，2-已打款，3-已驳回',
  `detail` text COLLATE utf8mb4_unicode_ci COMMENT '收入明细数据',
  `create_month` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '创建月份',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_level
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_level (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `level` int(11) NOT NULL,
  `level_name` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `order_money` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `order_count` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `goods_id` int(11) DEFAULT NULL,
  `discount` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  `is_default` int(11) DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_mini_app
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_mini_app (
  `mini_app_id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `openid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` tinyint(1) NOT NULL,
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`mini_app_id`),
  UNIQUE INDEX `un_openid`(`openid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_qq
# ------------------------------------------------------------


CREATE TABLE IF NOT EXISTS ims_yz_member_qq (
  `qq_id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `nickname` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `figureurl` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `figureurl_1` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `figureurl_2` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `figureurl_qq_1` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `figureurl_qq_2` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` tinyint(1) NOT NULL DEFAULT '0',
  `is_yellow_year_vip` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `vip` int(11) NOT NULL DEFAULT '0',
  `yellow_vip_level` tinyint(1) NOT NULL DEFAULT '0',
  `level` tinyint(1) NOT NULL DEFAULT '0',
  `is_yellow_vip` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`qq_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_relation
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_relation (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `become` tinyint(1) NOT NULL DEFAULT '0',
  `become_order` tinyint(1) NOT NULL DEFAULT '0',
  `become_child` tinyint(1) NOT NULL DEFAULT '0',
  `become_ordercount` int(11) DEFAULT '0',
  `become_moneycount` decimal(14,2) DEFAULT '0.00',
  `become_goods_id` int(11) DEFAULT '0',
  `become_info` tinyint(1) NOT NULL DEFAULT '1',
  `become_check` tinyint(1) NOT NULL DEFAULT '1',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_unique
# ------------------------------------------------------------


CREATE TABLE IF NOT EXISTS ims_yz_member_unique (
  `unique_id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `unionid` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `member_id` int(11) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`unique_id`),
  UNIQUE INDEX `un_unionid`(`unionid`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_member_wechat
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_member_wechat (
  `wechat_id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `openid` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nickname` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `gender` tinyint(1) NOT NULL DEFAULT '0',
  `avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `province` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `city` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`wechat_id`),
  KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_menu
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_menu (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `item` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url_params` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `permit` tinyint(1) NOT NULL DEFAULT '0',
  `menu` tinyint(1) NOT NULL DEFAULT '0',
  `icon` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `parent_id` int(11) NOT NULL DEFAULT '0',
  `sort` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_options
# ------------------------------------------------------------


CREATE TABLE IF NOT EXISTS ims_yz_options (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `option_name` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `option_value` varchar(200) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_order
# ------------------------------------------------------------


CREATE TABLE IF NOT EXISTS ims_yz_order (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0' COMMENT '公众号ID',
  `uid` int(10) NOT NULL DEFAULT '0' COMMENT '会员ID',
  `order_sn` varchar(23) NOT NULL DEFAULT '' COMMENT '订单编号',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '实付金额',
  `goods_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '商品现价和',
  `goods_total` int(11) NOT NULL DEFAULT '1' COMMENT '商品数量和',
  `status` tinyint(4) NOT NULL DEFAULT '0' COMMENT '状态-1关闭0未支付1代发货2代收货3完成',
  `create_time` int(10) NOT NULL DEFAULT '0' COMMENT '创建时间',
  `is_deleted` tinyint(3) NOT NULL DEFAULT '0',
  `is_member_deleted` tinyint(3) NOT NULL DEFAULT '0' COMMENT '会员删除',
  `finish_time` int(11) NOT NULL DEFAULT '0' COMMENT '完成时间',
  `pay_time` int(11) NOT NULL DEFAULT '0' COMMENT '支付时间',
  `send_time` int(11) NOT NULL DEFAULT '0' COMMENT '发货时间',
  `cancel_time` int(11) NOT NULL DEFAULT '0' COMMENT '关闭时间',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) NOT NULL DEFAULT '0',
  `cancel_pay_time` int(11) NOT NULL DEFAULT '0' COMMENT '取消支付时间',
  `cancel_send_time` int(11) NOT NULL DEFAULT '0' COMMENT '取消发货时间',
  `dispatch_type_id` tinyint(3) NOT NULL DEFAULT '0' COMMENT '配送方式ID',
  `dispatch_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '运费',
  `discount_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
  `pay_type_id` tinyint(3) NOT NULL DEFAULT '0' COMMENT '支付类型',
  `order_goods_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '订单商品金额和',
  `deduction_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '抵扣金额',
  `refund_id` int(11) NOT NULL DEFAULT '0' COMMENT '售后记录ID',
  `is_plugin` int(11) NOT NULL DEFAULT '0',
  `change_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '改价金额',
  `change_dispatch_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '改价运费金额',
  `comment_status` tinyint(2) NOT NULL DEFAULT '0',
  `order_pay_id` varchar(23) NOT NULL DEFAULT '' COMMENT '已支付记录id',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--订单主表';



# Dump of table ims_yz_order_address
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_order_address (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `address` varchar(255) NOT NULL DEFAULT '0' COMMENT '详细地址',
  `mobile` varchar(20) NOT NULL DEFAULT '' COMMENT '联系电话',
  `realname` varchar(50) NOT NULL DEFAULT '' COMMENT '收件人姓名',
  `updated_at` int(11) NOT NULL,
  `created_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--收货地址表';



# Dump of table ims_yz_order_change_log
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_order_change_log (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT NULL,
  `username` varchar(50) NOT NULL DEFAULT '',
  `old_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '原金额',
  `new_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '新金额',
  `change_price` decimal(10,2) NOT NULL COMMENT '改价金额',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  `change_dispatch_price` decimal(10,2) NOT NULL DEFAULT '0.00',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--改价记录';



# Dump of table ims_yz_order_express
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_order_express (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL DEFAULT '0',
  `express_company_name` varchar(50) NOT NULL DEFAULT '0' COMMENT '快递名称',
  `express_sn` varchar(50) NOT NULL DEFAULT '0' COMMENT '快递编号',
  `express_code` varchar(20) NOT NULL DEFAULT '0' COMMENT '快递标识',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--快递记录';



# Dump of table ims_yz_order_goods
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_order_goods (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0' COMMENT '公众号',
  `order_id` int(11) NOT NULL DEFAULT '0' COMMENT '订单ID',
  `goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品ID',
  `pay_sn` varchar(23) NOT NULL DEFAULT '',
  `total` int(11) NOT NULL DEFAULT '1' COMMENT '数量',
  `create_at` int(11) NOT NULL DEFAULT '0',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '初始金额',
  `goods_sn` varchar(50) NOT NULL DEFAULT '' COMMENT '商品编号',
  `uid` int(10) NOT NULL DEFAULT '0' COMMENT '会员ID',
  `thumb` varchar(255) NOT NULL DEFAULT '' COMMENT '主图',
  `title` varchar(255) NOT NULL DEFAULT '' COMMENT '商品名称',
  `goods_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '商品现价',
  `goods_option_id` int(11) NOT NULL DEFAULT '0' COMMENT '商品规格ID',
  `goods_option_title` varchar(255) NOT NULL DEFAULT '' COMMENT '商品规格名称',
  `product_sn` varchar(23) NOT NULL DEFAULT '' COMMENT '条码',
  `created_at` int(11) NOT NULL,
  `updated_at` int(11) NOT NULL,
  `discount_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '优惠金额',
  `comment_status` tinyint(3) NOT NULL DEFAULT '0' COMMENT '评论状态',
  `change_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '改价金额',
  `comment_id` int(11) NOT NULL DEFAULT '0' COMMENT '评论ID',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--商品记录表';



# Dump of table ims_yz_order_goods_change_log
# ------------------------------------------------------------


CREATE TABLE IF NOT EXISTS ims_yz_order_goods_change_log (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `order_change_log_id` int(11) DEFAULT NULL,
  `order_goods_id` int(11) NOT NULL COMMENT '订单商品ID',
  `old_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '原金额',
  `new_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '新金额',
  `change_price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '改价金额',
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--订单商品改价表';



# Dump of table ims_yz_order_mapping
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_order_mapping (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `old_order_id` int(11) NOT NULL,
  `new_order_id` int(11) NOT NULL,
  `old_openid` char(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `new_member_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_order_operation_log
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_order_operation_log (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `order_id` int(11) DEFAULT '0' COMMENT '订单ID',
  `before_operation_status` tinyint(1) DEFAULT '0' COMMENT '操作前状态',
  `after_operation_status` tinyint(1) DEFAULT '0' COMMENT '操作后状态',
  `operator` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT '操作账号名',
  `operation_time` int(11) DEFAULT '0' COMMENT '操作时间',
  `created_at` int(11) DEFAULT '0',
  `updated_at` int(11) DEFAULT NULL,
  `type` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--订单状态操作记录';



# Dump of table ims_yz_order_pay
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_order_pay (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `pay_sn` varchar(23) NOT NULL DEFAULT '' COMMENT '支付流水号',
  `status` tinyint(3) NOT NULL DEFAULT '0' COMMENT '状态',
  `pay_type_id` tinyint(3) NOT NULL DEFAULT '0' COMMENT '支付类型ID',
  `pay_time` int(11) NOT NULL DEFAULT '0' COMMENT '支付时间',
  `refund_time` int(11) NOT NULL DEFAULT '0',
  `order_ids` varchar(500) NOT NULL DEFAULT '' COMMENT '合并支付订单ID数组',
  `amount` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '支付金额',
  `uid` int(11) NOT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--支付流水记录';



# Dump of table ims_yz_order_refund
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_order_refund (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0' COMMENT '公众号',
  `uid` int(11) NOT NULL COMMENT '会员ID',
  `order_id` int(11) NOT NULL DEFAULT '0' COMMENT '订单ID',
  `refund_sn` varchar(255) NOT NULL COMMENT '退款编号',
  `refund_type` tinyint(1) DEFAULT '0' COMMENT '退款类型：0退款1退货退款2换货',
  `status` tinyint(3) DEFAULT '0' COMMENT '状态 -3关闭  -2用户取消 -1驳回 0待审核 1待退货 2待收货/用户发货 3重新发货 4重新收货/商家发货 5待打款 6已完成 7手动退款',
  `price` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '总金额',
  `reason` varchar(255) NOT NULL DEFAULT '' COMMENT '退款原因',
  `images` text NOT NULL COMMENT '图片',
  `content` text NOT NULL COMMENT '用户说明',
  `create_time` int(11) DEFAULT '0' COMMENT '申请时间',
  `reply` text,
  `reject_reason` text COMMENT '驳回原因',
  `refund_way_type` tinyint(3) DEFAULT '0' COMMENT '寄回方式 0寄回1上门取件	',
  `apply_price` decimal(10,2) DEFAULT '0.00' COMMENT '申请金额',
  `freight_price` decimal(10,2) DEFAULT '0.00' COMMENT '运费金额',
  `refund_proof_imgs` text,
  `refund_time` int(11) DEFAULT '0' COMMENT '完成时间',
  `refund_address` text COMMENT '退货寄回地址ID',
  `remark` text COMMENT '备注',
  `operate_time` int(11) DEFAULT '0' COMMENT '通用时间',
  `send_time` int(11) DEFAULT '0' COMMENT '商家发货时间',
  `return_time` int(11) DEFAULT '0' COMMENT '商家寄回时间',
  `end_time` int(11) DEFAULT '0',
  `alipay_batch_sn` varchar(255) DEFAULT '',
  `updated_at` int(11) DEFAULT NULL,
  `created_at` int(1) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_create_time` (`create_time`),
  KEY `idx_shop_id` (`uniacid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--售后记录表';



# Dump of table ims_yz_order_remark
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_order_remark (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `order_id` int(11) NOT NULL,
  `remark` char(255) NOT NULL,
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`order_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--商家注释';



# Dump of table ims_yz_pay_access_log
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_pay_access_log (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `url` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `http_method` char(7) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(135) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_pay_log
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_pay_log (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `type` tinyint(4) NOT NULL,
  `third_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `price` decimal(14,2) NOT NULL,
  `operation` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ip` varchar(135) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(13) NOT NULL DEFAULT '0',
  `updated_at` int(13) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统--支付请求日志';



# Dump of table ims_yz_pay_order
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_pay_order (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `int_order_no` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `out_order_no` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `trade_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `price` decimal(14,2) NOT NULL,
  `type` tinyint(1) NOT NULL,
  `third_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_uniacid` (`uniacid`),
  KEY `idx_member_id` (`member_id`),
  KEY `idx_order_no` (`out_order_no`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--支付流水记录';



# Dump of table ims_yz_pay_refund_order
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_pay_refund_order (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `int_order_no` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `out_order_no` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `trade_no` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `price` decimal(14,2) NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `status` tinyint(1) NOT NULL,
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--支付退款记录';



# Dump of table ims_yz_pay_request_data
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_pay_request_data (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `out_order_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `third_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `params` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统--支付请求参数日志';



# Dump of table ims_yz_pay_response_data
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_pay_response_data (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `out_order_no` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `third_type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `params` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统--支付回调参数日志';



# Dump of table ims_yz_pay_type
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_pay_type (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(50) NOT NULL DEFAULT '',
  `plugin_id` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统--支付类型';



# Dump of table ims_yz_pay_withdraw_order
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_pay_withdraw_order (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `int_order_no` varchar(32) DEFAULT NULL,
  `out_order_no` varchar(32) NOT NULL DEFAULT '',
  `trade_no` varchar(255) DEFAULT NULL,
  `price` decimal(14,2) NOT NULL,
  `type` varchar(255) NOT NULL DEFAULT '',
  `status` int(11) NOT NULL,
  `created_at` int(13) NOT NULL DEFAULT '0',
  `updated_at` int(13) NOT NULL DEFAULT '0',
  `deleted_at` int(13) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='财务--支付提现单';



# Dump of table ims_yz_permission
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_permission (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` tinyint(1) NOT NULL,
  `item_id` int(11) NOT NULL,
  `permission` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_users_permission
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_users_permission (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(10) unsigned NOT NULL,
  `uid` int(10) unsigned NOT NULL,
  `type` varchar(100) NOT NULL,
  `permission` varchar(10000) NOT NULL,
  `url` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_plugin_article
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_plugin_article (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL DEFAULT '',
  `desc` text,
  `thumb` text,
  `content` longtext NOT NULL,
  `virtual_created_at` int(11) DEFAULT NULL,
  `author` varchar(20) NOT NULL DEFAULT '',
  `virtual_read_num` int(11) DEFAULT NULL,
  `read_num` int(11) NOT NULL DEFAULT '0',
  `virtual_like_num` int(11) DEFAULT NULL,
  `like_num` int(11) NOT NULL DEFAULT '0',
  `link` varchar(255) DEFAULT NULL,
  `per_person_per_day` int(11) DEFAULT NULL,
  `total_per_person` int(11) DEFAULT NULL,
  `point` int(11) DEFAULT NULL,
  `credit` int(11) DEFAULT NULL,
  `bonus_total` int(11) DEFAULT NULL,
  `bonus_total_now` int(11) DEFAULT NULL,
  `no_copy_url` tinyint(1) DEFAULT NULL,
  `no_share` tinyint(1) DEFAULT NULL,
  `no_share_to_friend` tinyint(1) DEFAULT NULL,
  `keyword` varchar(255) NOT NULL DEFAULT '',
  `report_enabled` tinyint(1) DEFAULT NULL,
  `advs_type` tinyint(1) DEFAULT NULL,
  `advs_title` varchar(255) DEFAULT NULL,
  `advs_title_footer` varchar(255) DEFAULT NULL,
  `advs_link` varchar(255) DEFAULT NULL,
  `advs` text,
  `state` tinyint(1) DEFAULT NULL,
  `state_wechat` tinyint(1) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_plugin_article_category
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_plugin_article_category (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `member_level_limit` int(11) DEFAULT NULL,
  `commission_level_limit` int(11) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_plugin_article_log
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_plugin_article_log (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `article_id` int(11) DEFAULT NULL,
  `read_num` int(11) DEFAULT NULL,
  `like_num` int(11) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `uniacid` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_plugin_article_report
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_plugin_article_report (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `uid` int(11) DEFAULT NULL,
  `article_id` int(11) DEFAULT NULL,
  `type` varchar(255) DEFAULT NULL,
  `desc` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_plugin_article_share
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_plugin_article_share (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `article_id` int(11) DEFAULT NULL,
  `share_uid` int(11) DEFAULT NULL,
  `click_uid` int(11) DEFAULT NULL,
  `click_time` int(11) DEFAULT NULL,
  `point` int(11) DEFAULT NULL,
  `credit` text,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_plugin_goods_assistant
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_plugin_goods_assistant (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `goods_id` int(11) NOT NULL,
  `uniacid` int(11) NOT NULL,
  `itemid` varchar(255) DEFAULT NULL,
  `source` varchar(255) DEFAULT NULL,
  `url` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_point_log
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_point_log (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `member_id` int(11) NOT NULL DEFAULT '0' COMMENT '会员ID',
  `order_goods_id` int(11) NOT NULL DEFAULT '0' COMMENT '订单商品id,退款返还积分使用',
  `point` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '变动积分',
  `point_income_type` tinyint(2) NOT NULL DEFAULT '0' COMMENT '变动方式：1-获得，-1失去',
  `point_mode` tinyint(5) NOT NULL DEFAULT '0' COMMENT '变动类型',
  `before_point` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '变动前积分',
  `after_point` decimal(10,2) NOT NULL DEFAULT '0.00' COMMENT '变动后积分',
  `remark` varchar(255) NOT NULL DEFAULT '0' COMMENT '备注',
  `order_id` int(11) NOT NULL DEFAULT '0' COMMENT '订单ID',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  INDEX `idx_order_id`(`order_id`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='财务--积分明细';
 

# Dump of table ims_yz_qq_config
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_qq_config (
  `config_id` int(11) NOT NULL,
  `uniacid` int(11) NOT NULL DEFAULT '0',
  `app_key` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `app_secret` varchar(64) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` tinyint(1) NOT NULL DEFAULT '0',
  `created_at` int(10) unsigned NOT NULL DEFAULT '0',
  `updated_at` int(10) unsigned NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`config_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_resend_express
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_resend_express (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `refund_id` int(11) NOT NULL DEFAULT '0',
  `express_company_name` varchar(50) NOT NULL DEFAULT '0',
  `express_sn` varchar(50) NOT NULL DEFAULT '0',
  `express_code` varchar(20) NOT NULL DEFAULT '0',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`refund_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--售后换货发货快递';



# Dump of table ims_yz_return_express
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_return_express (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `refund_id` int(11) NOT NULL DEFAULT '0',
  `express_company_name` varchar(50) NOT NULL DEFAULT '0',
  `express_sn` varchar(50) NOT NULL DEFAULT '0',
  `express_code` varchar(20) NOT NULL DEFAULT '0',
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_order_id` (`refund_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='订单--售后退换货寄回快递';



# Dump of table ims_yz_role
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_role (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `name` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_setting
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_setting (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `group` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'shop',
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `type` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` text COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统--商城设置表';



# Dump of table ims_yz_slide
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_slide (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `slide_name` varchar(100) DEFAULT NULL,
  `link` varchar(255) DEFAULT NULL,
  `thumb` varchar(255) DEFAULT NULL,
  `display_order` int(11) DEFAULT '0',
  `enabled` tinyint(3) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `updated_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_sms_send_limit
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_sms_send_limit (
  `sms_id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) NOT NULL,
  `mobile` varchar(11) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total` tinyint(1) NOT NULL,
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `deleted_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`sms_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_street
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_street (
  `id` int(10) NOT NULL AUTO_INCREMENT,
  `areaname` varchar(255) DEFAULT NULL,
  `parentid` int(10) DEFAULT NULL,
  `level` int(10) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='系统--地址街道表';

 
# Dump of table ims_yz_template_message
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_template_message (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `type` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT 'system',
  `item` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `parent_item` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id_short` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `template_id` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `content` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `example` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_template_message_record
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_template_message_record (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `uniacid` int(11) DEFAULT NULL,
  `member_id` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `openid` char(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `template_id` varchar(45) COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `top_color` char(7) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `data` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` int(11) NOT NULL DEFAULT '0',
  `updated_at` int(11) NOT NULL DEFAULT '0',
  `send_time` int(11) NOT NULL DEFAULT '0',
  `status` tinyint(1) NOT NULL DEFAULT '0',
  `msgid` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `result` tinyint(1) NOT NULL DEFAULT '0',
  `wechat_send_at` int(11) NOT NULL DEFAULT '0',
  `sended_count` tinyint(1) NOT NULL DEFAULT '1',
  `extend_data` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_user_role
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_user_role (
  `user_id` int(11) NOT NULL,
  `role_id` int(11) NOT NULL,
  PRIMARY KEY (`user_id`,`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



# Dump of table ims_yz_withdraw
# ------------------------------------------------------------

CREATE TABLE IF NOT EXISTS ims_yz_withdraw (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `withdraw_sn` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '提现单号',
  `uniacid` int(11) DEFAULT NULL,
  `member_id` int(11) DEFAULT NULL,
  `type` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_id` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `type_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '提现收入类型',
  `amounts` decimal(14,2) DEFAULT NULL,
  `poundage` decimal(14,2) DEFAULT NULL,
  `poundage_rate` int(11) DEFAULT NULL,
  `pay_way` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` tinyint(1) DEFAULT NULL,
  `created_at` int(11) DEFAULT NULL,
  `audit_at` int(11) DEFAULT NULL COMMENT '审核时间',
  `pay_at` int(11) DEFAULT NULL COMMENT '打款时间',
  `arrival_at` int(11) DEFAULT NULL COMMENT '到账时间',
  `updated_at` int(11) DEFAULT NULL,
  `deleted_at` int(11) DEFAULT NULL,
  `actual_amounts` decimal(14,2) NOT NULL COMMENT '实际打款金额',
  `actual_poundage` decimal(14,2) NOT NULL COMMENT '最终手续费',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='财务--提现记录';


";

config('app.framework') == 'platform' ? \Illuminate\Support\Facades\DB::unprepared($sql) : pdo_fetchall($sql) ;

