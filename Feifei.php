<?php

namespace app\pay\controller;

use Adapay\AdaPay;
use Adapay\AdaPayCommon;
use think\Db;
use think\Request;

class Feifei extends Pay
{
    private $key = '293509668f1b8a3560efae95ad612683';
    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("pay_orderid");
        $body = I('pay_productname');
        $money = I("post.pay_amount");
        $parameter = [
            'code' => 'Feifei',       // 通道代码
            'title' => 'feifei支付',      //通道名称
            'exchange' => 1,          // 金额比例
            'gateway' => '',            //网关地址
            'orderid' => 'Feifei' . date("YmdHis") . rand(100000, 999999),            //平台订单号（有特殊需求的订单号接口使用）
            'out_trade_id' => $orderid,   //外部商家订单号
            'body' => $body,              //商品名称
            'channel' => $array,          //通道信息
            'amount' => $money,//支付金额
            'pay_attach' => I("post.pay_attach"),
            'pay_notifyurl' => I("post.pay_notifyurl"),
            'pay_callbackurl' => I("post.pay_callbackurl"),
        ];
        //生成系统订单，并返回三方请求所需要参数
        $return = $this->orderadd($parameter);
        $notifyUrl = 'http://' . $_SERVER['HTTP_HOST'] . url("/pay/feifei/notify");
        $backUrl = 'http://' . $_SERVER['HTTP_HOST'] . url("/pay/feifei/callback");
        $goods = getGoodsInfo();
        $data = [
            'mchid' => $return['mch_id'],
            'out_trade_no' => $parameter['orderid'],
            'amount' => $money,
            'channel' => 'h5_zfb',
            'return_url' => $backUrl,
            'notify_url' => $notifyUrl,
            'time_stamp' => date("YmdHis"),
            'body' => $goods['goods_name'],
        ];
        $fxgetway = 'http://184.168.123.130/api/pay/unifiedorder';
        $data["sign"] = $this->sign($data,$return['signkey']);

        $r = $this->getHttpContent($fxgetway, "POST", $data);

        file_put_contents('1.txt',json_encode($r).PHP_EOL,FILE_APPEND);
        $r = json_decode($r, true); //json转数组
        if (empty($r)||$r['code'] != 0) {
            exit(print_r($r)); //如果转换错误，原样输出返回
        }
        $this->successMessage('成功', ['url' => $r['data']['request_url']]);
    }

    /**
     * 签名
     * @param $param
     * @param $secret string 秘钥
     * @return string
     */
    private function sign($param, $secret)
    {
        if (isset($param['sign'])) {
            unset($param['sign']);
        }
        $signArr = [];
        ksort($param);
        if (!empty($param)) {
            foreach ($param as $key => $val) {
                $signArr[] = $key . '=' . $val;
            }
        }
        $string = implode('&', $signArr) . '&key=' . $secret;
        file_put_contents('1.txt', $string . PHP_EOL, FILE_APPEND);
        return md5($string);
    }

    private function getHttpContent($url, $method = 'GET', $postData = [])
    {
        $data = '';
        $user_agent = $_SERVER ['HTTP_USER_AGENT'];
        $header = array(
            "User-Agent: $user_agent"
        );
        if (!empty($url)) {
            try {
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT, 30); //30秒超时
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                //curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie_jar);
                if (strstr($url, 'https://')) {
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE); // https请求 不验证证书和hosts
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
                }

                if (strtoupper($method) == 'POST') {
                    $curlPost = is_array($postData) ? http_build_query($postData) : $postData;
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPost);
                }
                $data = curl_exec($ch);
                curl_close($ch);
            } catch (\Exception $e) {
                $data = '';
            }
        }
        return $data;
    }

    private function getClientIP($type = 0, $adv = false)
    {
        global $ip;
        $type = $type ? 1 : 0;
        if ($ip !== NULL)
            return $ip[$type];
        if ($adv) {
            if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
                $pos = array_search('unknown', $arr);
                if (false !== $pos)
                    unset($arr[$pos]);
                $ip = trim($arr[0]);
            } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (isset($_SERVER['REMOTE_ADDR'])) {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        // IP地址合法验证
        $long = sprintf("%u", ip2long($ip));
        $ip = $long ? array(
            $ip,
            $long) : array(
            '0.0.0.0',
            0);
        return $ip[$type];
    }

    //回调信息
    public function notify()
    {
        $post = $_POST;
        $notifyData['post'] = json_encode($_POST);
        $notifyData['get'] = json_encode($_GET);
        $notifyData['input'] = json_encode(file_get_contents("php://input"));
        $notifyData['ctime'] = time();
        $notifyData['type'] = 'feifei';
        Db::name('order_notify')->insert($notifyData);
        file_put_contents('1.txt', json_encode($_POST) . PHP_EOL, FILE_APPEND);
        $order = Db::name('order')->where(['pay_orderid' => $post['out_trade_no']])->find();
        if (empty($order)) {
            exit('fail');
        }
        $sign = $this->sign($post,$order['key']);
        if(!isset($post['sign']) || $post['sign'] != $sign){
            exit('签名错误');
        }
        if ($post['order_status'] == '1') {//支付成功
            //支付成功 更改支付状态 完善支付逻辑
            try {
                $this->EditMoney($post['out_trade_no'], 0);
            } catch (\Exception $e) {
                exit($e->getTraceAsString());
            }
            echo 'SUCCESS';
        } else { //支付失败
            echo 'fail';
        }
    }
}