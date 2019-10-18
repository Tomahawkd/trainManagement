<?php
/**
 * Created by PhpStorm.
 * User: ghost
 * Date: 2018/9/12
 * Time: 17:50
 */

namespace app\index\model;


class Status {

    // Errors
    public static $data_error = '402';
    public static $not_found = '404';
    public static $not_login = '403';
    public static $already_exist = '405';
    public static $internal_error = '501';

    // Success
    public static $success = '200';
}