<?php
namespace app\pay\controller;

use think\Controller;
//比心支付函数
class Bixin extends Controller {
    
      //支付发起
    public function pay(){
        // ini_set("user_agent","Mozilla/5.0 (iPhone; CPU iPhone OS 13_2_3 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/13.0.3 Mobile/15E148 Safari/604.1 Edg/92.0.4515.107");
        $ck['pay_type'] = 1;
        $ck['bixin_mobile'] = '18530034919';
        $ck['bixin_actype'] = 0;
        $money = 100;
        if($ck['pay_type'] == '1'){
            $pay_type = "UNION_ALIPAY";
        }else{
            $pay_type = "WEIXIN";
        }
        
        //{"nationCode":"86","mobile":"17681011910","payChannel":"WEIXIN","payAmount":"1","returnUrl":"https://h5.bxyuer.com/bixin/buy-coins/index#/result","unionReturnUrl":"https://h5.bxyuer.com/bixin/buy-coins/index?type=union","payPlatform":"MOBILE_BROWSER","app":"BIXIN"}
        $p_data = '{"nationCode":"86","mobile":"'.$ck['bixin_mobile'].'","payChannel":"'.$pay_type.'","payAmount":"'.$money.'","returnUrl":"https://h5.bxyuer.com/bixin/buy-coins/index#/result","unionReturnUrl":"https://h5.bxyuer.com/bixin/buy-coins/index?type=union","payPlatform":"MOBILE_BROWSER","app":"BIXIN"}';
        
        $header = [
	        'Referer:https://h5.bxyuer.com/bixin/buy-coins/index',
	        'User-Agent: Mozilla/5.0 (Linux; Android 5.0; SM-G900P Build/LRX21T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Mobile Safari/537.36 Edg/92.0.902.73',
	        'Content-Type: application/json',
    	];
    	if($ck['bixin_actype'] == 0){
    	   $url = 'https://h5.bxyuer.com/pay/recharge-balance'; 
    	}else{
    	   $url = 'https://h5.bxyuer.com/pay/recharge-balance-showno';
    	}
        $curl = curl_init() ;
        curl_setopt($curl, CURLOPT_URL,$url) ;
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header );
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1) ;
        // curl_setopt($curl, CURLOPT_TIMEOUT,3 );
        curl_setopt($curl, CURLOPT_POST, 1 );
    	curl_setopt($curl, CURLOPT_POSTFIELDS, $p_data );
        $contents=curl_exec($curl);
        curl_close($curl);
        var_dump($contents);
        $payinfo = json_decode($contents,true);
        var_dump($payinfo);die;
        if($payinfo['msg']!='SUCCESS'){
           $order_id='';
           $wx_url='';
           $message='比心充值渠道错误状态码为1：'.print_r($contents,true);
           $order_mb='';
        }else{
           $order_id=$payinfo['result']['payNo'];//订单号
           $message='ok';//订单号;
        } 
        
        
        if($message=='ok'){
            if($ck['pay_type'] == '0'){
                $realurl = $payurl = $payinfo['result']['weixinPrepay']['mweb'];
                $opts = array('http' => array('header'=> 'Referer: https://h5.bxyuer.com/'));
                $context = stream_context_create($opts);
                $contents = file_get_contents($realurl, false, $context);
                $payurl = pay_getSubstr3($contents, 'var url="', '";');
                $wx_url = $payurl;
                $order_mb='nanmuwxh5'; 
            }else{
                $wx_url=$payinfo['result']['unionPayPrepay']['unionWebPrepay'];
                $order_mb='nanmualih5'; 
            }
        }

        if($contents = ''){
            $message = '下单失败，请重新下单！';
        }
        $order['order_id']=$order_id;
		$order['order_url']=$wx_url;
		$order['order_message']=$message;
		$order['alipay_url']='';
		$order['order_mb']=$order_mb;
		$order['hy_sign']='';
		$order['hy_time']=$payinfo['result']['orderNo'];
		return $order;
        
    }
    
    //YY支付查询
    
    static function status($ck,$order,$money){
        $post_data = '{"payNo":"'.$order.'"}';
        $url = 'https://h5.bxyuer.com/pay/recharge-query';
        $header = [
	        'Referer:https://h5.bxyuer.com/bixin/buy-coins/index',
	        'User-Agent: Mozilla/5.0 (Linux; Android 5.0; SM-G900P Build/LRX21T) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/92.0.4515.131 Mobile Safari/537.36 Edg/92.0.902.73',
	        'Content-Type: application/json',
    	];

        $ch = curl_init ();
    	curl_setopt ( $ch, CURLOPT_URL, $url );
    	curl_setopt ( $ch, CURLOPT_HTTPHEADER, $header );
    	curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, 1 );
    	curl_setopt ( $ch, CURLOPT_POST, 1 );
    	curl_setopt ( $ch, CURLOPT_POSTFIELDS, $post_data );
    	
        $daili = db('config')->where('name','new_daili')->find();
        curl_setopt ( $ch, CURLOPT_PROXY, $daili['value']);
    	curl_setopt ( $ch, CURLOPT_TIMEOUT,3 );
    	$output = curl_exec ( $ch );
    	curl_close ( $ch );

        $payinfo = json_decode($output,true);
        $status = $payinfo['result']['state']['name'];
        if($status == 'COMPLETE'){
            
          $syorder=$order;  
          return $syorder;
          
        }
    }
    
}