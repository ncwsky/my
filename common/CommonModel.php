<?php
namespace common;

/**
 * 通用model基类
 */
class CommonModel extends \myphp\Model {
    //public static $resetOption = false; //sql组合项执行后是否重置
    //public static $resetWhere = false; //使用->where是否重置之前的条件

    //状态
    const STATUS_OK = 1;
    const STATUS_NO = 0;
}