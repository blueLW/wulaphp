<?php

namespace wulaphp\auth;

use wulaphp\mvc\view\SmartyView;
use wulaphp\mvc\view\ThemeView;
use wulaphp\mvc\view\View;
use wulaphp\util\Annotation;

/**
 * 用户通行证认证特性。添加此特性后，在控制器中可直接通过$passport属性访问当前用户的通行证.
 *
 * 注：此特性依赖SessionSupport.
 *
 * @package wulaphp\auth
 * @property-read  string     $passportType
 * @property-read  Annotation $ann
 */
trait PassportSupport {
    /**
     * @var Passport
     */
    protected $passport;

    protected final function onInitPassportSupport() {
        if (!isset($this->passportType) && $this->ann instanceof Annotation && ($type = $this->ann->getString('passport'))) {
            $this->passportType = $type;
        }
        if (isset($this->passportType)) {
            $this->passport = Passport::get($this->passportType);
        } else {
            trigger_error('passportType property not found in Controller:' . get_class($this) . ', use default passport', E_USER_WARNING);
            $this->passport = Passport::get();
        }
    }

    /**
     * @param \Reflector $method
     * @param View       $view
     *
     * @return mixed
     */
    protected function beforeRunInPassportSupport(\Reflector $method, $view) {
        if ($this->passport->uid && !$this->passport->status) {
            if ($this->passport->status) {
                return $this->onLocked($view);
            }
            if ($this->passport->screenLocked) {
                return $this->onScreenLocked($view);
            }
        }

        return $view;
    }

    protected function afterRunInPassportSupport($action, $view, $method) {
        if ($view instanceof SmartyView || $view instanceof ThemeView) {
            $view->assign('myPassport', $this->passport);
        }

        return $view;
    }

    /**
     * 用户被禁用时.
     *
     * @param mixed $view
     *
     * @return mixed
     */
    protected function onLocked($view) {
        return $view;
    }

    /**
     * 用户锁定界面时.
     *
     * @param mixed $view
     *
     * @return mixed
     */
    protected function onScreenLocked($view) {
        return $view;
    }
}