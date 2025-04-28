<form action="{$form_action}" method="post">
    <fieldset>
        <legend><img src="{$logo_path}" alt="Logo" /> {l s='Loyalty Points Configuration' mod='brandloyaltypoints'}</legend>
        <table class="table">
            <thead>
                <tr>
                    <th>{l s='Manufacturer' mod='brandloyaltypoints'}</th>
                    <th>{l s='Points Conversion Rate' mod='brandloyaltypoints'}</th>
                </tr>
            </thead>
            <tbody>
                {foreach from=$manufacturers item=manufacturer}
                    <tr>
                        <td>{$manufacturer.name}</td>
                        <td>
                            <input type="text" name="points_conversion_rate[{$manufacturer.id_manufacturer}]" 
                                   value="{$manufacturer.points_conversion_rate}" 
                                   class="form-control" />
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
        <div class="form-group">
            <button type="submit" name="submit_loyalty_points_config" class="btn btn-default">
                {l s='Save Configuration' mod='brandloyaltypoints'}
            </button>
        </div>
    </fieldset>
</form>
