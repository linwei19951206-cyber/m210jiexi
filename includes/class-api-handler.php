<?php

class VPP_API_Handler {
    
    private $api_url = 'https://www.52api.cn/api/video_parse';
    
    public function handle_parse_request() {
        // 验证nonce
        if (!wp_verify_nonce($_POST['nonce'], 'vpp_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $video_url = sanitize_text_field($_POST['video_url']);
        
        // 验证URL格式
        if (!$this->validate_video_url($video_url)) {
            wp_send_json_error('请输入有效的短视频链接');
        }
        
        // 检查用户权限和次数
        $user_check = $this->check_user_quota();
        if (!$user_check['success']) {
            wp_send_json_error($user_check['message']);
        }
        
        // 调用API解析
        $result = $this->call_parse_api($video_url);
        
        if ($result['success']) {
            // 记录解析历史
            $this->log_parse_history($result['data'], $video_url);
            
            // 扣除次数
            if (is_user_logged_in()) {
                VPP_Database::getInstance()->use_quota(get_current_user_id());
            }
            
            wp_send_json_success($result['data']);
        } else {
            wp_send_json_error($result['message']);
        }
    }
    
    private function validate_video_url($url) {
        $patterns = array(
            '/https?:\/\/(v\.douyin\.com|www\.douyin\.com\/video)/',
            '/https?:\/\/(www\.iesdouyin\.com)/',
            '/https?:\/\/(v\.huoshan\.com)/',
            '/https?:\/\/(www\.kuaishou\.com)/',
            '/https?:\/\/(v\.kuaishou\.com)/'
        );
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url)) {
                return true;
            }
        }
        
        return false;
    }
    
    private function check_user_quota() {
        $require_login = get_option('vpp_require_login', 1);
        $allow_guests = get_option('vpp_allow_guests', 0);
        
        // 如果用户未登录
        if (!is_user_logged_in()) {
            if ($require_login) {
                return array('success' => false, 'message' => '请登录后使用解析功能');
            }
            
            if (!$allow_guests) {
                return array('success' => false, 'message' => '游客暂时无法使用解析功能');
            }
            
            // 游客使用，检查IP限制
            $guest_usage = $this->check_guest_usage();
            if (!$guest_usage) {
                return array('success' => false, 'message' => '今日游客解析次数已用完');
            }
            
            return array('success' => true);
        }
        
        // 登录用户检查次数
        $user_id = get_current_user_id();
        $quota = VPP_Database::getInstance()->get_user_quota($user_id);
        
        $free_quota = get_option('vpp_free_quota', 5);
        $available_quota = $free_quota;
        
        if ($quota) {
            $available_quota = $quota->total_quota - $quota->used_quota;
        }
        
        if ($available_quota <= 0) {
            return array('success' => false, 'message' => '解析次数不足，请充值');
        }
        
        return array('success' => true);
    }
    
    private function check_guest_usage() {
        $ip = $this->get_client_ip();
        $transient_key = 'vpp_guest_' . md5($ip . date('Ymd'));
        $usage = get_transient($transient_key);
        
        if ($usage === false) {
            set_transient($transient_key, 1, DAY_IN_SECONDS);
            return true;
        }
        
        if ($usage < 3) { // 游客每天最多3次
            set_transient($transient_key, $usage + 1, DAY_IN_SECONDS);
            return true;
        }
        
        return false;
    }
    
    private function call_parse_api($video_url) {
        $api_key = get_option('vpp_api_key');
        
        if (empty($api_key)) {
            return array('success' => false, 'message' => 'API配置错误');
        }
        
        $client = new VPP_ApiClient($api_key);
        
        try {
            $response = $client->get($this->api_url, [
                'key' => $api_key,
                'url' => $video_url
            ]);
            
            if ($response['code'] == 200) {
                return array(
                    'success' => true,
                    'data' => $response['data']
                );
            } else {
                return array(
                    'success' => false,
                    'message' => $response['msg'] ?? '解析失败'
                );
            }
            
        } catch (Exception $e) {
            return array(
                'success' => false,
                'message' => 'API请求失败: ' . $e->getMessage()
            );
        }
    }
    
    private function log_parse_history($data, $original_url) {
        $log_data = array(
            'user_id' => get_current_user_id() ?: 0,
            'video_url' => $original_url,
            'video_title' => sanitize_text_field($data['work_title']),
            'video_cover' => esc_url_raw($data['work_cover']),
            'video_url_parsed' => esc_url_raw($data['work_url']),
            'ip_address' => $this->get_client_ip()
        );
        
        VPP_Database::getInstance()->log_parse_history($log_data);
    }
    
    public function handle_download_request() {
        // 代理下载实现
        $video_url = esc_url_raw($_GET['url']);
        $filename = sanitize_file_name($_GET['filename'] . '.mp4');
        
        // 安全验证
        if (!wp_verify_nonce($_GET['nonce'], 'vpp_download')) {
            wp_die('下载链接已失效');
        }
        
        // 设置下载头
        header('Content-Type: video/mp4');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        // 使用WP HTTP API获取视频流
        $response = wp_remote_get($video_url, array(
            'timeout' => 300,
            'stream' => true,
            'headers' => array(
                'Referer' => 'https://www.douyin.com/'
            )
        ));
        
        if (is_wp_error($response)) {
            wp_die('下载失败: ' . $response->get_error_message());
        }
        
        echo wp_remote_retrieve_body($response);
        exit;
    }
    
    private function get_client_ip() {
        $ip_keys = array('HTTP_X_FORWARDED_FOR', 'HTTP_CLIENT_IP', 'REMOTE_ADDR');
        
        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ip_list = explode(',', $ip);
                    $ip = trim($ip_list[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }
        
        return '0.0.0.0';
    }
}

class VPP_ApiClient {
    // 简化的API客户端实现
    // 完整实现参考您提供的官方示例
}