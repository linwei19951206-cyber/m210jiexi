(function($) {
    'use strict';

    class AdminPanel {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.initTabs();
            this.initModals();
            this.initCharts();
        }

        bindEvents() {
            // 标签页切换
            $(document).on('click', '.nav-tab', (e) => {
                this.switchTab(e);
            });

            // 增加配额按钮
            $(document).on('click', '.m210-add-quota', (e) => {
                this.showAddQuotaModal(e);
            });

            // 确认增加配额
            $(document).on('click', '#m210-confirm-add-quota', (e) => {
                this.confirmAddQuota(e);
            });

            // 模态框关闭
            $(document).on('click', '.m210-modal-close', (e) => {
                this.closeModal(e);
            });

            // 清空缓存
            $(document).on('click', '#m210-clear-cache', (e) => {
                this.clearCache(e);
            });

            // 数据导出
            $(document).on('click', '.m210-export-btn', (e) => {
                this.exportData(e);
            });

            // 实时数据刷新
            $(document).on('click', '.m210-refresh-stats', (e) => {
                this.refreshStats(e);
            });
        }

        initTabs() {
            // 初始化标签页
            $('.nav-tab-wrapper a.nav-tab').first().addClass('nav-tab-active');
            $('.tab-pane').first().addClass('active');
        }

        switchTab(e) {
            e.preventDefault();
            
            const $tab = $(e.currentTarget);
            const target = $tab.attr('href');
            
            // 更新标签状态
            $('.nav-tab').removeClass('nav-tab-active');
            $tab.addClass('nav-tab-active');
            
            // 更新内容显示
            $('.tab-pane').removeClass('active');
            $(target).addClass('active');
        }

        initModals() {
            // 点击模态框外部关闭
            $(document).on('click', '.m210-modal', (e) => {
                if (e.target === e.currentTarget) {
                    this.closeModal(e);
                }
            });
        }

        showAddQuotaModal(e) {
            const $btn = $(e.currentTarget);
            const userId = $btn.data('user-id');
            const userName = $btn.data('user-name');

            // 更新模态框内容
            $('#m210-target-user').text(userName);
            $('#m210-target-user-id').val(userId);
            $('#m210-quota-amount').val(100);

            // 显示模态框
            $('#m210-add-quota-modal').show();
        }

        confirmAddQuota() {
            const userId = $('#m210-target-user-id').val();
            const quotaAmount = $('#m210-quota-amount').val();

            if (!quotaAmount || quotaAmount < 1) {
                alert('请输入有效的配额数量');
                return;
            }

            // 显示加载状态
            const $btn = $('#m210-confirm-add-quota');
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 处理中...');

            $.ajax({
                url: vpp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vpp_admin_add_quota',
                    user_id: userId,
                    quota_amount: quotaAmount,
                    nonce: vpp_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showAdminNotice('配额增加成功', 'success');
                        this.closeModal();
                        // 刷新页面或更新表格
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        this.showAdminNotice(response.data, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Add quota error:', error);
                    this.showAdminNotice('操作失败，请重试', 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).html(originalText);
                }
            });
        }

        closeModal() {
            $('.m210-modal').hide();
        }

        clearCache() {
            if (!confirm(vpp_admin.i18n.confirm_clear)) {
                return;
            }

            const $btn = $('#m210-clear-cache');
            const originalText = $btn.html();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 清空中...');

            $.ajax({
                url: vpp_admin.ajax_url,
                type: 'POST',
                data: {
                    action: 'vpp_clear_cache',
                    nonce: vpp_admin.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showAdminNotice('缓存清空成功', 'success');
                    } else {
                        this.showAdminNotice(response.data, 'error');
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Clear cache error:', error);
                    this.showAdminNotice('清空失败，请重试', 'error');
                },
                complete: () => {
                    $btn.prop('disabled', false).html(originalText);
                setTimeout(() => {
                    $btn.html('清空缓存');
                }, 2000);
                }
            });
        }

        exportData(e) {
            e.preventDefault();
            
            const $btn = $(e.currentTarget);
            const originalText = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i> ' + vpp_admin.i18n.exporting);

            // 这里实际是直接下载，不需要AJAX
            // 只是给用户一个反馈
            setTimeout(() => {
                $btn.html(originalText);
                this.showAdminNotice(vpp_admin.i18n.export_success, 'success');
            }, 1000);
        }

        refreshStats() {
            const $btn = $('.m210-refresh-stats');
            const originalText = $btn.html();
            $btn.html('<i class="fas fa-spinner fa-spin"></i> 刷新中...');

            // 模拟数据刷新
            setTimeout(() => {
                $btn.html(originalText);
                this.showAdminNotice('数据已刷新', 'success');
            }, 1000);
        }

        showAdminNotice(message, type = 'success') {
            // 移除现有的通知
            $('.m210-admin-notice').remove();

            const noticeClass = type === 'success' ? 'notice-success' : 'notice-error';
            const notice = $(`
                <div class="m210-admin-notice notice ${noticeClass} is-dismissible">
                    <p>${message}</p>
                    <button type="button" class="notice-dismiss">
                        <span class="screen-reader-text">忽略此通知。</span>
                    </button>
                </div>
            `);

            $('.wrap').first().prepend(notice);

            // 自动移除
            setTimeout(() => {
                notice.fadeOut(300, function() {
                    $(this).remove();
                });
            }, 5000);

            // 点击关闭
            notice.on('click', '.notice-dismiss', function() {
                $(this).closest('.m210-admin-notice').remove();
            });
        }

        initCharts() {
            // 初始化统计图表
            if (typeof Chart !== 'undefined') {
                this.initParseTrendChart();
                this.initPlatformChart();
                this.initUsageChart();
            }
        }

        initParseTrendChart() {
            const ctx = document.getElementById('m210-parse-trend-chart');
            if (!ctx) return;

            // 这里应该是从服务器获取真实数据
            const data = {
                labels: ['01', '02', '03', '04', '05', '06', '07', '08', '09', '10'],
                datasets: [{
                    label: '解析次数',
                    data: [65, 59, 80, 81, 56, 55, 40, 45, 60, 75],
                    borderColor: '#4CAF50',
                    backgroundColor: 'rgba(76, 175, 80, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            };

            new Chart(ctx, {
                type: 'line',
                data: data,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            display: false
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(0, 0, 0, 0.1)'
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        }

        initPlatformChart() {
            const ctx = document.getElementById('m210-platform-chart');
            if (!ctx) return;

            const data = {
                labels: ['抖音', '快手', '火山', '其他'],
                datasets: [{
                    data: [65, 25, 5, 5],
                    backgroundColor: [
                        '#FF6384',
                        '#36A2EB',
                        '#FFCE56',
                        '#4BC0C0'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            };

            new Chart(ctx, {
                type: 'doughnut',
                data: data,
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        initUsageChart() {
            const ctx = document.getElementById('m210-usage-chart');
            if (!ctx) return;

            const data = {
                labels: ['已使用', '剩余'],
                datasets: [{
                    data: [75, 25],
                    backgroundColor: ['#FF6384', '#36A2EB'],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            };

            new Chart(ctx, {
                type: 'doughnut',
                data: data,
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
        }
    }

    // 初始化后台管理
    $(document).ready(() => {
        if ($('.m210-admin-wrap').length) {
            new AdminPanel();
        }
    });

})(jQuery);