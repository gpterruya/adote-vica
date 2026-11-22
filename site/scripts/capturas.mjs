// Tira capturas de tela da página inteira em celular, tablet e computador.
// Usado pelo GitHub Actions (ficam disponíveis para download na execução)
// e localmente para comparar antes/depois de uma mudança.
//
// Uso: node scripts/capturas.mjs [url] [pasta]
//   url   padrão: http://localhost:4321/  (rode `npm run preview` antes)
//   pasta padrão: capturas/
import fs from 'node:fs';
import { chromium } from 'playwright';

const url = process.argv[2] || 'http://localhost:4321/';
const pasta = process.argv[3] || 'capturas';
const telas = [
	{ nome: 'celular-375', width: 375, height: 812, isMobile: true },
	{ nome: 'celular-deitado-740', width: 740, height: 360, isMobile: true },
	{ nome: 'tablet-768', width: 768, height: 1024 },
	{ nome: 'tablet-deitado-1024', width: 1024, height: 768 },
	{ nome: 'notebook-1366', width: 1366, height: 768 },
	{ nome: 'desktop-1440', width: 1440, height: 900 },
];

fs.mkdirSync(pasta, { recursive: true });
const browser = await chromium.launch();
let problemas = 0;

for (const t of telas) {
	const page = await browser.newPage({ viewport: { width: t.width, height: t.height }, isMobile: !!t.isMobile });
	await page.goto(url, { waitUntil: 'networkidle' });
	await page.evaluate(() => document.fonts.ready);
	// carrega as imagens com loading="lazy" antes da captura
	await page.evaluate(async () => {
		for (const img of document.querySelectorAll('img[loading="lazy"]')) img.loading = 'eager';
		await Promise.all([...document.images].map((i) => (i.complete ? null : new Promise((r) => (i.onload = i.onerror = r)))));
	});

	// rolagem horizontal = algo passando da borda da tela
	const larguraExtra = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
	if (larguraExtra > 0) {
		problemas++;
		console.error(`ERRO ${t.nome}: a página tem ${larguraExtra}px de rolagem horizontal.`);
	}

	await page.screenshot({ path: `${pasta}/${t.nome}.png`, fullPage: true });
	console.log(`${pasta}/${t.nome}.png`);
	await page.close();
}

await browser.close();
if (problemas) process.exit(1);
