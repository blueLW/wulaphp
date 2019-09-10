<?php
/*
 * This file is part of wulacms.
 *
 * (c) Leo Ning <windywany@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace wulaphp\artisan;

abstract class GmWorker {
    private $workload = false;
    private $out      = null;
    private $rst      = null;
    /**
     * @var \GearmanJob
     */
    protected $job     = null;
    protected $jobName = null;
    protected $jobId   = null;

    public function __construct() {
        global $argv;
        if ($argv) {
            $this->jobName = isset($argv[2]) ? $argv[2] : $this->getFuncName();
            $this->jobId   = isset($argv[3]) ? $argv[3] : '0';
        }
        $this->setWorkload();
    }

    /**
     * 仅供内部使用
     *
     * @param string           $workload
     * @param \GearmanJob|null $job
     *
     * @internal
     */
    public function setWorkload($workload = null, $job = null) {
        global $argv;
        if ($workload) {
            $this->workload = $workload;
        } else if (isset($argv[1])) {
            $this->workload = $argv[1];
        }
        $this->rst = [];
        $this->out = [];
        $this->job = $job;
        if ($this->job) {
            $this->jobName = $job->functionName();
            $this->jobId   = $job->unique();
        }
    }

    /**
     * 处理任务.
     *
     * @param bool $workloadIsJson
     * @param bool $output output是否直接echo.
     *
     * @return int 0 for success
     */
    public final function run($workloadIsJson = true, $output = true) {
        $rst = false;
        if ($output) {
            $this->out = null;
        }
        try {
            if ($workloadIsJson && $this->workload) {
                $workload = json_decode($this->workload, true);
            } else if ($this->workload) {
                $workload = $this->workload;
            } else {
                $workload = false;
            }
            if ($workload) {
                $rst = $this->doJob($workload);
            }
        } catch (\Exception $e) {
            log_error($e->getMessage(), 'gearman.' . $this->getFuncName());
            $this->output($e->getMessage());
        }
        if ($output) {
            if ($rst === false) {
                exit(1);
            }
            exit(0);
        }
        if ($rst === false) {
            return 1;
        }

        return 0;
    }

    /**
     * 输出数据
     *
     * @param string $data
     */
    protected function output($data) {
        if ($this->out === null) {
            echo $data, "\n";
        } else {
            $this->out[] = $data;
        }
    }

    /**
     * 发送结果.
     *
     * @param string $data
     */
    protected function send($data) {
        if ($this->out === null) {
            echo $data, "\n";
        } else {
            $this->rst [] = $data;
        }
    }

    /**
     * 报告进度。
     *
     * @param int $num 分子
     * @param int $den 分母
     */
    protected function sendStatus($num, $den) {
        if (!$this->job && $this->jobId) {
            try {
                $client = new \GearmanClient();
                $client->setTimeout(5000);
                if ($client->addServer()) {
                    ;
                } else {
                    $this->jobId = null;
                }
            } catch (\Exception $e) {
                $this->jobId = null;
            }
        }
        if ($this->job) {
            $this->job->sendStatus($num, $den);
        }
    }

    /**
     * 获取worker工作结果.
     *
     * @return array
     */
    public final function getOutput() {
        if ($this->out) {
            return array_merge($this->out, $this->rst);
        }

        return $this->rst;
    }

    /**
     * 函数名
     * @return string
     */
    public function getFuncName() {
        return '';
    }

    /**
     * @param array|string $workload
     *
     * @return bool
     */
    protected abstract function doJob($workload);
}