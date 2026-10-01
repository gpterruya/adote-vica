<?php
/**
 * Página 404 com o visual do Adote Vi.Ca.
 *
 * Substitui o 404 do tema (Hello Elementor), que não tem o visual do site.
 * O status HTTP continua 404; wp_head()/wp_footer() mantêm o Yoast,
 * o Tag Manager e o botão flutuante de telefone do Elementor.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$vica_uploads = wp_get_upload_dir()['baseurl'] . '/2025/10';
$vica_home    = home_url( '/' );
?>
<!doctype html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php wp_head(); ?>
</head>
<body <?php body_class( 'vica-404' ); ?>>
<?php wp_body_open(); ?>

<header class="vica-404__header">
	<a href="<?php echo esc_url( $vica_home ); ?>" aria-label="Adote Vi.Ca — página inicial">
		<img src="<?php echo esc_url( $vica_uploads . '/logo-topo.svg' ); ?>" alt="Vi.Ca" width="178" height="47">
	</a>
</header>

<main class="vica-404__main">
	<div class="vica-404__card">
		<div class="vica-404__texto">
			<p class="vica-404__ops">ops!</p>
			<h1 class="vica-404__titulo">Página não encontrada</h1>
			<p class="vica-404__descricao">
				O endereço que você tentou acessar não existe ou mudou de lugar.
				Mas os nossos pets continuam esperando por um lar.
			</p>
			<a class="vica-404__botao" href="<?php echo esc_url( $vica_home ); ?>">voltar para o início</a>
			<p class="vica-404__contato">
				ou fale com a equipe: <a href="tel:+551138850749">(11) 3885-0749</a>
			</p>
		</div>
		<img
			class="vica-404__imagem"
			src="<?php echo esc_url( $vica_uploads . '/pet-1024x817.webp' ); ?>"
			alt="Buldogue e gato em caminhas"
			width="1024" height="817"
		>
	</div>
</main>

<footer class="vica-404__footer">
	<p><strong>Centro de Adoção Responsável</strong></p>
	<p>R. Pamplona, 1481 – Jardins, São Paulo – SP, 01405-003 · <a href="tel:+551138850749">(11) 3885-0749</a></p>
	<p>© <?php echo esc_html( wp_date( 'Y' ) ); ?> Adote Vi.Ca. Todos os direitos reservados.</p>
</footer>

<?php wp_footer(); ?>
</body>
</html>
