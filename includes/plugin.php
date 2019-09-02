<?php
/**
 * 运行时插件
 *
 * @global array
 * @name   $__ksg_rtk_hooks
 * @var array
 */

use wulaphp\app\App;

global $__ksg_rtk_hooks;
$__ksg_rtk_hooks = [];
/**
 * 已经排序的插件
 *
 * @global array
 * @name   $__ksg_sorted_hooks
 * @var array
 */
global $__ksg_sorted_hooks;
$__ksg_sorted_hooks = [];

/**
 * 注册一个HOOK的回调函数
 *
 * @param              $hook
 * @param mixed        $hook_func     回调函数
 * @param int          $priority      优先级
 * @param int          $accepted_args 接受参数个数
 *
 * @return string 失败返回'',成功返回hook_func的ID，可用于unbind
 */
function bind(string $hook, $hook_func, int $priority = 10, int $accepted_args = 1): string {
    global $__ksg_rtk_hooks, $__ksg_sorted_hooks;

    if (empty ($hook)) {
        log_error('the hook name must not be empty!', 'plugin');

        return '';
    }
    if (empty ($hook_func)) {
        log_error('the hook function must not be empty!', 'plugin');

        return '';
    }

    $hook     = __rt_real_hook($hook);
    $priority = $priority ? $priority : 10;
    $extra    = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
    if (is_string($hook_func) && $hook_func{0} == '&') {
        $hook_func = ltrim($hook_func, '&');
        $hook_func = [$hook_func, str_replace(['.', '\\', '/', '-'], ['_', ''], $hook)];
    }

    $idx                                              = __rt_hook_unique_id($hook_func);
    $__ksg_rtk_hooks [ $hook ] [ $priority ] [ $idx ] = [
        'func'          => $hook_func,
        'accepted_args' => $accepted_args,
        'extra'         => $extra
    ];

    unset ($__ksg_sorted_hooks [ $hook ]);

    return $idx;
}

/**
 * 移除一个HOOK回调函数
 *
 * @param string $hook
 * @param string $hookId   bind返回的ID
 * @param int    $priority 优先级
 *
 * @return boolean
 */
function unbind(string $hook, string $hookId, int $priority = 10) {
    global $__ksg_rtk_hooks, $__ksg_sorted_hooks;
    $hook = __rt_real_hook($hook);
    $idx  = __rt_hook_unique_id($hookId);

    $r = isset ($__ksg_rtk_hooks [ $hook ] [ $priority ] [ $idx ]);

    if (true === $r) {
        unset ($__ksg_rtk_hooks [ $hook ] [ $priority ] [ $idx ]);
        if (empty ($__ksg_rtk_hooks [ $hook ] [ $priority ])) {
            unset ($__ksg_rtk_hooks [ $hook ] [ $priority ]);
        }
        unset ($__ksg_sorted_hooks [ $hook ]);
    }

    return $r;
}

/**
 * 移除$hook对应的所有回调函数
 *
 * @param string   $hook
 * @param bool|int $priority 优先级
 *
 * @return bool
 */
function unbind_all(string $hook, $priority = false): bool {
    global $__ksg_rtk_hooks, $__ksg_sorted_hooks;
    $hook = __rt_real_hook($hook);
    if (isset ($__ksg_rtk_hooks [ $hook ])) {
        if (false !== $priority && isset ($__ksg_rtk_hooks [ $hook ] [ $priority ])) {
            unset ($__ksg_rtk_hooks [ $hook ] [ $priority ]);
        } else {
            unset ($__ksg_rtk_hooks [ $hook ]);
        }
    }
    if (isset ($__ksg_sorted_hooks [ $hook ])) {
        unset ($__ksg_sorted_hooks [ $hook ]);
    }

    return true;
}

/**
 * 触发HOOK
 *
 * @param string $hook               HOOK名称
 * @param mixed  $arg                参数
 *
 * @return string
 * @return string
 * @throws \Exception
 * @global array $__ksg_rtk_hooks    系统所有HOOK的回调
 * @global array $__ksg_sorted_hooks 当前的HOOK回调是否已经排序
 */
function fire(string $hook, $arg = '') {
    global $__ksg_rtk_hooks, $__ksg_sorted_hooks;
    __rt_scan_hook($hook); // 懒加载
    $hook = __rt_real_hook($hook);
    if (!isset ($__ksg_rtk_hooks [ $hook ])) { // 没有该HOOK的回调
        return '';
    }
    $args = [];
    if (is_array($arg) && 1 == count($arg) && is_object($arg [0])) { // array(&$this)
        $args [] = &$arg [0];
    } else {
        $args [] = $arg;
    }
    for ($a = 2; $a < func_num_args(); $a++) {
        $args [] = func_get_arg($a);
    }

    // 对hook的回调进行排序
    if (!isset ($__ksg_sorted_hooks [ $hook ])) {
        ksort($__ksg_rtk_hooks [ $hook ]);
        $__ksg_sorted_hooks [ $hook ] = true;
    }
    // 重置hook回调数组
    reset($__ksg_rtk_hooks [ $hook ]);
    try {
        @ob_start();
        do {
            foreach (( array )current($__ksg_rtk_hooks [ $hook ]) as $the_) {
                if (!is_null($the_ ['func'])) {
                    if (is_array($the_['func'])) {
                        \wulaphp\util\ObjectCaller::callClzMethod($the_['func'][0], $the_['func'][1], $args);
                    } else if ($the_ ['func'] instanceof Closure) {
                        $params = array_slice($args, 0, ( int )$the_ ['accepted_args']);
                        $the_ ['func'](...$params);
                    } else if (is_callable($the_ ['func'])) {
                        $params = array_slice($args, 0, ( int )$the_ ['accepted_args']);
                        $the_ ['func'](...$params);
                    }
                }
            }
        } while (next($__ksg_rtk_hooks [ $hook ]) !== false);
    } catch (Exception $e) {
        throw $e;
    } finally {
        $rtn = @ob_get_clean();
    }

    return $rtn;
}

/**
 * 调用与指定过滤器关联的HOOK
 *
 *
 * @param string $filter 过滤器名
 * @param mixed  $value
 *
 * @return mixed The filtered value after all hooked functions are applied to it.
 */
function apply_filter(string $filter, $value) {
    global $__ksg_rtk_hooks, $__ksg_sorted_hooks;
    __rt_scan_hook($filter); // 懒加载
    $filter = __rt_real_hook($filter);

    if (!isset ($__ksg_rtk_hooks [ $filter ])) {
        return $value;
    }

    if (!isset ($__ksg_sorted_hooks [ $filter ])) {
        ksort($__ksg_rtk_hooks [ $filter ]);
        $__ksg_sorted_hooks [ $filter ] = true;
    }

    reset($__ksg_rtk_hooks [ $filter ]);

    $args = func_get_args();
    try {
        do {
            foreach (( array )current($__ksg_rtk_hooks [ $filter ]) as $the_) {
                if (!is_null($the_ ['func'])) {
                    $args [1] = $value;
                    if (is_array($the_['func'])) {
                        $value = \wulaphp\util\ObjectCaller::callClzMethod($the_['func'][0], $the_['func'][1], array_slice($args, 1));
                    } else if ($the_ ['func'] instanceof Closure) {
                        $params = array_slice($args, 1, ( int )$the_ ['accepted_args']);
                        $value  = $the_ ['func'](...$params);
                    } else if (is_callable($the_ ['func'])) {
                        $params = array_slice($args, 1, ( int )$the_ ['accepted_args']);
                        $value  = $the_ ['func'](...$params);
                    }
                }
            }
        } while (next($__ksg_rtk_hooks [ $filter ]) !== false);

        return $value;
    } catch (Exception $e) {
        return $value;
    }
}

/**
 * 定义一个hook处理器.
 *
 * @param \Closure $handler
 * @param int      $accepted_args
 * @param int      $priority
 *
 * @return bool|string
 */
function hook(Closure $handler, int $accepted_args = 1, int $priority = 10) {
    global $__rt_hook_name;
    if ($__rt_hook_name) {
        $id             = bind($__rt_hook_name, $handler, $priority, $accepted_args);
        $__rt_hook_name = null;

        return $id;
    }

    return false;
}

/**
 * Check if any hook has been registered.
 *
 * @param string $hook
 * @param string $function_to_check        optional. If specified, return the priority of that function on this
 *                                         hook or false if not attached.
 *
 * @return int boolean returns the priority on that hook for the specified function.
 * @global array $__ksg_rtk_hooks          Stores all of the hooks
 */
function has_hook(string $hook, string $function_to_check = '') {
    global $__ksg_rtk_hooks;
    $hook = __rt_real_hook($hook);
    $has  = !empty ($__ksg_rtk_hooks [ $hook ]);
    if ('' === $function_to_check || false == $has) {
        return $has;
    }
    if (($idx = __rt_hook_unique_id($function_to_check)) == false) {
        return false;
    }
    foreach (( array )array_keys($__ksg_rtk_hooks [ $hook ]) as $priority) {
        if (isset ($__ksg_rtk_hooks [ $hook ] [ $priority ] [ $idx ])) {
            return $priority;
        }
    }

    return false;
}

/**
 * Build Unique ID for storage and retrieval.
 *
 * @param string $function
 *
 * @return string bool ID for usage as array key or false if $priority === false and $function is an object
 *                reference, and it does not already have a uniqe id.
 * @see wordpress
 * @global array $__ksg_rtk_hooks Storage for all of the filters and actions
 * @staticvar $filter_id_count
 *
 */
function __rt_hook_unique_id($function) {
    if (is_string($function)) {
        return $function;
    } else if (is_array($function)) {
        if (is_object($function [0])) {
            return spl_object_hash($function [0]) . $function [1];
        } else if (is_string($function [0])) {
            return $function [0] . $function [1];
        }
    } else if ($function instanceof Closure) {
        return spl_object_hash($function);
    }

    return false;
}

// real hook name
function __rt_real_hook(string $hook): string {
    $hook = ucwords(ltrim($hook, '\\'), '\\');

    return lcfirst($hook);
}

// scan hook handlers
function __rt_scan_hook(string $hook) {
    global $__rt_hook_name;
    static $hooks = [], $modules = null;
    if (!$modules || !defined('WULA_BOOTSTRAPPED')) {
        $modules = App::modules('hasHooks');
    }
    $__rt_hook_name = $hook;
    if (!isset($hooks[ $hook ])) {
        foreach ($modules as $m) {
            $hookFile = $m->hookPath . str_replace(['\\', '/', '-'], '.', $hook) . '.php';
            if (is_file($hookFile)) {
                include $hookFile;
            }
        }
        $hooks[ $hook ] = 1;
    }
    $__rt_hook_name = null;
}
// end of file plugin.php