<div class="wrap">
    <h2>MyPCList integration</h2>

    <form method="post" action="options.php">
        <?php settings_fields('mpcl-options'); ?>
        <?php do_settings_sections('mpcl-options'); ?>
        <?php submit_button(__('Clear cache', 'mpcl'), 'secondary', 'reset', false); ?>
        <?php submit_button(); ?>
    </form>
</div>