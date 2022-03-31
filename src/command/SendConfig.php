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

use think\console\Command;
use think\console\Input;
use think\console\input\Argument;
use think\console\input\Option;
use think\console\Output;
use think\facade\Env;

class SendConfig extends Command
{

    public function configure()
    {
        $this->setName('auth:config')
             ->setDescription('install auth addons for the framework ,to do list such as send config to config folder, install mysql script into database');
    }

    //  具体执行方法
    public function execute(Input $input, Output $output)
    {
        //  获取默认配置文件安装状态信息
        $configSetStatus = self::execute_config();
        //  获取数据库安装状态信息
        $tableSetStatus = self::execute_sql();
        if($configSetStatus && $tableSetStatus) {
            $output->writeln('install addons succeed!');
        }
        $output->writeln('install addons failure!');
    }
	
	//  写入配置文件
	public static function execute_config(){
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
	public static function execute_sql(){
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
	
	// //	整理SQL语句中的内容
    // private static function sql_split($sql, $prefix)
    // {
    //     //去掉注释 /* */：/\*{1,2}[\s\S]*?\*/    /\*[\s\S]+?\*/   /(\/\*.*\*\/)|(#.*?\n)/s
    //     $sql = preg_replace('/(\/\*.*\*\/)/s', '', $sql);
    //     //删除空白行 ^\s*\n
    //     //$sql=preg_replace('/($\s*$)|(^\s*^)/m','\n',$sql);
    //     //删除 注释 //：//[\s\S]*?\n
    //     //$sql=preg_replace('//[\s\S]*?\n','',$sql);
    //     //替换前缀
    //     //$sql = str_replace("\r", "\n", str_replace(' `'.$this->_table_pre, ' `'.$prefix, $sql));
    //     //$sql=preg_replace('/ `[\S](.*)_/isU',' `'.$prefix,$sql);
    //     //替换 DROP TABLE IF EXISTS
    //     $sql = preg_replace('/DROP TABLE IF EXISTS `([\S]+_)/isU', 'DROP TABLE IF EXISTS `' . $prefix, $sql);
    //     //替换 CREATE TABLE
    //     $sql = preg_replace('/CREATE TABLE `([\S]+_)/isU', 'CREATE TABLE `' . $prefix, $sql);
    //     //替换 INSERT INTO `
    //     $sql = preg_replace('/INSERT INTO `([\S]+_)/isU', 'INSERT INTO `' . $prefix, $sql);

    //     //替换
    //     $sql = preg_replace("/TYPE=(InnoDB|MyISAM|MEMORY)( DEFAULT CHARSET=[^; ]+)?/", "ENGINE=\\1 DEFAULT CHARSET=utf8", $sql);
    //     //替换换行符
    //     $sql = str_replace("\r", "\n", $sql);
    //     //return $sql;
    //     $ret = array();
    //     $num = 0;
    //     $queriesArr = explode(";\n", trim($sql));
    //     // unset($queriesArr['BEGIN']);
    //     //unset($queriesArr['COMMIT']);
    //     unset($sql);
    //     //return $queriesArr;
    //     foreach ($queriesArr as $query) {
    //         $ret[$num] = '';
    //         $queries = explode("\n", trim($query));
    //         $queries = array_filter($queries);
    //         foreach ($queries as $query) {
    //             $str1 = substr($query, 0, 1);
    //             if ($str1 != '#' && $str1 != '-' && $query != 'BEGIN' && $query != 'COMMIT')
    //                 $ret[$num] .= $query;
    //         }
    //         $num++;
    //     }
    //     return $ret;
    // }
}