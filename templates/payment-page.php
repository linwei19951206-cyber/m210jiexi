<?php
$payment_handler = VPP()->get_payment_handler();
$packages = $payment_handler->get_payment_packages();
$user_stats = VPP()->get_user_manager()->get_user_stats(get_current_user_id());
?>

<div class="m210-payment-container">
    <div class="m210-payment-header">
        <h1 class="m210-payment-title">
            <i class="fas fa-credit-card"></i>
            解析次数充值
        </h1>
        <div class="m210-current-balance">
            当前剩余次数：<strong><?php echo $user_stats['remaining_quota']; ?>次</strong>
        </div>
    </div>

    <!-- 套餐选择 -->
    <div class="m210-packages-section">
        <h2 class="m210-section-title">选择充值套餐</h2>
        <div class="m210-packages-grid">
            <?php foreach ($packages as $index => $package): ?>
            <div class="m210-package-card <?php echo $package['popular'] ? 'm210-popular' : ''; ?>">
                <?php if ($package['popular']): ?>
                <div class="m210-popular-badge">推荐</div>
                <?php endif; ?>
                
                <div class="m210-package-header">
                    <h3 class="m210-package-quota"><?php echo $package['quota']; ?>次</h3>
                    <?php if ($package['discount'] > 0): ?>
                    <div class="m210-discount-badge">节省<?php echo $package['discount']; ?>%</div>
                    <?php endif; ?>
                </div>

                <div class="m210-package-price">
                    <span class="m210-price">¥<?php echo number_format($package['price'], 2); ?></span>
                    <?php if ($package['discount'] > 0): ?>
                    <span class="m210-original-price">
                        ¥<?php echo number_format($package['price'] / (1 - $package['discount'] / 100), 2); ?>
                    </span>
                    <?php endif; ?>
                </div>

                <div class="m210-package-features">
                    <div class="m210-feature">
                        <i class="fas fa-check"></i>
                        <span>永久有效</span>
                    </div>
                    <div class="m210-feature">
                        <i class="fas fa-check"></i>
                        <span>无使用限制</span>
                    </div>
                    <div class="m210-feature">
                        <i class="fas fa-check"></i>
                        <span>支持所有平台</span>
                    </div>
                </div>

                <button type="button" 
                        class="m210-select-package" 
                        data-amount="<?php echo $package['price']; ?>" 
                        data-quota="<?php echo $package['quota']; ?>">
                    <?php if ($package['popular']): ?>
                    <i class="fas fa-crown"></i>
                    <?php endif; ?>
                    立即购买
                </button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 自定义金额 -->
    <div class="m210-custom-amount-section">
        <h2 class="m210-section-title">自定义充值</h2>
        <div class="m210-custom-amount-form">
            <div class="m210-amount-input-group">
                <label for="m210-custom-amount">充值金额（元）：</label>
                <input type="number" 
                       id="m210-custom-amount" 
                       class="m210-amount-input" 
                       min="1" 
                       step="1" 
                       value="10" />
            </div>
            <div class="m210-quota-preview">
                <span>可获得解析次数：</span>
                <strong id="m210-custom-quota">100次</strong>
            </div>
            <button type="button" id="m210-custom-pay" class="m210-custom-pay-btn">
                立即支付
            </button>
        </div>
    </div>

    <!-- 支付方式 -->
    <div class="m210-payment-method-section">
        <h2 class="m210-section-title">支付方式</h2>
        <div class="m210-payment-methods">
            <div class="m210-payment-method active" data-method="wechat">
                <div class="m210-method-radio">
                    <input type="radio" id="m210-method-wechat" name="payment_method" value="wechat" checked />
                    <label for="m210-method-wechat"></label>
                </div>
                <div class="m210-method-icon">
                    <i class="fab fa-weixin"></i>
                </div>
                <div class="m210-method-info">
                    <h4>微信支付</h4>
                    <p>扫码完成支付</p>
                </div>
            </div>
        </div>
    </div>

    <!-- 支付二维码 -->
    <div id="m210-payment-modal" class="m210-modal" style="display: none;">
        <div class="m210-modal-content m210-payment-modal">
            <div class="m210-modal-header">
                <h3>微信支付</h3>
                <span class="m210-modal-close">&times;</span>
            </div>
            <div class="m210-modal-body">
                <div class="m210-payment-info">
                    <div class="m210-order-info">
                        <div class="m210-order-item">
                            <span class="m210-order-label">订单号：</span>
                            <span id="m210-order-id" class="m210-order-value"></span>
                        </div>
                        <div class="m210-order-item">
                            <span class="m210-order-label">充值金额：</span>
                            <span id="m210-order-amount" class="m210-order-value"></span>
                        </div>
                        <div class="m210-order-item">
                            <span class="m210-order-label">获得次数：</span>
                            <span id="m210-order-quota" class="m210-order-value"></span>
                        </div>
                    </div>
                    
                    <div class="m210-qr-code">
                        <div id="m210-qr-container"></div>
                        <p class="m210-qr-tip">请使用微信扫描二维码完成支付</p>
                    </div>
                </div>
                
                <div class="m210-payment-status">
                    <div class="m210-status-waiting">
                        <i class="fas fa-spinner fa-spin"></i>
                        <span>等待支付...</span>
                    </div>
                    <div class="m210-status-success" style="display: none;">
                        <i class="fas fa-check-circle"></i>
                        <span>支付成功！正在更新账户...</span>
                    </div>
                </div>
            </div>
            <div class="m210-modal-footer">
                <button type="button" class="m210-cancel-payment">取消支付</button>
                <button type="button" class="m210-check-payment">我已支付</button>
            </div>
        </div>
    </div>

    <!-- 支付说明 -->
    <div class="m210-payment-instruction">
        <h3 class="m210-instruction-title">
            <i class="fas fa-info-circle"></i>
            充值说明
        </h3>
        <div class="m210-instruction-content">
            <div class="m210-instruction-item">
                <i class="fas fa-shield-alt"></i>
                <div class="m210-instruction-text">
                    <h4>安全支付</h4>
                    <p>采用微信官方支付接口，保障资金安全</p>
                </div>
            </div>
            <div class="m210-instruction-item">
                <i class="fas fa-bolt"></i>
                <div class="m210-instruction-text">
                    <h4>即时到账</h4>
                    <p>支付成功后，解析次数立即到账</p>
                </div>
            </div>
            <div class="m210-instruction-item">
                <i class="fas fa-headset"></i>
                <div class="m210-instruction-text">
                    <h4>客服支持</h4>
                    <p>如有问题，请联系在线客服</p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
jQuery(document).ready(function($) {
    var currentPackage = null;
    
    // 套餐选择
    $('.m210-select-package').on('click', function() {
        var amount = $(this).data('amount');
        var quota = $(this).data('quota');
        currentPackage = { amount: amount, quota: quota };
        createPaymentOrder(amount, quota);
    });
    
    // 自定义金额支付
    $('#m210-custom-pay').on('click', function() {
        var amount = $('#m210-custom-amount').val();
        if (amount < 1) {
            alert('充值金额不能小于1元');
            return;
        }
        var quota = Math.floor(amount * 10); // 1元=10次
        currentPackage = { amount: amount, quota: quota };
        createPaymentOrder(amount, quota);
    });
    
    // 自定义金额实时计算
    $('#m210-custom-amount').on('input', function() {
        var amount = $(this).val();
        var quota = Math.floor(amount * 10);
        $('#m210-custom-quota').text(quota + '次');
    });
    
    function createPaymentOrder(amount, quota) {
        $.ajax({
            url: vpp_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'vpp_create_payment',
                amount: amount,
                quota_amount: quota,
                nonce: vpp_ajax.nonce
            },
            beforeSend: function() {
                // 显示加载状态
            },
            success: function(response) {
                if (response.success) {
                    showPaymentModal(response.data);
                } else {
                    alert(response.data);
                }
            },
            error: function() {
                alert('创建订单失败，请重试');
            }
        });
    }
    
    function showPaymentModal(orderData) {
        $('#m210-order-id').text(orderData.order_id);
        $('#m210-order-amount').text(orderData.payment_data.amount + '元');
        $('#m210-order-quota').text(currentPackage.quota + '次');
        
        // 生成二维码
        generateQRCode(orderData.payment_data.code_url);
        
        $('#m210-payment-modal').show();
        checkPaymentStatus(orderData.order_id);
    }
    
    function generateQRCode(url) {
        // 使用QRCode.js生成二维码
        $('#m210-qr-container').empty();
        new QRCode('m210-qr-container', {
            text: url,
            width: 200,
            height: 200,
            colorDark: '#000000',
            colorLight: '#ffffff',
            correctLevel: QRCode.CorrectLevel.H
        });
    }
    
    function checkPaymentStatus(orderId) {
        // 轮询检查支付状态
        var checkInterval = setInterval(function() {
            // 检查支付状态的逻辑
        }, 3000);
    }
});
</script>