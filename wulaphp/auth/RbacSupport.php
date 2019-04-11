<?php

namespace wulaphp\auth;

use wulaphp\mvc\view\View;
use wulaphp\util\Annotation;

/**
 * 权限认证特性，此特性依赖AuthSupport的子类.
 *
 * 可通过Annotation指定每个方法的权限。
 *
 * @package wulaphp\auth
 *
 * @property-read  Passport          $passport
 * @property-read  \ReflectionObject $reflectionObj
 * @property-read  bool              $loginBack
 */
trait RbacSupport {
    protected $globalRbacSetting = ['login' => false];

    protected function onInitRbacSupport() {
        if ($this->reflectionObj instanceof \ReflectionObject) {
            $ann                               = new Annotation($this->reflectionObj);
            $this->globalRbacSetting['login']  = $this->globalRbacSetting['login'] || $ann->has('login');
            $this->globalRbacSetting['roles']  = $ann->getArray('roles');
            $this->globalRbacSetting['acl']    = $ann->getArray('acl');
            $this->globalRbacSetting['aclmsg'] = $ann->getString('aclmsg');
            if ($ann->getBool('loginBack')) {
                $this->loginBack = true;
            }
        }
    }

    /**
     * @param \Reflector $method
     * @param View       $view
     *
     * @return mixed
     */
    protected function beforeRunInRbacSupport(\Reflector $method, $view) {
        if ($this->passport instanceof Passport) {
            $annotation = new Annotation($method);

            //不需要登录
            $nologin = $annotation->has('nologin');
            if ($nologin) {
                return $view;
            }

            //登录检测
            $login = $annotation->has('login') || $this->globalRbacSetting['login'];

            if ($annotation->has('acl')) {
                $acl = $annotation->getArray('acl');
            } else {
                $acl = $this->globalRbacSetting['acl'];
            }

            if ($annotation->has('roles')) {
                $roles = $annotation->getArray('roles');
            } else {
                $roles = $this->globalRbacSetting['roles'];
            }

            $login = $login || $acl || $roles;
            if ($login && !$this->passport->isLogin) {
                return $this->needLogin($view);
            }
            if ($login && !$this->passport['status']) {
                return $this->onLocked($view);
            }
            $rst = true;
            if ($acl) {
                $res = array_shift($acl);
                $rst = $this->passport->cando($res, $acl);
            }
            // 同时还要有角色 $roles
            if ($rst && $roles) {
                $rst = $this->passport->is($roles);
            }

            if (!$rst) {
                $msg = $annotation->getString('aclmsg');

                return $this->onDenied($msg ? $msg : $this->globalRbacSetting['aclmsg'], $view);
            }
        } else {
            return $this->onDenied($this->globalRbacSetting['aclmsg'], $view);
        }

        return $view;
    }

    /**
     * 未登录时.
     *
     * @param mixed $view
     *
     * @return mixed
     */
    protected abstract function needLogin($view);

    /**
     * 用户被禁用时.
     *
     * @param mixed $view
     *
     * @return mixed
     */
    protected abstract function onLocked($view);

    /**
     * 用户无权限时.
     *
     * @param mixed  $view
     * @param string $message
     *
     * @return mixed
     */
    protected abstract function onDenied($message, $view);
}