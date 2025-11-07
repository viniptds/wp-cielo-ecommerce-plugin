jQuery(function ($) {
    'use strict';

    function startCountdownTimer() {
        const $clock = $('#dateClock');
        if (!$clock.length) return;

        const originAttr = $clock.attr('date-origin');
        const format = $clock.attr('date-format') || 'minutes';
        const period = parseInt($clock.attr('date-period'), 10) || 0;

        const originDate = new Date(originAttr.replace(' ', 'T')); // safe parse
        let endDate = new Date(originDate);

        if (format === 'minutes') {
            endDate.setMinutes(originDate.getMinutes() + period);
        } else if (format === 'hours') {
            endDate.setHours(originDate.getHours() + period);
        } else {
            console.warn('Unknown date-format:', format);
            return;
        }

        // Create a nice Bootstrap badge if empty
        if ($clock.is(':empty')) {
            $clock.html('<span class="badge bg-primary p-2 timer-text"></span>');
        }
        const $display = $clock.find('.timer-text').length ? $clock.find('.timer-text') : $clock;

        function updateTimer() {
            const now = new Date();
            const diff = endDate - now;

            if (diff <= 0) {
                $display.text('00:00');
                $display.removeClass('bg-primary').addClass('bg-danger');
                clearInterval(timerInterval);
                return;
            }

            const totalSeconds = Math.floor(diff / 1000);
            const minutes = Math.floor(totalSeconds / 60);
            const seconds = totalSeconds % 60;

            if (format === 'minutes') {
                $display.text(`${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`);
            } else if (format === 'hours') {
                const hours = Math.floor(minutes / 60);
                const mins = minutes % 60;
                $display.text(`${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`);
            }
        }

        // Initial update and interval
        updateTimer();
        const timerInterval = setInterval(updateTimer, 1000);
    }

    // Initialize when document ready
    startCountdownTimer();

    // Copy Pix code to clipboard
    $('.cielo-pix-copy-btn').on('click', function () {
        const button = $(this);
        const code = button.data('code');

        // Try to copy to clipboard
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(code).then(function () {
                // Success
                button.addClass('copied');
                const originalText = button.text();
                button.text('✓ Código Copiado!');

                setTimeout(function () {
                    button.removeClass('copied');
                    button.text(originalText);
                }, 3000);
            }).catch(function (err) {
                console.error('Erro ao copiar:', err);
                fallbackCopyTextToClipboard(code, button);
            });
        } else {
            // Fallback for older browsers
            fallbackCopyTextToClipboard(code, button);
        }
    });

    // Fallback copy method for older browsers
    function fallbackCopyTextToClipboard(text, button) {
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.top = '0';
        textArea.style.left = '0';
        textArea.style.width = '2em';
        textArea.style.height = '2em';
        textArea.style.padding = '0';
        textArea.style.border = 'none';
        textArea.style.outline = 'none';
        textArea.style.boxShadow = 'none';
        textArea.style.background = 'transparent';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                button.addClass('copied');
                const originalText = button.text();
                button.text('✓ Código Copiado!');

                setTimeout(function () {
                    button.removeClass('copied');
                    button.text(originalText);
                }, 3000);
            } else {
                alert('Não foi possível copiar o código. Por favor, copie manualmente.');
            }
        } catch (err) {
            console.error('Erro ao copiar:', err);
            alert('Não foi possível copiar o código. Por favor, copie manualmente.');
        }

        document.body.removeChild(textArea);
    }

    // Auto-check payment status
    const pixContainer = $('.cielo-pix-container');
    if (pixContainer.length > 0) {
        const orderId = pixContainer.data('order-id');
        const paymentId = pixContainer.data('payment-id');
        let checkCount = 0;
        const maxChecks = 120; // Check for 10 minutes (every 5 seconds)

        function checkPaymentStatus() {
            if (checkCount >= maxChecks) {
                $('.cielo-pix-status').removeClass('checking').addClass('timeout');
                $('.cielo-pix-status').html('⏱️ Tempo de verificação automática esgotado. Recarregue a página para verificar o status.');
                return;
            }

            $.ajax({
                url: cielo_pix_params.check_payment_url,
                type: 'GET',
                data: {
                    order_id: orderId
                },
                success: function (response) {
                    if (response.success && response.data.paid) {
                        // Payment confirmed
                        $('.cielo-pix-status').removeClass('checking').addClass('paid');
                        $('.cielo-pix-status').html('✓ ' + response.data.message);

                        // Show success message
                        $('.cielo-pix-instructions').html('<strong>Pagamento confirmado com sucesso!</strong><br>Sua compra está sendo processada.');

                        // Reload page after 3 seconds to show updated order status
                        setTimeout(function () {
                            location.reload();
                        }, 3000);
                    } else {
                        // Payment still pending, check again
                        checkCount++;
                        setTimeout(checkPaymentStatus, 5000); // Check every 5 seconds
                    }
                },
                error: function (xhr, status, error) {
                    console.error('Erro ao verificar pagamento:', error);
                    checkCount++;
                    if (checkCount < maxChecks) {
                        setTimeout(checkPaymentStatus, 5000);
                    }
                }
            });
        }

        // Start checking after 5 seconds
        setTimeout(checkPaymentStatus, 5000);
    }

    // Add Pix icon to payment method selection
    if ($('input[name="payment_method"][value="cielo_pix"]').length > 0) {
        $('input[name="payment_method"][value="cielo_pix"]').parent().find('label').prepend('<span style="color: #32bcad; font-size: 18px; margin-right: 5px;">◈</span>');
    }
});
