<?php

namespace wulaphp\mvc\controller;

use wulaphp\app\App;
use wulaphp\util\Annotation;

/**
 * Class LayoutSupport
 * @property-read  string           $layout 布局模板.
 * @property-read  \ReflectionClass $reflectionObj
 * @package wulaphp\mvc\controller
 */
trait LayoutSupport {
    /**
     * @param string|array $tpl
     * @param array        $data
     *
     * @return \wulaphp\mvc\view\View
     */
    protected function render($tpl = null, array $data = []) {
        if ($this instanceof Controller) {
            if (is_array($tpl)) {
                $data = $tpl;
                $tpl  = null;
            }
            $layout = '~' . $this->layout;
            $data   = $this->onInitLayoutData($data);
            if ($tpl && $tpl{0} == '~') {
                $tpl = substr($tpl, 1);
                $tpl = $this->realPath($tpl . '.tpl');
            } else if ($tpl) {
                $path = str_replace(['\\', 'controllers'], [DS, 'views'], $this->reflectionObj->getNamespaceName());
                $tpl  = $this->realPath($path . DS . $tpl . '.tpl');
            } else {
                $path = str_replace(['\\', 'controllers'], [DS, 'views'], $this->reflectionObj->getNamespaceName());
                $tpl  = $this->realPath($path . DS . $this->ctrName . DS . $this->action . '.tpl');
            }
            $data['workspaceView'] = $tpl;
            $view                  = view($layout, $data);

            return $view;
        }

        return null;
    }

    protected final function onInitLayoutSupport() {
        if ($this instanceof Controller) {
            if (!isset($this->layout)) {
                if ($this->reflectionObj instanceof \ReflectionClass) {
                    $ann          = new Annotation($this->reflectionObj);
                    $this->layout = $ann->getString('layout');
                } else {
                    $this->layout = null;
                }
            }
            if (!$this->layout) {
                $msg = __('The layout property of %s is not found', get_class($this));
                throw new \BadMethodCallException($msg);
            }
        } else {
            throw new \BadMethodCallException('LayoutSupport is not for ' . get_class($this));
        }
    }

    /**
     * 初始化Layout数据.
     *
     * @param array $data
     *
     * @return array
     */
    protected function onInitLayoutData(array $data) {
        return $data;
    }

    /**
     * 真实路径
     *
     * @param string $path
     *
     * @return string
     */
    private function realPath($path) {
        $tpls    = explode('/', $path);
        $tpls[0] = App::id2dir($tpls[0]);
        $tpl     = implode('/', $tpls);

        return $tpl;
    }
}