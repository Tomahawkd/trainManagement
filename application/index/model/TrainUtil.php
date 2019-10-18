<?php
/**
 * Created by PhpStorm.
 * User: ghost
 * Date: 2018/9/14
 * Time: 15:44
 */

namespace app\index\model;


class TrainUtil {


    public static $length = 12;
	public static $reject_list = [];

    /**
     *  Get train information from db result map
     *
     * @param array $arr
     * @param $train_id
     *
     * @return mixed|null
     */
	public static function get_by_train(array $arr, $train_id) {
	    foreach ($arr as $item) {
	        if ($item['train_id'] === $train_id) return $item;
        }
        return null;
    }

    /**
     * @param $data array required { train_id, depart, arrival, station_count, seat_count }
     *
     * @return bool
     */
	public static function check_data($data) {
		foreach (['train_id', 'depart', 'arrival', 'station_count', 'seat_count'] as $item) {
			if (!array_key_exists($item, $data)) return false;
		}

		if (!is_int($data['station_count']) || $data['station_count'] <= 0) return false;

		if (!is_int($data['seat_count']) || $data['seat_count'] <= 0) return false;

		return true;
	}
}