<?php
/**
 * Created by PhpStorm.
 * User: ghost
 * Date: 2018/9/14
 * Time: 15:58
 */

namespace app\index\model;


use think\Db;
use think\Exception;
use think\facade\Session;
use think\facade\Validate;

class AccountUtil {

    public static $salt = 'QWERTY';

    /**
     * <h3>Account control</h3>
     *
     * @param callable $callback function
     * @param bool $restricted admin controlling area
     *
     * @return string
     */
    public static function session(callable $callback, $restricted = false) {

        // check login
        if (Session::has('user_id')) {

            $id = Session::get('user_id');
            if (is_int($id)) {
                if ($restricted === true) {
                    $role = Db::table(DbTable::$user)
                        ->where('id', $id)
                        ->value('role');
                    if ($role !== 1) {
                        Session::delete('user_id');
                        return Status::$not_login;
                    }
                }
                try {
                    return $callback($id);
                } catch (Exception $e) {
                    return Status::$internal_error;
                }
            } else return Status::$internal_error;
        } else return Status::$not_login;
    }

    /**
     * <h3>Check data complement</h3>
     *
     * @param array $data [id, name, sex, birthday,
     * id_type, id_number, phone, ticket_type]
     *
     * @return bool
     */
    public static function check_data(array $data) {

        foreach (['name', 'sex', 'birthday', 'id_number', 'phone', 'ticket_type'] as $item) {
            if (!array_key_exists($item, $data)) return false;
        }

        if (strlen($data['id_number']) !== 18) return false;
        if (intval($data['id_number']) == 0) return false;
        if (strlen($data['phone']) !== 11) return false;
        if (intval($data['phone']) == 0) return false;
        if (abs($data['sex'] - 1) > 1) return false;

        if (!(Validate::isDate($data['birthday']) &&
            2 === preg_match_all('/-/', $data['birthday'])))
            return false;

        return true;
    }

    /**
     * <h3>Clean up restricted data</h3>
     *
     * @param array $data user data
     *
     * @return array cleaned data
     */
    public static function clean_up_data(array $data) {
        foreach (['id', 'passwd', 'role'] as $item) {
            if (array_key_exists($item, $data)) unset($data[$item]);
        }
        return $data;
    }
}