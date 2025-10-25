<?php

class VPP_Payment_Handler {
    
    private $wechat_pay;
    
    public function __construct() {
        $this->wechat_pay = new VPP_WeChat_Pay();
    }
    
    public function create_payment_order() {
        if (!is_user_logged_in()) {
            wp_send_json_error('请先登录');
        }
        
        if (!wp_verify_nonce($_POST['nonce'], 'vpp_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $amount = floatval($_POST['amount']);
        $quota_amount = intval($_POST['quota_amount']);
        
        // 验证金额和配额
        if ($amount <= 0 || $quota_amount <= 0) {
            wp_send_json_error('无效的支付金额');
        }
        
        // 生成订单号
        $order_id = $this->generate_order_id();
        
        // 创建支付记录
        $payment_data = array(
            'user_id' => get_current_user_id(),
            'order_id' => $order_id,
            'amount' => $amount,
            'quota_amount' => $quota_amount,
            'payment_status' => 'pending',
            'create_time' => current_time('mysql')
        );
        
        $result = VPP()->get_database()->create_payment_record($payment_data);
        
        if (!$result) {
            wp_send_json_error('创建订单失败，请重试');
        }
        
        // 调用微信支付
        $payment_result = $this->wechat_pay->create_payment($order_id, $amount, '视频解析配额充值');
        
        if ($payment_result['success']) {
            wp_send_json_success(array(
                'order_id' => $order_id,
                'payment_data' => $payment_result['data']
            ));
        } else {
            wp_send_json_error($payment_result['message']);
        }
    }
    
    public function handle_wechat_callback() {
        $xml_data = file_get_contents('php://input');
        
        if (empty($xml_data)) {
            $this->log_payment_callback('Empty callback data');
            exit;
        }
        
        $this->log_payment_callback('Received callback: ' . $xml_data);
        
        $result = $this->wechat_pay->verify_callback($xml_data);
        
        if ($result['success']) {
            $order_id = $result['data']['out_trade_no'];
            $transaction_id = $result['data']['transaction_id'];
            
            // 更新支付状态
            $this->update_payment_status($order_id, 'completed', $transaction_id);
            
            // 返回成功响应给微信
            echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        } else {
            $this->log_payment_callback('Callback verification failed: ' . $result['message']);
            echo '<xml><return_code><![CDATA[FAIL]]></return_code><return_msg><![CDATA[签名失败]]></return_msg></xml>';
        }
        
        exit;
    }
    
    private function generate_order_id() {
        return date('YmdHis') . mt_rand(1000, 9999) . get_current_user_id();
    }
    
    private function update_payment_status($order_id, $status, $transaction_id = '') {
        $result = VPP()->get_database()->update_payment_status($order_id, $status, $transaction_id);
        
        if ($result && $status === 'completed') {
            // 支付成功，为用户添加配额
            $payment_record = VPP()->get_database()->get_payment_record($order_id);
            if ($payment_record) {
                VPP()->get_database()->update_user_quota($payment_record->user_id, $payment_record->quota_amount);
                
                // 发送邮件通知
                $this->send_payment_success_email($payment_record);
            }
        }
        
        return $result;
    }
    
    private function send_payment_success_email($payment_record) {
        $user = get_userdata($payment_record->user_id);
        if (!$user) {
            return;
        }
        
        $to = $user->user_email;
        $subject = '充值成功通知 - ' . get_bloginfo('name');
        $message = "
            <h3>充值成功</h3>
            <p>尊敬的 {$user->display_name}，</p>
            <p>您的视频解析配额充值已成功！</p>
            <ul>
                <li>订单号：{$payment_record->order_id}</li>
                <li>充值金额：{$payment_record->amount}元</li>
                <li>获得配额：{$payment_record->quota_amount}次</li>
                <li>支付时间：" . date('Y-m-d H:i:s', strtotime($payment_record->payment_time)) . "</li>
            </ul>
            <p>感谢您的使用！</p>
        ";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        wp_mail($to, $subject, $message, $headers);
    }
    
    private function log_payment_callback($message) {
        $log_file = WP_CONTENT_DIR . '/vpp-payment.log';
        $timestamp = date('Y-m-d H:i:s');
        file_put_contents($log_file, "[{$timestamp}] {$message}\n", FILE_APPEND | LOCK_EX);
    }
    
    public function get_payment_packages() {
        $price_per_100 = get_option('vpp_price_per_100', 10);
        
        return array(
            array(
                'quota' => 100,
                'price' => $price_per_100,
                'discount' => 0,
                'popular' => false
            ),
            array(
                'quota' => 300,
                'price' => $price_per_100 * 3 * 0.9, // 9折
                'discount' => 10,
                'popular' => true
            ),
            array(
                'quota' => 500,
                'price' => $price_per_100 * 5 * 0.8, // 8折
                'discount' => 20,
                'popular' => false
            ),
            array(
                'quota' => 1000,
                'price' => $price_per_100 * 10 * 0.7, // 7折
                'discount' => 30,
                'popular' => false
            )
        );
    }
}