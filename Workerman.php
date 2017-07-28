<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Workerman\Worker;
use Workerman\Lib\Timer;

class Workerman extends Command
{
    /**
     *  前端添加代码
     *  var ws = new WebSocket('ws://websocket_server:port');
            ws.onopen = function(){
            var uid = 'xxxx';
            ws.send(uid);
        };
        ws.onmessage = function(e){
            alert(e.data);
        };
     */

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'workerman:server {action} {--daemonize}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'start workerman';

    /**
     * websocket端口
     * @var mixed|string
     */
    private $webscoket_port = '';

    /**
     * text端口
     * @var mixed|string
     */

    private $text_port = '';

    protected $http_server;
    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        $websocket_port = env('WORKERMAN_WEBSOCKET_PORT','1234');
        $text_port = env('WORKERMAN_TEXT_PORT','5678');
        $this->webscoket_port = $websocket_port;
        $this->text_port = $text_port;
        parent::__construct();
    }
    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        global $argv;
        $action = $this->argument('action');

        if(!in_array($action,['start','stop'])){
            $this->error('Error Arguments');
            exit;
        }

        $argv[0]='workerman:server';
        $argv[1]=$action;
        $argv[2]=$this->option('daemonize')?'-d':'';

        // 初始化一个worker容器，监听1234端口
        $worker = new Worker("websocket://0.0.0.0:$this->webscoket_port");
        // 这里进程数必须设置为1
        $worker->count = 1;
        // worker进程启动后建立一个内部通讯端口
        $worker->onWorkerStart = function($worker)
        {
            // 开启一个内部端口，方便内部系统推送数据，Text协议格式 文本+换行符
            $inner_text_worker = new Worker("Text://0.0.0.0:$this->text_port");
            $inner_text_worker->onMessage = function($connection, $buffer)use($worker)
            {
                // $data数组格式，里面有uid，表示向那个uid的页面推送数据
                $data = json_decode($buffer, true);
                $uid = $data['uid'];
                $time = $data['time'];
                $message = $data['message'];
                // 通过workerman，向uid的页面推送数据
                if($uid==='all'){
                    if($time>0){
                        // n秒后执行，最后一个参数传递false，表示只运行一次
                        Timer::add($time, array($this, 'broadcast'), array($message), false);
                        $ret = true;
                    }else{
                        $ret = $this->broadcast($worker,$message);
                    }
                }elseif(is_array($uid)){
                    if($time>0){
                        // n秒后执行，最后一个参数传递false，表示只运行一次
                        Timer::add($time, array($this, 'sendMessageByUidArr'), array($uid, $message), false);
                        $ret = true;
                    }else{
                        $ret = $this->sendMessageByUidArr($worker,$uid,$message);
                    }
                }else{
                    if($time > 0){
                        // n秒后执行，最后一个参数传递false，表示只运行一次
                        Timer::add($time, array($this, 'sendMessageByUid'), array($uid, $message), false);
                        $ret = true;
                    }else{
                        $ret = $this->sendMessageByUid($uid, $message);
                    }
                }
                // 返回推送结果
                $connection->send($ret ? 'ok' : 'fail');
            };
            $inner_text_worker->listen();
        };
        // 新增加一个属性，用来保存uid到connection的映射
        $worker->uidConnections = array();
        // 当有客户端发来消息时执行的回调函数
        $worker->onMessage = function($connection, $data)use($worker)
        {
            // 判断当前客户端是否已经验证,既是否设置了uid
            if(!isset($connection->uid))
            {
                // 没验证的话把第一个包当做uid（这里为了方便演示，没做真正的验证）
                $connection->uid = $data;
                /* 保存uid到connection的映射，这样可以方便的通过uid查找connection，
                 * 实现针对特定uid推送数据
                 */
                $worker->uidConnections[$connection->uid] = $connection;
                $connection->send('ws链接成功');
                return;
            }
        };

        // 当有客户端连接断开时
        $worker->onClose = function($connection)use($worker)
        {
            if(isset($connection->uid))
            {
                // 连接断开时删除映射
                unset($worker->uidConnections[$connection->uid]);
            }
        };

        // 运行所有的worker（其实当前只定义了一个）
        $this->http_server = $worker;
        Worker::runAll();
    }
    private function start()
    {

    }
    // 向所有验证的用户推送数据
    public function broadcast($message)
    {
        $worker = $this->http_server;
        foreach($worker->uidConnections as $connection)
        {
            $connection->send($message);
        }
    }
    // 针对uid推送数据
    public function sendMessageByUid($uid, $message)
    {
        $worker = $this->http_server;
        if(isset($worker->uidConnections[$uid]))
        {
            $connection = $worker->uidConnections[$uid];
            $connection->send($message);
            return true;
        }
        return false;
    }
    // 针对uid推送数据
    public function sendMessageByUidArr($uid_arr, $message)
    {
        $worker = $this->http_server;
        foreach($uid_arr as $uid){
            if(isset($worker->uidConnections[$uid]))
            {
                $connection = $worker->uidConnections[$uid];
                $connection->send($message);
            }
        }
        return true;
    }
}
