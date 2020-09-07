<?php
/*
 * This file is part of wulacms.
 *
 * (c) Leo Ning <windywany@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace wulaphp\command\service;

use wulaphp\artisan\Colors;

/**
 * Class Service
 * @package wulaphp\command\service
 * @internal
 */
abstract class Service {
    protected $pid;
    protected $name;
    protected $config   = null;
    protected $shutdown = false;
    protected $color    = null;
    protected $rSignal;
    protected $verbose  = 3;//0:off;1:info;2:error;3:warn;4:debug
    protected $logFile;

    /**
     * Service constructor.
     *
     * @param string $name
     * @param array  $config 配置
     */
    public function __construct(string $name, array $config) {
        $this->pid     = '[' . @posix_getpid() . ']';
        $this->name    = $name;
        $this->config  = $config;
        $this->color   = new Colors();
        $this->logFile = LOGS_PATH . 'service.' . $this->name . '.log';
    }

    // 监听信号
    public final function initSignal() {
        $signals = [SIGTERM, SIGINT, SIGHUP, SIGUSR1, SIGTSTP, SIGTTOU];
        foreach (array_unique($signals) as $signal) {
            @pcntl_signal($signal, [$this, 'signal']);
        }
    }

    //处理信号事件
    public function signal($signal) {
        if (!$this->shutdown) {
            $this->logi('signal recived: ' . $signal);
            $this->shutdown = true;
            $this->rSignal  = $signal;
        }
    }

    /**
     * 读取配置.
     *
     * @param string $name
     * @param mixed  $default
     *
     * @return string
     */
    protected function getOption(string $name, $default = '') {
        if ($this->config && array_key_exists($name, $this->config)) {
            return $this->config[ $name ];
        }

        return $default;
    }

    /**
     * 输出
     *
     * @param string $message
     * @param bool   $rtn
     */
    protected final function output(string $message, $rtn = true) {
        echo $message, $rtn ? "\n" : '';

        flush();
    }

    protected final function logd(string $message = '') {
        if ($this->verbose > 3) {
            $msg = date('[d/M/Y:H:i:s O] ') . ' DEBUG ' . $this->name . ' ' . $message;
            $this->_log($msg);
        }
    }

    protected final function logw(string $message = '') {
        if ($this->verbose > 2) {
            $msg = date('[d/M/Y:H:i:s O] ') . ' WARN ' . $this->name . ' ' . $message;
            $this->_log($msg);
        }
    }

    protected final function loge(string $message = '') {
        if ($this->verbose > 1) {
            $msg = date('[d/M/Y:H:i:s O] ') . ' ERROR ' . $this->name . ' ' . $message;
            $this->_log($msg);
        }
    }

    protected final function logi(string $message = '') {
        if ($this->verbose > 0) {
            $msg = date('[d/M/Y:H:i:s O] ') . ' INFO ' . $this->name . ' ' . $message;
            $this->_log($msg);
        }
    }

    public function setVerbose($verbose) {
        if (is_numeric($verbose)) {
            $this->verbose = $verbose;
        } else {
            $this->verbose = strlen($verbose) - strlen(str_replace('v', '', $verbose));
        }
    }

    private function _log($msg) {
        if (LOG_DRIVER == 'container') {
            echo $msg, "\n";
            flush();
        } else {
            @error_log($msg . "\n", 3, $this->logFile);
        }
    }

    /**
     * 运行
     * @return mixed
     */
    public abstract function run();
}