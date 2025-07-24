<div id="loyalty-summary-container">
  <div class="loyalty-summary-block">
    {foreach from=$pointsData item=entry}
      {if $entry.can_apply || $entry.is_applied}
        <div class="loyalty-point-record mb-1 p-2 border rounded">
          <strong>{$entry.manufacturer_name}</strong>
          <small class="text-muted d-block">{$entry.total_points} miles</small>

          {if $entry.can_apply}
            <div class="mt-2">
              <button class="btn btn-sm btn-success apply-discount-btn apply-brand-loyalty" data-brand-id="{$entry.id_manufacturer}">
                Apply Discount
              </button>

              {assign var=gifts value=$availableGifts[$entry.id_manufacturer]}
              {if $gifts|@count > 0}
                <button class="btn btn-sm btn-primary apply-discount-btn choose-gift-btn" data-brand-id="{$entry.id_manufacturer}">
                  Choose Gift
                </button>

                <div class="gift-selection mt-2" id="gift-options-{$entry.id_manufacturer}" style="display: none;">
                  <select class="form-control gift-dropdown" data-brand-id="{$entry.id_manufacturer}">
                    <option value="">-- Select a gift --</option>
                    {foreach from=$gifts item=gift}
                      <option value="{$gift.id_product}">{$gift.name}</option>
                    {/foreach}
                  </select>
                  {foreach from=$gifts item=gift}
                  <div class="gift-image mt-1" data-gift-id="{$gift.id_product}" style="display: none;">
                    {if $gift.image_url}
                      <img src="{$gift.image_url}" alt="{$gift.name}" class="img-thumbnail" style="max-width: 100px;" />
                    {/if}
                  </div>
                {/foreach}
                  <button class="btn btn-sm btn-success mt-1 apply-gift-btn" data-brand-id="{$entry.id_manufacturer}">
                    Apply Gift
                  </button>
                </div>
              {/if}
            </div>
          {/if}
        </div>
      {/if}
    {/foreach}

    {if $hasAppliedLoyalty}
      <button id="remove-loyalty-points" class="btn btn-danger btn-sm mt-3">
        Reset
      </button>
    {/if}

    <div id="loyalty-message-container" style="margin-top: 12px;"></div>
  </div>
</div>

    <script>
        var applyLoyaltyPointsUrl = '{$loyaltyPointsApplyUrl|escape:'javascript'}';
        var removeLoyaltyPointsUrl = '{$loyaltyPointsRemoveUrl|escape:'javascript'}';
        var applyGiftUrl = '{$loyaltyPointsApplyGiftUrl|escape:'javascript'}';
    </script>
