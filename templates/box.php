<?php
/** @var \Reprostar\MpclWordpress\View\BoxView $view */
$machine = $view->machine;
?>
<?php if (null !== $machine) : ?>
    <div class="mpcl-embed">
        <div class="mpcl-box<?php echo $view->fullWidth ? ' full-width' : ''; ?>">
            <div class="mpcl-box-thumbnail">
                <img src="<?php echo esc_url($machine->thumbnailUrl); ?>"/>
            </div>

            <div class="mpcl-box-contents">
                <div class="mpcl-box-info">
                    <h6><?php echo esc_html($machine->name); ?></h6>
                    <p>
                        <?php if (null !== $machine->manufacturer) : ?>
                            <?php echo __('Manufacturer', 'mpcl'); ?>: <?php echo esc_html($machine->manufacturer); ?>
                        <?php endif; ?>
                    </p>
                    <p>
                        <?php if (null !== $machine->typeName) : ?>
                            <?php echo __('Machine type', 'mpcl'); ?>:  <?php echo esc_html($machine->typeName); ?>
                        <?php endif; ?>
                    </p>
                </div>

                <div class="mpcl-box-cta">
                    <a href="<?php echo esc_url((string) $machine->catalogUrl); ?>" <?php echo null === $machine->catalogUrl ? 'disabled' : ''; ?>>
                        <?php echo __('View machine &raquo;', 'mpcl'); ?>
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>
