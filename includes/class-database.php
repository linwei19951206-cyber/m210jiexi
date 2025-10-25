<?php

class VPP_Database {
    
    public function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        $table_parse_history = $wpdb->prefix . 'vpp_parse_history';
        $table_payment_records = $wpdb->prefix . 'vpp_payment_records';
        $table_user_quota = $wpdb->prefix . 'vpp_user_quota';
        
        $sql = array();
        
        $sql[] = "CREATE TABLE $table_parse_history (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL DEFAULT 0,
            video_url text NOT NULL,
            video_title varchar(255) NOT NULL,
            video_cover varchar(255) NOT NULL,
            video_url_parsed text NOT NULL,
            parse_time datetime DEFAULT CURRENT_TIMESTAMP,
            ip_address varchar(45) NOT NULL,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY parse_time (parse_time)
        ) $charset_collate;";
        
        $sql[] = "CREATE TABLE $table_payment_records (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            order_id varchar(64) NOT NULL,
            amount decimal(10,2) NOT NULL,
            quota_amount int(11) NOT NULL,
            payment_status varchar(20) DEFAULT 'pending',
            payment_time datetime NULL,
            wechat_transaction_id varchar(64) DEFAULT '',
            create_time datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY order_id (order_id),
            KEY user_id (user_id),
            KEY payment_status (payment_status)
        ) $charset_collate;";
        
        $sql[] = "CREATE TABLE $table_user_quota (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            user_id bigint(20) NOT NULL,
            total_quota int(11) NOT NULL DEFAULT 0,
            used_quota int(11) NOT NULL DEFAULT 0,
            update_time datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY user_id (user_id)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }
    
    public function log_parse_history($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vpp_parse_history';
        
        return $wpdb->insert($table, $data);
    }
    
    public function get_parse_history($user_id, $limit = 20) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vpp_parse_history';
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d ORDER BY parse_time DESC LIMIT %d",
            $user_id, $limit
        ));
    }
    
    public function create_payment_record($data) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vpp_payment_records';
        
        return $wpdb->insert($table, $data);
    }
    
    public function update_payment_status($order_id, $status, $transaction_id = '') {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vpp_payment_records';
        
        $data = array(
            'payment_status' => $status,
            'payment_time' => current_time('mysql')
        );
        
        if ($transaction_id) {
            $data['wechat_transaction_id'] = $transaction_id;
        }
        
        return $wpdb->update($table, $data, array('order_id' => $order_id));
    }
    
    public function get_user_quota($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vpp_user_quota';
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table WHERE user_id = %d", $user_id
        ));
    }
    
    public function update_user_quota($user_id, $quota_to_add) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vpp_user_quota';
        $current = $this->get_user_quota($user_id);
        
        if ($current) {
            return $wpdb->update($table, 
                array('total_quota' => $current->total_quota + $quota_to_add),
                array('user_id' => $user_id)
            );
        } else {
            return $wpdb->insert($table, array(
                'user_id' => $user_id,
                'total_quota' => $quota_to_add,
                'used_quota' => 0
            ));
        }
    }
    
    public function use_quota($user_id) {
        global $wpdb;
        
        $table = $wpdb->prefix . 'vpp_user_quota';
        $current = $this->get_user_quota($user_id);
        
        if ($current && $current->total_quota > $current->used_quota) {
            return $wpdb->update($table,
                array('used_quota' => $current->used_quota + 1),
                array('user_id' => $user_id)
            );
        }
        
        return false;
    }
}