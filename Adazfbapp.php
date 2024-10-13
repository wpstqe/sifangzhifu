<?php

namespace app\pay\controller;

use Adapay\AdaPay;
use Adapay\AdaPayCommon;
use Adapay\AdapayTools;
use Adapay\Payment;
use think\Db;

class Adazfbapp extends Pay
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
            'code' => 'Adazfb',       // 通道代码
            'title' => '汇付支付宝小程序',      //通道名称
            'exchange' => 1,          // 金额比例
            'gateway' => '',            //网关地址
            'orderid' => 'Adazfb' . date("YmdHis") . rand(100000, 999999),            //平台订单号（有特殊需求的订单号接口使用）
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
        define("SDK_VERSION", "v1.4.4");
        define("GATE_WAY_URL", "https://%s.adapay.tech");
        $info = new AdaPay();
        $tool = new AdapayTools();
        $info::$api_key = $return['signkey'];
        $info::$rsaPrivateKey = $return['appsecret'];
        $info->gateWayType = 'page';
        $info->gateWayUrl = 'https://%s.adapay.tech';
        $info::$logDir = '../runtime/prod';
        $info::$isDebug = true;
        $money = number_format($money, 2, ".", "");
        $div_members = [['member_id'=>$return['mch_id'],'amount'=> $money,'fee_flag'=>'Y']];
        $goods = getGoodsInfo();
        $payment_params = [
            'adapay_func_code'=> 'prePay.preOrder',
            'order_no' => $return['orderid'],
            'pay_amt'=> $money,
            'app_id' => $return['appid'],
            // 'app_id'=>'app_40803863-13a5-409b-9fd2-da54662122da',
            'time_expire'=> date("YmdHis", time()+3600),
            'pay_channel'=>'alipay_lite',
            'goods_title' => isset($goods['goods_name']) ? $goods['goods_name'] : '商城购物',
            'goods_desc' => isset($goods['goods_name']) ? $goods['goods_name'] : '商城购物',
            'currency'=>'cny',
            'description'=> 'description',
            'div_members'=> $div_members,
            'notify_url' => 'http://' . $_SERVER['HTTP_HOST'] . url("/pay/adazfbapp/notify"),
            'callback_url' => 'http://' . $_SERVER['HTTP_HOST'] . url("/pay/adazfbapp/callback"),
        ];
        $data = new AdaPayCommon();
        $data->requestAdapayUits($payment_params);
        $result = $data->result;
        if(!isset($result[0]) || $result[0] != 200){
            $data = json_decode($result[1],true);
            $info = json_decode($data['data'],true);
            $msg = isset($info['error_msg'])?$info['error_msg']:'请求出错';
            $this->showmessage($msg);
        }
        $data = json_decode($result[1],true);
        $payData = json_decode($data['data'],true);
        $h5Url = '';
        if(isset($payData['expend'])){
            $h5Url = $payData['expend']['ali_h5_pay_url'];
        }else{
            $this->showmessage('支付连接请求失败');
        }

        $this->successMessage('成功',['url'=>$h5Url]);
        die;
//        $this->assign('url',$h5Url);
//        $this->assign('money',$money);
//        $this->assign('siteurl',$this->_site);
//        $this->assign('imgurl',$h5Url);
//        $this->assign('params',$return);
//        $this->assign('orderid',$payment_params['order_no']);
//        echo $this->fetch();die;
    }

    //回调信息
    public function notify()
    {
        $notifyData['post'] = json_encode($_POST);
        $notifyData['get'] = json_encode($_GET);
        $notifyData['input'] = json_encode(file_get_contents("php://input"));
        $notifyData['ctime'] = time();
        $notifyData['type'] = 'adazfbapp';
        Db::name('order_notify')->insert($notifyData);
        // $post_data_str = request()->param();
        file_put_contents('2.txt','------------'.date("Y-m-d H:i:s").'------------------'.PHP_EOL,FILE_APPEND);
        // file_put_contents('2.txt',json_encode($post_data_str).PHP_EOL,FILE_APPEND);
        $ddd = request()->param();
        $fff = json_decode($ddd['data'],1);
        file_put_contents('2.txt',json_encode($fff).PHP_EOL,FILE_APPEND);
        file_put_contents('2.txt',json_encode($fff).PHP_EOL,FILE_APPEND);

        $adapay_tools = new AdapayTools();

        $post_data_str = json_encode($ddd['data']);

        $post_sign_str = $ddd['sign'];

        $sign_flag = $adapay_tools->verifySign($ddd['data'], $post_sign_str);
        // file_put_contents('2.txt',json_encode($sign_flag).PHP_EOL,FILE_APPEND);
        if ($sign_flag){
            $fxddh = $fff['order_no'];
            $this->EditMoney($fxddh, 0);
            echo 'success';
        }else{
            echo 'fail';
        }
    }
}