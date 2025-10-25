(function($) {
    'use strict';

    class VideoParserPro {
        constructor() {
            this.init();
        }

        init() {
            this.bindEvents();
            this.initClipboard();
            this.initAutoDetect();
        }

        bindEvents() {
            // 解析按钮点击事件
            $(document).on('click', '#m210-parse-btn', (e) => {
                this.handleParse(e);
            });

            // 回车键触发解析
            $(document).on('keypress', '#m210-video-url', (e) => {
                if (e.which === 13) {
                    this.handleParse(e);
                }
            });

            // 重新解析按钮
            $(document).on('click', '#m210-refresh-parse', () => {
                this.resetParser();
            });

            // 复制按钮
            $(document).on('click', '.m210-copy-btn', (e) => {
                const target = $(e.currentTarget).data('copy-target');
                this.copyToClipboard(target);
            });

            // 下载按钮
            $(document).on('click', '#m210-download-btn', (e) => {
                e.preventDefault();
                this.handleDownload();
            });

            // 粘贴事件处理
            $(document).on('paste', '#m210-video-url', (e) => {
                setTimeout(() => {
                    this.autoParseIfValid();
                }, 100);
            });

            // 输入事件处理
            $(document).on('input', '#m210-video-url', () => {
                this.validateUrl();
            });
        }

        handleParse(e) {
            e.preventDefault();
            
            const $btn = $('#m210-parse-btn');
            const $input = $('#m210-video-url');
            const videoUrl = $input.val().trim();

            // 验证URL
            if (!this.isValidUrl(videoUrl)) {
                this.showError('请输入有效的短视频链接');
                $input.focus();
                return;
            }

            // 显示加载状态
            this.showLoading();
            $btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> 解析中...');

            // 发送AJAX请求
            $.ajax({
                url: vpp_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'vpp_parse_video',
                    video_url: videoUrl,
                    nonce: vpp_ajax.nonce
                },
                success: (response) => {
                    if (response.success) {
                        this.showResult(response.data);
                        this.updateUserQuota(response.data.remaining_quota);
                    } else {
                        this.showError(response.data);
                    }
                },
                error: (xhr, status, error) => {
                    console.error('Parse error:', error);
                    this.showError('网络错误，请稍后重试');
                },
                complete: () => {
                    this.hideLoading();
                    $btn.prop('disabled', false).html('<i class="fas fa-bolt"></i> 立即解析');
                }
            });
        }

        isValidUrl(url) {
            if (!url) return false;

            // 支持的平台正则表达式
            const patterns = [
                /https?:\/\/(v\.douyin\.com|www\.douyin\.com\/video)/,
                /https?:\/\/(www\.iesdouyin\.com)/,
                /https?:\/\/(v\.huoshan\.com)/,
                /https?:\/\/(www\.kuaishou\.com)/,
                /https?:\/\/(v\.kuaishou\.com)/,
                /https?:\/\/(m\.kuaishou\.com)/,
                /https?:\/\/(www\.xigua\.com)/,
                /https?:\/\/(www\.ixigua\.com)/
            ];

            return patterns.some(pattern => pattern.test(url));
        }

        showLoading() {
            this.hideAll();
            $('#m210-loading-area').show();
        }

        hideLoading() {
            $('#m210-loading-area').hide();
        }

        showResult(data) {
            this.hideAll();

            // 更新视频信息
            $('#m210-video-title').text(data.work_title || '未知标题');
            $('#m210-video-cover').attr('src', data.work_cover || '');
            $('#m210-video-url-parsed').val(data.work_url || '');
            
            // 设置下载链接
            const downloadUrl = this.generateDownloadUrl(data.work_url, data.work_title);
            $('#m210-download-btn').attr('href', downloadUrl);
            $('#m210-direct-link').attr('href', data.work_url || '#');

            // 检测平台
            const platform = this.detectPlatform(data.work_url);
            $('#m210-platform').html(`<i class="fas fa-mobile-alt"></i> ${platform}`);

            // 显示结果区域
            $('#m210-result-area').show();

            // 滚动到结果区域
            $('html, body').animate({
                scrollTop: $('#m210-result-area').offset().top - 100
            }, 500);
        }

        showError(message) {
            this.hideAll();
            $('#m210-error-message').text(message);
            $('#m210-error-area').show();
        }

        hideAll() {
            $('#m210-result-area').hide();
            $('#m210-error-area').hide();
            $('#m210-loading-area').hide();
        }

        resetParser() {
            $('#m210-video-url').val('').focus();
            this.hideAll();
        }

        generateDownloadUrl(videoUrl, title) {
            if (!videoUrl) return '#';
            
            const filename = this.sanitizeFilename(title || 'video');
            return vpp_ajax.ajax_url + '?action=vpp_download_video&url=' + 
                   encodeURIComponent(videoUrl) + '&filename=' + 
                   encodeURIComponent(filename) + '&nonce=' + 
                   this.createDownloadNonce();
        }

        createDownloadNonce() {
            // 生成下载专用的nonce
            return Math.random().toString(36).substring(2, 15) + 
                   Math.random().toString(36).substring(2, 15);
        }

        sanitizeFilename(filename) {
            return filename.replace(/[^a-zA-Z0-9\u4e00-\u9fa5-_]/g, '_')
                          .substring(0, 100);
        }

        detectPlatform(url) {
            if (!url) return '未知';
            
            const platforms = {
                'douyin': '抖音',
                'iesdouyin': '抖音',
                'huoshan': '火山小视频',
                'kuaishou': '快手',
                'xigua': '西瓜视频',
                'ixigua': '西瓜视频'
            };

            for (const [key, name] of Object.entries(platforms)) {
                if (url.includes(key)) {
                    return name;
                }
            }

            return '其他平台';
        }

        initClipboard() {
            // 使用Clipboard.js初始化复制功能
            if (typeof ClipboardJS !== 'undefined') {
                new ClipboardJS('.m210-copy-btn', {
                    text: (trigger) => {
                        const target = $(trigger).data('copy-target');
                        return $(`#${target}`).val();
                    }
                }).on('success', (e) => {
                    this.showToast('链接已复制到剪贴板');
                    e.clearSelection();
                }).on('error', (e) => {
                    this.showToast('复制失败，请手动复制');
                });
            }
        }

        copyToClipboard(targetId) {
            const element = document.getElementById(targetId);
            if (!element) return;

            element.select();
            element.setSelectionRange(0, 99999);

            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    this.showToast('链接已复制到剪贴板');
                } else {
                    this.showToast('复制失败，请手动复制');
                }
            } catch (err) {
                this.showToast('复制失败，请手动复制');
            }
        }

        showToast(message) {
            // 移除现有的toast
            $('.m210-toast').remove();

            const toast = $(`
                <div class="m210-toast">
                    <i class="fas fa-check-circle"></i>
                    <span>${message}</span>
                </div>
            `);

            $('body').append(toast);

            // 3秒后自动移除
            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        initAutoDetect() {
            // 从localStorage恢复上次的输入
            const lastUrl = localStorage.getItem('vpp_last_url');
            if (lastUrl) {
                $('#m210-video-url').val(lastUrl);
            }

            // 监听输入变化
            $('#m210-video-url').on('input', () => {
                const url = $('#m210-video-url').val();
                localStorage.setItem('vpp_last_url', url);
            });
        }

        autoParseIfValid() {
            const url = $('#m210-video-url').val().trim();
            if (this.isValidUrl(url)) {
                // 自动解析逻辑（可选）
                // this.handleParse(new Event('click'));
            }
        }

        validateUrl() {
            const $input = $('#m210-video-url');
            const url = $input.val().trim();
            
            if (url && !this.isValidUrl(url)) {
                $input.addClass('m210-invalid');
            } else {
                $input.removeClass('m210-invalid');
            }
        }

        updateUserQuota(remainingQuota) {
            const $quotaCount = $('.m210-quota-count');
            if ($quotaCount.length) {
                $quotaCount.text(remainingQuota);
                
                // 如果次数为0，显示充值提示
                if (remainingQuota <= 0) {
                    $('.m210-quota-info').addClass('m210-no-quota');
                }
            }
        }

        handleDownload() {
            const $btn = $('#m210-download-btn');
            const originalHtml = $btn.html();
            
            $btn.html('<i class="fas fa-spinner fa-spin"></i> 准备下载...')
                .prop('disabled', true);

            // 模拟下载准备过程
            setTimeout(() => {
                $btn.html(originalHtml).prop('disabled', false);
                
                // 实际下载由浏览器处理，这里只是UI反馈
                this.showToast('开始下载视频...');
            }, 1000);
        }
    }

    // 初始化
    $(document).ready(() => {
        new VideoParserPro();
    });

})(jQuery);