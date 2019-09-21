<?php
/*
 * This file is part of wulacms.
 *
 * (c) Leo Ning <windywany@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace login\hooks\passport;

use login\classes\VipTestPassport;
use wulaphp\hook\Alter;

class NewViptestPassport extends Alter {
    public function alter($value, ...$args) {
        $passport = new VipTestPassport();

        return $passport;
    }
}