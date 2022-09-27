<?php
declare (strict_types = 1);
/**
 * +----------------------------------------------------------------------
 * | think-auth [thinkphp6]
 * +----------------------------------------------------------------------
 * | FILE: Auth.php
 * | AUTHOR: DreamLee
 * | EMAIL: 1755773846@qq.com
 * | QQ: 1755773846
 * | DATETIME: 2022/03/31 14:47
 * |-----------------------------------------
 * | 不积跬步,无以至千里；不积小流，无以成江海！
 * +----------------------------------------------------------------------
 * | Copyright (c) 2022 DreamLee All rights reserved.
 * +----------------------------------------------------------------------
 */
namespace think;

use think\facade\Db;
use think\facade\Cache;
use think\facade\Session;
use think\facade\Request;
/**
 * 权限认证类
 * 功能特性：
 * 1，是对规则进行认证，不是对节点进行认证。用户可以把节点当作规则名称实现对节点进行认证。
 *      $auth=new Auth();  $auth->check('规则名称','用户id')
 * 2，可以同时对多条规则进行认证，并设置多条规则的关系（or或者and）
 *      $auth=new Auth();  $auth->check('规则1,规则2','用户id','and')
 *      第三个参数为and时表示，用户需要同时具有规则1和规则2的权限。 当第三个参数为or时，表示用户值需要具备其中一个条件即可。默认为or
 * 3，一个用户可以属于多个用户组(auth_group_access表 定义了用户所属用户组)。我们需要设置每个用户组拥有哪些规则(auth_group 定义了用户组权限)
 *
 * 4，支持规则表达式。
 *      在auth_rule 表中定义一条规则时，如果type为1， condition字段就可以定义规则表达式。 如定义{score}>5  and {score}<100  表示用户的分数在5-100之间时这条规则才会通过。
 */
//数据库 请手动创建下sql
/*
------------------------------
-- think_auth_rule，规则表，
-- id:主键，name：规则唯一标识, title：规则中文名称 status 状态：为1正常，为0禁用，condition：规则表达式，为空表示存在就验证，不为空表示按照条件验证
------------------------------
 DROP TABLE IF EXISTS `think_auth_rule`;
CREATE TABLE `think_auth_rule` (  
    `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,  
    `name` char(80) NOT NULL DEFAULT '',  
    `title` char(20) NOT NULL DEFAULT '',  
    `status` tinyint(1) NOT NULL DEFAULT '1',  
    `condition` char(100) NOT NULL DEFAULT '',  
    PRIMARY KEY (`id`),  
    UNIQUE KEY `name` (`name`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
------------------------------
-- think_auth_group 用户组表， 
-- id：主键， title:用户组中文名称， rules：用户组拥有的规则id， 多个规则","隔开，status 状态：为1正常，为0禁用
------------------------------
 DROP TABLE IF EXISTS `think_auth_group`;
CREATE TABLE `think_auth_group` ( 
    `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT, 
    `title` char(100) NOT NULL DEFAULT '', 
    `status` tinyint(1) NOT NULL DEFAULT '1', 
    `rules` char(80) NOT NULL DEFAULT '', 
    PRIMARY KEY (`id`)
) ENGINE=MyISAM  DEFAULT CHARSET=utf8;
------------------------------
-- think_auth_group_access 用户组明细表
-- uid:用户id，group_id：用户组id
------------------------------
DROP TABLE IF EXISTS `think_auth_group_access`;
CREATE TABLE `think_auth_group_access` (  
    `uid` mediumint(8) unsigned NOT NULL,  
    `group_id` mediumint(8) unsigned NOT NULL, 
    UNIQUE KEY `uid_group_id` (`uid`,`group_id`),  
    KEY `uid` (`uid`), 
    KEY `group_id` (`group_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
*/
class Auth
{
    /**
     * var object 对象实例
     */
    protected static $instance;

    //默认配置
    protected $config = [
		'auth_on'           => 1, // 权限开关
		'auth_type'         => 1, // 认证方式，1为实时认证；2为登录认证。
		'auth_group'        => 'auth_group', // 用户组数据不带前缀表名
		'auth_group_access' => 'auth_group_access', // 用户-用户组关系不带前缀表名
		'auth_rule'         => 'auth_rule', // 权限规则不带前缀表名
		'auth_user'         => 'user', // 用户信息表不带前缀表名,主键自增字段为id
		'auth_driver'       => 'cache', // 用户信息存贮介质
		'auth_pk'           => 'id',// 用户表ID字段名
    ];

    /**
     * 默认配置  -   存储驱动
     */
    protected $driver = 'cache';

    /**
     * 类架构函数
     * Auth constructor.
     */
    public function __construct($isSeparate=false)
    {
        //可设置配置项 auth, 此配置项为数组。
        if($isSeparate){    //  是否多端分离权限配置
            $module     = app('http')->getName();   //应用名
            if ( $module=='api' ) {
                //  获取API配置
                $config = config('auth.api');
            }else{
                //  获取API配置
                $config = config('auth.other');
            }
        }else{  //  单一/统一权限配置
            $config = config('auth');
        }
        if ($config) {
            $this->config = array_merge($this->config, $config);
        }
        if ($this->config['auth_driver']) {
            $this->driver = $this->config['auth_driver'];
        }
    }

    /**
     * 初始化
     * access public
     * @param array $options 参数
     * return \think\Request
     */
    public static function instance($options = null)
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($options);
        }
        return self::$instance;
    }

    /**
     * 检查权限
     * @param $name string|array  需要验证的规则列表,支持逗号分隔的权限规则或索引数组
     * @param $uid  int           认证用户的id
     * @param int $type 认证类型
     * @param string $mode 执行check的模式
     * @param string $relation 如果为 'or' 表示满足任一条规则即通过验证;如果为 'and'则表示需满足所有规则才能通过验证
     * @param int $authRelationWay 权限获取是否依赖父级用户组权限，0 表示不依赖父级用户组， 1 表示依赖父级用户组
     * @return bool               通过验证返回true;失败返回false
     */
    public function check($name, $uid, $type = 1, $mode = 'url', $relation = 'or', $authRelationWay = 0)
    {
        if (!$this->config['auth_on']) {
            return true;
        }
        // 获取用户需要验证的所有有效规则列表
        $authList = $this->getAuthList($uid, $type, $authRelationWay);
        if (is_string($name)) {
            $name = strtolower($name);
            if (strpos($name, ',') !== false) {
                $name = explode(',', $name);
            } else {
                $name = [$name];
            }
        }
        $list = []; //保存验证通过的规则名
        if ('url' == $mode) {
            $REQUEST = unserialize(strtolower(serialize(Request::param())));
        }
        foreach ($authList as $auth) {
            $query = preg_replace('/^.+\?/U', '', $auth);
            if ('url' == $mode && $query != $auth) {
                parse_str($query, $param); //解析规则中的param
                $intersect = array_intersect_assoc($REQUEST, $param);
                $auth = preg_replace('/\?.*$/U', '', $auth);
                if (in_array($auth, $name) && $intersect == $param) {
                    //如果节点相符且url参数满足
                    $list[] = $auth;
                }
            } else {
                if (in_array($auth, $name)) {
                    $list[] = $auth;
                }
            }
        }
        if ('or' == $relation && !empty($list)) {
            return true;
        }
        $diff = array_diff($name, $list);
        if ('and' == $relation && empty($diff)) {
            return true;
        }
        return false;
    }

    /**
     * 根据用户id获取用户组,返回值为数组
     * @param  $uid int     用户id
     * return array       用户所属的用户组 array(
     *     array('uid'=>'用户id','group_id'=>'用户组id','title'=>'用户组名称','rules'=>'用户组拥有的规则id,多个,号隔开'),
     *     ...)
     */
    public function getGroups($uid)
    {
        static $groups = [];
        if (isset($groups[$uid])) {
            return $groups[$uid];
        }
        // 转换表名
        $auth_group_access = $this->config['auth_group_access'];
        $auth_group = $this->config['auth_group'];
        // 执行查询
        $user_groups = Db::view($auth_group_access, 'uid,group_id')
            ->view($auth_group, 'title,rules', "{$auth_group_access}.group_id={$auth_group}.id", 'LEFT')
            ->where("{$auth_group_access}.uid='{$uid}' and {$auth_group}.status='1'")
            ->select();
        $groups[$uid] = $user_groups ?: [];
        return $groups[$uid];
    }


    /**
     * 获取当前用户所在用户组以及用户组的所有父级节点
     * @param integer $uid 用户ID
     * @param integer $deep 依赖层级，0 表示依赖于父级用户组，1 表示依赖于所有的层级用户组（需要获取当前用户所在用户组的所有父级用户组，直到用户组的父级ID为0）
     * @return array       用户所属的用户组 array(
     *     array('uid'=>'用户id','group_id'=>'用户组id','title'=>'用户组名称','rules'=>'用户组拥有的规则id,多个,号隔开'),
     *     ...)
     */
    public function getUserAllRulesByGroupRelation(int $uid = null, int $deep = 0)
    {
        //  获取当前用户全部可用的用户组以及所有的系统用户组
        $userGroups = $this->getUserRoles($uid);        
        $groups = $this->getAllRules(1);
        if(empty($userGroups) || empty($groups)){
            return [];
        }
        if($deep){
            //  获取当前用户的用户组的所有父级用户组按照父级用户组等级  -   方式一：筛选出当前用户组的所有用户组层级
            $ruleNodes = [];
            foreach ($userGroups as $g) {
                $node = $this->getParents($groups, $g['id']);  
                $ruleNodes = array_merge($ruleNodes, $this->getMergeArrayField($node, 'rules'));
            }
            $ruleNodes = array_unique($ruleNodes);
        }else{
            //  获取当前用户的用户组的父级用户组    -   方式二：筛选出当前用户组的父级用户组
            $ruleNodes = [];
            foreach ($userGroups as $g) {
                $node = $this->getParent($groups, $g['id']);
                $ruleNodes = array_merge($ruleNodes, $this->getMergeArrayField($node, 'rules'));
            }
            //  获取当前用户所在的用户组，并将数据加入到其中
            $userGroupInfo = $this->getGroups($uid);
            $ruleNodes = array_merge($ruleNodes, $this->getMergeArrayField($userGroupInfo[0], 'rules'));
        }
        return array_unique($ruleNodes);
    }

    /**
     * 获得权限列表
     * @param integer $uid 用户id
     * @param integer $type
     * @param integer $authRelationWay 获取权限节点的方式 0：仅仅获取当前用户所在的当前用户组， 1：以及用户组子父级关系，获取包含权限所有节点（适用于分组继承权限）
     * return array
     */
    protected function getAuthList($uid, $type, $authRelationWay = 0)
    {
        static $_authList = []; //保存用户验证通过的权限列表
        $t = implode(',', (array)$type);
        if (isset($_authList[$uid . $t])) {
            return $_authList[$uid . $t];
        }
        //返回cache/session中结果
        if(strtolower($this->driver) == "cache") {
            if (2 == $this->config['auth_type'] && Cache::has('_auth_list_' . $uid . $t)) {
                return Cache::get('_auth_list_' . $uid . $t);   
            }
        }else if(strtolower($this->driver) == "session"){
            if (2 == $this->config['auth_type'] && Session::has('_auth_list_' . $uid . $t)) {
                return Session::get('_auth_list_' . $uid . $t);  
            }
        }
        //  根据authRelationWay分型获取权限节点
        if($authRelationWay) {
            //  合并父级权限获取总权限，默认只合并用户所在用户组及该用户组的直系父级用户组权限
            $ids = $this->getUserAllRulesByGroupRelation($uid, 0);
        }else{
            //读取用户所属用户组
            $groups = $this->getGroups($uid);
            $ids = []; //保存用户所属用户组设置的所有权限规则id
            foreach ($groups as $g) {
                $ids = array_merge($ids, explode(',', trim($g['rules'], ',')));
            }
            $ids = array_unique($ids);            
        }
        if (empty($ids)) {
            $_authList[$uid . $t] = [];
            return [];
        }
        $map = [
            ['type','=',$type],
            ['id','in', $ids],
            ['status','=',1],
        ];
        //读取用户组所有权限规则
        $rules = Db::name($this->config['auth_rule'])->where($map)->field('condition,name')->select();
        //循环规则，判断结果。
        $authList = []; //
        foreach ($rules as $rule) {
            if (!empty($rule['condition'])) {
                //根据condition进行验证
                $user = $this->getUserInfo($uid); //获取用户信息,一维数组
                $command = preg_replace('/\{(\w*?)\}/', '$user[\'\\1\']', $rule['condition']);
                //dump($command); //debug
                @(eval('$condition=(' . $command . ');'));
                if ($condition) {
                    $authList[] = strtolower($rule['name']);
                }
            } else {
                //只要存在就记录
                $authList[] = strtolower($rule['name']);
            }
        }
        $_authList[$uid . $t] = $authList;
        if (2 == $this->config['auth_type']) {
            //规则列表结果保存到cache/session
            if(strtolower($this->driver) == "cache") {
                Cache::set('_auth_list_' . $uid . $t, $authList);                
            }else if(strtolower($this->driver) == "session"){
                Session::set('_auth_list_' . $uid . $t, $authList);  
            }
        }
        return array_unique($authList);
    }

    /**
     * 获得用户资料,根据自己的情况读取数据库 
     */
    function getUserInfo($uid)
    {
        static $userinfo = [];
        $user = Db::name($this->config['auth_user']);
        // 获取用户表主键
        $_pk = is_string($user->getPk()) ? $user->getPk() : $this->config['auth_pk'];
        if (!isset($userinfo[$uid])) {
            $userinfo[$uid] = $user->where($_pk, $uid)->find();
        }
        return $userinfo[$uid];
    }

    //根据uid获取角色名称
    //根据uid获取角色名称
    function getRole($uid){
        try{
            $auth_group_access =  Db::name($this->config['auth_group_access'])->where('uid',$uid)->find();
            $title =   Db::name($this->config['auth_group'])->where('id',$auth_group_access['group_id'])->value('title');
            return $title;
        }catch (\Exception $e){
            return '此用户未授予角色';
        }
    }

    //根据uid获取角色名称
    //根据uid获取角色名称
    /**
     * 根据用户UID获取该用户所有角色
     * @param integer $uid 用户ID
     * @param integer $type 想要获取到的字段信息类型， 0 全部字段， 1 id字段
     * @return array 用户组的ID信息
     */
    function getUserRoles($uid = null,  int $type = 1){
        try{
            if($type){
                return Db::view($this->config['auth_group_access'], [])
                ->view($this->config['auth_group'], ['id'], "{$this->config['auth_group_access']}.group_id={$this->config['auth_group']}.id", 'LEFT')
                ->where("{$this->config['auth_group_access']}.uid='{$uid}' and {$this->config['auth_group']}.status=1")
                ->select();
            }else{
                return Db::view($this->config['auth_group_access'], 'group_id as id')
                ->view($this->config['auth_group'], 'title,rules', "{$this->config['auth_group_access']}.group_id={$this->config['auth_group']}.id", 'LEFT')
                ->where("{$this->config['auth_group_access']}.uid='{$uid}' and {$this->config['auth_group']}.status=1")
                ->select();
            }
        }catch (\Exception $e){
            return '此用户未授予角色';
        }
    }

	//传递一个子分类ID返回他的所有父级分类
    /**
     * 获取所有用户组信息
     * @param integer $type, 用户组状态类型， 0 全部，1 正常
     */
	function getAllRules($type=0) {
        // 转换表名
        $auth_group = $this->config['auth_group'];
        //  获取所有用户组
        if($type){
            return Db::view($auth_group, ['id', 'pid', 'title', 'rules', 'sort'])->where("{$auth_group}.status=1")->select();
        }else{
            return Db::view($auth_group, ['id', 'pid', 'title', 'rules', 'sort'])->select();
        }
	}

	//传递一个子分类ID返回他的所有父级分类
	function getParents($cate, $id) {
		$arr = [];
        //  判断用户组数据是否为空
        if(!empty($cate)){
            foreach ($cate as $v) {
                if ($v['id'] == $id) {
                    $arr[] = $v;
                    $arr   = array_merge($this->getParents($cate, $v['pid']), $arr);
                }
            }
        }
        return $arr;
	}

	//传递分类ID，返回他父级分类或顶级分类
	// $type 1为父级，2为顶级
	function getParent($cate, $id, $type = 1) {
		$parent_info = [];
		$arrs        = $this->getParents($cate, $id);
		if (empty($arrs)) {
			return $parent_info;
		}
		$self = [];
		foreach ($arrs as $v) {
			if ($v['id'] == $id) {
				$self = $v;
				break;
			}
		}
		//父级/顶级 是自己，则直接返回
		if ($self['pid'] == 0) {
			return $parent_info; //空/null
		}
		foreach ($arrs as $v) {
			if ($type == 1) {
				if ($v['id'] == $self['pid']) {
					$parent_info = $v;
					break;
				}
			} else if ($type == 2) {
				//顶级
				if ($v['pid'] == 0) {
					$parent_info = $v;
					break;
				}
			}
		}
		return $parent_info;
	}

    /**
     * 对数组中指定的字段进行提取合并
     * @param array $array 需要处理的数组
     * @param string $field 需要提取的字段名称
     * @return array 返回的信息
     */
    function getMergeArrayField(array $array = null, string $field = null){
        if(!$array || !$field){
            return [];
        }
        $rules = [];
        //  判断并提取字段值
        if(is_array($array) && array_key_exists($field, $array)){   //  单数组
            $rules = explode(',', $array[$field]);
        } else if(is_array($array) && !array_key_exists($field, $array)) {  //  多维数组
            $_rules = array_column($array, $field);
            foreach($_rules as $r){
                $rules = array_merge($rules, explode(',', $r));
            }
        }
        return array_unique($rules);
    }

    /**
     * 授予用户权限
     */
    public function setRole($uid,$group_id){
        $res =  Db::name('auth_group_access')
            ->where('uid',$uid)
            ->update(['group_id'=>$group_id]);
        return true;
    }
}
