<?php

class VPP_WeChat_Pay {
    
    private $appid;
    private $mch_id;
    private $key;
    private $notify_url;
    
    public function __construct() {
        $this->appid = get_option('vpp_wechat_appid');
        $this->mch_id = get_option('vpp_wechat_mchid');
        $this->key = get_option('vpp_wechat_key');
        $this->notify_url = home_url('/wp-json/vpp/wechat/callback');
        
        // 注册REST API路由
        add_action('rest_api_init', array($this, 'register_rest_routes'));
    }
    
    public function register_rest_routes() {
        register_rest_route('vpp', '/wechat/callback', array(
            'methods' => 'POST',
            'callback' => array($this, 'handle_rest_callback'),
            'permission_callback' => '__return_true'
        ));
    }
    
    public function handle_rest_callback($request) {
        $xml_data = $request->get_body();
        $result = $this->verify_callback($xml_data);
        
        if ($result['success']) {
            $order_id = $result['data']['out_trade_no'];
            $transaction_id = $result['data']['transaction_id'];
            
            // 更新支付状态
            VPP()->get_database()->update_payment_status($order_id, 'completed', $transaction_id);
            
            return new WP_REST_Response('<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>', 200);
        }
        
        return new WP_REST_Response('<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>', 200);
    }
    
    public function create_payment($order_id, $amount, $description) {
        if (empty($this->appid) || empty($this->mch_id) || empty($this->key)) {
            return array('success' => false, 'message' => '微信支付配置不完整');
        }
        
        $params = array(
            'appid' => $this->appid,
            'mch_id' => $this->mch_id,
            'nonce_str' => $this->generate_nonce_str(),
            'body' => $description,
            'out_trade_no' => $order_id,
            'total_fee' => intval($amount * 100), // 转换为分
            'spbill_create_ip' => $this->get_client_ip(),
            'notify_url' => $this->notify_url,
            'trade_type' => 'NATIVE',
            'product_id' => $order_id
        );
        
        $params['sign'] = $this->generate_sign($params);
        
        $xml = $this->array_to_xml($params);
        $response = $this->post_xml('https://api.mch.weixin.qq.com/pay/unifiedorder', $xml);
        
        if (!$response) {
            return array('success' => false, 'message' => '支付请求失败');
        }
        
        $result = $this->xml_to_array($response);
        
        if ($result['return_code'] == 'SUCCESS' && $result['result_code'] == 'SUCCESS') {
            return array(
                'success' => true,
                'data' => array(
                    'code_url' => $result['code_url'],
                    'order_id' => $order_id
                )
            );
        } else {
            $error_msg = $result['return_msg'] ?? $result['err_code_des'] ?? '支付创建失败';
            return array('success' => false, 'message' => $error_msg);
        }
    }
    
    public function verify_callback($xml_data) {
        $data = $this->xml_to_array($xml_data);
        
        if (!$data || $data['return_code'] != 'SUCCESS') {
            return array('success' => false, 'message' => '回调数据无效');
        }
        
        // 验证签名
        $sign = $data['sign'];
        unset($data['sign']);
        
        if ($this->generate_sign($data) != $sign) {
            return array('success' => false, 'message' => '签名验证失败');
        }
        
        return array('success' => true, 'data' => $data);
    }
    
    private function generate_sign($params) {
        ksort($params);
        $string = '';
        
        foreach ($params as $k => $v) {
            if ($v != '' && $k != 'sign') {
                $string .= $k . '=' . $v . '&';
            }
        }
        
        $string .= 'key=' . $this->key;
        return strtoupper(md5($string));
    }
    
    private function generate_nonce_str($length = 32) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $str = '';
        for ($i = 0; $i < $length; $i++) {
            $str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
        }
        return $str;
    }
    
    private function array_to_xml($arr) {
        $xml = '<xml>';
        foreach ($arr as $key => $val) {
            if (is_numeric($val)) {
                $xml .= '<' . $key . '>' . $val . '</' . $key . '>';
            } else {
                $xml .= '<' . $key . '><![CDATA[' . $val . ']]></' . $key . '>';
            }
        }
        $xml .= '</xml>';
        return $xml;
    }
    
    private function xml_to_array($xml) {
        if (!$xml) {
            return false;
        }
        
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        return $data;
    }
    
    private function post_xml($url, $xml) {
        $response = wp_remote_post($url, array(
            'body' => $xml,
            'headers' => array('Content-Type' => 'text/xml'),
            'timeout' => 30
        ));
        
        if (is_wp_error($response)) {
            return false;
        }
        
        return wp_remote_retrieve_body($response);
    }
    
    private function get_client_ip() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'];
        }
    }
}