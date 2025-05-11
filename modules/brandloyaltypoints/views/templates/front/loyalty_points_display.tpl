{foreach from=$pointsData item=entry}
{if $entry.can_apply || $entry.is_applied}
    <li class="list-group-item d-flex justify-content-between align-items-center">
        <div class="d-flex align-items-center gap-2">
            <span>
                <strong>{$entry.manufacturer_name}</strong> -
                <small class="text-muted me-2">{$entry.total_points} points</small>
            </span>

           
                
            {if $entry.can_apply}
                <button 
                    class="btn btn-sm btn-success apply-brand-loyalty" 
                    data-brand-id="{$entry.id_manufacturer}">
                    Apply
                </button>
            {/if}
        </div>
    </li>
    {/if}
{/foreach}

{if $hasAppliedLoyalty}
    <button id="remove-loyalty-points" class="btn btn-danger btn-sm mt-2">
        Reset
    </button>
{/if}
 <script>
        var applyLoyaltyPointsUrl = '{$loyaltyPointsApplyUrl|escape:'javascript'}';
        var removeLoyaltyPointsUrl = '{$loyaltyPointsRemoveUrl|escape:'javascript'}';
    </script>
<div id="loyalty-message-container" class="mt-3"></div>
