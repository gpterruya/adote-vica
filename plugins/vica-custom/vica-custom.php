<?php
/**
 * Plugin Name: Vi.Ca Custom
 * Description: Customizações de front-end do Adote Vi.Ca (CSS/PHP versionados no Git).
 * Version:     1.4.0
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
 * SVGs otimizados (assets/img/). O Elementor embute no HTML o conteúdo dos
 * SVGs enviados em widgets de ícone, lido do meta "_elementor_inline_svg".
 * Os originais de uploads/2025/10/ eram enormes: logo do topo e ícones com
 * textura (~4 mil curvas e coordenadas com até 8 casas decimais, ~210 KB cada)
 * e logo do rodapé (PNG 3201×2601 em base64 usado como máscara, 290 KB).
 * Um SVG de uploads/2025/10/ que tenha versão com o mesmo nome em assets/img/
 * é entregue otimizado, sem alterar banco nem uploads.
 * Depois de publicar, limpe o cache do Elementor (Ferramentas → Limpar
 * arquivos e dados), que guarda o HTML dos widgets por até 24 h.
 */
add_filter( 'get_post_metadata', function ( $value, $object_id, $meta_key ) {
	if ( '_elementor_inline_svg' !== $meta_key ) {
		return $value;
	}

	$file = get_post_meta( $object_id, '_wp_attached_file', true );

	if ( ! is_string( $file ) || ! str_starts_with( $file, '2025/10/' ) || ! str_ends_with( $file, '.svg' ) ) {
		return $value;
	}

	$path = __DIR__ . '/assets/img/' . basename( $file );

	if ( ! is_readable( $path ) ) {
		return $value;
	}

	static $cache = [];
	$cache[ $path ] ??= file_get_contents( $path );

	// get_metadata() usa o índice 0 quando $single é true.
	return [ $cache[ $path ] ];
}, 10, 3 );

/*
 * Página 404 com o visual do site (templates/404.php + assets/css/404.css),
 * no lugar do 404 básico do tema.
 */
add_filter( 'template_include', function ( $template ) {
	return is_404() ? __DIR__ . '/templates/404.php' : $template;
}, 99 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_404() ) {
		return;
	}

	$file = __DIR__ . '/assets/css/404.css';

	wp_enqueue_style(
		'vica-custom-404',
		plugins_url( 'assets/css/404.css', __FILE__ ),
		[],
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

// URLs residuais sem conteúdo público respondem 404 (com a página 404 do plugin):
// - /e-floating-buttons/<nome>/: o botão flutuante do Elementor exibido como página;
// - /category/<nome>/: arquivos de categoria (o site não tem blog).
// Quem pode editar continua vendo, para não quebrar o editor/preview do Elementor.
add_action( 'template_redirect', function () {
	if ( current_user_can( 'edit_posts' ) ) {
		return;
	}

	if ( is_singular( 'e-floating-buttons' ) || is_category() ) {
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}
}, 1 );

// O oEmbed (/wp-json/oembed/1.0/embed?url=...) também traz o login do autor.
add_filter( 'oembed_response_data', function ( $data ) {
	unset( $data['author_name'], $data['author_url'] );
	return $data;
} );

// Tira os autores do sitemap do Yoast (author-sitemap.xml).
add_filter( 'wpseo_sitemap_exclude_author', '__return_empty_array' );
