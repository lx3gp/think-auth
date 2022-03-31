/*
Navicat MySQL Data Transfer
Date: 2022-03-28 11:32:07
*/

SET FOREIGN_KEY_CHECKS=0;

-- ----------------------------
-- Table structure for __PREFIX__auth_group
-- ----------------------------
DROP TABLE IF EXISTS `__PREFIX__auth_group`;
CREATE TABLE `__PREFIX__auth_group` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(11) DEFAULT '0' COMMENT '上级用户组ID',
  `title` char(100) NOT NULL DEFAULT '',
  `rules` text NOT NULL COMMENT '所拥有的权限ID',
  `sort` int(11) DEFAULT '100' COMMENT '排序，默认100',
  `remark` varchar(255) DEFAULT NULL COMMENT '用户组描述',
  `status` tinyint(1) NOT NULL DEFAULT '1' COMMENT '状态',
  PRIMARY KEY (`id`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=6 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of __PREFIX__auth_group
-- ----------------------------

-- ----------------------------
-- Table structure for __PREFIX__auth_group_access
-- ----------------------------
DROP TABLE IF EXISTS `__PREFIX__auth_group_access`;
CREATE TABLE `__PREFIX__auth_group_access` (
  `uid` mediumint(8) unsigned NOT NULL COMMENT '用户ID',
  `group_id` mediumint(8) unsigned NOT NULL COMMENT '用户组ID',
  UNIQUE KEY `uid_group_id` (`uid`,`group_id`) USING BTREE,
  KEY `uid` (`uid`) USING BTREE,
  KEY `group_id` (`group_id`) USING BTREE
) ENGINE=MyISAM DEFAULT CHARSET=utf8 ROW_FORMAT=FIXED;

-- ----------------------------
-- Records of __PREFIX__auth_group_access
-- ----------------------------

-- ----------------------------
-- Table structure for __PREFIX__auth_rule
-- ----------------------------
DROP TABLE IF EXISTS `__PREFIX__auth_rule`;
CREATE TABLE `__PREFIX__auth_rule` (
  `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
  `pid` int(11) DEFAULT '0' COMMENT '上级规则',
  `name` char(80) NOT NULL DEFAULT '',
  `title` char(20) NOT NULL DEFAULT '',
  `type` tinyint(1) NOT NULL DEFAULT '1' COMMENT '规则生效方式，1为实时认证；2为登录认证',
  `isMenu` tinyint(1) DEFAULT '0' COMMENT '是否为菜单项：0 按钮， 1 菜单',
  `menuIcon` char(80) DEFAULT NULL COMMENT '菜单图标',
  `menuUrl` varchar(255) DEFAULT NULL COMMENT '菜单地址',
  `menuModule` varchar(255) DEFAULT NULL,
  `sort` int(11) DEFAULT '100' COMMENT '排序，默认 100',
  `isIframe` tinyint(1) DEFAULT '0' COMMENT '是否为弹窗链接：0，否  1，是',
  `iframeWidth` int(11) DEFAULT NULL COMMENT '弹窗宽度',
  `iframeHeight` int(11) DEFAULT NULL COMMENT '弹窗高度',
  `status` tinyint(1) NOT NULL DEFAULT '1',
  `condition` char(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`) USING BTREE,
  UNIQUE KEY `name` (`name`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=147 DEFAULT CHARSET=utf8 ROW_FORMAT=DYNAMIC;

-- ----------------------------
-- Records of __PREFIX__auth_rule
-- ----------------------------

-- ----------------------------
-- Table structure for __PREFIX__user
-- ----------------------------
DROP TABLE IF EXISTS `__PREFIX__user`;
CREATE TABLE `__PREFIX__user` (
  `id` int(11) unsigned NOT NULL AUTO_INCREMENT COMMENT '管理员ID',
  `IDmask` char(120) NOT NULL DEFAULT '' COMMENT 'IDmask为显示操作的用户ID',
  `open_id` char(80) DEFAULT NULL COMMENT '用户的openID',
  `union_id` char(80) DEFAULT NULL COMMENT '同一关联主体下的统一账号ID',
  `pid` int(11) NOT NULL DEFAULT '0' COMMENT '当前用户上级推荐用户',
  `is_admin` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否为管理员',
  `username` varchar(50) NOT NULL DEFAULT '' COMMENT '用户名',
  `nickname` varchar(50) NOT NULL DEFAULT '' COMMENT '用户昵称',
  `password` varchar(255) DEFAULT '' COMMENT '密码',
  `salt` varchar(8) DEFAULT NULL COMMENT '混淆密码盐值',
  `phone` varchar(20) NOT NULL DEFAULT '' COMMENT '用户电话',
  `email` varchar(255) NOT NULL DEFAULT '' COMMENT '邮箱',
  `type` tinyint(1) NOT NULL DEFAULT '0' COMMENT '用户类型，0 客服，1 代理，2 直销客户',
  `score` int(11) DEFAULT '0' COMMENT '用户积分',
  `level` int(2) DEFAULT '1' COMMENT '用户等级',
  `headimg` varchar(500) DEFAULT NULL COMMENT '用户头像',
  `security_question` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT '安全问题',
  `security_answer` varchar(255) CHARACTER SET utf8 DEFAULT NULL COMMENT '安全回答',
  `website_id` char(80) CHARACTER SET utf8 DEFAULT '0,1' COMMENT '站点id，用于多站点，默认0',
  `invitecode` char(120) DEFAULT NULL COMMENT '邀请码',
  `promote_code` char(120) DEFAULT NULL COMMENT '推广码',
  `remark` varchar(1200) DEFAULT NULL COMMENT '用户备注',
  `review_remark` varchar(255) DEFAULT NULL COMMENT '审核意见',
  `create_time` datetime DEFAULT NULL COMMENT '账户创建时间',
  `update_time` datetime DEFAULT NULL COMMENT '账户更新时间',
  `status` tinyint(1) NOT NULL DEFAULT '9' COMMENT '0 暂停， 1 正常，2 删除，3 冻结， 4 未通过， 9未审核',
  `is_del` tinyint(1) DEFAULT '0' COMMENT '是否删除，默认为：0 ，1：删除',
  PRIMARY KEY (`id`,`username`,`IDmask`) USING BTREE,
  UNIQUE KEY `id` (`id`) USING BTREE,
  UNIQUE KEY `username` (`username`) USING BTREE,
  UNIQUE KEY `IDmask` (`IDmask`) USING BTREE
) ENGINE=InnoDB AUTO_INCREMENT=11 DEFAULT CHARSET=utf8mb4 ROW_FORMAT=COMPACT COMMENT='用户表';