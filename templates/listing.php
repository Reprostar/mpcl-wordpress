<?php
/** @var \Reprostar\MpclWordpress\View\ListingView $view */
?>
<div class="mpcl-listing mpcl-listing-grid">
    <div class="mpcl-entries">
        <?php foreach ($view->rows as $row) : ?>
            <div class="entries-row">
                <?php foreach ($row as $machine) : ?>
                    <div class="mpcl-entry" style="width: calc(<?php echo esc_attr($view->columnWidth); ?> - 10px);">
                        <a href="<?php echo esc_url(add_query_arg('machine_id', $machine->id)); ?>">
                            <img class="thumb" src="<?php echo esc_url($machine->thumbnailUrl); ?>"/>

                            <h4><?php echo esc_attr($machine->name); ?></h4>
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>
