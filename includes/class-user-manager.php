<?php

class VPP_User_Manager {
    
    public function get_user_stats($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return false;
        }
        
        $quota = VPP()->get_database()->get_user_quota($user_id);
        $parse_history = VPP()->get_database()->get_parse_history_count($user_id);
        $payment_history = VPP()->get_database()->get_user_payment_history($user_id, 5);
        
        return array(
            'quota' => $quota,
            'total_parses' => $parse_history,
            'recent_payments' => $payment_history,
            'remaining_quota' => $quota->total_quota - $quota->used_quota
        );
    }
    
    public function init_free_quota_for_existing_users() {
        // 为现有用户初始化免费配额
        $free_quota = get_option('vpp_free_quota', 5);
        
        $users = get_users(array(
            'fields' => 'ID',
            'number' => -1
        ));
        
        foreach ($users as $user_id) {
            $current_quota = VPP()->get_database()->get_user_quota($user_id);
            if (!$current_quota) {
                VPP()->get_database()->update_user_quota($user_id, $free_quota);
            }
        }
    }
    
    public function get_user_parse_history($user_id = null, $page = 1, $per_page = 20) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        if (!$user_id) {
            return array();
        }
        
        $offset = ($page - 1) * $per_page;
        return VPP()->get_database()->get_parse_history($user_id, $per_page, $offset);
    }
    
    public function can_user_parse($user_id = null) {
        if (!$user_id) {
            $user_id = get_current_user_id();
        }
        
        $require_login = get_option('vpp_require_login', 1);
        
        // 未登录用户
        if (!$user_id) {
            if ($require_login) {
                return array('success' => false, 'message' => '请登录后使用解析功能');
            }
            
            $allow_guests = get_option('vpp_allow_guests', 0);
            if (!$allow_guests) {
                return array('success' => false, 'message' => '游客暂时无法使用解析功能');
            }
            
            // 检查游客限制
            return $this->check_guest_quota();
        }
        
        // 登录用户检查配额
        $quota = VPP()->get_database()->get_user_quota($user_id);
        $remaining = $quota->total_quota - $quota->used_quota;
        
        if ($remaining <= 0) {
            return array('success' => false, 'message' => '解析次数不足，请充值后使用');
        }
        
        return array('success' => true, 'remaining' => $remaining);
    }
    
    private function check_guest_quota() {
        $ip = $this->get_client_ip();
        $transient_key = 'vpp_guest_' . md5($ip . date('Ymd'));
        $usage = get_transient($transient_key);
        $daily_limit = get_option('vpp_guest_daily_limit', 3);
        
        if ($usage === false) {
            return array('success' => true, 'remaining' => $daily_limit);
        }
        
        $remaining = max(0, $daily_limit - $usage);
        if ($remaining <= 0) {
            return array('success' => false, 'message' => "游客每天最多使用{$daily_limit}次解析功能");
        }
        
        return array('success' => true, 'remaining' => $remaining);
    }
    
    public function add_quota_to_user($user_id, $quota_amount) {
        return VPP()->get_database()->update_user_quota($user_id, $quota_amount);
    }
    
    public function get_client_ip() {
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
    
    // AJAX处理函数
    public function get_user_stats() {
        if (!is_user_logged_in()) {
            wp_send_json_error('请先登录');
        }
        
        $stats = $this->get_user_stats(get_current_user_id());
        wp_send_json_success($stats);
    }
}