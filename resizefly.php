<?php

namespace Alpipego\Resizefly;

/**
 * Plugin Name: Resizefly
 * Description: Dynamically resize your images on the fly
 * Plugin URI:  http://resizefly.com/
 * Version:     1.1.3
 * Author:      Alex
 * Author URI:  http://alpipego.com/
 */

use Alpipego\Resizefly\Image\Editor as ImageEditor;
use Alpipego\Resizefly\Image\Handler as ImageHandler;
use Alpipego\Resizefly\Image\Image;
use Alpipego\Resizefly\Image\Stream;
use Alpipego\Resizefly\Upload\Fake;

require_once __DIR__ . '/src/Autoload.php';
new Autoload();

\add_action( 'plugins_loaded', function () {
	$plugin = new Plugin();

	$plugin['path']    = realpath( \plugin_dir_path( __FILE__ ) ) . DIRECTORY_SEPARATOR;
	$plugin['url']     = \plugin_dir_url( __FILE__ );
	$plugin['version'] = '1.1.3';

	$plugin['addons'] = apply_filters( 'resizefly_addons', [ ] );

	foreach ( $plugin['addons'] as $addonName => $addon ) {
		add_filter( "resizefly_plugin_{$addonName}", function () use ( $plugin ) {
			return $plugin;
		} );
	}

	$plugin['fake'] = function () {
		return new Fake();
	};

	$plugin->run();


	\add_action( 'template_redirect', function () use ( $plugin ) {
		if ( ! is_404() ) {
			return;
		}

		if ( preg_match( '/(.*?)-([0-9]+)x([0-9]+)\.(jpeg|jpg|png|gif)/i', $_SERVER['REQUEST_URI'], $matches ) ) {
			$plugin['requested_file'] = $matches;

			// get the correct path ("regardless" of WordPress installation path etc)
			$plugin['image'] = function ( $plugin ) {
				return new Image( $plugin['requested_file'], \wp_upload_dir( null, false ), \get_bloginfo( 'url' ) );
			};

			// get wp image editor and handle errors
			$plugin['wp_image_editor'] = \wp_get_image_editor( $plugin['image']->original );
			if ( ! file_exists( $plugin['image']->original ) || \is_wp_error( $plugin['wp_image_editor'] ) ) {
				\status_header( '404' );
				@include_once \get_404_template();

				exit;
			}

			// create image editor wrapper instance
			$plugin['image_editor'] = function ( $plugin ) {
				return new ImageEditor( $plugin['wp_image_editor'] );
			};

			// create image handling instance
			$plugin['image_handler'] = function ( $plugin ) {
				return new ImageHandler( $plugin['image'], $plugin['image_editor'] );
			};

			// output stream the resized image
			$plugin['output'] = function ( $plugin ) {
				return new Stream( \wp_get_image_editor( $plugin['image_handler']->file ) );
			};

			$plugin->run();
		}
	} );
} );
