<?php

namespace m1\controllers;

use m1\classes\M1Prefix;
use wulaphp\mvc\controller\Controller;

/**
 * 默认控制器.
 */
class Index extends Controller {
    use M1Prefix;

    /**
     * 默认控制方法.
     */
    public function index() {
        return 'admin prefix is ok';
    }
}