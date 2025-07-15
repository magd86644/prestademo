<form action="{$form_action}" method="post">
  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex align-items-center">
      <img src="{$logo_path}" alt="Logo" style="height: 30px; margin-right: 10px;" />
      <h5 class="mb-0">{l s='Loyalty Points Configuration' mod='brandloyaltypoints'}</h5>
    </div>
    <div class="card-body">

      <!-- Conversion Rates -->
      <h5 class="mb-3">{l s='Points Conversion Rates by Brand' mod='brandloyaltypoints'}</h5>
      <div class="table-responsive mb-4">
        <table class="table table-striped table-bordered">
          <thead class="thead-light">
            <tr>
              <th>{l s='Manufacturer' mod='brandloyaltypoints'}</th>
              <th>{l s='Points Conversion Rate' mod='brandloyaltypoints'}</th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$manufacturers item=manufacturer}
              <tr>
                <td>{$manufacturer.name|escape:'html':'UTF-8'}</td>
                <td style="width: 200px;">
                  <input type="number" min="0" step="0.01"
                         name="points_conversion_rate[{$manufacturer.id_manufacturer}]"
                         value="{$manufacturer.points_conversion_rate|escape:'html':'UTF-8'}"
                         class="form-control" />
                </td>
              </tr>
            {/foreach}
          </tbody>
        </table>
      </div>

      <!-- Brand Gifts -->
      <h5 class="mb-3">{l s='Available Gifts per Brand' mod='brandloyaltypoints'}</h5>
      {foreach from=$brands item=brand}
        <div class="card mb-3 border">
          <div class="card-header bg-light font-weight-bold">
            {$brand.name|escape:'html':'UTF-8'}
          </div>
          <div class="card-body">
            {if $brand.products|count > 0}
              <div class="row">
                {foreach from=$brand.products item=product}
                  <div class="col-md-4 mb-2">
                    <div class="form-check">
                      <input class="form-check-input" 
                             type="checkbox"
                             name="brand_gifts[{$brand.id_manufacturer}][]"
                             value="{$product.id_product}"
                             id="gift_{$brand.id_manufacturer}_{$product.id_product}"
                             {if $product.is_selected}checked{/if} />
                      <label class="form-check-label" for="gift_{$brand.id_manufacturer}_{$product.id_product}">
                        {$product.name|escape:'html':'UTF-8'}
                      </label>
                    </div>
                  </div>
                {/foreach}
              </div>
            {else}
              <p class="text-muted mb-0">
                {l s='No products marked as gifts for this brand.' mod='brandloyaltypoints'}
              </p>
            {/if}
          </div>
        </div>
      {/foreach}

      <div class="text-right mt-4">
        <button type="submit" name="submit_loyalty_points_config" class="btn btn-primary">
          <i class="material-icons">save</i>
          {l s='Save Configuration' mod='brandloyaltypoints'}
        </button>
      </div>

    </div>
  </div>
</form>
