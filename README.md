# thinkphp6.0权限扩展
The ThinkPHP6 Auth Package
- 本插件经thinkphp官方推荐认证扩展，请放心使用
- 开发者基本礼仪，star一下
## 安装
> composer require lx3gp/think-auth

## 兼容版本
- thinkphp 6.0

## 配置
```
// auth配置  自定义数据表位置在 ./config/auth.php里面,安装扩展会自动生成。如果没有的话建议手动建立下。
//下面的数据表映射需要与表对应上
[
    'auth_on'           => 1, // 权限开关
    'auth_type'         => 1, // 认证方式，1为实时认证；2为登录认证。
    'auth_group'        => 'think_auth_group', // 用户组数据
    'auth_group_access' => 'think_auth_group_access', // 用户-用户组关系
    'auth_rule'         => 'think_auth_rule', // 权限规则
    'auth_user'         => 'user', // 用户信息表,主键自增字段为id
    'auth_driver'		=> 'cache', // 权限数据存储的介质
],
```
## 官网 
http://www.waytomilky.com/

# 交流qq群
606645328

### 导入数据表
> - `__PREFIX__` 为自定义的数据表前缀
> - 请确认已经配置了数据库相关信息
> - 请确认数据库中不存在`auth_rule`、`auth_group`、`auth_group_access`、`user`这四个数据表
> - 使用`composer`安装该插件后，系统会自动将上述数据表导入到数据库中，无需手动导入
> - 如果安装插件后，在数据库中没有发现上述4张表，请将./vendor/lx3gp/data/install.sql文件手动导入至数据库

## 原理
Auth权限认证是按规则进行认证。
在数据库中我们有 

- 规则表（think_auth_rule） 
- 用户组表(think_auth_group) 
- 用户组明显表（think_auth_group_access）

我们在规则表中定义权限规则， 在用户组表中定义每个用户组有哪些权限规则，在用户组明显表中定义用户所属的用户组。 

下面举例说明：

我们要判断用户是否有显示一个操作按钮的权限， 首先定义一个规则， 在规则表中添加一个名为 show_button 的规则。 然后在用户组表添加一个用户组，定义这个用户组有show_button 的权限规则（think_auth_group表中rules字段存得时规则ID，多个以逗号隔开）， 然后在用户组明细表定义 UID 为1 的用户 属于刚才这个的这个用户组。 

## 使用
判断权限方法
```
// 引入类库
use think\auth\Auth;

// 获取auth实例
$auth = Auth::instance();

// 检测权限
if($auth->check('show_button',1)){// 第一个参数是规则名称,第二个参数是用户UID
	//有显示操作按钮的权限
}else{
	//没有显示操作按钮的权限
}
```

Auth类也可以对节点进行认证，我们只要将规则名称，定义为节点名称就行了。 
可以在公共控制器Base中定义_initialize方法
```
<?php
use think\Controller;
use think\auth\Auth;
class Base extends Controller
{
    public function _initialize()
	{
		$controller = request()->controller();
		$action = request()->action();
		$auth = new Auth();
		if(!$auth->check($controller . '-' . $action, session('id'))){
			$this->error('你没有权限访问');
		}
    }
 }
```
这时候我们可以在数据库中添加的节点规则， 格式为： “控制器名称-方法名称”

Auth 类 还可以多个规则一起认证 如： 
```
$auth->check('rule1,rule2',uid); 
```
表示 认证用户只要有rule1的权限或rule2的权限，只要有一个规则的权限，认证返回结果就为true 即认证通过。 默认多个权限的关系是 “or” 关系，也就是说多个权限中，只要有个权限通过则通过。 我们也可以定义为 “and” 关系
```
$auth->check('rule1,rule2',uid,'and'); 
```
第三个参数指定为"and" 表示多个规则以and关系进行认证， 这时候多个规则同时通过认证才有权限。只要一个规则没有权限则就会返回false。

Auth认证，一个用户可以属于多个用户组。 比如我们对 show_button这个规则进行认证， 用户A 同时属于 用户组1 和用户组2 两个用户组 ， 用户组1 没有show_button 规则权限， 但如果用户组2 有show_button 规则权限，则一样会权限认证通过。 
```
$auth->getGroups(uid)
```
通过上面代码，可以获得用户所属的所有用户组，方便我们在网站上面显示。

Auth类还可以按用户属性进行判断权限， 比如
按照用户积分进行判断， 假设我们的用户表 (think_members) 有字段 score 记录了用户积分。 
我在规则表添加规则时，定义规则表的condition 字段，condition字段是规则条件，默认为空 表示没有附加条件，用户组中只有规则 就通过认证。
如果定义了 condition字段，用户组中有规则不一定能通过认证，程序还会判断是否满足附加条件。
比如我们添加几条规则： 

> `name`字段：grade1 `condition`字段：{score}<100 <br/>
> `name`字段：grade2 `condition`字段：{score}>100 and {score}<200<br/>
> `name`字段：grade3 `condition`字段：{score}>200 and {score}<300

这里 `{score}` 表示 `think_members` 表 中字段 `score` 的值。 

那么这时候 

> $auth->check('grade1', uid) 是判断用户积分是不是0-100<br/>
> $auth->check('grade2', uid) 判断用户积分是不是在100-200<br/>
> $auth->check('grade3', uid) 判断用户积分是不是在200-300

