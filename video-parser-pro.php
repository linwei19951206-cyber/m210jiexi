<?php
/**
 * Plugin Name: Video Parser Pro
 * Plugin URI: https://yourdomain.com
 * Description: 专业的短视频解析下载插件
 * Version: 1.0.0
 * Author: Your Name
 * Text Domain: video-parser-pro
 * Requires at least: 5.0
 * Tested up to: 6.3
 * Requires PHP: 7.4
 */

// 防止直接访问
if (!defined('ABSPATH')) {
    exit;
}

// 定义常量
define('VPP_PLUGIN_URL', plugin_dir_url(__FILE__));
define('VPP_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('VPP_PLUGIN_VERSION', '1.0.0');

class VideoParserPro {
    
    private static $instance = null;
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        add_action('init', array($this, 'init'));
        register_activation_hook(__FILE__, array($this, 'activate'));
        
        // 注册设置
        add_action('admin_init', array($this, 'register_settings'));
    }
    
    public function init() {
        // 添加管理菜单
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // 注册短代码
        add_shortcode('video_parser', array($this, 'render_parser_shortcode'));
        
        // 注册AJAX处理
        $this->register_ajax_handlers();
        
        // 加载文本域
        load_plugin_textdomain('video-parser-pro', false, dirname(plugin_basename(__FILE__)) . '/languages');
    }
    
    public function add_admin_menu() {
        add_menu_page(
            '视频解析设置',           // 页面标题
            '视频解析',              // 菜单标题
            'manage_options',        // 权限
            'video-parser-pro',      // 菜单slug
            array($this, 'admin_settings_page'), // 回调函数
            'dashicons-video-alt3',  // 图标
            30                       // 位置
        );
        
        add_submenu_page(
            'video-parser-pro',
            '基本设置',
            '基本设置',
            'manage_options',
            'video-parser-pro',
            array($this, 'admin_settings_page')
        );
        
        add_submenu_page(
            'video-parser-pro',
            '解析记录',
            '解析记录',
            'manage_options',
            'video-parser-pro-records',
            array($this, 'records_page')
        );
    }
    
    public function register_settings() {
        register_setting('vpp_settings_group', 'vpp_api_key');
        register_setting('vpp_settings_group', 'vpp_free_quota');
        register_setting('vpp_settings_group', 'vpp_require_login');
        register_setting('vpp_settings_group', 'vpp_allow_guests');
        register_setting('vpp_settings_group', 'vpp_guest_daily_limit');
        register_setting('vpp_settings_group', 'vpp_price_per_100');
    }
    
    public function admin_settings_page() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <i class="dashicons dashicons-video-alt3"></i>
                视频解析专业版设置
            </h1>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>基本设置</h2>
                
                <form method="post" action="options.php">
                    <?php settings_fields('vpp_settings_group'); ?>
                    <?php do_settings_sections('vpp_settings_group'); ?>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">52API密钥</th>
                            <td>
                                <input type="password" name="vpp_api_key" value="<?php echo esc_attr(get_option('vpp_api_key')); ?>" class="regular-text" />
                                <p class="description">在 <a href="https://www.52api.cn/" target="_blank">52API控制台</a> 获取您的API密钥</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">免费解析次数</th>
                            <td>
                                <input type="number" name="vpp_free_quota" value="<?php echo esc_attr(get_option('vpp_free_quota', 5)); ?>" class="small-text" min="0" />
                                <p class="description">新用户注册后获得的免费解析次数</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">要求登录</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vpp_require_login" value="1" <?php checked(1, get_option('vpp_require_login', 1)); ?> />
                                    必须登录才能使用解析功能
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">允许游客使用</th>
                            <td>
                                <label>
                                    <input type="checkbox" name="vpp_allow_guests" value="1" <?php checked(1, get_option('vpp_allow_guests', 0)); ?> />
                                    允许未登录用户使用解析功能
                                </label>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">游客每日限制</th>
                            <td>
                                <input type="number" name="vpp_guest_daily_limit" value="<?php echo esc_attr(get_option('vpp_guest_daily_limit', 3)); ?>" class="small-text" min="1" />
                                <p class="description">每个IP地址每天最多可解析的次数</p>
                            </td>
                        </tr>
                        
                        <tr>
                            <th scope="row">100次解析价格</th>
                            <td>
                                <input type="number" name="vpp_price_per_100" value="<?php echo esc_attr(get_option('vpp_price_per_100', 10)); ?>" class="small-text" min="1" step="0.01" />
                                <span>元</span>
                                <p class="description">设置100次解析的售价，其他套餐会自动计算折扣</p>
                            </td>
                        </tr>
                    </table>
                    
                    <?php submit_button('保存设置'); ?>
                </form>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>短代码使用</h2>
                
                <h3>解析器短代码</h3>
                <code>[video_parser show_history="true" max_history="10"]</code>
                
                <h4>参数说明：</h4>
                <ul>
                    <li><code>show_history</code> - 是否显示解析历史 (true/false)</li>
                    <li><code>max_history</code> - 最大显示历史记录数</li>
                </ul>
                
                <h3>使用示例</h3>
                <p>在文章或页面中直接插入短代码：</p>
                <code>[video_parser]</code>
            </div>
            
            <div class="card" style="max-width: 800px; margin-top: 20px;">
                <h2>系统状态</h2>
                
                <table class="widefat">
                    <thead>
                        <tr>
                            <th>项目</th>
                            <th>状态</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>插件版本</td>
                            <td><?php echo VPP_PLUGIN_VERSION; ?></td>
                        </tr>
                        <tr>
                            <td>WordPress版本</td>
                            <td><?php echo get_bloginfo('version'); ?></td>
                        </tr>
                        <tr>
                            <td>PHP版本</td>
                            <td><?php echo phpversion(); ?></td>
                        </tr>
                        <tr>
                            <td>API密钥状态</td>
                            <td>
                                <?php if (get_option('vpp_api_key')): ?>
                                    <span style="color: green;">✓ 已配置</span>
                                <?php else: ?>
                                    <span style="color: red;">✗ 未配置</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <style>
        .card {
            background: #fff;
            border: 1px solid #ccd0d4;
            border-radius: 4px;
            padding: 20px;
        }
        .card h2 {
            margin-top: 0;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }
        .form-table th {
            width: 200px;
        }
        code {
            background: #f1f1f1;
            padding: 8px 12px;
            border-radius: 3px;
            display: inline-block;
            margin: 5px 0;
        }
        </style>
        <?php
    }
    
    public function records_page() {
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">
                <i class="dashicons dashicons-list-view"></i>
                解析记录
            </h1>
            
            <div class="card" style="margin-top: 20px;">
                <p>解析记录功能将在完整版本中提供。</p>
                <p>当前版本为基础版本，主要功能包括：</p>
                <ul>
                    <li>基本视频解析功能</li>
                    <li>用户权限管理</li>
                    <li>基础设置界面</li>
                    <li>短代码支持</li>
                </ul>
                <p>完整版本将包含：解析记录、支付系统、用户面板、数据统计等功能。</p>
            </div>
        </div>
        <?php
    }
    
    private function register_ajax_handlers() {
        $actions = array(
            'vpp_parse_video'
        );
        
        foreach ($actions as $action) {
            add_action('wp_ajax_' . $action, array($this, 'handle_ajax'));
            add_action('wp_ajax_nopriv_' . $action, array($this, 'handle_ajax'));
        }
    }
    
    public function handle_ajax() {
        $action = $_REQUEST['action'] ?? '';
        
        switch ($action) {
            case 'vpp_parse_video':
                $this->handle_parse_request();
                break;
            default:
                wp_send_json_error('未知操作');
        }
        
        wp_die();
    }
    
    private function handle_parse_request() {
        // 安全验证
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'vpp_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $video_url = sanitize_text_field($_POST['video_url'] ?? '');
        
        if (empty($video_url)) {
            wp_send_json_error('请输入视频链接');
        }
        
        // 检查API密钥
        $api_key = get_option('vpp_api_key');
        if (empty($api_key)) {
            wp_send_json_error('系统未配置API密钥，请联系管理员');
        }
        
        // 检查用户权限
        $can_parse = $this->check_user_quota();
        if (!$can_parse['success']) {
            wp_send_json_error($can_parse['message']);
        }
        
        // 模拟API调用（实际使用时需要替换为真实的API调用）
        $result = $this->call_parse_api($video_url, $api_key);
        
        if ($result['success']) {
            wp_send_json_success(array(
                'data' => $result['data'],
                'remaining_quota' => $this->get_user_remaining_quota()
            ));
        } else {
            wp_send_json_error($result['message']);
        }
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
            
            // 检查游客限制
            $guest_usage = $this->check_guest_usage();
            if (!$guest_usage['success']) {
                return array('success' => false, 'message' => $guest_usage['message']);
            }
            
            return array('success' => true);
        }
        
        // 登录用户检查免费次数
        $free_quota = get_option('vpp_free_quota', 5);
        $user_parse_count = $this->get_user_parse_count(get_current_user_id());
        
        if ($user_parse_count >= $free_quota) {
            return array('success' => false, 'message' => '免费解析次数已用完');
        }
        
        return array('success' => true);
    }
    
    private function check_guest_usage() {
        $ip = $this->get_client_ip();
        $transient_key = 'vpp_guest_' . md5($ip . date('Ymd'));
        $usage = get_transient($transient_key);
        $daily_limit = get_option('vpp_guest_daily_limit', 3);
        
        if ($usage === false) {
            set_transient($transient_key, 1, DAY_IN_SECONDS);
            return array('success' => true, 'usage' => 1);
        }
        
        if ($usage < $daily_limit) {
            set_transient($transient_key, $usage + 1, DAY_IN_SECONDS);
            return array('success' => true, 'usage' => $usage + 1);
        }
        
        return array('success' => false, 'message' => "游客每天最多使用{$daily_limit}次解析功能");
    }
    
    private function call_parse_api($video_url, $api_key) {
        // 这里应该是真实的API调用
        // 暂时返回模拟数据
        
        $supported_platforms = array(
            'douyin.com' => '抖音',
            'iesdouyin.com' => '抖音', 
            'huoshan.com' => '火山小视频',
            'kuaishou.com' => '快手'
        );
        
        $platform = '其他平台';
        foreach ($supported_platforms as $domain => $name) {
            if (strpos($video_url, $domain) !== false) {
                $platform = $name;
                break;
            }
        }
        
        // 模拟API响应
        return array(
            'success' => true,
            'data' => array(
                'work_title' => "来自{$platform}的视频 - " . date('Y-m-d H:i:s'),
                'work_cover' => 'https://via.placeholder.com/300x200?text=Video+Cover',
                'work_url' => 'https://example.com/sample-video.mp4',
                'platform' => $platform
            )
        );
    }
    
    private function get_user_parse_count($user_id) {
        // 简化版本，实际应该从数据库查询
        return 0;
    }
    
    private function get_user_remaining_quota() {
        if (!is_user_logged_in()) {
            $ip = $this->get_client_ip();
            $transient_key = 'vpp_guest_' . md5($ip . date('Ymd'));
            $usage = get_transient($transient_key) ?: 0;
            $daily_limit = get_option('vpp_guest_daily_limit', 3);
            return max(0, $daily_limit - $usage);
        }
        
        $free_quota = get_option('vpp_free_quota', 5);
        $used = $this->get_user_parse_count(get_current_user_id());
        return max(0, $free_quota - $used);
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
        
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
    
    public function render_parser_shortcode($atts) {
        $atts = shortcode_atts(array(
            'show_history' => 'true',
            'max_history' => '10'
        ), $atts);
        
        ob_start();
        ?>
        <div class="m210-parser-container">
            <!-- 用户状态栏 -->
            <?php if (is_user_logged_in()): ?>
            <div class="m210-user-status-bar">
                <div class="m210-user-info">
                    <i class="fas fa-user"></i>
                    <span>欢迎，<?php echo wp_get_current_user()->display_name; ?></span>
                </div>
                <div class="m210-quota-info">
                    <span class="m210-quota-label">剩余解析次数：</span>
                    <span class="m210-quota-count"><?php echo $this->get_user_remaining_quota(); ?></span>
                </div>
            </div>
            <?php elseif (get_option('vpp_require_login', 1)): ?>
            <div class="m210-login-prompt">
                <i class="fas fa-exclamation-triangle"></i>
                <span>请登录后使用解析功能</span>
                <a href="<?php echo wp_login_url(get_permalink()); ?>" class="m210-login-btn">立即登录</a>
            </div>
            <?php endif; ?>

            <div class="m210-parser-main">
                <div class="m210-parser-header">
                    <h2 class="m210-title">
                        <i class="fas fa-video"></i>
                        短视频解析下载
                    </h2>
                    <p class="m210-subtitle">支持抖音、快手、火山等平台视频解析</p>
                </div>

                <div class="m210-input-section">
                    <div class="m210-input-group">
                        <input type="text" 
                               id="m210-video-url" 
                               class="m210-url-input" 
                               placeholder="请粘贴短视频分享链接，例如：https://v.douyin.com/xxxxx/"
                               value="" />
                        <button type="button" 
                                id="m210-parse-btn" 
                                class="m210-parse-btn">
                            <i class="fas fa-bolt"></i>
                            立即解析
                        </button>
                    </div>
                    <div class="m210-input-tips">
                        <i class="fas fa-lightbulb"></i>
                        <span>支持平台：抖音、快手、火山小视频、西瓜视频等</span>
                    </div>
                </div>

                <div id="m210-result-area" class="m210-result-area" style="display: none;">
                    <div class="m210-result-header">
                        <h3 class="m210-result-title">
                            <i class="fas fa-check-circle"></i>
                            解析成功
                        </h3>
                    </div>
                    <div class="m210-video-preview">
                        <div class="m210-video-info">
                            <h4 id="m210-video-title" class="m210-video-title"></h4>
                            <div class="m210-video-meta">
                                <span class="m210-platform" id="m210-platform">
                                    <i class="fas fa-mobile-alt"></i> 平台
                                </span>
                            </div>
                            <div class="m210-download-section">
                                <a href="#" id="m210-download-btn" class="m210-download-btn">
                                    <i class="fas fa-download"></i>
                                    下载视频
                                </a>
                                <a href="#" id="m210-direct-link" class="m210-direct-link" target="_blank">
                                    <i class="fas fa-external-link-alt"></i>
                                    直接访问
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="m210-error-area" class="m210-error-area" style="display: none;">
                    <div class="m210-error-content">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="m210-error-text">
                            <h4>解析失败</h4>
                            <p id="m210-error-message"></p>
                        </div>
                    </div>
                </div>

                <div id="m210-loading-area" class="m210-loading-area" style="display: none;">
                    <div class="m210-loading-spinner">
                        <div class="m210-spinner"></div>
                        <p>正在解析视频链接，请稍候...</p>
                    </div>
                </div>
            </div>
        </div>

        <script>
        jQuery(document).ready(function($) {
            $('#m210-parse-btn').on('click', function() {
                var $btn = $(this);
                var $input = $('#m210-video-url');
                var videoUrl = $input.val().trim();

                if (!videoUrl) {
                    showError('请输入视频链接');
                    return;
                }

                showLoading();
                $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 解析中...');

                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'vpp_parse_video',
                        video_url: videoUrl,
                        nonce: '<?php echo wp_create_nonce('vpp_nonce'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            showResult(response.data);
                        } else {
                            showError(response.data);
                        }
                    },
                    error: function() {
                        showError('网络错误，请稍后重试');
                    },
                    complete: function() {
                        hideLoading();
                        $btn.prop('disabled', false).html('<i class="fas fa-bolt"></i> 立即解析');
                    }
                });
            });

            function showLoading() {
                hideAll();
                $('#m210-loading-area').show();
            }

            function hideLoading() {
                $('#m210-loading-area').hide();
            }

            function showResult(data) {
                hideAll();
                $('#m210-video-title').text(data.work_title);
                $('#m210-platform').html('<i class="fas fa-mobile-alt"></i> ' + (data.platform || '未知平台'));
                $('#m210-direct-link').attr('href', data.work_url);
                $('#m210-download-btn').attr('href', data.work_url);
                $('#m210-result-area').show();
            }

            function showError(message) {
                hideAll();
                $('#m210-error-message').text(message);
                $('#m210-error-area').show();
            }

            function hideAll() {
                $('#m210-result-area').hide();
                $('#m210-error-area').hide();
                $('#m210-loading-area').hide();
            }
        });
        </script>

        <style>
        .m210-parser-container {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 0 auto;
        }
        .m210-user-status-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 25px;
        }
        .m210-login-prompt {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #FFF3CD;
            border: 1px solid #FFEAA7;
            color: #856404;
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
        }
        .m210-login-btn {
            background: #28a745;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
        }
        .m210-parser-main {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 4px 25px rgba(0, 0, 0, 0.08);
        }
        .m210-title {
            text-align: center;
            color: #2D3748;
            margin-bottom: 10px;
        }
        .m210-subtitle {
            text-align: center;
            color: #718096;
            margin-bottom: 30px;
        }
        .m210-input-group {
            display: flex;
            gap: 12px;
            margin-bottom: 15px;
        }
        .m210-url-input {
            flex: 1;
            padding: 16px 20px;
            border: 2px solid #E2E8F0;
            border-radius: 12px;
            font-size: 16px;
        }
        .m210-parse-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 16px 32px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            min-width: 140px;
        }
        .m210-parse-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
        .m210-input-tips {
            display: flex;
            align-items: center;
            gap: 8px;
            color: #718096;
            font-size: 14px;
        }
        .m210-result-area, .m210-error-area, .m210-loading-area {
            background: #F8F9FF;
            border: 2px solid #E9ECFF;
            border-radius: 16px;
            padding: 25px;
            margin-top: 20px;
        }
        .m210-error-area {
            background: #FEF2F2;
            border-color: #FECACA;
        }
        .m210-result-title {
            color: #28a745;
            margin-top: 0;
        }
        .m210-error-content {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .m210-download-btn, .m210-direct-link {
            background: #4CAF50;
            color: white;
            padding: 12px 24px;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin-right: 12px;
        }
        .m210-direct-link {
            background: #2196F3;
        }
        .m210-spinner {
            width: 50px;
            height: 50px;
            border: 4px solid #E2E8F0;
            border-left: 4px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        </style>
        <?php
        return ob_get_clean();
    }
    
    public function activate() {
        // 设置默认选项
        $defaults = array(
            'vpp_free_quota' => 5,
            'vpp_require_login' => 1,
            'vpp_allow_guests' => 0,
            'vpp_guest_daily_limit' => 3,
            'vpp_price_per_100' => 10
        );
        
        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
        
        update_option('vpp_plugin_activated', time());
    }
}

// 安全启动插件
function vpp_init_plugin() {
    try {
        VideoParserPro::getInstance();
    } catch (Exception $e) {
        error_log('Video Parser Pro 启动失败: ' . $e->getMessage());
    }
}
add_action('plugins_loaded', 'vpp_init_plugin');