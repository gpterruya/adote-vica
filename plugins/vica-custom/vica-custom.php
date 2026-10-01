<?php
/**
 * Plugin Name: Vi.Ca Custom
 * Description: Customizações de front-end do Adote Vi.Ca (CSS/PHP versionados no Git).
 * Version:     1.1.0
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

/*
 * Segurança: não expor os nomes de usuário do painel.
 * Sem isso, qualquer visitante lista os logins por /wp-json/wp/v2/users
 * ou por /?author=N (que redireciona para /author/<login>/).
 */

// Endpoints de usuários da REST API só para quem está logado
// (o editor de blocos e o Elementor continuam funcionando).
add_filter( 'rest_endpoints', function ( $endpoints ) {
	if ( is_user_logged_in() ) {
		return $endpoints;
	}

	foreach ( array_keys( $endpoints ) as $route ) {
		if ( str_starts_with( $route, '/wp/v2/users' ) ) {
			unset( $endpoints[ $route ] );
		}
	}

	return $endpoints;
} );

// Arquivos de autor (/author/<login>/ e /?author=N) vão para a página inicial.
// Prioridade 1: roda antes do redirect_canonical do WordPress, que revelaria
// o login no cabeçalho Location do redirecionamento.
add_action( 'template_redirect', function () {
	if ( is_author() || isset( $_GET['author'] ) ) {
		wp_safe_redirect( home_url( '/' ), 301 );
		exit;
	}
}, 1 );

// O oEmbed (/wp-json/oembed/1.0/embed?url=...) também traz o login do autor.
add_filter( 'oembed_response_data', function ( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
} );

// Tira os autores do sitemap do Yoast (author-sitemap.xml).
add_filter( 'wpseo_sitemap_exclude_author', '__return_empty_array' );
