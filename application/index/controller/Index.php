<?php

namespace app\index\controller;

use app\index\model\Status;
use think\Controller;


class Index extends Controller {

    /**
     * @return string
     *
     * @route('index')
     */
    public function index() {
        return 'nothing here';
    }

    /**
     * @return string
     *
     * @route('connectivity')
     */
    public function connectivity_test() {

        return Status::$success;
    }
}
