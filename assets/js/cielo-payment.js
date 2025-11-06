jQuery(function($) {
    'use strict';

    // Formata número do cartão
    $('#cielo_card_number').on('input', function() {
        let value = $(this).val().replace(/\s/g, '');
        let formattedValue = value.match(/.{1,4}/g);
        $(this).val(formattedValue ? formattedValue.join(' ') : value);
    });

    // Formata data de validade
    $('#cielo_card_expiry').on('input', function() {
        let value = $(this).val().replace(/\D/g, '');
        if (value.length >= 2) {
            value = value.substring(0, 2) + '/' + value.substring(2, 4);
        }
        $(this).val(value);
    });

    // Apenas números no CVV
    $('#cielo_card_cvc').on('input', function() {
        $(this).val($(this).val().replace(/\D/g, ''));
    });

    // Validação do formulário
    $('form.checkout').on('checkout_place_order_cielo_ecommerce', function() {
        if ($('#payment_method_cielo_ecommerce').is(':checked')) {
            let isValid = true;

            // Valida número do cartão
            let cardNumber = $('#cielo_card_number').val().replace(/\s/g, '');
            if (cardNumber.length < 13 || cardNumber.length > 19) {
                alert('Número do cartão inválido');
                isValid = false;
            }

            // Valida nome
            let cardHolder = $('#cielo_card_holder').val();
            if (cardHolder.length < 3) {
                alert('Nome no cartão inválido');
                isValid = false;
            }

            // Valida validade
            let expiry = $('#cielo_card_expiry').val();
            if (!/^\d{2}\/\d{2}$/.test(expiry)) {
                alert('Data de validade inválida');
                isValid = false;
            } else {
                let parts = expiry.split('/');
                let month = parseInt(parts[0]);
                let year = parseInt('20' + parts[1]);
                let now = new Date();
                let currentYear = now.getFullYear();
                let currentMonth = now.getMonth() + 1;

                if (month < 1 || month > 12) {
                    alert('Mês inválido');
                    isValid = false;
                } else if (year < currentYear || (year === currentYear && month < currentMonth)) {
                    alert('Cartão vencido');
                    isValid = false;
                }
            }

            // Valida CVV
            let cvc = $('#cielo_card_cvc').val();
            if (cvc.length < 3 || cvc.length > 4) {
                alert('CVV inválido');
                isValid = false;
            }

            return isValid;
        }
        return true;
    });

    // Detecta bandeira do cartão
    $('#cielo_card_number').on('input', function() {
        let cardNumber = $(this).val().replace(/\s/g, '');
        let brand = detectCardBrand(cardNumber);
        
        // Remove classes de bandeira anteriores
        $(this).removeClass('visa master amex elo hipercard');
        
        if (brand) {
            $(this).addClass(brand.toLowerCase());
        }
    });

    function detectCardBrand(number) {
        const patterns = {
            'visa': /^4/,
            'master': /^5[1-5]/,
            'amex': /^3[47]/,
            'elo': /^636368|^438935|^504175|^451416|^636297/,
            'hipercard': /^606282|^3841/,
            'diners': /^36|^38/
        };

        for (let brand in patterns) {
            if (patterns[brand].test(number)) {
                return brand;
            }
        }
        return null;
    }

    // Algoritmo de Luhn para validação do cartão
    function luhnCheck(cardNumber) {
        let sum = 0;
        let isEven = false;

        for (let i = cardNumber.length - 1; i >= 0; i--) {
            let digit = parseInt(cardNumber.charAt(i));

            if (isEven) {
                digit *= 2;
                if (digit > 9) {
                    digit -= 9;
                }
            }

            sum += digit;
            isEven = !isEven;
        }

        return (sum % 10) === 0;
    }
});