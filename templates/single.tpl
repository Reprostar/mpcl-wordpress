{if is_array($machine)}
    <div class="mpcl-single">
        <a href="{esc_url(remove_query_arg('machine_id'))}"
           class="trigger-back">{__("&laquo; Back to the list", "mpcl")}</a>
        <table style="width: 100%">
            <tr class="head">
                <td style="width: 33.333%">
                    <img class="thumbnail" src="{$machine['thumbnail_uri']}"/>
                </td>
                <td style="width: 66.666%">
                    <h2>{esc_html($machine['name'])}</h2>
                    <table>
                        {if !empty($machine['manufacturer'])}
                            <tr>
                                <td>{__("Manufacturer", "mpcl")}</td>
                                <td>{esc_html_e($machine['manufacturer'])}</td>
                            </tr>
                        {/if}
                        {if !empty($machine['type_name'])}
                            <tr>
                                <td>{__("Machine type", "mpcl")}</td>
                                <td>{esc_html_e($machine['type_name'])}</td>
                            </tr>
                        {/if}
                        {if !empty($machine['custom_name'])}
                            <tr>
                                <td>{__("Custom name", "mpcl")}</td>
                                <td>{esc_html_e($machine['custom_name'])}</td>
                            </tr>
                        {/if}
                        <tr>
                            <td>{__("Physical state", "mpcl")}</td>
                            <td>{esc_html_e($machine['physical_state'])}</td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="description">
                <td colspan="3">
                    {$machine['description']}
                </td>
            </tr>
            {if count($machine['photos'])}
                <tr class="photos">
                    <td colspan="3">
                        <div class="mpcl-baguette-list">
                            {foreach from=$machine['photos'] item=photo}
                                <a class="single-photo" href="{esc_url($photo['raw'])}">
                                    <img class="thumbnail" src="{esc_url($photo[100])}" width="100"/>
                                </a>
                            {/foreach}
                        </div>
                    </td>
                </tr>
            {/if}
        </table>
    </div>
{else}
    <h5>{__("Requested machine cannot be found. Check if you entered a valid URL.", "mpcl")}</h5>
{/if}
