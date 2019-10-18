<?php
/**
 * Created by PhpStorm.
 * User: ghost
 * Date: 2018/8/6
 * Time: 20:20
 */

namespace app\index\controller;


use app\index\model\AccountUtil;
use app\index\model\DbTable;
use app\index\model\Status;
use app\index\model\TrainUtil;
use think\Controller;
use think\Db;
use think\exception\DbException;
use think\response\Json;

class Train extends Controller {

    /**
     * <h3> Amount of pages for all train's display</h3>
     *
     * @return string
     *
     * @route('train/pages', 'get')
     */
    public function get_pages() {
        return AccountUtil::session(function ($id) {
            return Status::$success .
                (intval(intval(Db::table(DbTable::$train)->count())/TrainUtil::$length) + 1);
        }, true);
    }

    /**
     * <h3> All trains' information divided by pages</h3>
     *
     * @see get_pages
     *
     * @param $page int list page
     *
     * @return string|\think\response\Json
     *
     * @route('train/info/:page', 'get')
     */
    public function trains_info($page) {
        return AccountUtil::session(function ($id) use ($page) {
            try {

                $page = intval($page);
                if ($page == 0) return Status::$data_error;

                // select * from train limit page*length, length
                return Status::$success .
                    json(Db::table(DbTable::$train)
                        ->limit($page * TrainUtil::$length, TrainUtil::$length)
                        ->select())->getContent();
            } catch (DbException $e) {
                return Status::$internal_error;
            }
        }, true);
    }

    /**
     * <h3> Getting stations the specific train passed by </h3>
     *
     * @param $id
     *
     * @return string|\think\response\Json
     *
     * @route('train/id/:id', 'get')
     */
    public function train_info($id) {
        try {
            // select * from train_station
            // where train_id = $id order by station_order
            $data = Db::table(DbTable::$station)
                ->where('train_id', $id)
                ->order("station_order")->select();
            if (!$data) return Status::$not_found;

        } catch (DbException $e) {
            return Status::$internal_error;
        }
        return Status::$success . json($data)->getContent();
    }

    /**
     * <h3>Train properties edit</h3>
     *
     * New profile need all data
     * {train_id, depart, arrival, station_count, seat_count}
     *
     * Existing profile only needs profile to be edited
     *
     * Notice that this could not edit train_id
     * because it's a foreign key of train_station
     * and the update rule is not cascade,
     * to edit it you need go to database for edition
     *
     * @param $data
     *
     * @return string
     *
     * @route('train/edit', 'post')
     *
     * @deprecated using crawler for adding data
     */

    public function edit_train($data) {
        return AccountUtil::session(function ($id) use ($data) {
            $data = json_decode($data, true);

            // select * from train where train_id = $id
            $original = Db::table(DbTable::$train)
                ->where('train_id', $data['train_id'])->find();

            try {
                if ($original === null) {

                    // update new data
                    if (!TrainUtil::check_data($data)) return Status::$data_error;

                    // insert into train values ($data)
                    Db::table(DbTable::$train)->strict(false)
                        ->insert($data);
                } else {

                    // existing data
                    foreach ($original as $k => $v) {
                        if ($k === 'train_id') continue;
                        if (array_key_exists($k, $data)) $original[$k] = $data[$k];
                    }

                    // check
                    if (!TrainUtil::check_data($original)) return Status::$data_error;

                    // insert into train values ($original)
                    Db::table(DbTable::$train)->strict(false)
                        ->insert($original, true);
                }
            } catch (DbException $e) {
                return Status::$internal_error;
            }
            return Status::$success;
        }, true);
    }

    /**
     * <h3> Train station edit</h3>
     *
     * @param $train
     * @param $data
     *
     * @return string
     *
     * @route('train/station/edit', 'post')
     *
     * @deprecated using crawler for adding data
     */
    public function edit_train_info($train, $data) {
        return AccountUtil::session(function ($id) use ($train, $data) {
            $data = json_decode($data, true);

            $num = count($data);

            $train_data = Db::table(DbTable::$train)
                ->where('train_id', $train)->find();

            if ($train_data == null) return Status::$not_found;

            if ($train_data['station_count'] !== $num) return Status::$data_error;

            for ($index = 0; $index < $num; $index++) {
                if ($data[$index]['station_order'] === 1) {
                    $data[$index]['arrival_time'] = null;
                    if ($train_data['depart'] !== $data[$index]['station']) return Status::$data_error;
                }

                if ($data[$index]['station_order'] === $num) {
                    $data[$index]['depart_time'] = null;
                    if ($train_data['arrival'] !== $data[$index]['station']) return Status::$data_error;
                }
            }

            for ($i = 0; $i < $num; $i++) {
                $data[$i]['train_id'] = $train;
            }

            Db::table(DbTable::$station)->strict(false)->insertAll($data, false, $num);

            return Status::$success;

        }, true);
    }

    /**
     * <h3> Search trip for traveling</h3>
     *
     * @param $start
     * @param $end
     *
     * @return Json|string { title, trip(time list) }
     *
     * @route('train/routine/:start/:end', 'get')
     */
    public function get_trip($start, $end) {

        TrainUtil::$reject_list = [];
        $result = [];

        // instant line to the destination
        try {
            //select s.train_id from
            //    (select train_id, station_order start
            //      from train_station where station like '%北京%') as s
            //    inner join
            //    (select train_id, station_order end
            //      from train_station where station like '%西安%') as e
            //on s.train_id = e.train_id
            //where start < end

            $startSubSql = Db::table(DbTable::$station)
                ->where('station', 'like', "%$start%")
                ->field('train_id, station_order start, station st')->buildSql();

            $endSubSql = Db::table(DbTable::$station)
                ->where('station', 'like', "%$end%")
                ->field('train_id, station_order end, station ed')->buildSql();

            $straight_info = Db::table("$startSubSql s")
                ->join("$endSubSql e", 's.train_id = e.train_id')
                ->where('start - end < 0')
                ->field('s.train_id, st, ed, start, end')->select();

            foreach ($straight_info as $train) {
                $title = $train['st'] . '-' . $train['train_id'] . '-' . $train['ed'];
                $trip = Db::table(DbTable::$station)
                    ->where('train_id', $train['train_id'])
                    ->whereBetween('station_order', [$train['start'], $train['end']])
                    ->select();
                array_push(TrainUtil::$reject_list, $train['train_id']);
                array_push($result, ['title' => $title, 'trip' => $trip]);
            }

        } catch (DbException $e) {
            return Status::$not_found;
        }

        if (count($result) > 3) return Status::$success . json($result)->getContent();

        // need transfer
        try {
            // select train_id, station_order, station
            // from train_station where station like %name%
            $start_info = Db::table(DbTable::$station)
                ->where('station', 'like', "%$start%")
                ->whereNotIn('train_id', TrainUtil::$reject_list)
                ->field('train_id, station_order, station')->select();

            $end_info = Db::table(DbTable::$station)
                ->where('station', 'like', "%$end%")
                ->whereNotIn('train_id', TrainUtil::$reject_list)
                ->field('train_id, station_order, station')->select();

            $data = [];

            foreach ($start_info as $start_train) {
                foreach ($end_info as $end_train) {

                    $start_sql = Db::table(DbTable::$cross)
                        ->where('train_id', $start_train['train_id'])
                        ->where('station_order > ' . $start_train['station_order'])
                        ->buildSql();

                    $end_sql = Db::table(DbTable::$cross)
                        ->where('train_id', $end_train['train_id'])
                        ->where('station_order < ' . $end_train['station_order'])
                        ->buildSql();

                    $res = Db::table("$start_sql s, $end_sql e")
                        ->where('s.station = e.station')
                        ->where('s.arrival < e.depart')
                        ->field('s.train_id start, s.station, e.train_id end, 
                        s.station_order sorder, e.station_order eorder')
                        ->order('s.station_order')
                        ->find();

                    if (count($res) > 0) array_push($data, $res);
                }
            }
            if (count($data) <= 0) return Status::$not_found;

            // Parse data
            foreach ($data as $list) {

                $start_train_info = TrainUtil::get_by_train($start_info, $list['start']);
                $end_train_info = TrainUtil::get_by_train($end_info, $list['end']);

                if ($start_train_info != null && $end_train_info != null) {
                    $trip1 = Db::table(DbTable::$station)
                        ->where('train_id', $start_train_info['train_id'])
                        ->whereBetween('station_order', [$start_train_info['station_order'], $list['sorder']])
                        ->select();

                    $trip2 = Db::table(DbTable::$station)
                        ->where('train_id', $end_train_info['train_id'])
                        ->whereBetween('station_order', [($list['eorder'] + 1), $end_train_info['station_order']])
                        ->select();

                    $trip = array_merge($trip1, $trip2);

                    $title = $start_train_info['station'] . '-' .
                        $list['start'] . '-' . $list['station'] . '-' . $list['end'] . '-' .
                        $end_train_info['station'];

                    array_push($result, ['title' => $title, 'trip' => $trip]);
                }
            }

            return Status::$success . json($result)->getContent();
        } catch (DbException $e) {
            if (count($result) !== 0) return Status::$success . json($result)->getContent();
            else return Status::$not_found;
        }
    }


    //////////////////////////////////////////////////////////
    ///                                                    ///
    ///    The next code is for the crawler to add data    ///
    ///                                                    ///
    //////////////////////////////////////////////////////////


    /**
     * @param $passwd
     * @param $head
     * @param $content
     *
     * @return string
     *
     * @route('edit/train', 'post')
     */
    public function set_train($passwd, $head, $content) {

        if ($passwd !== 'Tomahawk') return 'not login';

        $head = json_decode($head, true);
        $content = json_decode($content, true);
        $num = count($content);

        $train = $head['train_id'];
        try {
            // select * from train where train_id = $id
            $original = Db::table(DbTable::$train)
                ->where('train_id', $head['train_id'])->find();

            if ($original === null) {

                // update new data
                if (!TrainUtil::check_data($head)) return Status::$data_error;

                // insert into train values ($data)
                Db::table(DbTable::$train)->strict(false)
                    ->insert($head);
            } else return Status::$success;

            $train_data = Db::table(DbTable::$train)->where('train_id', $train)->find();

            if ($train_data == null) return Status::$not_found;
            if ($train_data['station_count'] !== $num) return Status::$data_error;

            for ($index = 0; $index < $num; $index++) {
                if ($content[$index]['station_order'] === 1) {
                    $content[$index]['arrival_time'] = null;
                    if ($train_data['depart'] !== $content[$index]['station']) return Status::$data_error;
                }

                if ($content[$index]['station_order'] === $num) {
                    $content[$index]['depart_time'] = null;
                    if ($train_data['arrival'] !== $content[$index]['station']) return Status::$data_error;
                }
            }

            for ($i = 0; $i < $num; $i++) {
                $content[$i]['train_id'] = $train;
            }

            Db::table(DbTable::$station)->strict(false)
                ->insertAll($content, false, $num);


        } catch (DbException $e) {
            return Status::$internal_error;
        }
        return Status::$success;
    }
}

