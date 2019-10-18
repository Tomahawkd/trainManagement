<?php
/**
 * Created by PhpStorm.
 * User: ghost
 * Date: 2018/9/14
 * Time: 16:01
 */

namespace app\index\model;


use think\Db;
use think\exception\DbException;

class OrderUtil {

    public static $first_class_max = 20;

    /**
     * @param $data array requires
     * { depart_date, train_id, depart_arrival, passenger: [{ seat_id, passenger_id, ticket_type }] }
     *
     * @return bool
     */
    public static function check_order($data) {
        foreach (['depart_date', 'train_id', 'depart_arrival', 'passenger'] as $item) {
            if (!array_key_exists($item, $data)) return false;
        }

        try {
            $result = Db::table(DbTable::$train)
                ->where('train_id', $data['train_id'])
                ->field('train_id, seat_count, station_count')
                ->findOrFail();

            if (!OrderUtil::check_depart_arrival($data['depart_arrival'], $result['station_count'])) return false;

            if (!is_array($data['passenger'])) return false;
            if (count($data['passenger']) <= 0) return false;
            foreach ($data['passenger'] as $passenger) {
                if (!OrderUtil::check_passenger($passenger, $result['seat_count'])) return false;
            }
            return true;
        } catch (DbException $e) {
            return false;
        }
    }

    public static function generate_depart_arrival($station_count, $start, $end) {
        $basis = 0;
        for ($i = 0; $i < $station_count; $i++) {
            if ($i >= $start && $i <= $end) {
                $basis += 1;
            }
            $basis <<= 1;
        }
        return $basis;
    }

    /**
     * @param $passenger array requires { seat_id, passenger_id, ticket_type }
     *
     * @param $seat_count int
     *
     * @return bool
     */
    private static function check_passenger($passenger, $seat_count) {
        foreach (['passenger', 'seat_id', 'passenger_id', 'ticket_type'] as $item) {
            if (!array_key_exists($item, $passenger)) return false;
        }

        if ($seat_count < $passenger['seat_id']) return false;
        if (strlen($passenger['passenger_id']) !== 18) return false;
        if (intval($passenger['passenger_id']) == 0) return false;

        return true;
    }

    /**
     * @param $seat_id int
     *
     * @return string result
     */
    public static function get_seat_info($seat_id) {
        $seat_id -= 1;
        if (is_int($seat_id)) {
            $cabin_id = $seat_id / 80 + 1;
            $line_id = ($seat_id % 80) / 5 + 1;
            $divider = 5;
            if ($seat_id <= OrderUtil::$first_class_max)
                $divider = 4;
            $seat_num = ($seat_id % 80) % $divider + 1;
            return intval($cabin_id) . '车' .
                intval($line_id) . '排' .
                chr(64 + intval($seat_num)) . '座';
        }
        return '0车0排0座';
    }

    /**
     * @param $station_count int
     * @param $depart_arrival int train travel
     *
     * @return array|null
     */
    public static function get_station_info($station_count, $depart_arrival) {

        if (!OrderUtil::check_depart_arrival($depart_arrival, $station_count)) return null;

        $arrival_index = 0;
        $depart_index = 1;
        $status = 1;
        for ($index = $station_count; $index > 0; $index--) {
            if ($depart_arrival & $status) {
                $arrival_index = $index;
                $status = 0;

            } else if (!($depart_arrival | $status)) {
                $depart_index = $index + 1;
                break;

            }
            $depart_arrival >>= 1;
        }

        if ($arrival_index < $depart_index) return null;
        else return [$depart_index, $arrival_index];
    }

    public static function check_depart_arrival($data, $station_count) {

        $check_data = $data;

        if ($check_data == 0 || (($check_data & ($check_data - 1)) == 0))
            return false;

        if ($check_data > pow(2, $station_count + 1))
            return false;

        for ($index = $station_count; $index > 0; $index--) {
            if ($check_data & 1) {
                $temp = $check_data + 1;
                if (($temp & ($temp - 1)) !== 0) return false;
            }
            $check_data >>= 1;
        }
        return true;
    }

    /**
     * @param $order array requires { depart_date, train_id, depart_arrival, price, passenger: [{ seat_id, passenger_id, ticket_type }] }
     *
     * @return int
     */
    public static function calculate_price($order) {

        try {

            $station_count = Db::table(DbTable::$train)
                ->where('train_id', $order['train_id'])
                ->value('station_count');

            $index = OrderUtil::get_station_info($station_count, $order['depart_arrival']);

            $basis = ($index[1] - $index[0]) * 25 + 100;
            $amount = 0;

            foreach ($order['passenger'] as $passenger) {
                $single = $basis;
                if ($passenger['seat_id'] < OrderUtil::$first_class_max)
                    $single *= 125;
                if ($passenger['ticket_type'] === 1) $basis *= 60;
                $single /= 100;

                $amount += $single;
            }

            return $amount;
        } catch (DbException $e) {
            return 0;
        }
    }
}