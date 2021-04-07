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
 * @property-read  Passport                $passport
 * @property-read  Annotation              $ann
 * @property-read \wulaphp\util\Annotation $methodAnn           正在执行动作的注解
 */
trait RbacSupport {
    private $globalRbacSetting = [];

    protected final function onInitRbacSupport() {
        if ($this->ann instanceof Annotation) {
            $ann                               = $this->ann;
            $this->globalRbacSetting['login']  = $ann->has('login');
            $this->globalRbacSetting['roles']  = $ann->getArray('roles');
            $this->globalRbacSetting['acl']    = $ann->getArray('acl');
            $this->globalRbacSetting['aclmsg'] = $ann->getString('aclmsg');
        }
    }

    /**
     * @param \Reflector $method
     * @param View       $view
     *
     * @return mixed
     */
    protected final function beforeRunInRbacSupport(\Reflector $method, $view) {
        if ($this->passport instanceof Passport) {
            $annotation = $this->methodAnn;

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
                $msg = $msg ? $msg : $this->globalRbacSetting['aclmsg'];
                if (preg_match('#^\{.+\}$#', $msg)) {
                    $msg = _tt(substr($msg, 1, - 1));
                }

                return $this->onDenied($msg, $view);
            }

            return $view;
        } else {
            return $this->needLogin($view);
        }
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
     * 用户无权限时.
     *
     * @param mixed  $view
     * @param string $message
     *
     * @return mixed
     */
    protected abstract function onDenied(string $message, ?View $view = null): ?View;
}