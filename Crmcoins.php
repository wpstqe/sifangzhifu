<?php
namespace app\pay\controller;

use Think\Controller;
use think\Db;
use think\Request;
/**
 * Created by PhpStorm.
 * User: feng
 * Date: 2017/10/24
 * Time: 11:18
 */
class Crmcoins extends Pay{
	
    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("pay_orderid");
        $body = I('pay_productname');
        $money = I("post.pay_amount");
        $parameter = [
            'code' => 'Crmcoins',       // 通道代码
            'title' => 'CrmcoinsBRL',      //通道名称
            'exchange' => 1,          // 金额比例
            'gateway' => '',            //网关地址
            'orderid' => 'Cr' . date("YmdHis") . rand(100000, 999999),            //平台订单号（有特殊需求的订单号接口使用）
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
        $url = 'https://www.crmcoins.com.br/script/pixapi.prg/createpix';
        $notifyUrl = 'https://' . $_SERVER['HTTP_HOST'] . url("/pay/Crmcoins/notify");
        $data = ['partner'=>"011e8de2-47dd-4729-b4a6-e6d33341ce4c",'id'=>$parameter['orderid'],'valor'=>floatval($money),"img"=>'S','nome'=>"",'cpf'=>'', "mudavalor"=> 'false','imgtype'=>'png','webhook'=>$notifyUrl];
        $header = [
            'Content-Type'=>'application/json'
            ];
        $result = curlPost($url,json_encode($data),$header);
        $result = json_decode($result,true);
        if(isset($result['code'])&&$result['code']==0){
            $result['money'] = $return['amount'];
            cache("transaction_uuidCrmcoins".$return['orderid'],$result);
            // M('order')->where(['out_trade_id'=>$return['out_trade_id']])->update(['up_trade_id'=>$result['data']['transaction_uuid']]);
            // $data = ['id' => $result['data']['transaction_uuid']];
            $h5Url = 'https://' . $_SERVER['HTTP_HOST'] . url("/index/index/crmcoins", ['id'=>$return['orderid']]);
            $this->successMessage('成功', ['url' => $h5Url]);
        }else{
            var_dump($data);
            var_dump($result);
            $this->showmessage('支付链接请求失败');
        }
    }
    
    public function notify(){
        logResult('Crmcoins','notify _POST:'.json_encode($_POST), $_POST);
        $notifyData['post'] = json_encode($_POST);
        $notifyData['get'] = json_encode($_GET);
        $notifyData['input'] = json_encode(file_get_contents("php://input"));
        $notifyData['ctime'] = time();
        $notifyData['type'] = 'Crmcoins';
        $id = Db::name('order_notify')->insertGetId($notifyData);
        $time = date("Y-m-d H:i:s");
        file_put_contents('./pay/'.date('Ymd').'.txt','-------------------------------'.$time.'-------------------------------'.PHP_EOL,FILE_APPEND);
        file_put_contents('./pay/'.date('Ymd').'.txt',$time.'---'.json_encode(file_get_contents("php://input")).PHP_EOL,FILE_APPEND);
        file_put_contents('./pay/'.date('Ymd').'.txt',$time.'---'.json_encode($_SERVER).PHP_EOL,FILE_APPEND);
        $re = Request::instance();
        file_put_contents('./pay/'.date('Ymd').'.txt',$time.'---'.json_encode($re->ip()).PHP_EOL,FILE_APPEND);
        if($re->ip() != '164.68.108.180'&&$re->ip() != '149.115.234.6'){
            file_put_contents('./pay/'.date('Ymd').'.txt',$time.'---'.$re->ip().' disabled'.PHP_EOL,FILE_APPEND);
            echo 'IP disabled';die;
        }
        $data = json_decode(file_get_contents("php://input"),true);
        // $data = $_POST;
        // $data = json_decode($str,true);
        if(!isset($data['status_process']) || strtolower($data['status_process']) != 2){
            file_put_contents('./pay/'.date('Ymd').'.txt',$time.'---'.'status != 2'.PHP_EOL,FILE_APPEND);
            echo 'status != SUCCESS';die;
        }
        file_put_contents('./pay/'.date('Ymd').'.txt',$time.'---'.'开始进行代收的修改'.PHP_EOL,FILE_APPEND);
        $order = M("order")->where(['pay_orderid'=>$data['transactionid']])->find();
        if(!$order){
            file_put_contents('./pay/'.date('Ymd').'.txt',$time.'---'.'订单不存在'.PHP_EOL,FILE_APPEND);
            echo '订单不存在';die;
        }
        if($order['pay_status'] > 0){
            file_put_contents('./pay/'.date('Ymd').'.txt',$time.'---'.'订单已支付'.PHP_EOL,FILE_APPEND);
            echo '订单已支付--OK';die;
        }
        $update['userinfo'] = $data['payername'];
        $update['pay_memberer'] = $data['payerdoc'];
        $update['up_trade_id'] = $data['endtoend'];
        Db::name("order")->where(['id'=>$order['id']])->update($update);
        Db::name("order_notify")->where(['id'=>$id])->update(['status'=>2]);
        $result = $this->EditMoney($data['transactionid'], 0);
        if($result){
            return 'OK';
        }else{
            return 'FAILED';
        }
    }
}
