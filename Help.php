<?php
class Help
{
    /**
     * Socket推送消息
     * @param $uid 客户端发过来的uid
     * @param $msg 消息
     * @param int $time 几秒后执行
     * @return bool
     */
    public static function push_msg($uid,$msg,$time=0){
        $data['uid'] = $uid;
        $data['time'] = $time;
        $data['message'] = $msg;
        try{
            // 建立socket连接到内部推送端口
            $text_port = env('WORKERMAN_TEXT_PORT','5678');
            $client = stream_socket_client('tcp://127.0.0.1:'.$text_port, $errno, $errmsg, 1);
            if(!$client){
                Log::error("推送消息连接Socket失败：$errmsg");
            }else{
                // 推送的数据，包含uid字段，表示是给这个uid推送
                // 发送数据，注意5678端口是Text协议的端口，Text协议需要在数据末尾加上换行符
                fwrite($client, json_encode($data)."\n");
                // 读取推送结果
                $re = fread($client, 8192);
                return $re == 'ok' ? true : false;
            }
        }catch(\Exception $e){
            Log::error("推送消息失败：".$e->getMessage());
        }
    }
}
