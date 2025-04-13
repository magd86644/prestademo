{if isset($pointsData) && $pointsData|@count > 0}
    <div class="loyalty-points-apply-box card p-3 my-3">
        <h3 class="h5 mb-3">💡 Apply Your Loyalty Points</h3>
        <button id="apply-loyalty-points" class="btn btn-success">
            Apply Loyalty Points Now
        </button>
    </div>
    <script>
        var applyLoyaltyPointsUrl = '{$loyaltyPointsApplyUrl|escape:'javascript'}';
    </script>
{/if}
