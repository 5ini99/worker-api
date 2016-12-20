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
namespace worker\api;

use Workerman\Connection\TcpConnection;
use Workerman\Worker;
use Workerman\Lib\Timer;
use Workerman\Autoloader;
use Workerman\Connection\AsyncTcpConnection;
use Workerman\Protocols\Http;
use worker\api\StatisticClient;

/**
 * api基类
 * Class Addons
 * @author Byron Sampson <xiaobo.sun@qq.com>
 * @package think\addons
 */
class App extends Worker
{
    /**
     * 版本
     * @var string
     */
    const VERSION = '0.1.0';

    private $connect = false;
    private $map = [];

    public $autoload = [];
    public $on404 = "";
    public $onAppStart = null;
    public $statistic_server = false;

    /**
     * 类架构函数
     * App constructor.
     * @param string $socket_name
     * @param array $context_option
     */
    public function __construct($socket_name, $context_option = [])
    {
        parent::__construct($socket_name, $context_option);
    }

    public function handleFunc($url, callable $callback)
    {
        if ($url != "/") {
            $url = strtolower(trim($url, "/"));
        }
        if (is_callable($callback)) {
            if ($callback instanceof \Closure) {
                $callback = \Closure::bind($callback, $this, get_class());
            }
        } else {
            throw new \Exception('can not handleFunc');
        }
        $this->map[] = [$url, $callback, 1];
    }

    public function addFunc($url, callable $callback)
    {
        if ($url != "/") {
            $url = strtolower(trim($url, "/"));
        }
        if (is_callable($callback)) {
            if ($callback instanceof \Closure) {
                $callback = \Closure::bind($callback, $this, get_class());
            }
        } else {
            throw new \Exception('can not HandleFunc');
        }
        $this->map[] = [$url, $callback, 2];
    }

    private function show_404($connection)
    {
        if ($this->on404) {
            call_user_func($this->on404);
        } else {
            Http::header("HTTP/1.1 404 Not Found");
            $html = '<html>
                <head><title>404 Not Found</title></head>
                <body bgcolor="white">
                <center><h1>404 Not Found</h1></center>
                <hr><center>Workerman</center>
                </body>
                </html>';
            $connection->send($html);
        }
    }

    private function autoClose($conn)
    {
        if (strtolower($_SERVER["SERVER_PROTOCOL"]) == "http/1.1") {
            if (isset($_SERVER["HTTP_CONNECTION"])) {
                if (strtolower($_SERVER["HTTP_CONNECTION"]) == "close") {
                    $conn->close();
                }
            }
        } else {
            if ($_SERVER["HTTP_CONNECTION"] == "keep-alive") {

            } else {
                $conn->close();
            }
        }
    }

    public function onClientMessage($connection, $data)
    {
        if (empty($this->map)) {
            $str = <<<'EOD'
<div style="margin: 200px auto;width:600px;height:800px;text-align:left;">基于<a href="http://www.workerman.net/" target="_blank">Workerman</a>实现的自带http server的web开发框架.没有添加路由，请添加路由!
<pre>$app->HandleFunc("/",function($conn,$data) use($app){
    $conn->send("默认页");
});</pre>
</div>
EOD;
            $connection->send($str);
            return;
        }
        if ($this->statistic_server) {
            require_once __DIR__ . '/Libs/StatisticClient.php';
            $statistic_address = $this->statistic_server;
        }
        $this->connect = $connection;
        $url = $_SERVER["REQUEST_URI"];
        $pos = stripos($url, "?");
        if ($pos != false) {
            $url = substr($url, 0, $pos);
        }
        if ($url != "/") {
            $url = strtolower(trim($url, "/"));
        }
        $url_arr = explode("/", $url);
        $class = empty($url_arr[0]) ? "_default" : $url_arr[0];
        $method = empty($url_arr[1]) ? "_default" : $url_arr[1];
        if ($this->statistic_server) {
            StatisticClient::tick($class, $method);
        }
        $success = false;
        foreach ($this->map as $route) {
            if ($route[2] == 1) {//正常路由
                if ($route[0] == $url) {
                    $callback[] = $route[1];
                }
            } else {
                if ($route[2] == 2) {//中间件
                    if ($route[0] == "/") {
                        $callback[] = $route[1];
                    } else {
                        if (stripos($url, $route[0]) === 0) {
                            $callback[] = $route[1];
                        }
                    }
                }
            }
        }
        if (isset($callback)) {
            try {
                foreach ($callback as $cl) {
                    if (call_user_func($cl) === true) {
                        break;
                    }
                }
                if ($this->statistic_server) {
                    StatisticClient::report($class, $method, 1, 0, '', $statistic_address);
                }
            } catch (\Exception $e) {
                // Jump_exit?
                if ($e->getMessage() != 'jump_exit') {
                    echo $e;
                }
                $code = $e->getCode() ? $e->getCode() : 500;
                if ($this->statistic_server) {
                    StatisticClient::report($class, $method, $success, $code, $e, $statistic_address);
                }
            }
        } else {
            $this->show_404($connection);
            $code = 404;
            $msg = "class $class not found";
            if ($this->statistic_server) {
                StatisticClient::report($class, $method, $success, $code, $msg, $statistic_address);
            }
        }
        $this->autoClose($connection);
    }

    public function ServerJson($data)
    {
        Http::header("Content-type: application/json");
        $this->connect->send(json_encode($data));
    }

    public function ServerHtml($data)
    {
        $this->connect->send($data);
    }

    public function run()
    {
        autoload_worker_dir($this->autoload);
        $this->reusePort = true;
        $this->onWorkerStart = $this->onAppStart;
        $this->onMessage = array($this, 'onClientMessage');
        parent::run();
    }
}
