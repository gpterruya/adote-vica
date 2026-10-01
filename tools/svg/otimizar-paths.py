"""Otimiza um SVG vetorial reescrevendo os paths com coordenadas relativas.

Os pontos absolutos são arredondados para uma grade fixa e só então
convertidos em deltas, então o arredondamento não acumula erro ao longo
do path. Também remove letras de comando repetidas e espaços desnecessários.

Uso (no Ubuntu, a partir de ~/adote-vica):
    python3 tools/svg/otimizar-paths.py <entrada.svg> <saida.svg> [escala]

    escala 100 = grade de 0,01 unidade (padrão, visualmente idêntico)
    escala 10  = grade de 0,1 unidade (menor; conferir a olho)

Suporta os comandos absolutos M, L, H, V, C e Z (os que os arquivos
exportados do Figma usam). Para um SVG com outros comandos o script para
com erro, sem gravar nada.
"""
import re
import sys

TOKEN = re.compile(r'[A-Za-z]|-?(?:\d+\.\d*|\.\d+|\d+)(?:[eE][-+]?\d+)?')


def main():
    if len(sys.argv) not in (3, 4):
        sys.exit(__doc__)
    src, dst = sys.argv[1], sys.argv[2]
    scale = int(sys.argv[3]) if len(sys.argv) == 4 else 100
    decimals = len(str(scale)) - 1

    def grid(v):
        return int(round(float(v) * scale))

    def fmt(i):
        """Inteiro na grade -> texto mínimo (ex.: 3 -> '.3', -12 -> '-1.2')."""
        if i == 0:
            return '0'
        sign = '-' if i < 0 else ''
        whole, frac = divmod(abs(i), scale)
        text = str(whole) if frac == 0 else ('%d.%0*d' % (whole, decimals, frac)).rstrip('0')
        if text.startswith('0.'):
            text = text[1:]
        return sign + text

    def needs_space(prev, nxt):
        """Separador só quando o próximo número não se separa sozinho."""
        if not prev or prev[-1].isalpha():
            return False
        if nxt.startswith('-'):
            return False
        last_number = re.split(r'[^0-9.]', prev)[-1]
        return not (nxt.startswith('.') and '.' in last_number)

    def convert(d):
        toks = TOKEN.findall(d)
        out = ''
        cx = cy = sx = sy = 0
        last = None
        first = True
        cmd = None
        i = 0

        def emit(letter, values):
            nonlocal out, last
            nums = [fmt(v) for v in values]
            if letter != last or letter == 'm':
                out += letter
            for n in nums:
                if needs_space(out, n):
                    out += ' '
                out += n
            last = letter

        while i < len(toks):
            t = toks[i]
            if t.isalpha():
                cmd = t
                i += 1
                if cmd in 'Zz':
                    out += 'z'
                    last = 'z'
                    cx, cy = sx, sy
                    continue
            if cmd is None or cmd not in 'MLHVC':
                raise SystemExit('comando de path não suportado: %r' % cmd)
            if cmd == 'M':
                x, y = grid(toks[i]), grid(toks[i + 1])
                i += 2
                if first:
                    emit('M', [x, y])
                    first = False
                else:
                    emit('m', [x - cx, y - cy])
                cx, cy = sx, sy = x, y
                cmd = 'L'  # pares seguintes de um M são lineto
            elif cmd == 'L':
                x, y = grid(toks[i]), grid(toks[i + 1])
                i += 2
                emit('l', [x - cx, y - cy])
                cx, cy = x, y
            elif cmd == 'H':
                x = grid(toks[i])
                i += 1
                emit('h', [x - cx])
                cx = x
            elif cmd == 'V':
                y = grid(toks[i])
                i += 1
                emit('v', [y - cy])
                cy = y
            elif cmd == 'C':
                v = [grid(t) for t in toks[i:i + 6]]
                i += 6
                emit('c', [v[0] - cx, v[1] - cy, v[2] - cx, v[3] - cy, v[4] - cx, v[5] - cy])
                cx, cy = v[4], v[5]
        return out

    original = open(src, encoding='utf-8').read()
    result = re.sub(r'(\sd=")([^"]*)(")', lambda m: m.group(1) + convert(m.group(2)) + m.group(3), original)
    result = re.sub(r'>\s+<', '><', result).strip()
    open(dst, 'w', encoding='utf-8').write(result)
    before, after = len(original.encode()), len(result.encode())
    print('%s: %d -> %d bytes (%.0f%% menor)' % (dst, before, after, 100 - 100 * after / before))


if __name__ == '__main__':
    main()
