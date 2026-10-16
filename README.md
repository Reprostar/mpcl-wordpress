# mypclist-wordpress
WordPress plugin that downloads your machine collection from MyPClist and displays it on your blog subpage.

### Installation
You can download prepared package file from releases in this repo then upload it to your WordPress instance 
or extract it directly to the `/wp-contents/plugins` folder on target server. The zip file comes with all 
dependencies bundled into, so the package is ready to go.

[Latest version (ZIP package)](https://github.com/Reprostar/mpcl-wordpress/releases)
 
### Installation (the hard way)
Clone this repo into `/wp-content/plugins/mpcl`. The plugin has no runtime dependencies to install.

### Usage
To display your collection, create a new WordPress page (not a post!), then paste the following shortcode:
```
[mpcl columns=4]
```

Of course, you can change the *columns* parameter to display different amount of columns in a grid.
View of single machine is also displayed in the place of this shortcode (you don't need any other page for 
the other views).

You can also display a box referencing specific machine by pasting shortcode on any post or page:
```
[mpcl id=15]
```

Where `15` should be replaced with target machine ID.

### Dependencies
This WordPress plugin bundles:

* [baguetteBox.js lightbox](https://feimosi.github.io/baguetteBox.js/)
* a BBCode converter adapted from [JBBCode](https://github.com/jbowens/jBBCode) (MIT, see `licenses/jbbcode.txt`)

### Requirements
Version 2.x requires **WordPress 6.9** or newer and **PHP 7.4** or newer. The collection is refreshed in the
background by WP-Cron; sites with `DISABLE_WP_CRON` need a system cron calling `wp-cron.php`.

Version 1.2.2 is the last release for older sites (declared for WordPress 4.4 on PHP 5.6).