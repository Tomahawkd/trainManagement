<?php
/**
 * Created by PhpStorm.
 * User: ghost
 * Date: 2018/8/3
 * Time: 10:49
 */

namespace app\index\controller;

use app\index\model\AccountUtil;
use app\index\model\DbTable;
use app\index\model\OrderUtil;
use app\index\model\Status;
use think\Controller;
use think\Db;
use think\exception\DbException;
use think\facade\Validate;


/**
 * Class Order
 *
 *
 * Standard data transfer json {depart_date, train_id, create_date, depart_arrival,
 * passenger: [{ passenger, seat_id, passenger_id, ticket_type }]}
 * @package app\index\controller
 *
 *
 */
class Order extends Controller {

    /**
     * @return string
     *
     * @route('ticket/manage', 'get')
     */
    public function orders_list() {
        return AccountUtil::session(function ($id) {
            return Status::$success .
                json(Db::table(DbTable::$order)->select())->getContent();
        }, true);
    }

    /**
     * @return string|\think\response\Json
     *
     * @route('ticket/list', 'get')
     */
    public function orders_info() {
        return AccountUtil::session(function ($id) {
            try {
                // select * from orders where user_id = $id
                $data = Db::table(DbTable::$order)
                    ->where('user_id', $id)
                    ->select();
                if (!$data) return Status::$not_found;
                for ($i = 0; $i < count($data); $i++) {
                    foreach ($data as $item) {
                        unset($item['user_id']);
                        unset($item['depart_arrival']);
                    }
                }
            } catch (DbException $e) {
                return Status::$internal_error;
            }

            return Status::$success . json($data)->getContent();
        });
    }

    /**
     * <h3> Order information</h3>
     *
     * @param $order_id
     *
     * @return string
     *
     * @route('ticket/id/:order_id', 'get')
     */
    public function order_info($order_id) {

        return AccountUtil::session(function ($id) use ($order_id) {
            try {
                $order_id = intval($order_id);

                // select * from orders where id = $order_id and user_id = $user limit 1
                $data = Db::table(DbTable::$order)
                    ->where('user_id', $id)
                    ->where('order_id', $order_id)
                    ->findOrFail();

                unset($data['user_id']);

                $passenger = Db::table(DbTable::$passenger)
                    ->where('order_id', $data['order_id'])
                    ->select();

                $data['passenger'] = $passenger;

                return Status::$success . json($data)->getContent();
            } catch (DbException $e) {
                return Status::$not_found;
            }
        });
    }

    /**
     * <h3> Order ticket</h3>
     *
     * @param $data string requires
     * { depart_date, train_id, depart_arrival,
     * passenger: [{ passenger, seat_id, passenger_id, ticket_type }] }
     *
     * @return string
     *
     * @route('ticket/order', 'post')
     */
    public function order_ticket($data) {

        return AccountUtil::session(function ($id) use ($data) {
            $data = json_decode($data, true);

            if (array_key_exists('create_date', $data)) unset($data['create_date']);
            if (array_key_exists('order_id', $data)) unset($data['order_id']);
            if (array_key_exists('status', $data)) unset($data['status']);
            if (array_key_exists('price', $data)) unset($data['price']);

            if (!OrderUtil::check_order($data)) return Status::$data_error;

            try {
                $query = [
                    'depart_date' => $data['depart_date'],
                    'train_id' => $data['train_id'],
                    'status' => [0, 1]
                ];

                $depart_arrival_list = Db::table(DbTable::$order)
                    ->where($query)
                    ->column('depart_arrival');

                foreach ($data['passenger'] as $pass) {

                    foreach ($depart_arrival_list as $depart_arrival) {
                        if (($data['depart_arrival'] ^ $depart_arrival) !=
                            ($data['depart_arrival'] | $depart_arrival))
                            return Status::$already_exist . json($pass)->getContent();
                    }
                }

                $order = [
                    'user_id' => $id,
                    'depart_date' => $data['depart_date'],
                    'status' => 0,
                    'train_id' => $data['train_id'],
                    'depart_arrival' => $data['depart_arrival'],
                    'price' => OrderUtil::calculate_price($data)
                ];

                $order_id = Db::table(DbTable::$order)->strict(false)->insertGetId($order);
                $passenger = [];
                foreach ($data['passenger'] as $pass) {
                    $i = [
                        'order_id' => $order_id,
                        'seat_id' => $pass['seat_id'],
                        'passenger' => $pass['passenger'],
                        'passenger_id' => $pass['passenger_id'],
                        'ticket_type' => $pass['ticket_type']
                    ];
                    array_push($passenger, $i);
                }
                $res = Db::table(DbTable::$passenger)->strict(false)->insertAll($passenger);
                if ($res === count($data['passenger'])) return Status::$success . $order_id;
                else return Status::$internal_error;

            } catch (DbException $e) {
                return Status::$internal_error;
            }
        });
    }

    /**
     * <h3> Confirm payment from user</h3>
     *
     * @param $order_id
     *
     * @return string
     *
     * @route('ticket/payment', 'post')
     */
    public function confirm_pay($order_id) {
        return AccountUtil::session(function ($id) use ($order_id) {

            try {

                $order_id = intval($order_id);
                if ($order_id == 0) return Status::$data_error;

                // select * from orders
                // where order_id = $order_id and
                // user_id = $id
                $status = Db::table(DbTable::$order)
                    ->where('order_id', $order_id)
                    ->where('user_id', $id)
                    ->value('status');

                if ($status == -1 || is_null($status)) return Status::$not_found;
                else if ($status === 1) return Status::$already_exist;
                else if ($status === 0) {
                    Db::table(DbTable::$order)
                        ->where('order_id', $order_id)
                        ->where('user_id', $id)
                        ->update(['status' => 1]);

                    return Status::$success;
                }

                return Status::$internal_error;

            } catch (DbException $e) {
                return Status::$internal_error;
            }
        });
    }

    /**
     * <h3> Change travel date</h3>
     *
     * @param $order_id int id
     * @param $date
     *
     * @return string
     *
     * @route('ticket/change', 'post')
     */
    public function change_travel($order_id, $date) {

        return AccountUtil::session(function ($id) use ($order_id, $date) {

            $order_id = intval($order_id);

            if (!(Validate::isDate($date) && 2 === preg_match_all('/-/', $date)))
                return Status::$data_error;

            try {
                Db::table(DbTable::$order)
                    ->where('order_id', $order_id)
                    ->where('user_id', $id)->findOrFail();

                // select * from orders
                // where order_id = $order_id and
                // user_id = $id
                $res = Db::table(DbTable::$order)
                    ->where('order_id', $order_id)
                    ->where('user_id', $id)
                    ->update(['depart_date' => $date]);

                if ($res === 1) return Status::$success;
                else return Status::$internal_error;
            } catch (DbException $e) {
                return Status::$not_found;
            }
        });
    }

    /**
     * <h3> Cancel order</h3>
     *
     * @param $order_id
     *
     * @return string
     *
     * @route('ticket/refund', 'post')
     */
    public function delete_order($order_id) {

        return AccountUtil::session(function ($id) use ($order_id) {

            try {
                // select * from orders
                // where order_id = $order_id and
                // user_id = $id
                $order = Db::table(DbTable::$order)
                    ->where('order_id', $order_id)
                    ->where('user_id', $id)
                    ->where('depart_date > '. date("Y-m-d"))
                    ->value('order_id');

                if (is_null($order)) return Status::$not_found;
                // not trust the framework, so using data in database
                // although it will decrease the process speed
                $res = Db::table(DbTable::$order)
                    ->where('order_id', $order)
                    ->where('user_id', $id)
                    ->update(['status' => -1]);

                if ($res === 1) return Status::$success;
                else return Status::$internal_error;
            } catch (DbException $e) {
                return Status::$not_found;
            }
        });
    }


    /**
     * <h3> Get remaining ticket</h3>
     *
     * @param $train string train_id
     * @param $date
     * @param $trip int depart_arrival
     *
     * @return string
     *
     * @route('ticket/remain', 'post')
     */
    public function remaining_ticket($train, $date, $trip) {

        try {

            $trip = intval($trip);

            $result = Db::table(DbTable::$train)
                ->where('train_id', $train)
                ->field('seat_count, station_count')
                ->findOrFail();

            if (!OrderUtil::check_depart_arrival($trip, $result['station_count']))
                return Status::$data_error;

            if (!(Validate::isDate($date) &&
                2 === preg_match_all('/-/', $date)))
                return Status::$data_error;

            $order = Db::table(DbTable::$order)
                ->where('train_id', $train)
                ->where('depart_date', $date)
                ->whereIn('status', [0, 1])
                ->select('order_id, depart_arrival');

            $list = [];
            foreach ($order as $item) {
                if (($item['depart_arrival'] ^ $trip) ===
                    ($item['depart_arrival'] | $trip))
                    array_push($list, $item['order_id']);
            }

            $first_class_taken = Db::table(DbTable::$passenger)
                ->whereIn('order_id', $order)
                ->where('seat_id', '<=', OrderUtil::$first_class_max)
                ->value('count(*) t');

            $first = OrderUtil::$first_class_max - $first_class_taken;

            $second_class_taken = Db::table(DbTable::$passenger)
                ->whereIn('order_id', $order)
                ->where('seat_id', '>', OrderUtil::$first_class_max)
                ->value('count(*) t');

            $second = $result['seat_count'] -
                OrderUtil::$first_class_max -
                $second_class_taken;

            return Status::$success . json(array($first, $second))->getContent();

        } catch (DbException $e) {
            return Status::$not_found;
        }
    }
}