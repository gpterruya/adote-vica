// Comparação visual com o site antigo (Elementor) durante a migração.
// Cole no console da página em produção, com LOCAL = saída de medir() na
// versão nova (mesma largura de janela). Lista os blocos cuja posição ou
// tamanho difere mais que TOL pixels.
//
// Na versão nova:   medir()  -> copia a string
// Na produção:      comparar(LOCAL)
function medir(seletor = '[data-ref]', chave = (e) => e.dataset.ref) {
	return [...document.querySelectorAll(seletor)]
		.map((e) => {
			const b = e.getBoundingClientRect();
			return chave(e) + ':' + [b.left, b.top + scrollY, b.width, b.height].map(Math.round).join(',');
		})
		.join(' ');
}

function comparar(local, tol = 4) {
	const out = [];
	for (const par of local.split(' ')) {
		const [id, v] = par.split(':');
		const a = v.split(',').map(Number);
		const e = document.querySelector('.elementor-element-' + id);
		if (!e || !e.offsetParent) {
			out.push(id + ' (não existe/oculto na produção)');
			continue;
		}
		const b = e.getBoundingClientRect();
		const p = [b.left, b.top + scrollY, b.width, b.height].map(Math.round);
		const d = a.map((x, i) => x - p[i]);
		if (d.some((x) => Math.abs(x) > tol)) out.push(`${id} novo=${a.join(',')} prod=${p.join(',')} Δ=${d.join(',')}`);
	}
	return out.join('\n');
}
