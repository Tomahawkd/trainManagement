<?php
/**
 * Created by PhpStorm.
 * User: ghost
 * Date: 2018/7/24
 * Time: 00:39
 */

namespace app\index\controller;

use app\index\model\AccountUtil;
use app\index\model\DbTable;
use app\index\model\Status;
use think\Controller;
use think\Db;
use think\exception\DbException;
use think\facade\Session;

class Account extends Controller {

    /**
     * <h3>Administration Account Control</h3>
     *
     * @return string
     *
     * @route('account/manage', 'get')
     */
    public function account_list() {
        return AccountUtil::session(function ($id) {
            return Status::$success . json(Db::table(DbTable::$user)
                    ->field('id, user_id, name'))->getContent();
        }, true);
    }

    /**
     * <h3>Account login control</h3>
     *
     * @param $username string username
     * @param $password string md5 of the password
     *
     * @return string status
     *
     * @route('account/login', 'post')
     */
    public function account_login($username, $password) {

        $password .= AccountUtil::$salt;

        // select id, role from users where user_id = $name and passwd = $passwd
        $id = Db::table(DbTable::$user)
            ->where('user_id', $username)
            ->where('passwd', md5($password))
            ->value('id');

        if ($id != null) {
            Session::set('user_id', $id);

            $role = Db::table(DbTable::$user)
                ->where('id', $id)
                ->value('role');
            return Status::$success . "$role";
        } else {
            return Status::$not_found;
        }
    }


    /**
     * <h3>Acquire account info</h3>
     *
     * @return \think\response\Json|string data|status
     *
     * @route('account/info', 'get')
     */
    public function account_info() {
        return AccountUtil::session(function ($id) {
            try {
                // select * from info where id = $id limit 1
                $data = Db::table(DbTable::$user)
                    ->where('id', $id)->find();
                if (!$data) return Status::$not_found;

                $data = AccountUtil::clean_up_data($data);
            } catch (DbException $e) {
                return Status::$internal_error;
            }

            return Status::$success . json($data)->getContent();
        });
    }

    /**
     * <h3>User profile edit</h3>
     *
     * New profile need all data exclude user id
     * {name, sex, birthday, id_number, phone, ticket_type}
     *
     * Existing profile only needs data which will be edited
     *
     * @param $data string json string
     *
     * @return string status
     *
     * @route('account/edit', 'post')
     */
    public function edit_profile($data) {
        return AccountUtil::session(function ($id) use ($data) {

            $data = AccountUtil::clean_up_data(
                json_decode($data, true));
            unset($data['user_id']);

            try {
                // check
                if (!AccountUtil::check_data($data)) return Status::$data_error;

                // insert into users_information values ($original)
                $res = Db::table(DbTable::$user)
                    ->where('id', $id)
                    ->update($data);

                if ($res === 1) return Status::$success;
                else if ($res === 0) return Status::$already_exist;
                else return Status::$internal_error;
            } catch (DbException $e) {
                return Status::$internal_error;
            }
        });
    }

    /**
     * <h3>Change account password</h3>
     *
     * @param $old string old password
     *
     * @param $new string md5 password string
     *
     * @return string status
     *
     * @route('account/passwd', 'post')
     */
    public function change_password($old, $new) {
        return AccountUtil::session(function ($id) use ($old, $new) {

            if (strlen($new) == 32) {
                $new .= AccountUtil::$salt;
                $old .= AccountUtil::$salt;
                try {
                    $res = Db::table(DbTable::$user)
                        ->where('id', $id)
                        ->where('passwd', md5($old))
                        ->update(['passwd' => md5($new)]);
                    if ($res === 1) return Status::$success;
                    else return Status::$not_found;
                } catch (DbException $e) {
                    return Status::$internal_error;
                }
            } else return Status::$data_error;
        });
    }

    /**
     * <h3>Account registration</h3>
     *
     * @param $username string username
     * @param $password string md5 password string
     *
     * @return string status
     *
     * @route('account/register', 'post')
     */
    public function account_register($username, $password) {
        try {
            $password .= AccountUtil::$salt;

            // select id from users where user_id = $username
            $id = Db::table(DbTable::$user)
                ->where('user_id', $username)
                ->value('id');

            if ($id != null) return Status::$already_exist;

            $data = ['user_id' => $username,
                'passwd' => md5($password)];

            // insert into users values ($data)
            $id = Db::table(DbTable::$user)->insertGetId($data);

            $id = intval($id);
            if ($id !== 0) {
                Session::set('user_id', $id);
                return Status::$success;
            } else return Status::$internal_error;

        } catch (DbException $e) {
            return Status::$internal_error;
        }
    }

    /**
     * <h3>Account logout</h3>
     *
     * @return string status
     *
     * @route('account/logout', 'get')
     */
    public function logout() {
        Session::delete('user_id');
        return Status::$success;
    }
}
