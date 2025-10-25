<?php
$user_id = get_current_user_id();
$user_stats = VPP()->get_user_manager()->get_user_stats($user_id);
$parse_history = VPP()->get_user_manager()->get_user_parse_history($user_id, 1, 10);
$payment_history = VPP()->get_database()->get_user_payment_history($user_id, 5);
?>

<div class="m210-dashboard-container">
    <div class="m210-dashboard-header">
        <h1 class="m210-dashboard-title">
            <i class="fas fa-tachometer-alt"></i>
            用户控制面板
        </h1>
        <div class="m210-user-welcome">
            欢迎回来，<strong><?php echo wp_get_current_user()->display_name; ?></strong>
        </div>
    </div>

    <!-- 数据统计卡片 -->
    <div class="m210-stats-cards">
        <div class="m210-stat-card m210-stat-quota">
            <div class="m210-stat-icon">
                <i class="fas fa-bolt"></i>
            </div>
            <div class="m210-stat-content">
                <div class="m210-stat-number"><?php echo $user_stats['remaining_quota']; ?></div>
                <div class="m210-stat-label">剩余解析次数</div>
            </div>
            <div class="m210-stat-action">
                <a href="<?php echo home_url('/payment'); ?>" class="m210-action-btn">
                    <i class="fas fa-plus"></i> 充值
                </a>
            </div>
        </div>

        <div class="m210-stat-card m210-stat-total">
            <div class="m210-stat-icon">
                <i class="fas fa-chart-line"></i>
            </div>
            <div class="m210-stat-content">
                <div class="m210-stat-number"><?php echo $user_stats['total_parses']; ?></div>
                <div class="m210-stat-label">总解析次数</div>
            </div>
        </div>

        <div class="m210-stat-card m210-stat-used">
            <div class="m210-stat-icon">
                <i class="fas fa-history"></i>
            </div>
            <div class="m210-stat-content">
                <div class="m210-stat-number"><?php echo $user_stats['quota']->used_quota; ?></div>
                <div class="m210-stat-label">已使用次数</div>
            </div>
        </div>

        <div class="m210-stat-card m210-stat-payments">
            <div class="m210-stat-icon">
                <i class="fas fa-credit-card"></i>
            </div>
            <div class="m210-stat-content">
                <div class="m210-stat-number"><?php echo count($payment_history); ?></div>
                <div class="m210-stat-label">充值记录</div>
            </div>
        </div>
    </div>

    <div class="m210-dashboard-content">
        <!-- 最近解析记录 -->
        <div class="m210-dashboard-section">
            <div class="m210-section-header">
                <h3 class="m210-section-title">
                    <i class="fas fa-video"></i>
                    最近解析记录
                </h3>
                <a href="#" class="m210-section-action" id="m210-load-more-history">加载更多</a>
            </div>

            <div class="m210-history-table">
                <?php if ($parse_history): ?>
                    <div class="m210-table-responsive">
                        <table class="m210-table">
                            <thead>
                                <tr>
                                    <th>视频封面</th>
                                    <th>视频信息</th>
                                    <th>解析时间</th>
                                    <th>操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($parse_history as $item): ?>
                                <tr class="m210-history-row">
                                    <td class="m210-history-cover">
                                        <img src="<?php echo esc_url($item->video_cover); ?>" 
                                             alt="<?php echo esc_attr($item->video_title); ?>" 
                                             class="m210-cover-thumb" />
                                    </td>
                                    <td class="m210-history-info">
                                        <h4 class="m210-video-title"><?php echo esc_html($item->video_title); ?></h4>
                                        <div class="m210-video-url">
                                            <a href="<?php echo esc_url($item->video_url); ?>" target="_blank">
                                                <?php echo esc_url($item->video_url); ?>
                                            </a>
                                        </div>
                                    </td>
                                    <td class="m210-history-time">
                                        <?php echo date('Y-m-d H:i:s', strtotime($item->parse_time)); ?>
                                    </td>
                                    <td class="m210-history-actions">
                                        <a href="<?php echo esc_url($item->video_url_parsed); ?>" 
                                           target="_blank" 
                                           class="m210-action-link" 
                                           title="查看视频">
                                            <i class="fas fa-external-link-alt"></i>
                                        </a>
                                        <a href="<?php echo add_query_arg(array(
                                            'action' => 'vpp_download_video',
                                            'url' => urlencode($item->video_url_parsed),
                                            'filename' => urlencode($item->video_title),
                                            'nonce' => wp_create_nonce('vpp_download')
                                        ), admin_url('admin-ajax.php')); ?>" 
                                           class="m210-action-link" 
                                           title="下载视频">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="m210-empty-state">
                        <i class="fas fa-inbox"></i>
                        <h4>暂无解析记录</h4>
                        <p>您还没有解析过任何视频</p>
                        <a href="<?php echo home_url('/parser'); ?>" class="m210-primary-btn">
                            <i class="fas fa-bolt"></i> 开始解析
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 充值记录 -->
        <div class="m210-dashboard-section">
            <div class="m210-section-header">
                <h3 class="m210-section-title">
                    <i class="fas fa-credit-card"></i>
                    充值记录
                </h3>
            </div>

            <div class="m210-payment-history">
                <?php if ($payment_history): ?>
                    <div class="m210-payment-list">
                        <?php foreach ($payment_history as $payment): ?>
                        <div class="m210-payment-item">
                            <div class="m210-payment-main">
                                <div class="m210-payment-amount">
                                    <span class="m210-amount"><?php echo number_format($payment->amount, 2); ?>元</span>
                                    <span class="m210-quota">+<?php echo $payment->quota_amount; ?>次</span>
                                </div>
                                <div class="m210-payment-info">
                                    <span class="m210-order-id">订单号：<?php echo $payment->order_id; ?></span>
                                    <span class="m210-payment-time">
                                        <?php echo date('Y-m-d H:i:s', strtotime($payment->create_time)); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="m210-payment-status">
                                <span class="m210-status m210-status-<?php echo $payment->payment_status; ?>">
                                    <?php echo $this->get_payment_status_text($payment->payment_status); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="m210-empty-state m210-empty-payments">
                        <i class="fas fa-credit-card"></i>
                        <h4>暂无充值记录</h4>
                        <p>您还没有进行过充值</p>
                        <a href="<?php echo home_url('/payment'); ?>" class="m210-primary-btn">
                            <i class="fas fa-plus"></i> 立即充值
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- 使用统计 -->
        <div class="m210-dashboard-section">
            <div class="m210-section-header">
                <h3 class="m210-section-title">
                    <i class="fas fa-chart-pie"></i>
                    使用统计
                </h3>
            </div>

            <div class="m210-usage-stats">
                <div class="m210-usage-chart">
                    <canvas id="m210-usage-chart" width="400" height="200"></canvas>
                </div>
                <div class="m210-usage-summary">
                    <div class="m210-usage-item">
                        <span class="m210-usage-label">总配额：</span>
                        <span class="m210-usage-value"><?php echo $user_stats['quota']->total_quota; ?>次</span>
                    </div>
                    <div class="m210-usage-item">
                        <span class="m210-usage-label">已使用：</span>
                        <span class="m210-usage-value"><?php echo $user_stats['quota']->used_quota; ?>次</span>
                    </div>
                    <div class="m210-usage-item">
                        <span class="m210-usage-label">剩余：</span>
                        <span class="m210-usage-value m210-remaining-value"><?php echo $user_stats['remaining_quota']; ?>次</span>
                    </div>
                    <div class="m210-usage-item">
                        <span class="m210-usage-label">使用率：</span>
                        <span class="m210-usage-value">
                            <?php 
                            $usage_rate = $user_stats['quota']->total_quota > 0 ? 
                                ($user_stats['quota']->used_quota / $user_stats['quota']->total_quota) * 100 : 0;
                            echo number_format($usage_rate, 1); ?>%
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    // 使用统计图表
    var ctx = document.getElementById('m210-usage-chart').getContext('2d');
    var usageChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: ['已使用', '剩余'],
            datasets: [{
                data: [
                    <?php echo $user_stats['quota']->used_quota; ?>,
                    <?php echo $user_stats['remaining_quota']; ?>
                ],
                backgroundColor: ['#FF6384', '#36A2EB'],
                borderWidth: 2,
                borderColor: '#fff'
            }]
        },
        options: {
            responsive: true,
            cutout: '70%',
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });

    // 加载更多历史记录
    $('#m210-load-more-history').on('click', function(e) {
        e.preventDefault();
        // 加载更多逻辑
    });
});
</script>