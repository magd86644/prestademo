document.addEventListener('DOMContentLoaded', function() {
    const applyBtn = document.getElementById('apply-loyalty-points');
    if (applyBtn) {
        applyBtn.addEventListener('click', function() {
            fetch(applyLoyaltyPointsUrl, {  // dynamic global variable!
                method: 'POST',
                headers: {'Content-Type': 'application/json'}
            })
            .then(response => response.json())
            .then(data => {
                alert(data.message);
                if (data.success) {
                    location.reload();
                }
            });
        });
    }
    $('#remove-loyalty-points').on('click', function() {
        console.log('Remove loyalty points clicked');
        $.ajax({
            url: removeLoyaltyPointsUrl,
            method: 'POST',
            success: function(response) {
                let res = JSON.parse(response);
                alert(res.message);
                location.reload(); // Reload cart totals
            }
        });
    });
    
});
