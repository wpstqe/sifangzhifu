<?php

namespace app\pay\controller;

use Think\Controller;

class Jump extends Controller
{
    public function __construct()
    {
        parent::__construct();
    }

    public function index()
    {
        $orderid = I('get.orderid');
        $file = RUNTIME_PATH . '/Cache/mfzf/' . $orderid . '.cache';
        if (!file_exists($file)) {
            exit('<h1>订单不存在或已支付</h1>');
        }
        exit(file_get_contents($file));
    }

    public function indexop()
    {
        $orderid = I('get.orderid');
        $file = RUNTIME_PATH . '/Cache/yzsc/' . $orderid . '.cache';
        if (!file_exists($file)) {
            exit('<h1>订单不存在或已支付</h1>');
        }
        exit(file_get_contents($file));
    }

    public function indexwx()
    {
        $orderid = I('get.orderid');
        $file = RUNTIME_PATH . '/Cache/wxsc/' . $orderid . '.cache';
        if (!file_exists($file)) {
            exit('<h1>订单不存在或已支付</h1>');
        }
        exit(file_get_contents($file));
    }
}
