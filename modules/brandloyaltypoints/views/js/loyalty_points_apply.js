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

    function handleLoyaltyAction(url) {
        $.ajax({
            url: url,
            method: 'POST',
            dataType: 'json',
            success: function (response) {
                showLoyaltyMessage(response.message, response.success ? 'success' : 'warning');
                if (response.success) {
                    setTimeout(function () {
                        reloadLoyaltyBlock();
                    }, 1500);
                }
            },
            error: function () {
                showLoyaltyMessage('An unexpected error occurred, please try again.', 'danger');
            }
        });
    }

    $(document).on('click', '.apply-brand-loyalty', function () {
        const brandId = $(this).data('brand-id');
        if (!brandId) return;

        const url = `${applyLoyaltyPointsUrl}?brand=${brandId}`;
        $(this).text('Applying...').prop('disabled', true);
        handleLoyaltyAction(url);
    });
    $(document).on('click', '#remove-loyalty-points', function () {
        $(this).text('Removing...').prop('disabled', true);
        handleLoyaltyAction(removeLoyaltyPointsUrl);
    });

    function reloadLoyaltyBlock() {
        window.prestashop.emit('updateCart', {
            reason: {
                linkAction: 'loyaltyApplied' 
            },
            resp: {} 
        });
    }
});
