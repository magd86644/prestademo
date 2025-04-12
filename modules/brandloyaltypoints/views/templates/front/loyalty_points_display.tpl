{if $pointsData|@count > 0}
    <div class="brand-loyalty-points card p-3 my-3">
        <h3 class="h5 mb-3">🎁 Your Loyalty Points by Brand </h3>
        <ul class="list-group">
            {foreach from=$pointsData item=entry}
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <strong>{$entry.manufacturer_name}</strong>
                    <span>{$entry.total_points } points</span>
                </li>
            {/foreach}
        </ul>
    </div>
{else}
    <p class="alert alert-info my-3">You don’t have any loyalty points yet.</p>
{/if}
