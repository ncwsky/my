**创建项目**    
> composer create-project myphps/my  

**运行**  
```
创建应用  
php my --run=应用目录名 --init 

初始表Model  
php cli.php Model 1 "common\model" "\common\CommonModel" 

常驻内存运行  
php app.php  
或  
php app.php -n进程数 -p端口 [-s Swoole方式运行] 
```

**composer**  
```
二维码
composer require endroid/qr-code[:4.3.5]

JWT
composer require firebase/php-jwt[:^6.4]

Excel
composer require box/spout
composer require myphps/phpexcel

微信
composer require overtrue/wechat:~5.0
composer require symfony/cache-contracts:2.5.0
```
