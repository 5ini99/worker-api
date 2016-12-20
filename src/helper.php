<?php
// +----------------------------------------------------------------------
// | Workerman Api [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.zzstudio.net All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: Byron Sampson <xiaobo.sun@qq.com>
// +----------------------------------------------------------------------

/**
 * 自动载入文件目录
 * @param $dir_arr
 */
function autoload_worker_dir($dir_arr)
{
    extract($GLOBALS);
    foreach ($dir_arr as $dir) {
        foreach (glob($dir . '*.php') as $start_file) {
            require_once $start_file;
        }
    }
}