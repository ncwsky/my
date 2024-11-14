<?php
//windows 测试使用
while(true){
	#echo shell_exec('php cli.php ClearTmp'), PHP_EOL;
	echo shell_exec('php cli.php Queue'),PHP_EOL;
	#sleep(60);
    sleep(5);
}