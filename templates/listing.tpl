<div class="mpcl-listing mpcl-listing-grid">
    <div class="mpcl-entries">
        {foreach from=$rows item=row}
            <div class="entries-row">
                {foreach from=$row item=machine}
                    <div class="mpcl-entry" style="width: calc({esc_attr($columns_width)} - 10px);">
                        <a href="{esc_url(add_query_arg('machine_id', $machine['id']))}">
                            <img class="thumb" src="{esc_url($machine['thumbnail_uri'])}"/>

                            <h4>{esc_attr_e($machine['name'])}</h4>
                        </a>
                    </div>
                {/foreach}
            </div>
        {/foreach}

         {*<div class="pagination"></div>*}
             {*<div class="page-prev"></div>*}
                 {*<a href="#">&laquo; --><?php //_e("Previous page", "mpcl"); ?></a>*}
             {*</div>*}
             {*<div class="page-next">*}
                 {*<a href="#">--><?php //_e("Next page", "mpcl"); ?> &raquo;</a>*}
             {*</div>*}
         {*</div>*}
    </div>
</div>