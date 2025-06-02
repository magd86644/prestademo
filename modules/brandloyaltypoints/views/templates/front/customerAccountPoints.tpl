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
{/block}
