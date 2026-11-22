// Converte imagens PNG/JPG para WebP (bem menores, mesma aparência).
// Uso: node scripts/converter-webp.mjs public/img/arquivo.png [qualidade]
// Gera public/img/arquivo.webp ao lado do original.
import sharp from 'sharp';

const [entrada, qualidade = '82'] = process.argv.slice(2);
if (!entrada) {
	console.error('uso: node scripts/converter-webp.mjs <imagem> [qualidade]');
	process.exit(1);
}
const saida = entrada.replace(/\.(png|jpe?g)$/i, '.webp');
const info = await sharp(entrada).webp({ quality: Number(qualidade) }).toFile(saida);
console.log(`${saida}: ${Math.round(info.size / 1024)} KB (${info.width}×${info.height})`);
