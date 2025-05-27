<div id="loyalty-summary-container">
    <div class="loyalty-summary-block">
        {foreach from=$pointsData item=entry}
            {if $entry.can_apply || $entry.is_applied}
                <div class="loyalty-point-record">
                    <strong>{$entry.manufacturer_name}</strong>
                    <small>{$entry.total_points} pts</small>
                    {if $entry.can_apply}
                        <button 
                            class="btn btn-sm btn-success apply-brand-loyalty" 
                            data-brand-id="{$entry.id_manufacturer}">
                            Apply
                        </button>
                    {/if}
                </div>
            {/if}
        {/foreach}

        {if $hasAppliedLoyalty}
            <button id="remove-loyalty-points" class="btn btn-danger btn-sm mt-2">
                Reset
            </button>
        {/if}

        <div id="loyalty-message-container" style="margin-top: 12px;"></div>
    </div>
</div>

    <script>
        var applyLoyaltyPointsUrl = '{$loyaltyPointsApplyUrl|escape:'javascript'}';
        var removeLoyaltyPointsUrl = '{$loyaltyPointsRemoveUrl|escape:'javascript'}';
    </script>
