<?php

declare(strict_types=1);

namespace common;

/**
 * 通用model基类
 */
class CommonModel extends \myphp\Model
{
    //public static $resetOption = false; //sql组合项执行后是否重置
    //public static $resetWhere = false; //使用->where是否重置之前的条件

    //状态
    public const STATUS_OK = 1;
    public const STATUS_NO = 0;

    /**
     * 通过时间查询最大ID
     * @param string $timeColumn 时间字段名
     * @param int $targetTime 时间条件
     * @param bool $timestamp 是时间戳?
     * @param string $priKey 主键字段名
     * @return int
     */
    public static function findMaxIdByTime(string $timeColumn, int $targetTime, bool $timestamp = false, string $priKey = 'id')
    {
        // 获取最小和最大ID
        $ids = static::fields('MIN(' . $priKey . ') AS min_id, MAX(' . $priKey . ') AS max_id')->one();
        if (empty($ids['min_id'])) {
            return 0;
        }
        //echo toJson($ids), PHP_EOL;
        $low = (int)$ids['min_id'];
        $high = (int)$ids['max_id'];
        if ($low == $high) { //仅一条记录
            $row = static::fields($timeColumn)->where($priKey . '=' . $low)->one();
            $currentTime = $timestamp ? $row[$timeColumn] : strtotime($row[$timeColumn]);
            if ($currentTime <= $targetTime) {
                return $low;
            }
            return 0;
        }
        $result = 0;
        //$times = 0;
        // 二分查找
        while ($low < $high) {
            //$times++;
            $mid = (int)(($low + $high) / 2); //取中间值

            // 获取中间ID的时间
            $row = static::fields([$priKey, $timeColumn])->where($priKey . '<=' . $mid)->order($priKey . ' desc')->one();
            //echo $times . ': L' . $low . ' - H' . $high . ' -  - qID' . $mid . "\n";
            if (!$row) {
                // 处理ID不存在的情况：向左收缩范围
                $high = $mid - 1;
                continue;
            }
            //echo 'MID:' . $mid . ' - ' . toJson($row) . "\n";
            $currentTime = $timestamp ? $row[$timeColumn] : strtotime($row[$timeColumn]);
            if ($currentTime <= $targetTime) {
                $result = $mid;   // 当前ID符合条件，记录为候选
                $low = $mid + 1;  // 尝试更大的ID
            } else {
                $high = $mid - 1; // 缩小上限
            }
        }
        return $result;
    }
}