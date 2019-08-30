<?php
/*
 * This file is part of wulacms.
 *
 * (c) Leo Ning <windywany@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace m3\user\controllers;

use wulaphp\mvc\controller\Controller;

class Index extends Controller {
    public function index() {
        return 'admin/m3/user is ok';
    }
}