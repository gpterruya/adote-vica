<?php
/**
 * Otimiza um SVG que pinta uma cor usando um PNG em base64 como máscara
 * de alpha (é o caso do logo do rodapé, exportado do Figma).
 * Reduz o PNG para a largura pedida e regrava só o canal alpha.
 *
 * Precisa da extensão GD, que existe no container "web". Uso (no Ubuntu,
 * a partir de ~/adote-vica); o script vai pela entrada padrão e o SVG
 * otimizado sai na saída padrão:
 *
 *   docker compose exec -T web php -- <entrada-no-container.svg> <largura> \
 *     < tools/svg/otimizar-mascara.php > plugins/vica-custom/assets/img/<nome>.svg
 *
 * Use ~2,5× a largura em que o logo aparece no site (nítido em telas retina).
 */

if ( $argc !== 3 ) {
	fwrite( STDERR, "uso: php -- <entrada.svg> <largura_png> < otimizar-mascara.php > saida.svg\n" );
	exit( 1 );
}

[ , $src, $target_w ] = $argv;
$svg = file_get_contents( $src );

if ( ! preg_match( '#data:image/png;base64,([A-Za-z0-9+/=]+)#', $svg, $m ) ) {
	fwrite( STDERR, "nenhum PNG em base64 encontrado em $src\n" );
	exit( 1 );
}

$orig = imagecreatefromstring( base64_decode( $m[1] ) );
$ow   = imagesx( $orig );
$oh   = imagesy( $orig );
$tw   = (int) $target_w;
$th   = (int) round( $oh * $tw / $ow );

$small = imagecreatetruecolor( $tw, $th );
imagealphablending( $small, false );
imagesavealpha( $small, true );
imagefill( $small, 0, 0, imagecolorallocatealpha( $small, 0, 0, 0, 127 ) );
imagecopyresampled( $small, $orig, 0, 0, 0, 0, $tw, $th, $ow, $oh );

// Só o alpha importa (mask-type: alpha); a cor vira preto para comprimir melhor.
for ( $y = 0; $y < $th; $y++ ) {
	for ( $x = 0; $x < $tw; $x++ ) {
		$alpha = ( imagecolorat( $small, $x, $y ) >> 24 ) & 0x7F;
		imagesetpixel( $small, $x, $y, imagecolorallocatealpha( $small, 0, 0, 0, $alpha ) );
	}
}

ob_start();
imagepng( $small, null, 9 );
$png = ob_get_clean();

$out = str_replace( $m[1], base64_encode( $png ), $svg );

// A <image> passa a declarar as novas dimensões, e a matriz do <use> é
// recalculada para manter exatamente o mesmo enquadramento.
$out = str_replace( 'width="' . $ow . '" height="' . $oh . '"', 'width="' . $tw . '" height="' . $th . '"', $out );
$out = preg_replace_callback(
	'#transform="matrix\(([^ ]+) 0 0 ([^ ]+) ([^ ]+) ([^ ]+)\)"#',
	function ( $mm ) use ( $ow, $tw, $oh, $th ) {
		$sx = (float) $mm[1] * $ow / $tw;
		$sy = (float) $mm[2] * $oh / $th;
		return sprintf( 'transform="matrix(%.9g 0 0 %.9g %s %s)"', $sx, $sy, $mm[3], $mm[4] );
	},
	$out
);

echo $out;
fprintf( STDERR, "%s: %d -> %d bytes (PNG %dx%d -> %dx%d)\n", basename( $src ), strlen( $svg ), strlen( $out ), $ow, $oh, $tw, $th );
