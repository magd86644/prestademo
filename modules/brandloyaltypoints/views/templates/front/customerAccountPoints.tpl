{extends file='customer/page.tpl'}

{block name='page_title'}
  {l s='My Loyalty Miles by Brand' mod='brandloyaltypoints'}
{/block}

{block name='page_content'}
  <section class="card">
    <div class="card-header">
      <h3 class="h3 mb-0">{l s='Loyalty Miles Summary' mod='brandloyaltypoints'}</h3>
    </div>

    <div class="card-body">
      {if $pointsByBrand|count}
        <div class="table-responsive">
          <table class="table table-striped table-bordered mb-0">
            <thead class="thead-default">
              <tr>
                <th>{l s='Brand' mod='brandloyaltypoints'}</th>
                <th class="text-right">{l s='Miles' mod='brandloyaltypoints'}</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$pointsByBrand item=entry}
                <tr>
                  <td>{$entry.brand_name|escape:'html'}</td>
                  <td class="text-right">{$entry.points|intval}</td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </div>
      {else}
        <p class="alert alert-info mb-0">
          {l s='You have no loyalty miles yet.' mod='brandloyaltypoints'}
        </p>
      {/if}
    </div>
  </section>

{* New Section: Orders with Loyalty Points *}
  <section id="loyalty-points-client-summary" class="card mt-4">
    <div class="card-header">
      <h3 class="h3 mb-0">{l s='Loyalty Miles Earned From Orders' mod='brandloyaltypoints'}</h3>
    </div>

    <div class="card-body">
    {if $ordersWithPoints|count}
      <div class="table-responsive">
        <table class="table table-striped table-bordered mb-0">
          <thead class="thead-default">
            <tr>
              <th>{l s='Order Reference' mod='brandloyaltypoints'}</th>
              <th>{l s='Order Date' mod='brandloyaltypoints'}</th>
              <th class="text-right">{l s='Order Total (incl. tax)' mod='brandloyaltypoints'}</th>
              <th class="text-right">{l s='Points Earned' mod='brandloyaltypoints'}</th>
              
              <th>{l s='Delivery Date' mod='brandloyaltypoints'}</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$ordersWithPoints item=order}
              <tr>
                <td>{$order.reference|escape:'html'}</td>
                <td>{$order.order_date|date_format:'%Y-%m-%d'}</td>
                <td class="text-right">{$order.formatted_total}</td>
                <td class="text-right">{$order.points_granted|intval}</td>
                <td>
                  {if $order.delivery_date}
                    {$order.delivery_date|date_format:'%Y-%m-%d'}
                  {else}
                    {l s='Not yet delivered' mod='brandloyaltypoints'}
                  {/if}
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>
    {else}
      <p class="alert alert-info mb-0">
        {l s='No loyalty points have been earned from orders yet.' mod='brandloyaltypoints'}
      </p>
    {/if}
  </section>
{/block}
