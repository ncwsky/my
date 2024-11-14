
**使用参考实例**  
```
<?php
//定义项目路径
define('APP_PATH','./app');

// require 'conf.php'; // 这里可以载入全局配置参数数组 $cfg = array();
// 加载框架入口文件
//require("./myphp/base.php");

myphp::Run();	//运行类的Run的方法
```

**cli示例**   
>脚本参数输入基本同url地址  
```
php index.php m/c/a "b=1&d=1"|b=1 d=1  
php index.php m/c/a?b=1  
php index.php "m/c/a?b=1&d=1"  
```

**模板标签**    
```
#引入文件 
{include:文件名.后缀名}  

#循环数据    
list $retData -> $retData as $key=>$val;
list $retData $custom -> $retData as $k_custom=>$custom
{list $retData}
{/list}

#条件
{if x}{else}{elseif x}{/if}

#标签
~ => php    ~echo $name -> echo $name;
$ => var    $name -> echo $name;
* => echo   *$name -> echo $name;
@ => lang   @name -> echo GetL('name');
# => config #name -> echo Getc('name');
? => isset  ?$v[=$fun][:$defval]
    ?$name -> echo isset($name)?$name:'';
    ?$name:0 -> echo isset($name)?$name:0;
    ?$name=trim:$defval -> echo isset($name)?trim($name):$defval;
 
```