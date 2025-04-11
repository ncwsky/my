#!/bin/bash

# php cli.php operate:xxx step:间隔 limit:限制次数

#执行队列任务
#* * * * * cd pwd && /usr/bin/sh ./queue.sh Queue 2

if [ -n "$2" ]; then
    step=$2
else
    step=2
fi

if [ -n "$3" ]; then
    limit=$3
else
    limit=5
fi

if [ ! -n "$1" ]; then
    echo "usage：$0 <operate> <step:2> <limit:5>"
    exit 1
fi

# cli.php xxx 进程同时最多只允许x个存在
count=`ps -fe |grep "cli.php $1" -c`
echo "$0 $1 <step:$step> <limit:$limit>  count:$count"
if [ $count -gt $limit ]; then
    echo "cli.php $1 is full[$count]"
    exit 1
fi

for ((i=0; i<60;i=(i+step))); do
    php cli.php $1
    sleep $step
done
exit 0
