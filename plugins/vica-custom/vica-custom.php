<?php
/**
 * Plugin Name: Vi.Ca Custom
 * Description: Customizações de front-end do Adote Vi.Ca (CSS/PHP versionados no Git).
 * Version:     1.0.0
 * Author:      Adote Vi.Ca
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', function () {
	$file = __DIR__ . '/assets/css/style.css';

	wp_enqueue_style(
		'vica-custom',
		plugins_url( 'assets/css/style.css', __FILE__ ),
		// Carrega depois do CSS do Elementor para poder sobrescrevê-lo.
		wp_style_is( 'elementor-frontend', 'registered' ) ? [ 'elementor-frontend' ] : [],
		// Versão pelo mtime do arquivo: cada alteração invalida o cache do navegador.
		file_exists( $file ) ? (string) filemtime( $file ) : '1.0.0'
	);
}, 20 );
