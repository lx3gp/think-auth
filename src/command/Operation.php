<?php
declare (strict_types = 1);
/**
 * +----------------------------------------------------------------------
 * | think-auth [thinkphp6]
 * +----------------------------------------------------------------------
 * | FILE: SendConfig.php
 * | AUTHOR: DreamLee
 * | EMAIL: 1755773846@qq.com
 * | QQ: 1755773846
 * | DATETIME: 2022/03/07 14:47
 * |-----------------------------------------
 * | 不积跬步,无以至千里；不积小流，无以成江海！
 * +----------------------------------------------------------------------
 * | Copyright (c) 2022 DreamLee All rights reserved.
 * +----------------------------------------------------------------------
 */
namespace think\command;

use Composer\Script\Event;

class Operation
{
    //  资源包在被安装之后触发的方法，本包的安装命令：composer require lx3gp/think-auth
    public static function postPackageInstall(Event $event)
    {
        //  $installedPackage = $event->getOperation()->getPackage();
        //  do stuff
        //  获取默认配置文件安装状态信息
        $configSetStatus = self::postPackageInstallExecuteConfig();
        //  获取数据库安装状态信息
        $tableSetStatus = self::postPackageInstallExecuteSql();
        if($configSetStatus && $tableSetStatus) {
            $event->getIO('install addons succeed!');
        }
        $event->getIO('install addons failure!');
    }

    //  资源包在被更新之后触发的方法，本包的更新命令：composer update lx3gp/think-auth
    public static function postPackageUpdate(Event $event)
    {
        //  $installedPackage = $event->getOperation()->getPackage();
        //  do stuff
        //  获取默认配置文件安装状态信息
        // $configSetStatus = self::postPackageInstallExecuteConfig();
        //  获取数据库安装状态信息
        // $tableSetStatus = self::postPackageInstallExecuteSql();
        // if($configSetStatus && $tableSetStatus) {
        //     $event->getIO('install addons succeed!');
        // }
        // $event->getIO('install addons failure!');
    }

    //  资源包在被卸载之前触发的方法-一定要使用命令卸载，本包的卸载命令：composer remove lx3gp/think-auth
    public static function prePackageUninstall(Event $event){
        //  $composer = $event->getComposer();
        //  do stuff

        //  配置文件移除状态信息
        $configUninstallStatus = self::prePackageUninstallRemoveConfig();
        //  数据库卸载状态信息
        $tableUninstallStatus = self::prePackageUninstallRemoveTable();
        if($configUninstallStatus && $tableUninstallStatus) {
            $event->getIO('uninstall addons succeed!');
        }
        $event->getIO('uninstall addons failure!');

    }

    //  资源包在被卸载之后触发的方法-一定要使用命令卸载，本包的卸载命令：composer remove lx3gp/think-auth
    public static function postPackageUninstall(Event $event){
        //  $composer = $event->getComposer();
        //  do stuff
    }




	//  写入配置文件
	private static function postPackageInstallExecuteConfig(){
        //  获取默认配置文件
        $content = file_get_contents(root_path() . 'vendor/lx3gp/think-auth/src/config/auth.php');
        $configPath = config_path() . '/';
        $configFile = $configPath . 'auth.php';
        //  判断目录是否存在
        if (!file_exists($configPath)) {
            mkdir($configPath, 0755, true);
        }
        //  判断文件是否存在
        if (is_file($configFile)) {
            unlink($configFile);
        }
        //  判断是否写入
        if (false === file_put_contents($configFile, $content)) {
            return false;
        }
        return true;
	}

	//  写入数据表
	private static function postPackageInstallExecuteSql(){
        $sqlFilePath = root_path() . 'vendor/lx3gp/think-auth/src/data/install.sql';
		$config = config('database.connections.'.config('database.default'));
		/*** 执行SQL语句***/
        if(file_exists($sqlFilePath)){
            $sql_content = file_get_contents($sqlFilePath);
            $sql_content = array_values(array_filter(explode(';', $sql_content)));
            $sql_status = true;
            foreach ($sql_content as $sql) {
                $_step_status = true;
                try{
                    $sql = preg_replace('/__PREFIX__/isU', $config['prefix'], $sql) . ';';               
                    if(\think\facade\Db::execute($sql) !== false) {
                        $_step_status = true;
                    }                    
                }catch(\Exception $e){
                    $_step_status = false;
                    return false;
                }finally{
                    $sql_status = $_step_status && $sql_status;                    
                }
            }
            //  判断是否安装失败
            if( $sql_status === false ) {
                return false;
            }
        }
		//创建成功
        return true;
	}




    //  卸载资源包  -   移除配置文件
    private static function prePackageUninstallRemoveConfig(){
        //  获取配置文件位置
        $configPath = config_path() . 'auth.php';
        //  判断目录是否存在
        if (is_file($configPath) && !file_exists($configPath)) {
            if(false === unlink($configPath))
            {
                return false;
            }
        }
        return true;
    }

    //  卸载资源包  -   移除数据库中指定的数据表
    private static function prePackageUninstallRemoveTable(){
        //  获取系统的配置信息
        $config = config('database.connections.'.config('database.default'));
        //  获取配置文件位置
        $tableList = [
            $config['prefix'].'auth_group',
            $config['prefix'].'auth_group_access',
            $config['prefix'].'auth_rule',
            $config['prefix'].'user',
        ];
        //  删除数据表
        try{
            if(!empty($tableList)) {
                $sql = "DROP TABLE IF EXISTS ";
                foreach ($tableList as $table) {
                    $sql .= "`{$table}`,";
                }
                $sql = substr($sql, 0, -1) . ";";
                $sql_status = \think\facade\Db::execute($sql);
                if($sql_status === false) {
                    return false;
                }
            }
        }catch(\Exception $e){
            return false;
        }
        return true;
    }
}