<?php
class WebSocket {
    /**
     * 创建socket
     * @param $address
     * @param $port
     * @return resource
     */
    public function createSocket($address, $port) {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1); // 1表示接受所有的数据包
        socket_bind($server, $address, $port);
        socket_listen($server);
        return $server;
    }

    /**
     * 对创建的socket进行循环监听, 处理数据
     */
    public function run() {
        // 死循环, 直到socket断开
        while (true) {
            $changes = $this->sockets;
            $write = null;
            $except = null;

            /*
             * 这个函数时同时接收多个连接的关键, 我的理解它是为了阻塞程序继续往下执行.
             * socket_select ($sockets, $write = NULL, $except = NULL, NULL);
             *
             * $socket可以理解为一个数组, 这个数组中存放的时文件描述符. 当它有变化(就是由新消息到或者有客户端连接/断开)时,
             * socket_select函数才会返回, 继续往下执行.
             *
             * $write是监听是否有客户端写数据, 传入null是不关心是否由写变化.
             * $except是$sockets里面要被排除的元素, 传入null是"监听"全部.
             *
             * 最后一个参数是超时时间, 如果为0则立即结束;
             * 如果n>1则最多在n秒后结束, 如遇某一个连接有新动态, 则提前返回;
             * 如果为null: 如遇某一个连接有新动态, 则返回.
             */
            socket_select($changes, $write, $except, null);
            foreach ($changes as $sock) {
                // 如果有新的client连接进来
                if ($sock == $this->master) {

                    // 接收一个socket连接
                    $client = socket_accept($this->master);

                    // 给新连接进来的socket一个唯一的id
                    $key = uniqid();
                    $this->sockets[] = $client; // 将新连接进来的socket存进连接池
                    $this->users[$key] = [
                        'socket' => $client, // 记录新连接进来client的socket信息
                        'shou' => false // 标示该socket资源没有完成握手
                    ];
                } else { // client断开socket连接, 或者client发送信息
                    $len = 0;
                    $buffer = '';
                    // 读取该socket的信息, 注意: 第二个参数是引用传参即接收数据, 第三个参数是接收数据的长度
                    do {
                        $l = socket_recv($sock, $buf, 1000, 0);
                        $len += $l;
                        $buffer .= $buf;
                    } while ($l == 1000);

                    // 根据socket在user池里面查找相应的$k, 即健id
                    $k = $this->search($sock);

                    // 如果socket在user池里面查找相应的$k, 即健id
                    $k = $this->search($sock);

                    // 如果接收的信息长度小于7, 则该client的socket为断开连接
                    if ($len < 7) {
                        // 给该client的socket进行断开操作, 并在$this->sockets和$this->users里面进行删除
                        $this->send2($k);
                        continue;
                    }

                    // 判断该socket是否已经握手
                    if (!$this->users[$k]['shou']) {
                        // 如果没有握手, 则进行握手处理
                        $this->woshou($k, $buffer);
                    } else {
                        // 走到这里就是该client发送信息了, 对接收到的信息进行uncode处理
                        $buffer = $this->uncode($buffer, $k);
                        if ($buffer == false) {
                            continue;
                        }

                        // 如果不为空, 则进行消息推送操作
                        $this->send($k, $buffer);
                    }
                }
            }
        }
    }
}
?>

<html>
<script language="javascript">
    var ws = new WebSocket("ws://ip:端口");

    // 握手监听函数
    ws.onopen = function() {
        // 状态为1证明握手成功, 然后把client自定义的名字发送过去
        if (so.readyState == 1) {
            // 握手成功后对服务器发送信息
            so.send('type=add&ming='+n);
        }
    };

    // 错误返回信息函数
    ws.onerror = function() {
        console.log("error");
    };

    // 监听服务器端推送的消息
    ws.onmessage = function() {
        console.log(msg);
    };

    // 断开WebSocket连接
    ws.onclose = function() {
        ws = false;
    };
</script>
</html>
