{if is_array($machine)}
    <div class="mpcl-embed">
        <div class="mpcl-box{if $embedSettings['full_width']} full-width{/if}">
            <div class="mpcl-box-thumbnail">
                <img src="{$machine['thumbnail_uri']}"/>
            </div>

            <div class="mpcl-box-contents">
                <div class="mpcl-box-info">
                    <h6>{esc_html($machine['name'])}</h6>
                    <p>
                        {if !empty($machine['manufacturer'])}
                            {__("Manufacturer", "mpcl")}: {esc_html_e($machine['manufacturer'])}
                        {/if}
                    </p>
                    <p>
                        {if !empty($machine['type_name'])}
                            {__("Machine type", "mpcl")}:  {esc_html_e($machine['type_name'])}
                        {/if}
                    </p>
                </div>

                <div class="mpcl-box-cta">
                    <a href="{esc_url($machine['url'])}" {if $machine['url'] === false}disabled{/if}>
                        {__('View machine &raquo;', 'mpcl')}
                    </a>
                </div>
            </div>
        </div>
    </div>
{/if}