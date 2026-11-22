// Verificações automáticas do site gerado (pasta dist/). Roda no GitHub
// Actions a cada push/pull request e localmente com `npm run check`
// (depois de `npm run build`). Se alguma regra falhar, o processo sai
// com erro e a publicação é bloqueada.
import fs from 'node:fs';
import path from 'node:path';

const dist = new URL('../dist/', import.meta.url).pathname;
const erros = [];
const avisos = [];

if (!fs.existsSync(dist)) {
	console.error('Pasta dist/ não encontrada. Rode `npm run build` antes.');
	process.exit(1);
}

const arquivos = (dir) =>
	fs.readdirSync(dir, { withFileTypes: true }).flatMap((e) => {
		const p = path.join(dir, e.name);
		return e.isDirectory() ? arquivos(p) : [p];
	});

const todos = arquivos(dist);
const htmls = todos.filter((f) => f.endsWith('.html'));
const rel = (f) => path.relative(dist, f);

// 1) Jurídico: nenhum valor em reais em lugar nenhum (nem em texto oculto).
//    Código de Ética Veterinária — ver README, "Valores de serviços".
for (const f of todos.filter((f) => /\.(html|xml|txt|js|json|css)$/.test(f))) {
	const conteudo = fs.readFileSync(f, 'utf8');
	const achados = conteudo.match(/R\$\s*\d[\d.,]*/g);
	if (achados) erros.push(`${rel(f)}: valor em reais encontrado (${[...new Set(achados)].join(', ')}). O jurídico proíbe divulgar valores.`);
}

for (const f of htmls) {
	const html = fs.readFileSync(f, 'utf8');
	const nome = rel(f);
	const pagina404 = nome === '404.html';

	// 2) SEO básico
	const h1 = (html.match(/<h1[\s>]/g) || []).length;
	if (h1 !== 1) erros.push(`${nome}: deve ter exatamente 1 <h1> (tem ${h1}).`);
	if (!/<title>[^<]{10,}<\/title>/.test(html)) erros.push(`${nome}: <title> ausente ou curto demais.`);
	if (!/<meta name="description" content="[^"]{50,}"/.test(html)) erros.push(`${nome}: meta description ausente ou curta.`);
	if (!pagina404 && !/<link rel="canonical" href="https:\/\/adotevica\.com\.br\//.test(html))
		erros.push(`${nome}: link canonical ausente.`);
	if (pagina404 && !/noindex/.test(html)) erros.push(`${nome}: a página 404 deve ter noindex.`);

	// 3) Rastreamento e Search Console
	if (!html.includes('GTM-5QDXJBK2')) erros.push(`${nome}: Google Tag Manager (GTM-5QDXJBK2) ausente.`);
	if (!html.includes('google-site-verification')) erros.push(`${nome}: verificação do Search Console ausente.`);

	// 4) Telefone de contato
	if (!html.includes('tel:+551138850749')) erros.push(`${nome}: link de telefone (11) 3885-0749 ausente.`);

	// 5) Imagens: alt obrigatório (vazio é permitido para imagens decorativas)
	for (const img of html.match(/<img\b[^>]*>/g) || []) {
		if (!/\salt=/.test(img)) erros.push(`${nome}: imagem sem atributo alt: ${img.slice(0, 120)}`);
	}

	// 6) Links e imagens internos precisam existir em dist/
	for (const [, url] of html.matchAll(/(?:href|src|srcset)="(\/[^"#?\s]*)/g)) {
		if (url.startsWith('//')) continue;
		const alvo = path.join(dist, decodeURIComponent(url));
		const existe =
			fs.existsSync(alvo) && fs.statSync(alvo).isFile()
				? true
				: fs.existsSync(path.join(alvo, 'index.html')) || fs.existsSync(alvo + '.html');
		if (!existe) erros.push(`${nome}: link interno quebrado: ${url}`);
	}

	// 7) Âncoras internas (#compromissos, #contato…) precisam existir na página
	for (const [, ancora] of html.matchAll(/href="#([^"]+)"/g)) {
		if (!html.includes(`id="${ancora}"`)) erros.push(`${nome}: âncora #${ancora} não existe na página.`);
	}

	// 8) Links externos que abrem em nova aba devem ter rel="noopener"
	for (const a of html.match(/<a\b[^>]*target="_blank"[^>]*>/g) || []) {
		if (!/rel="[^"]*noopener/.test(a)) avisos.push(`${nome}: link com target="_blank" sem rel="noopener": ${a.slice(0, 100)}`);
	}
}

// 9) Peso dos arquivos (aviso, não bloqueia)
for (const f of todos) {
	const kb = fs.statSync(f).size / 1024;
	if (/\.(webp|png|jpe?g)$/.test(f) && kb > 400) avisos.push(`${rel(f)}: imagem com ${Math.round(kb)} KB (considere reduzir).`);
}

for (const a of avisos) console.warn('aviso:', a);
if (erros.length) {
	for (const e of erros) console.error('ERRO:', e);
	console.error(`\n${erros.length} erro(s). Corrija antes de publicar.`);
	process.exit(1);
}
console.log(`OK: ${htmls.length} página(s) e ${todos.length} arquivo(s) verificados.`);
