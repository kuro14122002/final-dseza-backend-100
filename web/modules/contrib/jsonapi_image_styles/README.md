INTRODUCTION
------------

JSON:API Image Styles is a JSON:API extension that exposes image style urls of a
drupal image to jsonapi export. This allows e.g. crop-defined image styles to be
consumed by frontend builds.

 * To submit bug reports and feature suggestions, or track changes:
   https://www.drupal.org/project/issues/jsonapi_image_styles


REQUIREMENTS
------------

 * PHP 7.4 (note: this requirement is not enforced by `composer.json` due to
   Drupal core's support of PHP 7.3. Requiring PHP 7.4 breaks testing against
   due to core's [`composer.json`](https://git.drupalcode.org/project/drupal/-/blob/9.3.x/composer.json#L51).)
 * Drupal 9+


INSTALLATION
------------

 * Install as any contributed Drupal module. For further information, visit
   [Installing modules](https://www.drupal.org/docs/extending-drupal/installing-modules).


CONFIGURATION
-------------

 * Configure the image styles you wish to expose in Administration »
 Configuration » Web Services » JSON:API Image Styles
 (/admin/config/services/jsonapi/image_styles)

   - Select the image styles you wish to expose for JSON:API

   - Select none to expose all defined image styles (default behaviour)


MAINTAINERS
-----------

Current maintainers:
 * Christopher C. Wells (wells) - https://www.drupal.org/u/wells
 * Andrii Podanenko (podarok) - https://www.drupal.org/u/podarok

This project has been sponsored by:
 * Cascade Public Media - https://www.drupal.org/cascade-public-media
 * ITCare - https://www.drupal.org/itcare
 * Wunder.io - https://www.drupal.org/wunder
