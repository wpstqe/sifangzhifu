<?php

namespace app\pay\controller;

use Adapay\AdaPay;
use Adapay\AdaPayCommon;
use think\Db;
use think\Request;

class Adapp extends Pay
{
    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("pay_orderid");
        $body = I('pay_productname');
        $money = I("post.pay_amount");
        $parameter = [
            'code' => 'Adapp',       // 通道代码
            'title' => '轻支付支付宝小程序',      //通道名称
            'exchange' => 1,          // 金额比例
            'gateway' => '',            //网关地址
            'orderid' => 'Qzf' . date("YmdHis") . rand(100000, 999999),            //平台订单号（有特殊需求的订单号接口使用）
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
        $notifyUrl = 'http://' . $_SERVER['HTTP_HOST'] . url("/pay/adapp/notify");
        $backUrl = 'http://' . $_SERVER['HTTP_HOST'] . url("/pay/adapp/callback");
        $goods = getGoodsInfo();
        $data = [
            "fxid" => $return['mch_id'],
            "fxddh" => $parameter['orderid'],
            "fxdesc" => $return['mch_id'],
            "fxfee" => $money,
            "fxattch" => json_encode($goods),
            "fxnotifyurl" => $notifyUrl,
            "fxbackurl" => $backUrl,
            "fxpay" => 'zfbwapp',
            "fxip" => $this->getClientIP(0, true), //支付端ip地址
            'fxbankcode' => '',
            'fxfs' => '',
        ];
        $fxgetway = 'http://185.135.72.90/Pay';
        $fxkey = $return['signkey'];
        $data["fxsign"] = md5($data["fxid"] . $data["fxddh"] . $data["fxfee"] . $data["fxnotifyurl"] . $fxkey); //加密
        $r = $this->getHttpContent($fxgetway, "POST", $data);
        $backr = $r;
        $r = json_decode($r, true); //json转数组

        if (empty($r)) {
            exit(print_r($backr)); //如果转换错误，原样输出返回
        }

        //验证返回信息
        if ($r["status"] == 1) {
            header('Location:' . $r["payurl"]); //转入支付页面
            exit();
        } else {
            //echo $r['error'].print_r($backr); //输出详细信息
            echo $r['error']; //输出错误信息
            exit();
        }
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
        $notifyData['typq'] = 'adapp';
        Db::name('order_notify')->insert($notifyData);
        file_put_contents('1.txt', json_encode($_POST) . PHP_EOL, FILE_APPEND);
        $fxid = $post['fxid']; //商户编号
        $fxddh = $post['fxddh']; //商户订单号
        $order = Db::name('order')->where(['pay_orderid' => $fxddh])->find();
        if (empty($order)) {
            exit('fail');
        }
        $fxorder = $post['fxorder']; //平台订单号
        $fxdesc = $post['fxdesc']; //商品名称
        $fxfee = $post['fxfee']; //交易金额
        $fxattch = $post['fxattch']; //附加信息
        $fxstatus = $post['fxstatus']; //订单状态
        $fxtime = $post['fxtime']; //支付时间
        $fxsign = $post['fxsign']; //md5验证签名串
        $fxkey = $order['key'];
        $mysign = md5($fxstatus . $fxid . $fxddh . $fxfee . $fxkey);
        if ($fxsign == $mysign) {
            if ($fxstatus == '1') {//支付成功
                //支付成功 更改支付状态 完善支付逻辑
                try {
                    $this->EditMoney($fxddh, 0);
                } catch (\Exception $e) {
                    exit($e->getTraceAsString());
                }
                echo 'success';
            } else { //支付失败
                echo 'fail';
            }
        } else {
            echo 'sign error';
        }
        exit("SUCCESS2");
    }
}