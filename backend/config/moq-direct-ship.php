<?php

return [
    'order_prefix' => 'MOQ',
    'shipment_prefix' => 'SH',

    'default_shipping_fee' => 0,

    'carriers' => [
        'sf' => '顺丰速运',
        'yto' => '圆通速递',
        'zto' => '中通快递',
        'sto' => '申通快递',
        'best' => '百世快递',
        'yunda' => '韵达快递',
        'jd' => '京东物流',
        'ems' => 'EMS',
    ],

    'payment_methods' => [
        'wechat' => '微信支付',
        'alipay' => '支付宝',
        'bank' => '银行转账',
        'cash' => '现金',
        'credit' => '赊账',
    ],

    'auto_confirm_days' => 0,
    'auto_complete_days' => 7,
    'auto_cancel_hours' => 24,
];
