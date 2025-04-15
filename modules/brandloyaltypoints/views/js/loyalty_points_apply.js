$(document).ready(function() {
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
            success: function(response) {
                showLoyaltyMessage(response.message, response.success ? 'success' : 'warning');
                if (response.success) {
                    setTimeout(function() {
                        location.reload();
                    }, 1500);
                }
            },
            error: function() {
                showLoyaltyMessage('An unexpected error occurred, please try again.', 'danger');
            }
        });
    }

    $('#apply-loyalty-points').on('click', function() {
        handleLoyaltyAction(applyLoyaltyPointsUrl);
    });

    $('#remove-loyalty-points').on('click', function() {
        handleLoyaltyAction(removeLoyaltyPointsUrl);
    });
});
