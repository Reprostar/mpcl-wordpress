<?php
defined('ABSPATH') || exit;
/** @var \Reprostar\MpclWordpress\View\SingleView $view */
$machine = $view->machine;
?>
<?php if (null !== $machine) : ?>
    <div class="mpcl-single">
        <a href="<?php echo esc_url(remove_query_arg('machine_id')); ?>"
           class="trigger-back"><?php echo __('&laquo; Back to the list', 'mpcl'); ?></a>
        <table style="width: 100%">
            <tr class="head">
                <td style="width: 33.333%">
                    <img class="thumbnail" src="<?php echo esc_url($machine->thumbnailUrl); ?>"/>
                </td>
                <td style="width: 66.666%">
                    <h2><?php echo esc_html($machine->name); ?></h2>
                    <table>
                        <?php if (null !== $machine->manufacturer) : ?>
                            <tr>
                                <td><?php echo __('Manufacturer', 'mpcl'); ?></td>
                                <td><?php echo esc_html($machine->manufacturer); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (null !== $machine->typeName) : ?>
                            <tr>
                                <td><?php echo __('Machine type', 'mpcl'); ?></td>
                                <td><?php echo esc_html($machine->typeName); ?></td>
                            </tr>
                        <?php endif; ?>
                        <?php if (null !== $machine->customName) : ?>
                            <tr>
                                <td><?php echo __('Custom name', 'mpcl'); ?></td>
                                <td><?php echo esc_html($machine->customName); ?></td>
                            </tr>
                        <?php endif; ?>
                        <tr>
                            <td><?php echo __('Physical state', 'mpcl'); ?></td>
                            <td><?php echo esc_html($machine->physicalState); ?></td>
                        </tr>
                    </table>
                </td>
            </tr>
            <tr class="description">
                <td colspan="3">
                    <?php echo $machine->descriptionHtml; ?>
                </td>
            </tr>
            <?php if ([] !== $machine->photos) : ?>
                <tr class="photos">
                    <td colspan="3">
                        <div class="mpcl-baguette-list">
                            <?php foreach ($machine->photos as $photo) : ?>
                                <a class="single-photo" href="<?php echo esc_url($photo->fullUrl); ?>">
                                    <img class="thumbnail" src="<?php echo esc_url($photo->smallUrl); ?>" width="100"/>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    </td>
                </tr>
            <?php endif; ?>
        </table>
    </div>
<?php else : ?>
    <h5><?php echo __('Requested machine cannot be found. Check if you entered a valid URL.', 'mpcl'); ?></h5>
<?php endif; ?>
