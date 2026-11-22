// Sitemap no mesmo endereço do Yoast (/sitemap_index.xml), que já está
// cadastrado no Google Search Console.
import type { APIRoute } from 'astro';

const paginas = ['/'];

export const GET: APIRoute = ({ site }) => {
	const hoje = new Date().toISOString();
	const urls = paginas
		.map((p) => `<url><loc>${new URL(p, site).href}</loc><lastmod>${hoje}</lastmod></url>`)
		.join('');
	return new Response(
		`<?xml version="1.0" encoding="UTF-8"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">${urls}</urlset>`,
		{ headers: { 'Content-Type': 'application/xml; charset=utf-8' } },
	);
};
