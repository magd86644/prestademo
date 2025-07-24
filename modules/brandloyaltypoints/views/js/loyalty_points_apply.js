$(document).ready(function () {
    const $messageContainer = $('#loyalty-message-container');

    function showLoyaltyMessage(message, type = 'success') {
        console.log(message);
        $messageContainer.html(`
            <div class="alert alert-${type} alert-dismissible" role="alert">
                ${message}
                <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
        `);
    }

    function handleLoyaltyAction(url, $button = null) {
        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            success: function (response) {
                showLoyaltyMessage(response.message, response.success ? 'success' : 'warning');
                if (response.success) {
                    setTimeout(function () {
                        reloadLoyaltyBlock();
                    }, 1000);
                } else {
                    if ($button) {
                        $button.text('Retry').prop('disabled', false);
                    }
                }
            },
            error: function (jqXHR, textStatus, errorThrown) {
                let errorMsg = 'An unexpected error occurred, please try again...';

                // Try to parse and show the server response if it's JSON
                if (jqXHR.responseJSON && jqXHR.responseJSON.message) {
                    errorMsg = jqXHR.responseJSON.message;
                }
                // Otherwise, try the raw response text
                else if (jqXHR.responseText) {
                    errorMsg = jqXHR.responseText;
                }

                // Optional: Add status code and errorThrown to help debug
                console.error('AJAX Error:', {
                    status: jqXHR.status,
                    statusText: textStatus,
                    error: errorThrown,
                    response: jqXHR.responseText
                });

                showLoyaltyMessage(errorMsg, 'danger');
                if ($button) {
                    $button.text('Apply').prop('disabled', false);
                }
            }
        });
    }

    $(document).on('click', '.apply-brand-loyalty', function () {
        const brandId = $(this).data('brand-id');
        if (!brandId) return;
        const $btn = $(this);
        const url = `${applyLoyaltyPointsUrl}?brand=${brandId}`;
        $btn.text('Applying...').prop('disabled', true);
        handleLoyaltyAction(url, $btn);
    });
    $(document).on('click', '#remove-loyalty-points', function () {
        const $btn = $(this);
        $btn.text('Removing...').prop('disabled', true);
        handleLoyaltyAction(removeLoyaltyPointsUrl, $btn);
    });

    function reloadLoyaltyBlock() {
        window.prestashop.emit('updateCart', {
            reason: {
                linkAction: 'loyaltyApplied'
            },
            resp: {}
        });
    }

    // $(document).ready(function() {
    console.log('Loyalty points apply script initialized');
    // Toggle gift selection UI
    $(document).on('click', '.choose-gift-btn', function () {
        const brandId = $(this).data('brand-id');
        const $section = $('#gift-options-' + brandId);
        if ($section.is(':visible')) {
            $section.hide();
        } else {
            $('.gift-selection').hide(); // hide others
            $section.show();
        }
    });

    // Handle applying selected gift
    $(document).on('click', '.apply-gift-btn', function () {
        const brandId = $(this).data('brand-id');
        const $dropdown = $('.gift-dropdown[data-brand-id="' + brandId + '"]');
        const productId = $dropdown.val();

        if (!productId) {
            showLoyaltyMessage('Please select a gift to apply.', 'warning');
            return;
        }
        const $btn = $(this);
        const url = `${applyGiftUrl}?brand=${brandId}&product=${productId}`;
        $btn.text('Applying...').prop('disabled', true);
        handleLoyaltyAction(url, $btn);
    });

    // Show gift image on selection 
    $(document).on('change', '.gift-dropdown', function () {
        const brandId = $(this).data('brand-id');
        const productId = $(this).val();

        const $section = $('#gift-options-' + brandId);

        $section.find('.gift-image').hide();

        if (productId) {
            $section.find('.gift-image[data-gift-id="' + productId + '"]').show();
        }
    });



});
