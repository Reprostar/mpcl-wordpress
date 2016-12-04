# mypclist-wordpress
WordPress plugin that downloads your machine collection from MyPClist and displays it on your blog subpage.

### Installation
You can download prepared package file from releases in this repo then upload it to your WordPress instance 
or extract it directly to the `/wp-contents/plugins` folder on target server. The zip file comes with all 
dependencies bundled into, so the package is ready to go.

[Latest version (ZIP package)](https://github.com/Reprostar/mpcl-wordpress/releases)
 
### Installation (the hard way)
Clone this repo to your `/wp-content/plugins` directory and run `composer update` within it. This method 
is not really recommended unless you want to contribute to this repository.

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
This WordPress plugin is using the following packages/modules: 

* [MyPClist PHP Connector](https://github.com/Reprostar/mpcl-connector-php)
* [Smarty 3 templating engine](http://smarty.net)
* [JBBCode parser](http://jbbcode.com/)
* [baguetteBox.js lightbox](https://feimosi.github.io/baguetteBox.js/)

Module has been tested on **WordPress 4.4** running on **PHP 5.6**