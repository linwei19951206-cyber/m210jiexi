<?php
$user_stats = VPP()->get_user_manager()->get_user_stats(get_current_user_id());
$can_parse = VPP()->get_user_manager()->can_user_parse();
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
            <span class="m210-quota-count"><?php echo $user_stats['remaining_quota']; ?></span>
            <?php if ($user_stats['remaining_quota'] <= 0): ?>
            <a href="<?php echo home_url('/payment'); ?>" class="m210-recharge-btn">立即充值</a>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (!$can_parse['success']): ?>
    <div class="m210-login-prompt">
        <i class="fas fa-exclamation-triangle"></i>
        <span><?php echo $can_parse['message']; ?></span>
        <a href="<?php echo wp_login_url(get_permalink()); ?>" class="m210-login-btn">立即登录</a>
    </div>
    <?php endif; ?>

    <!-- 解析器主界面 -->
    <div class="m210-parser-main">
        <div class="m210-parser-header">
            <h2 class="m210-title">
                <i class="fas fa-video"></i>
                短视频解析下载
            </h2>
            <p class="m210-subtitle">支持抖音、快手、火山等平台视频解析</p>
        </div>

        <!-- 输入区域 -->
        <div class="m210-input-section">
            <div class="m210-input-group">
                <input type="text" 
                       id="m210-video-url" 
                       class="m210-url-input" 
                       placeholder="请粘贴短视频分享链接，例如：https://v.douyin.com/xxxxx/"
                       value="" />
                <button type="button" 
                        id="m210-parse-btn" 
                        class="m210-parse-btn" 
                        data-loading-text="解析中...">
                    <i class="fas fa-bolt"></i>
                    立即解析
                </button>
            </div>
            <div class="m210-input-tips">
                <i class="fas fa-lightbulb"></i>
                <span>支持平台：抖音、快手、火山小视频、西瓜视频等</span>
            </div>
        </div>

        <!-- 解析结果区域 -->
        <div id="m210-result-area" class="m210-result-area" style="display: none;">
            <div class="m210-result-header">
                <h3 class="m210-result-title">
                    <i class="fas fa-check-circle"></i>
                    解析成功
                </h3>
                <div class="m210-result-actions">
                    <button type="button" class="m210-copy-btn" data-copy-target="m210-video-url-parsed">
                        <i class="fas fa-copy"></i> 复制链接
                    </button>
                    <button type="button" class="m210-refresh-btn" id="m210-refresh-parse">
                        <i class="fas fa-redo"></i> 重新解析
                    </button>
                </div>
            </div>

            <div class="m210-video-preview">
                <!-- 视频封面 -->
                <div class="m210-video-cover">
                    <img id="m210-video-cover" src="" alt="视频封面" />
                    <div class="m210-play-overlay">
                        <i class="fas fa-play"></i>
                    </div>
                </div>

                <!-- 视频信息 -->
                <div class="m210-video-info">
                    <h4 id="m210-video-title" class="m210-video-title"></h4>
                    <div class="m210-video-meta">
                        <span class="m210-video-type" id="m210-video-type">
                            <i class="fas fa-film"></i> 视频
                        </span>
                        <span class="m210-platform" id="m210-platform">
                            <i class="fas fa-mobile-alt"></i> 抖音
                        </span>
                    </div>
                    
                    <!-- 下载按钮 -->
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

                    <!-- 解析链接 -->
                    <div class="m210-parsed-url">
                        <label>解析链接：</label>
                        <div class="m210-url-display">
                            <input type="text" 
                                   id="m210-video-url-parsed" 
                                   readonly 
                                   class="m210-url-output" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 错误信息区域 -->
        <div id="m210-error-area" class="m210-error-area" style="display: none;">
            <div class="m210-error-content">
                <i class="fas fa-exclamation-triangle"></i>
                <div class="m210-error-text">
                    <h4>解析失败</h4>
                    <p id="m210-error-message"></p>
                </div>
            </div>
        </div>

        <!-- 加载动画 -->
        <div id="m210-loading-area" class="m210-loading-area" style="display: none;">
            <div class="m210-loading-spinner">
                <div class="m210-spinner"></div>
                <p>正在解析视频链接，请稍候...</p>
            </div>
        </div>
    </div>

    <!-- 解析历史 -->
    <?php if (is_user_logged_in() && $atts['show_history'] !== 'false'): ?>
    <div class="m210-history-section">
        <div class="m210-section-header">
            <h3 class="m210-section-title">
                <i class="fas fa-history"></i>
                最近解析记录
            </h3>
            <a href="<?php echo home_url('/dashboard'); ?>" class="m210-view-all">查看全部</a>
        </div>
        
        <div class="m210-history-list" id="m210-recent-history">
            <?php
            $history = VPP()->get_user_manager()->get_user_parse_history(get_current_user_id(), 1, intval($atts['max_history']));
            if ($history): ?>
                <?php foreach ($history as $item): ?>
                <div class="m210-history-item">
                    <div class="m210-history-thumb">
                        <img src="<?php echo esc_url($item->video_cover); ?>" alt="<?php echo esc_attr($item->video_title); ?>" />
                    </div>
                    <div class="m210-history-info">
                        <h4 class="m210-history-title"><?php echo esc_html($item->video_title); ?></h4>
                        <div class="m210-history-meta">
                            <span class="m210-history-time">
                                <?php echo human_time_diff(strtotime($item->parse_time), current_time('timestamp')) . '前'; ?>
                            </span>
                            <a href="<?php echo esc_url($item->video_url_parsed); ?>" 
                               target="_blank" 
                               class="m210-history-download">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="m210-empty-history">
                    <i class="fas fa-inbox"></i>
                    <p>暂无解析记录</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- 使用说明 -->
    <div class="m210-instruction-section">
        <div class="m210-section-header">
            <h3 class="m210-section-title">
                <i class="fas fa-info-circle"></i>
                使用说明
            </h3>
        </div>
        <div class="m210-instruction-content">
            <div class="m210-instruction-steps">
                <div class="m210-step">
                    <div class="m210-step-number">1</div>
                    <div class="m210-step-content">
                        <h4>复制链接</h4>
                        <p>在抖音、快手等APP中复制视频分享链接</p>
                    </div>
                </div>
                <div class="m210-step">
                    <div class="m210-step-number">2</div>
                    <div class="m210-step-content">
                        <h4>粘贴解析</h4>
                        <p>将链接粘贴到上方输入框，点击解析按钮</p>
                    </div>
                </div>
                <div class="m210-step">
                    <div class="m210-step-number">3</div>
                    <div class="m210-step-content">
                        <h4>下载视频</h4>
                        <p>解析成功后，点击下载按钮保存视频</p>
                    </div>
                </div>
            </div>
            
            <div class="m210-platform-support">
                <h4>支持平台</h4>
                <div class="m210-platform-icons">
                    <span class="m210-platform-icon" title="抖音">
                        <i class="fab fa-tiktok"></i>
                    </span>
                    <span class="m210-platform-icon" title="快手">
                        <i class="fas fa-rocket"></i>
                    </span>
                    <span class="m210-platform-icon" title="火山">
                        <i class="fas fa-fire"></i>
                    </span>
                    <span class="m210-platform-icon" title="西瓜视频">
                        <i class="fas fa-watermelon"></i>
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- 复制成功提示 -->
<div id="m210-copy-toast" class="m210-toast">
    <i class="fas fa-check-circle"></i>
    <span>链接已复制到剪贴板</span>
</div>