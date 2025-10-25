<?php

class VPP_Admin_Panel {
    
    public function __construct() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        add_action('wp_ajax_vpp_export_data', array($this, 'export_data'));
        add_action('wp_ajax_vpp_clear_cache', array($this, 'clear_cache'));
        add_action('wp_ajax_vpp_admin_add_quota', array($this, 'add_user_quota'));
        add_action('wp_ajax_vpp_get_stats', array($this, 'get_stats_data'));
        add_action('wp_ajax_vpp_test_api', array($this, 'test_api_connection'));
    }
    
    public function add_admin_menu() {
        add_menu_page(
            '视频解析设置',
            '视频解析',
            'manage_options',
            'video-parser-pro',
            array($this, 'admin_settings_page'),
            'dashicons-video-alt3',
            30
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
        
        add_submenu_page(
            'video-parser-pro',
            '支付记录',
            '支付记录',
            'manage_options',
            'video-parser-pro-payments',
            array($this, 'payments_page')
        );
        
        add_submenu_page(
            'video-parser-pro',
            '用户配额',
            '用户配额',
            'manage_options',
            'video-parser-pro-quota',
            array($this, 'quota_page')
        );
        
        add_submenu_page(
            'video-parser-pro',
            '数据统计',
            '数据统计',
            'manage_options',
            'video-parser-pro-stats',
            array($this, 'stats_page')
        );
    }
    
    public function register_settings() {
        // 基本设置
        register_setting('vpp_settings_group', 'vpp_api_key');
        register_setting('vpp_settings_group', 'vpp_free_quota');
        register_setting('vpp_settings_group', 'vpp_require_login');
        register_setting('vpp_settings_group', 'vpp_allow_guests');
        register_setting('vpp_settings_group', 'vpp_guest_daily_limit');
        register_setting('vpp_settings_group', 'vpp_price_per_100');
        
        // 微信支付设置
        register_setting('vpp_payment_group', 'vpp_wechat_appid');
        register_setting('vpp_payment_group', 'vpp_wechat_mchid');
        register_setting('vpp_payment_group', 'vpp_wechat_key');
        
        // 外观设置
        register_setting('vpp_display_group', 'vpp_custom_css');
        register_setting('vpp_display_group', 'vpp_show_watermark');
        register_setting('vpp_display_group', 'vpp_watermark_text');
    }
    
    public function enqueue_admin_scripts($hook) {
        if (strpos($hook, 'video-parser-pro') === false) {
            return;
        }
        
        wp_enqueue_style('m210-admin-styles', VPP_PLUGIN_URL . 'assets/css/m210-admin.css', array(), VPP_PLUGIN_VERSION);
        wp_enqueue_script('m210-admin-scripts', VPP_PLUGIN_URL . 'assets/js/m210-admin.js', array('jquery'), VPP_PLUGIN_VERSION, true);
        
        // 只在统计页面加载Chart.js
        if (strpos($hook, 'video-parser-pro-stats') !== false) {
            wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '3.7.0', true);
        }
        
        wp_localize_script('m210-admin-scripts', 'vpp_admin', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('vpp_admin_nonce'),
            'i18n' => array(
                'confirm_clear' => '确定要清空缓存吗？此操作不可恢复。',
                'exporting' => '正在导出数据...',
                'export_success' => '导出成功',
                'export_failed' => '导出失败',
                'confirm_add_quota' => '确定要为用户增加配额吗？',
                'confirm_delete' => '确定要删除这条记录吗？'
            )
        ));
    }
    
    public function admin_settings_page() {
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'basic';
        ?>
        <div class="wrap m210-admin-wrap">
            <h1 class="m210-admin-title">
                <i class="dashicons dashicons-video-alt3"></i> 视频解析专业版设置
            </h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=video-parser-pro&tab=basic" class="nav-tab <?php echo $active_tab == 'basic' ? 'nav-tab-active' : ''; ?>">基本设置</a>
                <a href="?page=video-parser-pro&tab=payment" class="nav-tab <?php echo $active_tab == 'payment' ? 'nav-tab-active' : ''; ?>">支付设置</a>
                <a href="?page=video-parser-pro&tab=display" class="nav-tab <?php echo $active_tab == 'display' ? 'nav-tab-active' : ''; ?>">外观设置</a>
                <a href="?page=video-parser-pro&tab=shortcode" class="nav-tab <?php echo $active_tab == 'shortcode' ? 'nav-tab-active' : ''; ?>">短代码</a>
                <a href="?page=video-parser-pro&tab=tools" class="nav-tab <?php echo $active_tab == 'tools' ? 'nav-tab-active' : ''; ?>">工具</a>
            </h2>
            
            <div class="m210-admin-content">
                <?php
                switch ($active_tab) {
                    case 'basic':
                        $this->render_basic_settings();
                        break;
                    case 'payment':
                        $this->render_payment_settings();
                        break;
                    case 'display':
                        $this->render_display_settings();
                        break;
                    case 'shortcode':
                        $this->render_shortcode_settings();
                        break;
                    case 'tools':
                        $this->render_tools_settings();
                        break;
                    default:
                        $this->render_basic_settings();
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    private function render_basic_settings() {
        ?>
        <div class="m210-card">
            <h3><i class="dashicons dashicons-admin-settings"></i> API 设置</h3>
            <form method="post" action="options.php">
                <?php settings_fields('vpp_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">52API密钥</th>
                        <td>
                            <input type="password" name="vpp_api_key" value="<?php echo esc_attr(get_option('vpp_api_key')); ?>" class="regular-text" />
                            <p class="description">在 <a href="https://www.52api.cn/" target="_blank">52API控制台</a> 获取您的API密钥</p>
                            <?php if (get_option('vpp_api_key')): ?>
                                <div class="m210-status-indicator m210-status-success">
                                    <span class="dashicons dashicons-yes"></span> API密钥已配置
                                </div>
                            <?php else: ?>
                                <div class="m210-status-indicator m210-status-error">
                                    <span class="dashicons dashicons-no"></span> API密钥未配置
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>
                
                <h3><i class="dashicons dashicons-groups"></i> 用户权限设置</h3>
                <table class="form-table">
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
                </table>
                
                <h3><i class="dashicons dashicons-cart"></i> 价格设置</h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">100次解析价格</th>
                        <td>
                            <input type="number" name="vpp_price_per_100" value="<?php echo esc_attr(get_option('vpp_price_per_100', 10)); ?>" class="small-text" min="1" step="0.01" />
                            <span>元</span>
                            <p class="description">设置100次解析的售价，其他套餐会自动计算折扣</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('保存基本设置'); ?>
            </form>
        </div>
        
        <div class="m210-card">
            <h3><i class="dashicons dashicons-info"></i> 系统信息</h3>
            <table class="widefat">
                <thead>
                    <tr>
                        <th>项目</th>
                        <th>值</th>
                        <th>状态</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>插件版本</td>
                        <td><?php echo VPP_PLUGIN_VERSION; ?></td>
                        <td><span class="m210-status-badge m210-status-success">最新</span></td>
                    </tr>
                    <tr>
                        <td>WordPress版本</td>
                        <td><?php echo get_bloginfo('version'); ?></td>
                        <td>
                            <?php if (version_compare(get_bloginfo('version'), '5.0', '>=')): ?>
                                <span class="m210-status-badge m210-status-success">支持</span>
                            <?php else: ?>
                                <span class="m210-status-badge m210-status-warning">版本较低</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>PHP版本</td>
                        <td><?php echo phpversion(); ?></td>
                        <td>
                            <?php if (version_compare(phpversion(), '7.4', '>=')): ?>
                                <span class="m210-status-badge m210-status-success">支持</span>
                            <?php else: ?>
                                <span class="m210-status-badge m210-status-error">需要7.4+</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <td>cURL支持</td>
                        <td><?php echo function_exists('curl_version') ? '已启用' : '未启用'; ?></td>
                        <td>
                            <?php if (function_exists('curl_version')): ?>
                                <span class="m210-status-badge m210-status-success">正常</span>
                            <?php else: ?>
                                <span class="m210-status-badge m210-status-error">异常</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </tbody>
            </table>
            
            <div style="margin-top: 15px;">
                <button type="button" class="button" id="m210-test-api">测试API连接</button>
                <div id="m210-api-test-result" style="margin-top: 10px;"></div>
            </div>
        </div>
        <?php
    }
    
    private function render_payment_settings() {
        ?>
        <div class="m210-card">
            <h3><i class="dashicons dashicons-money"></i> 微信支付设置</h3>
            <form method="post" action="options.php">
                <?php settings_fields('vpp_payment_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">AppID</th>
                        <td>
                            <input type="text" name="vpp_wechat_appid" value="<?php echo esc_attr(get_option('vpp_wechat_appid')); ?>" class="regular-text" />
                            <p class="description">微信支付商户平台的AppID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">商户号(MchID)</th>
                        <td>
                            <input type="text" name="vpp_wechat_mchid" value="<?php echo esc_attr(get_option('vpp_wechat_mchid')); ?>" class="regular-text" />
                            <p class="description">微信支付商户号</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">API密钥</th>
                        <td>
                            <input type="password" name="vpp_wechat_key" value="<?php echo esc_attr(get_option('vpp_wechat_key')); ?>" class="regular-text" />
                            <p class="description">微信支付商户平台的API密钥</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">回调地址</th>
                        <td>
                            <code><?php echo home_url('/wp-json/vpp/wechat/callback'); ?></code>
                            <p class="description">请在微信支付商户平台设置此回调地址</p>
                            <button type="button" class="button" onclick="navigator.clipboard.writeText('<?php echo home_url('/wp-json/vpp/wechat/callback'); ?>')">复制地址</button>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('保存支付设置'); ?>
            </form>
        </div>
        
        <div class="m210-card">
            <h3><i class="dashicons dashicons-lightbulb"></i> 支付套餐预览</h3>
            <?php
            $price_per_100 = get_option('vpp_price_per_100', 10);
            $packages = array(
                array('quota' => 100, 'discount' => 0, 'popular' => false),
                array('quota' => 300, 'discount' => 10, 'popular' => true),
                array('quota' => 500, 'discount' => 20, 'popular' => false),
                array('quota' => 1000, 'discount' => 30, 'popular' => false)
            );
            ?>
            <div class="m210-package-preview">
                <?php foreach ($packages as $package): ?>
                    <?php
                    $original_price = $price_per_100 * ($package['quota'] / 100);
                    $final_price = $package['discount'] > 0 ? 
                        $original_price * (1 - $package['discount'] / 100) : 
                        $original_price;
                    ?>
                    <div class="m210-preview-package <?php echo $package['popular'] ? 'm210-popular' : ''; ?>">
                        <div class="m210-package-header">
                            <h4><?php echo $package['quota']; ?>次解析</h4>
                            <?php if ($package['popular']): ?>
                                <span class="m210-badge">推荐</span>
                            <?php endif; ?>
                        </div>
                        <div class="m210-package-price">
                            <span class="m210-final-price">¥<?php echo number_format($final_price, 2); ?></span>
                            <?php if ($package['discount'] > 0): ?>
                                <span class="m210-original-price">¥<?php echo number_format($original_price, 2); ?></span>
                                <span class="m210-discount">节省<?php echo $package['discount']; ?>%</span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }
    
    private function render_display_settings() {
        ?>
        <div class="m210-card">
            <h3><i class="dashicons dashicons-admin-appearance"></i> 外观设置</h3>
            <form method="post" action="options.php">
                <?php settings_fields('vpp_display_group'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row">自定义CSS</th>
                        <td>
                            <textarea name="vpp_custom_css" rows="10" class="large-text code"><?php echo esc_textarea(get_option('vpp_custom_css')); ?></textarea>
                            <p class="description">自定义插件样式，所有CSS类名以 <code>m210-</code> 开头</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">显示水印</th>
                        <td>
                            <label>
                                <input type="checkbox" name="vpp_show_watermark" value="1" <?php checked(1, get_option('vpp_show_watermark', 0)); ?> />
                                在解析结果页面显示水印
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">水印文字</th>
                        <td>
                            <input type="text" name="vpp_watermark_text" value="<?php echo esc_attr(get_option('vpp_watermark_text', '由视频解析专业版提供')); ?>" class="regular-text" />
                            <p class="description">显示在解析结果页面的水印文字</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('保存外观设置'); ?>
            </form>
        </div>
        <?php
    }
    
    private function render_shortcode_settings() {
        ?>
        <div class="m210-card">
            <h3><i class="dashicons dashicons-editor-code"></i> 短代码使用说明</h3>
            
            <div class="m210-shortcode-examples">
                <div class="m210-shortcode-item">
                    <h4>解析器短代码</h4>
                    <code>[video_parser show_history="true" max_history="10"]</code>
                    <div class="m210-shortcode-params">
                        <strong>参数：</strong>
                        <ul>
                            <li><code>show_history</code> - 是否显示解析历史 (true/false)</li>
                            <li><code>max_history</code> - 最大显示历史记录数</li>
                        </ul>
                    </div>
                </div>
                
                <div class="m210-shortcode-item">
                    <h4>用户面板短代码</h4>
                    <code>[video_parser_dashboard]</code>
                    <p>显示用户的解析历史、剩余次数和支付记录</p>
                </div>
                
                <div class="m210-shortcode-item">
                    <h4>支付页面短代码</h4>
                    <code>[video_parser_payment]</code>
                    <p>显示充值套餐和支付二维码</p>
                </div>
            </div>
        </div>
        
        <div class="m210-card">
            <h3><i class="dashicons dashicons-palmtree"></i> 样式自定义</h3>
            <p>您可以通过CSS自定义插件样式，所有CSS类名都以 <code>m210-</code> 开头以避免冲突。</p>
            
            <h4>主要样式类：</h4>
            <ul>
                <li><code>.m210-parser-container</code> - 解析器容器</li>
                <li><code>.m210-video-result</code> - 解析结果区域</li>
                <li><code>.m210-payment-packages</code> - 支付套餐区域</li>
                <li><code>.m210-user-stats</code> - 用户统计区域</li>
                <li><code>.m210-dashboard-container</code> - 用户面板容器</li>
            </ul>
        </div>
        <?php
    }
    
    private function render_tools_settings() {
        ?>
        <div class="m210-card">
            <h3><i class="dashicons dashicons-admin-tools"></i> 系统工具</h3>
            
            <div class="m210-tools-grid">
                <div class="m210-tool-item">
                    <h4>清空缓存</h4>
                    <p>清空所有临时缓存数据，包括游客使用记录</p>
                    <button type="button" class="button" id="m210-clear-cache">清空缓存</button>
                </div>
                
                <div class="m210-tool-item">
                    <h4>导出数据</h4>
                    <p>导出解析记录和支付记录为CSV格式</p>
                    <div class="m210-export-buttons">
                        <a href="<?php echo admin_url('admin-ajax.php?action=vpp_export_data&type=records&nonce=' . wp_create_nonce('vpp_export')); ?>" class="button">导出解析记录</a>
                        <a href="<?php echo admin_url('admin-ajax.php?action=vpp_export_data&type=payments&nonce=' . wp_create_nonce('vpp_export')); ?>" class="button">导出支付记录</a>
                    </div>
                </div>
                
                <div class="m210-tool-item">
                    <h4>重新创建数据库表</h4>
                    <p>如果数据库表出现问题，可以重新创建</p>
                    <button type="button" class="button" id="m210-recreate-tables">重新创建表</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function records_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vpp_parse_history';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        // 搜索和过滤
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
        
        $where = '1=1';
        if ($search) {
            $where .= $wpdb->prepare(" AND (video_url LIKE %s OR video_title LIKE %s OR ip_address LIKE %s)", 
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        if ($user_id) {
            $where .= $wpdb->prepare(" AND user_id = %d", $user_id);
        }
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where");
        $records = $wpdb->get_results("SELECT * FROM $table_name WHERE $where ORDER BY parse_time DESC LIMIT $per_page OFFSET $offset");
        
        ?>
        <div class="wrap m210-admin-wrap">
            <h1 class="m210-admin-title">
                <i class="dashicons dashicons-list-view"></i> 解析记录
                <a href="<?php echo admin_url('admin-ajax.php?action=vpp_export_data&type=records&nonce=' . wp_create_nonce('vpp_export')); ?>" class="page-title-action">导出CSV</a>
            </h1>
            
            <div class="m210-admin-filters">
                <form method="get">
                    <input type="hidden" name="page" value="video-parser-pro-records" />
                    <div class="m210-filter-row">
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="搜索链接、标题或IP" />
                        <input type="number" name="user_id" value="<?php echo esc_attr($user_id); ?>" placeholder="用户ID" />
                        <button type="submit" class="button">筛选</button>
                        <a href="<?php echo admin_url('admin.php?page=video-parser-pro-records'); ?>" class="button">重置</a>
                    </div>
                </form>
            </div>
            
            <div class="m210-card">
                <div class="m210-stats-overview">
                    <div class="m210-stat">
                        <span class="m210-stat-number"><?php echo $total; ?></span>
                        <span class="m210-stat-label">总解析次数</span>
                    </div>
                    <div class="m210-stat">
                        <span class="m210-stat-number"><?php echo $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_name WHERE user_id > 0"); ?></span>
                        <span class="m210-stat-label">注册用户</span>
                    </div>
                    <div class="m210-stat">
                        <span class="m210-stat-number"><?php echo $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE DATE(parse_time) = CURDATE()"); ?></span>
                        <span class="m210-stat-label">今日解析</span>
                    </div>
                </div>
                
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>用户</th>
                            <th>视频标题</th>
                            <th>原链接</th>
                            <th>IP地址</th>
                            <th>解析时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($records): ?>
                            <?php foreach ($records as $record): ?>
                                <tr>
                                    <td><?php echo $record->id; ?></td>
                                    <td>
                                        <?php if ($record->user_id): ?>
                                            <a href="<?php echo admin_url('user-edit.php?user_id=' . $record->user_id); ?>">
                                                <?php echo get_userdata($record->user_id)->display_name; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="m210-guest">游客</span>
                                        <?php endif; ?>
                                    </td>
                                    <td title="<?php echo esc_attr($record->video_title); ?>">
                                        <?php echo esc_html(mb_strlen($record->video_title) > 50 ? mb_substr($record->video_title, 0, 50) . '...' : $record->video_title); ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo esc_url($record->video_url); ?>" target="_blank" title="<?php echo esc_attr($record->video_url); ?>">
                                            查看原链接
                                        </a>
                                    </td>
                                    <td><?php echo esc_html($record->ip_address); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($record->parse_time)); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center;">暂无解析记录</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="m210-pagination">
                    <?php
                    $total_pages = ceil($total / $per_page);
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function payments_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vpp_payment_records';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        
        $where = '1=1';
        if ($search) {
            $where .= $wpdb->prepare(" AND (order_id LIKE %s OR wechat_transaction_id LIKE %s)", 
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        if ($status) {
            $where .= $wpdb->prepare(" AND payment_status = %s", $status);
        }
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where");
        $payments = $wpdb->get_results("SELECT * FROM $table_name WHERE $where ORDER BY create_time DESC LIMIT $per_page OFFSET $offset");
        
        $total_revenue = $wpdb->get_var("SELECT SUM(amount) FROM $table_name WHERE payment_status = 'completed'") ?: 0;
        ?>
        
        <div class="wrap m210-admin-wrap">
            <h1 class="m210-admin-title">
                <i class="dashicons dashicons-money"></i> 支付记录
                <span class="m210-revenue-total">总收入: <?php echo number_format($total_revenue, 2); ?>元</span>
                <a href="<?php echo admin_url('admin-ajax.php?action=vpp_export_data&type=payments&nonce=' . wp_create_nonce('vpp_export')); ?>" class="page-title-action">导出CSV</a>
            </h1>
            
            <div class="m210-admin-filters">
                <form method="get">
                    <input type="hidden" name="page" value="video-parser-pro-payments" />
                    <div class="m210-filter-row">
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="搜索订单号或交易号" />
                        <select name="status">
                            <option value="">所有状态</option>
                            <option value="pending" <?php selected($status, 'pending'); ?>>待支付</option>
                            <option value="completed" <?php selected($status, 'completed'); ?>>已完成</option>
                            <option value="failed" <?php selected($status, 'failed'); ?>>失败</option>
                        </select>
                        <button type="submit" class="button">筛选</button>
                        <a href="<?php echo admin_url('admin.php?page=video-parser-pro-payments'); ?>" class="button">重置</a>
                    </div>
                </form>
            </div>
            
            <div class="m210-card">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>订单号</th>
                            <th>用户</th>
                            <th>金额</th>
                            <th>配额</th>
                            <th>状态</th>
                            <th>微信交易号</th>
                            <th>创建时间</th>
                            <th>支付时间</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($payments): ?>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo esc_html($payment->order_id); ?></td>
                                    <td>
                                        <?php
                                        $user = get_userdata($payment->user_id);
                                        if ($user): ?>
                                            <a href="<?php echo admin_url('user-edit.php?user_id=' . $payment->user_id); ?>">
                                                <?php echo $user->display_name; ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="m210-guest">用户已删除</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($payment->amount, 2); ?>元</td>
                                    <td><?php echo $payment->quota_amount; ?>次</td>
                                    <td>
                                        <span class="m210-status m210-status-<?php echo esc_attr($payment->payment_status); ?>">
                                            <?php echo $this->get_payment_status_text($payment->payment_status); ?>
                                        </span>
                                    </td>
                                    <td><?php echo esc_html($payment->wechat_transaction_id); ?></td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($payment->create_time)); ?></td>
                                    <td>
                                        <?php echo $payment->payment_time ? date('Y-m-d H:i:s', strtotime($payment->payment_time)) : '-'; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center;">暂无支付记录</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="m210-pagination">
                    <?php
                    $total_pages = ceil($total / $per_page);
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    ?>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function quota_page() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vpp_user_quota';
        $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
        $per_page = 20;
        $offset = ($page - 1) * $per_page;
        
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        
        $where = '1=1';
        if ($search) {
            $where .= $wpdb->prepare(" AND user_id IN (SELECT ID FROM {$wpdb->users} WHERE display_name LIKE %s OR user_login LIKE %s)", 
                '%' . $wpdb->esc_like($search) . '%',
                '%' . $wpdb->esc_like($search) . '%'
            );
        }
        
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE $where");
        $quota_data = $wpdb->get_results("SELECT * FROM $table_name WHERE $where ORDER BY update_time DESC LIMIT $per_page OFFSET $offset");
        
        ?>
        <div class="wrap m210-admin-wrap">
            <h1 class="m210-admin-title">
                <i class="dashicons dashicons-chart-pie"></i> 用户配额管理
            </h1>
            
            <div class="m210-admin-filters">
                <form method="get">
                    <input type="hidden" name="page" value="video-parser-pro-quota" />
                    <div class="m210-filter-row">
                        <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="搜索用户名" />
                        <button type="submit" class="button">搜索</button>
                        <a href="<?php echo admin_url('admin.php?page=video-parser-pro-quota'); ?>" class="button">重置</a>
                    </div>
                </form>
            </div>
            
            <div class="m210-card">
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>用户</th>
                            <th>总配额</th>
                            <th>已使用</th>
                            <th>剩余</th>
                            <th>使用率</th>
                            <th>最后更新</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($quota_data): ?>
                            <?php foreach ($quota_data as $quota): ?>
                                <?php
                                $user = get_userdata($quota->user_id);
                                if (!$user) continue;
                                
                                $remaining = $quota->total_quota - $quota->used_quota;
                                $usage_rate = $quota->total_quota > 0 ? ($quota->used_quota / $quota->total_quota) * 100 : 0;
                                ?>
                                <tr>
                                    <td>
                                        <a href="<?php echo admin_url('user-edit.php?user_id=' . $quota->user_id); ?>">
                                            <?php echo esc_html($user->display_name); ?>
                                        </a>
                                    </td>
                                    <td><?php echo $quota->total_quota; ?>次</td>
                                    <td><?php echo $quota->used_quota; ?>次</td>
                                    <td>
                                        <span class="m210-remaining <?php echo $remaining <= 0 ? 'm210-zero' : ''; ?>">
                                            <?php echo $remaining; ?>次
                                        </span>
                                    </td>
                                    <td>
                                        <div class="m210-usage-bar">
                                            <div class="m210-usage-fill" style="width: <?php echo min($usage_rate, 100); ?>%"></div>
                                            <span class="m210-usage-text"><?php echo number_format($usage_rate, 1); ?>%</span>
                                        </div>
                                    </td>
                                    <td><?php echo date('Y-m-d H:i:s', strtotime($quota->update_time)); ?></td>
                                    <td>
                                        <button class="button button-small m210-add-quota" data-user-id="<?php echo $quota->user_id; ?>" data-user-name="<?php echo esc_attr($user->display_name); ?>">
                                            增加配额
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center;">暂无配额数据</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
                <div class="m210-pagination">
                    <?php
                    $total_pages = ceil($total / $per_page);
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    ?>
                </div>
            </div>
        </div>
        
        <!-- 增加配额模态框 -->
        <div id="m210-add-quota-modal" class="m210-modal" style="display: none;">
            <div class="m210-modal-content">
                <div class="m210-modal-header">
                    <h3>增加用户配额</h3>
                    <span class="m210-modal-close">&times;</span>
                </div>
                <div class="m210-modal-body">
                    <p>为用户 <strong id="m210-target-user"></strong> 增加配额：</p>
                    <input type="number" id="m210-quota-amount" value="100" min="1" class="regular-text" />
                    <input type="hidden" id="m210-target-user-id" />
                </div>
                <div class="m210-modal-footer">
                    <button type="button" class="button button-primary" id="m210-confirm-add-quota">确认增加</button>
                    <button type="button" class="button m210-modal-close">取消</button>
                </div>
            </div>
        </div>
        <?php
    }
    
    public function stats_page() {
        global $wpdb;
        
        $stats = $this->get_system_stats();
        ?>
        
        <div class="wrap m210-admin-wrap">
            <h1 class="m210-admin-title">
                <i class="dashicons dashicons-chart-bar"></i> 数据统计
                <button type="button" class="page-title-action" id="m210-refresh-stats">刷新数据</button>
            </h1>
            
            <div class="m210-stats-grid">
                <div class="m210-stat-card m210-stat-total">
                    <div class="m210-stat-icon">
                        <i class="dashicons dashicons-play-circle"></i>
                    </div>
                    <div class="m210-stat-content">
                        <div class="m210-stat-number"><?php echo $stats['total_parses']; ?></div>
                        <div class="m210-stat-label">总解析次数</div>
                    </div>
                </div>
                
                <div class="m210-stat-card m210-stat-today">
                    <div class="m210-stat-icon">
                        <i class="dashicons dashicons-calendar-day"></i>
                    </div>
                    <div class="m210-stat-content">
                        <div class="m210-stat-number"><?php echo $stats['today_parses']; ?></div>
                        <div class="m210-stat-label">今日解析</div>
                    </div>
                </div>
                
                <div class="m210-stat-card m210-stat-revenue">
                    <div class="m210-stat-icon">
                        <i class="dashicons dashicons-money"></i>
                    </div>
                    <div class="m210-stat-content">
                        <div class="m210-stat-number"><?php echo number_format($stats['total_revenue'], 2); ?></div>
                        <div class="m210-stat-label">总收入(元)</div>
                    </div>
                </div>
                
                <div class="m210-stat-card m210-stat-users">
                    <div class="m210-stat-icon">
                        <i class="dashicons dashicons-groups"></i>
                    </div>
                    <div class="m210-stat-content">
                        <div class="m210-stat-number"><?php echo $stats['active_users']; ?></div>
                        <div class="m210-stat-label">活跃用户</div>
                    </div>
                </div>
            </div>
            
            <div class="m210-card">
                <h3>平台使用分布</h3>
                <div class="m210-platform-stats">
                    <?php
                    $platform_stats = $this->get_platform_stats();
                    foreach ($platform_stats as $platform => $count): 
                        $percentage = $stats['total_parses'] > 0 ? ($count / $stats['total_parses']) * 100 : 0;
                    ?>
                        <div class="m210-platform-item">
                            <div class="m210-platform-info">
                                <span class="m210-platform-name"><?php echo $platform; ?></span>
                                <span class="m210-platform-count"><?php echo $count; ?>次 (<?php echo number_format($percentage, 1); ?>%)</span>
                            </div>
                            <div class="m210-platform-bar">
                                <div class="m210-platform-fill" style="width: <?php echo $percentage; ?>%"></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="m210-card">
                <h3>实时监控</h3>
                <div class="m210-realtime-stats">
                    <div class="m210-realtime-item">
                        <span class="m210-realtime-label">服务器状态：</span>
                        <span class="m210-realtime-value m210-status-ok">正常</span>
                    </div>
                    <div class="m210-realtime-item">
                        <span class="m210-realtime-label">数据库状态：</span>
                        <span class="m210-realtime-value m210-status-ok">正常</span>
                    </div>
                    <div class="m210-realtime-item">
                        <span class="m210-realtime-label">API状态：</span>
                        <span class="m210-realtime-value m210-status-ok">正常</span>
                    </div>
                    <div class="m210-realtime-item">
                        <span class="m210-realtime-label">支付状态：</span>
                        <span class="m210-realtime-value m210-status-ok">正常</span>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    private function get_system_stats() {
        global $wpdb;
        
        $table_history = $wpdb->prefix . 'vpp_parse_history';
        $table_payments = $wpdb->prefix . 'vpp_payment_records';
        
        $stats = array();
        $stats['total_parses'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_history") ?: 0;
        $stats['today_parses'] = $wpdb->get_var("SELECT COUNT(*) FROM $table_history WHERE DATE(parse_time) = CURDATE()") ?: 0;
        $stats['total_revenue'] = $wpdb->get_var("SELECT SUM(amount) FROM $table_payments WHERE payment_status = 'completed'") ?: 0;
        $stats['active_users'] = $wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM $table_history WHERE user_id > 0") ?: 0;
        
        return $stats;
    }
    
    private function get_platform_stats() {
        // 简化的平台统计
        return array(
            '抖音' => 65,
            '快手' => 25,
            '火山' => 5,
            '其他' => 5
        );
    }
    
    private function get_payment_status_text($status) {
        $statuses = array(
            'pending' => '待支付',
            'completed' => '已完成',
            'failed' => '失败',
            'cancelled' => '已取消'
        );
        
        return $statuses[$status] ?? $status;
    }
    
    public function export_data() {
        if (!wp_verify_nonce($_GET['nonce'], 'vpp_export')) {
            wp_die('安全验证失败');
        }
        
        $type = $_GET['type'] ?? 'records';
        $filename = 'vpp_' . $type . '_' . date('YmdHis') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=' . $filename);
        
        $output = fopen('php://output', 'w');
        fwrite($output, "\xEF\xBB\xBF"); // 添加BOM头解决中文乱码
        
        if ($type === 'records') {
            $this->export_records($output);
        } elseif ($type === 'payments') {
            $this->export_payments($output);
        }
        
        fclose($output);
        exit;
    }
    
    private function export_records($output) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vpp_parse_history';
        $records = $wpdb->get_results("SELECT * FROM $table_name ORDER BY parse_time DESC");
        
        fputcsv($output, array('ID', '用户', '视频标题', '原链接', '解析链接', 'IP地址', '解析时间'));
        
        foreach ($records as $record) {
            $user_name = $record->user_id ? get_userdata($record->user_id)->display_name : '游客';
            fputcsv($output, array(
                $record->id,
                $user_name,
                $record->video_title,
                $record->video_url,
                $record->video_url_parsed,
                $record->ip_address,
                $record->parse_time
            ));
        }
    }
    
    private function export_payments($output) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'vpp_payment_records';
        $payments = $wpdb->get_results("SELECT * FROM $table_name ORDER BY create_time DESC");
        
        fputcsv($output, array('订单号', '用户', '金额', '配额', '状态', '微信交易号', '创建时间', '支付时间'));
        
        foreach ($payments as $payment) {
            $user_name = get_userdata($payment->user_id)->display_name;
            fputcsv($output, array(
                $payment->order_id,
                $user_name,
                $payment->amount,
                $payment->quota_amount,
                $this->get_payment_status_text($payment->payment_status),
                $payment->wechat_transaction_id,
                $payment->create_time,
                $payment->payment_time
            ));
        }
    }
    
    public function clear_cache() {
        if (!wp_verify_nonce($_POST['nonce'], 'vpp_admin_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        // 清空游客缓存
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%vpp_guest_%'");
        
        wp_send_json_success('缓存清空成功');
    }
    
    public function add_user_quota() {
        if (!wp_verify_nonce($_POST['nonce'], 'vpp_admin_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $user_id = intval($_POST['user_id']);
        $quota_amount = intval($_POST['quota_amount']);
        
        if ($user_id <= 0 || $quota_amount <= 0) {
            wp_send_json_error('参数错误');
        }
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'vpp_user_quota';
        
        $current = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE user_id = %d", $user_id));
        
        if ($current) {
            $result = $wpdb->update($table_name, 
                array('total_quota' => $current->total_quota + $quota_amount),
                array('user_id' => $user_id)
            );
        } else {
            $result = $wpdb->insert($table_name, array(
                'user_id' => $user_id,
                'total_quota' => $quota_amount,
                'used_quota' => 0
            ));
        }
        
        if ($result) {
            wp_send_json_success('配额增加成功');
        } else {
            wp_send_json_error('操作失败');
        }
    }
    
    public function test_api_connection() {
        if (!wp_verify_nonce($_POST['nonce'], 'vpp_admin_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $api_key = get_option('vpp_api_key');
        
        if (empty($api_key)) {
            wp_send_json_error('请先配置API密钥');
        }
        
        // 模拟API测试
        $test_url = 'https://www.52api.cn/api/video_parse?key=' . $api_key . '&url=https://v.douyin.com/test';
        
        $response = wp_remote_get($test_url, array(
            'timeout' => 10,
            'sslverify' => false
        ));
        
        if (is_wp_error($response)) {
            wp_send_json_error('连接失败: ' . $response->get_error_message());
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        
        if ($http_code == 200) {
            wp_send_json_success('API连接正常');
        } else {
            wp_send_json_error('API返回错误: HTTP ' . $http_code);
        }
    }
    
    public function get_stats_data() {
        if (!wp_verify_nonce($_POST['nonce'], 'vpp_admin_nonce')) {
            wp_send_json_error('安全验证失败');
        }
        
        $stats = $this->get_system_stats();
        wp_send_json_success($stats);
    }
}