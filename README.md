# Adote Vi.Ca — ambiente local

Cópia local do site [adotevica.com.br](https://adotevica.com.br) (WordPress + Elementor) rodando em Docker, e o plugin **`vica-custom`**, onde ficam as customizações de front-end versionadas no Git.

- **Produção:** Hostinger (plano Single Web Hosting, sem SSH) com DNS na Cloudflare
- **Local:** PHP 8.3 + Apache e MariaDB 10.6, em `http://localhost:8080`, rodando a partir do **WSL2 (Ubuntu)**

---

## Como o projeto está organizado

```text
~/adote-vica/                  ← dentro do Ubuntu (WSL2)
├── plugins/vica-custom/       ← plugin próprio (versionado) — ver seção 3
│   ├── vica-custom.php        ← todo o PHP do plugin
│   ├── assets/css/style.css   ← ajustes de layout sobre o Elementor
│   ├── assets/css/404.css     ← estilo da página 404
│   ├── assets/img/*.svg       ← versões otimizadas de SVGs dos uploads
│   └── templates/404.php      ← página 404 com o visual do site
├── site/                      ← NOVO site estático em Astro (seção 7, em migração)
├── .github/workflows/site.yml ← verificações automáticas do site estático
├── tools/svg/                 ← scripts para otimizar SVGs (seção 3.4)
├── duplicator/                ← WordPress restaurado (NÃO versionado)
├── dist/                      ← zips gerados para deploy (NÃO versionado)
├── Dockerfile
└── docker-compose.yml
```

### O que vai para o Git e o que não vai

| Tipo | Onde fica | Sentido |
|---|---|---|
| **Código** (CSS, PHP, JS próprios) | `plugins/vica-custom/` | local → Git → produção |
| **Conteúdo** (páginas do Elementor, textos, configurações, uploads) | Banco de dados e `wp-content/uploads` | produção → local |

- Todo o WordPress restaurado (`duplicator/`) fica **fora** do Git: core, plugins de terceiros, uploads, `wp-config.php` e backups.
- O layout feito no Elementor fica no **banco de dados**, não em arquivos. O banco local **nunca** deve ser enviado por cima do de produção.

### Por que WSL2

O projeto precisa ficar **dentro do sistema de arquivos do Ubuntu** (`/home/...`), e não em `C:\` ou `D:\`. Quando o Docker Desktop lê o WordPress a partir de uma pasta do Windows, cada página leva de 15 a 40 segundos. Dentro do WSL2, leva menos de 1 segundo.

---

## Requisitos

- Windows com **WSL2** e a distro **Ubuntu** (`wsl --install -d Ubuntu`)
- [Docker Desktop](https://www.docker.com/products/docker-desktop/) com a integração ativada em **Settings → Resources → WSL integration → Ubuntu**
- [VS Code](https://code.visualstudio.com/) com a extensão **WSL** (`ms-vscode-remote.remote-wsl`)
- Um backup do site gerado pelo plugin **Duplicator** em produção. São dois arquivos: `*_archive.zip` e `*_installer.php`.

> Todos os comandos deste README são rodados no **terminal do Ubuntu**, a partir de `~/adote-vica`, e não no PowerShell.

---

## 1. Primeira instalação (restaurar o site localmente)

1. No terminal do Ubuntu, clone o repositório na sua pasta pessoal:

   ```bash
   cd ~
   git clone https://github.com/gpterruya/adote-vica.git
   cd adote-vica
   ```

2. Crie a pasta `duplicator/` e copie para ela os dois arquivos do backup do Duplicator. A pasta Downloads do Windows fica em `/mnt/c/Users/<usuário-windows>/Downloads`:

   ```bash
   mkdir -p duplicator
   cp /mnt/c/Users/<usuário-windows>/Downloads/AAAAMMDD_adotevica_*_{archive.zip,installer.php} duplicator/
   ```

3. Suba os containers. A primeira vez demora, porque a imagem do PHP é construída:

   ```bash
   docker compose up -d
   ```

4. Passe a pasta do WordPress para o usuário do Apache (`www-data`), para que o instalador e o WordPress consigam gravar arquivos. O plugin `vica-custom` fica de fora e continua sendo seu:

   ```bash
   docker compose exec web sh -c 'find /var/www/html -path /var/www/html/wp-content/plugins/vica-custom -prune -o -exec chown www-data:www-data {} +'
   ```

5. Abra o instalador no navegador do Windows, trocando pelo nome real do arquivo:

   ```text
   http://localhost:8080/AAAAMMDD_adotevica_..._installer.php
   ```

6. No instalador, use estes dados do banco:

   | Campo | Valor |
   |---|---|
   | Action | Empty Database |
   | Host | `db` |
   | Database | `wordpress` |
   | User | `wordpress` |
   | Password | `wordpress` |

   Pode aparecer um aviso de collation `utf8mb4_unicode_520_ci` não suportada. Ele pode ser ignorado.

7. Conclua a instalação e **rode de novo o comando do passo 4**, porque o instalador cria arquivos novos. O site passa a abrir em `http://localhost:8080` e o painel em `http://localhost:8080/wp-admin`, com o mesmo login de produção.

8. O Duplicator desativa o **WP Rocket** na instalação. Deixe-o desativado no ambiente local.

9. Ative o plugin **Vi.Ca Custom** em *Plugins* no painel local.

> Essas credenciais de banco servem só para o ambiente local no Docker. Elas não são as de produção.

### Credenciais do GitHub no Ubuntu

Para o `git push` funcionar de dentro do Ubuntu, reaproveite o gerenciador de credenciais do Git for Windows:

```bash
git config --global credential.helper "/mnt/c/Program\ Files/Git/mingw64/bin/git-credential-manager.exe"
```

---

## 2. Uso no dia a dia

### Abrir o projeto no VS Code

No terminal do Ubuntu:

```bash
cd ~/adote-vica && code .
```

Outra opção: no VS Code, clique no botão **`><`** do canto inferior esquerdo, escolha **Connect to WSL** e depois **File → Open Folder → `/home/<usuário>/adote-vica`**.

O canto inferior esquerdo deve mostrar **`WSL: Ubuntu`**, e o terminal integrado (`` Ctrl+` ``) já abre no bash do Ubuntu.

> Não abra `\\wsl$\Ubuntu\...` pelo *Open Folder* normal, sem a extensão. Funciona, mas o git e as buscas voltam a ficar lentos.

Para ver os arquivos pelo Explorer do Windows, use `\\wsl$\Ubuntu\home\<usuário>\adote-vica`.

### Ligar e desligar o ambiente

```bash
docker compose up -d      # liga o ambiente
docker compose down       # desliga (o banco é mantido no volume db_data)
docker compose logs web   # logs do Apache/PHP
```

O Docker Desktop precisa estar aberto no Windows.

---

## 3. O plugin `vica-custom`

Tudo o que é código próprio do site fica no plugin, versionado no Git e publicado como zip (seção 4). O conteúdo e o layout continuam no Elementor.

### 3.1 O que o plugin faz

| Função | Onde | Observação |
|---|---|---|
| Ajustes de layout sobre o Elementor (overflow no desktop, hero no celular deitado, cards de "Custos", espaçamento após a remoção dos valores, coração de "ONGs"…) | `assets/css/style.css` | Carregado **depois** do CSS do Elementor, então sobrescreve o editor. |
| Página 404 com o visual do site | `templates/404.php` + `assets/css/404.css` | Status continua 404; o CSS só carrega nessa página. |
| SVGs otimizados (logos e ícones da página inicial) | `assets/img/*.svg` | Ver 3.4. |
| Não expor logins do painel | `vica-custom.php` | Bloqueia `/wp-json/wp/v2/users` para visitantes, redireciona `/author/…` e `/?author=N` para a home, tira o autor do oEmbed e do sitemap do Yoast. |
| URLs residuais respondem 404 | `vica-custom.php` | `/e-floating-buttons/…` e `/category/…`. Quem pode editar continua vendo (preview do Elementor). |

**Para desfazer qualquer coisa:** desativar o plugin em *Plugins* volta o site ao estado do Elementor. O plugin não altera banco, uploads nem o conteúdo do Elementor.

### 3.2 Como o plugin funciona no ambiente local

A pasta `plugins/vica-custom/` do repositório é **montada** dentro do WordPress local pelo `docker-compose.yml`. Cada arquivo salvo aparece ao recarregar `http://localhost:8080`, sem instalar nada.

> ⚠️ **Nunca envie o zip pelo painel local** (`localhost:8080/wp-admin`). Ao "substituir" o plugin, o WordPress cria uma cópia própria e o container deixa de ler a pasta do repositório: suas edições param de aparecer. Se isso acontecer, recrie o container (o banco não é afetado):
>
> ```bash
> docker compose up -d --force-recreate web
> ```

### 3.3 Editar o CSS

1. Edite `plugins/vica-custom/assets/css/style.css`.
2. Recarregue `http://localhost:8080` e teste nas larguras relevantes:
   - Mobile: 375 px (retrato) e 568–740 px (paisagem, layout mobile)
   - Celular grande deitado: 812–932 px × ~390 px (cai no layout de **tablet**)
   - Tablet: 768 px (retrato) e 1024 px (paisagem)
   - Desktop: 1100, 1180, 1280, 1366, 1440 e 1920 px
3. Confira se não há rolagem lateral colando no console do navegador (F12):

   ```js
   document.documentElement.scrollWidth - document.documentElement.clientWidth // deve ser 0
   ```

4. Faça o commit e o push.

#### Como mirar um elemento do Elementor

Cada elemento do Elementor tem uma classe `elementor-element-<id>`. Para achar o ID, inspecione o elemento no navegador ou veja o atributo `data-id`. Use seletores com especificidade suficiente para vencer o Elementor:

```css
.elementor .elementor-element.elementor-element-a77ee8c {
	max-width: calc(100vw - 32px);
}
```

Para aplicar a regra só em um breakpoint, use media queries com os mesmos limites do Elementor:

| Breakpoint | Media query |
|---|---|
| Mobile | `@media (max-width: 767px)` |
| Tablet | `@media (min-width: 768px) and (max-width: 1024px)` |
| Desktop | `@media (min-width: 1025px)` |

> ⚠️ Se um elemento for **apagado e recriado** no Elementor, o ID muda e a regra correspondente deixa de funcionar sem nenhum aviso. Comente no CSS a qual seção cada ID pertence.

> O Elementor também tem **elementos duplicados por dispositivo** (uma versão oculta no desktop e outra oculta no tablet/celular). Um ajuste de texto ou de CSS pode precisar ser feito nas duas.

#### Hero da página inicial

O topo da página inicial (container `5d4e108`) é o trecho mais ajustado do CSS. Ele foi calibrado para o texto atual:

| Elemento | Widget | Texto | Tag HTML |
|---|---|---|---|
| Título | `b965320` | **Adote Vi.Ca — Centro de REABILITAÇÃO e Adoção** | `h1` (o único H1 da página) |
| Palavra manuscrita | `e6ddbb5` | responsável | `span` |
| Texto de apresentação | `60d5882` | Somos uma empresa privada… | — |
| Botão | `557bc3f` | conheça nossos compromissos | — |

Regras em `style.css` que dependem do tamanho desse título:

- **Tablet (768–1024px):** título em `min(55px, 6vw)`, para "REABILITAÇÃO" caber na coluna sem passar por cima da foto no tablet em pé.
- **Celular grande deitado (768–1024px de largura, até 500px de altura):** título a 28px numa coluna de 52%, em 2 linhas.
- **Celular deitado (até 767px de largura, até 500px de altura):** título a 30px, "responsável" a 38px e espaços menores.

> ⚠️ **Se o título, o texto ou o botão do hero mudarem**, teste de novo em 375×812, 568×320, 740×360, 812×375, 932×430, 768×1024, 1024×768 e no desktop: o botão precisa continuar visível na primeira tela dos celulares deitados, e o título não pode passar da coluna no tablet. Dá para simular um texto novo no console do navegador, sem mexer no Elementor:
>
> ```js
> document.querySelector('.elementor-element-b965320 .elementor-heading-title').textContent = 'Novo título aqui'
> ```

Mantenha a tag **H1** no título e **span** no "responsável" (Elementor → widget → **Conteúdo → Tag HTML**). Elas não mudam o visual, mas o Google usa o H1 como título principal da página.

#### Textos que existem em duas versões

A seção **"Quem adota muda vidas"** existe duas vezes no Elementor: uma versão só para desktop (container `7bc55a9`) e outra só para tablet e celular (container `d79ca14`). Os textos dela precisam ser trocados **nas duas**, senão o site mostra informações diferentes conforme o aparelho (já aconteceu com "5 mil" × "20 mil adoções").

| Texto | Desktop | Tablet e celular | Conteúdo atual |
|---|---|---|---|
| Parágrafo ao lado das fotos | `e55b2c6` | `a79f4d6` | O Vi.Ca é hoje o maior Centro de Reabilitação e Adoção do país. Nosso papel é preparar cães e gatos resgatados por ONGs, protetores e canis parceiros para encontrarem lares seguros e saudáveis. Com um gatil para 60 animais e um canil com 30 baias, realizamos cerca de 300 adoções por mês. |
| Bloco azul de missão | `b4f7f97` | `48f2ee4` | Em nossa visão, um mundo sustentável… já realizou mais de 20 mil adoções desde 2017. Para que este número continue subindo… |

Para achar a versão de tablet/celular no editor, troque a visualização para **Celular** (barra de baixo) ou abra o **Navegador** (`Ctrl+I`): os elementos ocultos no desktop aparecem com o ícone de olho cortado.

Para conferir se as duas versões estão iguais em produção, cole no console do navegador (F12) em `https://adotevica.com.br/`:

```js
['e55b2c6', 'a79f4d6', 'b4f7f97', '48f2ee4'].map(id => id + ': ' + document.querySelector('.elementor-element-' + id).textContent.trim().replace(/\s+/g, ' '))
```

> **Ao colar texto no Elementor, use `Ctrl+Shift+V`** (colar sem formatação). Um `Ctrl+V` traz junto negrito, links e estilos da origem (e-mail, WhatsApp, documentos), e o trecho fica diferente do resto do parágrafo.

#### ⚠️ Valores de serviços: proibido divulgar

Por orientação do jurídico (Ferraz de Camargo Advogados, outubro de 2026), em atendimento ao **Código de Ética Veterinária**, o site **não pode mostrar valores** de consultas, castrações, taxa de adoção ou outros procedimentos médico-veterinários — nem em texto oculto (o Google e qualquer pessoa leem o código da página). Antes da mudança o jurídico registrou o site como estava no **Verifact** (relatório `registro_6ac93ce86336dcf9.pdf`), que deve ficar arquivado pela empresa.

O que foi alterado no Elementor:

| Onde | Antes | Agora |
|---|---|---|
| "Importante saber", 3º card (`36386b1`) | Por norma do Código de Ética Veterinária, não é permitido divulgar valores… | **Em atendimento ao Código de Ética Veterinária, não divulgamos os valores de consultas, castrações ou outros procedimentos médico veterinários.** |
| "Custos e transparência", card da esquerda (`d6c021e` título, `162c6de` lista) | Custos diretos do animal (castração, vacinação…) | **Custos Diretos – Responsabilidade do Tutor**: *Taxa de Adoção* (vacinação, vermifugação, testes rápidos, hemograma + bioquímico, identificação, consulta) e *Custos da Castração* ("Entre em contato conosco para informações sobre valores: (11) 3885-0749.") |
| "Valores de referência" (container `7840de6`, com R$ 855, R$ 2.750 e um card **oculto** de R$ 780) | Exibido | **Excluído** |

Mantidos: a faixa azul "Valores refletem protocolos clínicos reais…" (`fb6a879`) e "Compromissos e limites", que ficam no mesmo container (`c6a37b5`).

Regras do `style.css` que dependem disso:

- **Cards de custos (`6da732b`, `6a8586a`):** o fundo de cada card é um SVG com o card bege + a ilustração. Com o texto longo do jurídico, o SVG fica na largura do card, ancorado no topo, e o resto é completado com `#F9F2E4`; sem ilustração entre 768 e 899px; texto numa coluna de 64% entre 900 e 1024px; título com 76% no desktop.
- **Container `c6a37b5`:** sem a `min-height` de 1685px e a faixa azul com margem positiva (24px no celular, 40px a partir de 768px) em vez da margem negativa que a fazia subir sobre os cards de valores.

> Se algum dia valores voltarem a ser permitidos, essas regras precisam ser revistas junto. Enquanto isso, **antes de publicar qualquer conteúdo novo**, confira que nenhum valor escapou:
>
> ```bash
> curl -s https://adotevica.com.br/ | grep -o 'R\$[^<]\{0,20\}'   # não deve imprimir nada
> ```

#### Telefones de contato

O site tem um único telefone, o fixo **(11) 3885-0749**. Ele aparece em:

- rodapé;
- botão flutuante de telefone (template `532`, widget `4f540891`, `tel:+551138850749`);
- card "Custos da Castração" (`162c6de`);
- página 404 do plugin (`templates/404.php`). Ela não é editada no Elementor: um número novo ou trocado entra no código do plugin.

O rodapé faz parte da própria página inicial (seção `4451bd9`), não é um template global, e existe uma única versão para todos os aparelhos. O texto fica no campo **Descrição** do widget Caixa de imagem `b119f04` ("Centro de Adoção Responsável"), em HTML:

```html
<b>Endereço:</b> R. Pamplona, 1481 - Jardins, São Paulo - SP, 01405-003<br><b>Telefone:</b> (11) 3885-0749
```

Em novembro de 2026 um WhatsApp ((11) 95808-3658) entrou no rodapé e foi removido dias depois. Se um link voltar a ser colocado ali, use `style="color: inherit;"` no `<a>`: sem isso, o link fica rosa, que é a cor padrão de link do tema.

### 3.4 SVGs otimizados

O Elementor **embute no HTML** o conteúdo dos SVGs enviados em widgets de ícone (lido do meta `_elementor_inline_svg`). Os SVGs exportados com textura vinham com milhares de curvas e coordenadas de 8 casas decimais (~210 KB por ícone), e a página inicial chegava a 2,7 MB de HTML.

O plugin troca, só na hora de entregar a página, qualquer SVG de `uploads/2025/10/` que tenha uma versão **com o mesmo nome** em `plugins/vica-custom/assets/img/`. Para otimizar mais um:

1. **SVG vetorial** (paths): rode, no Ubuntu,

   ```bash
   python3 tools/svg/otimizar-paths.py duplicator/wp-content/uploads/2025/10/<nome>.svg plugins/vica-custom/assets/img/<nome>.svg
   ```

   O padrão é uma grade de 0,01 (visualmente idêntico). Acrescentar `10` no fim usa uma grade de 0,1, que deixa o arquivo ~25% menor: os ícones texturizados usam essa, mas linhas finas pioram com ela. Confira a olho.

2. **SVG com PNG embutido** (como o logo do rodapé): rode, no Ubuntu,

   ```bash
   docker compose exec -T web php -- /var/www/html/wp-content/uploads/2025/10/<nome>.svg 640 < tools/svg/otimizar-mascara.php > plugins/vica-custom/assets/img/<nome>.svg
   ```

   O `640` é a largura do PNG: use ~2,5× a largura em que a imagem aparece no site.

3. Limpe o cache do Elementor local para ver o resultado (ele guarda o HTML dos widgets por até 24 h):

   ```bash
   docker compose exec -T db mariadb -uwordpress -pwordpress wordpress -e "DELETE FROM wpm1_postmeta WHERE meta_key='_elementor_element_cache';"
   ```

4. Compare com o original em `http://localhost:8080` e faça o commit.

> Se alguém **trocar o SVG no Elementor** por um arquivo novo (outro nome ou outra pasta de mês), o plugin deixa de otimizá-lo, e o arquivo original volta a ser usado. Não quebra nada, mas o peso volta: otimize o novo também.

### 3.5 Versão do plugin

Ao mudar o PHP ou o comportamento do plugin, aumente o `Version:` no cabeçalho de `vica-custom.php` (ex.: 1.4.0 → 1.5.0). É o que aparece no painel de produção e ajuda a saber qual zip está instalado. O CSS não precisa: ele é versionado automaticamente pela data do arquivo.

---

## 4. Publicar em produção

O plano da Hostinger não tem SSH, então o deploy é feito pelo próprio painel do WordPress.

1. Faça o commit e o push. Depois gere o zip **a partir do último commit** (assim o zip contém exatamente o que está no Git):

   ```bash
   git archive --format=zip --prefix=vica-custom/ -o dist/vica-custom.zip HEAD:plugins/vica-custom
   ```

2. Em `https://adotevica.com.br/wp-admin` (**só em produção**, nunca no painel local), vá em **Plugins → Adicionar novo → Enviar plugin**, clique em **Escolher arquivo** e selecione o zip. Na janela de arquivos do Windows, ele fica em:

   ```text
   \\wsl$\Ubuntu\home\<usuário>\adote-vica\dist\vica-custom.zip
   ```

   Clique em **Instalar agora**.
   - Na **primeira vez**, clique em **Ativar**.
   - Nas **próximas**, o WordPress avisa que o plugin já existe. Escolha **Substituir a versão atual pela enviada**.
3. **Limpe os caches**, nesta ordem:
   1. **Elementor → Ferramentas → Limpar arquivos e dados** (obrigatório quando mudam SVGs em `assets/img/`; sem isso a página continua com os antigos por até 24 h).
   2. **WP Rocket → Limpar cache**.
4. Confira o site em produção, nas mesmas larguras do teste local. Para checar rapidamente se a versão nova está no ar:

   ```bash
   curl -s https://adotevica.com.br/ | grep -o 'vica-custom/assets/css/style.css?ver=[0-9]*'
   ```

**Para desfazer:** desative o plugin em *Plugins* (ou reenvie o zip de um commit anterior). Nada no Elementor é alterado por ele.

---

## 5. Atualizar a cópia local a partir da produção

Quando o conteúdo de produção mudar, por exemplo com edições no Elementor, textos ou imagens novas:

1. Em produção, gere um novo backup no **Duplicator** e baixe o `archive.zip` e o `installer.php`.
2. Copie os dois arquivos para `duplicator/`. A pasta pertence ao `www-data`, então use `sudo` (a senha é a do seu usuário do Ubuntu):

   ```bash
   sudo cp /mnt/c/Users/<usuário-windows>/Downloads/AAAAMMDD_adotevica_*_{archive.zip,installer.php} duplicator/
   ```

3. Rode o instalador de novo (seção 1, passos 4 a 9).

O plugin `vica-custom` não é afetado, porque vive fora de `duplicator/` e é montado por cima.

---

## 6. Google Search Console

O site está cadastrado no [Google Search Console](https://search.google.com/search-console), onde dá para ver como ele aparece na busca do Google e pedir que o Google leia uma página de novo.

| Item | Configuração |
|---|---|
| Propriedade | Prefixo de URL: `https://adotevica.com.br/` |
| Verificação | Meta tag `google-site-verification`, inserida pelo **Yoast SEO → Configurações → Conexões do site → Google** |
| Sitemap enviado | `sitemap_index.xml` (gerado pelo Yoast; hoje lista só a página inicial) |

> ⚠️ **Não apague o código de verificação do Yoast.** O Google confere a tag de tempos em tempos; se ela sumir, a propriedade deixa de estar verificada e os relatórios param de ser atualizados.

Para conferir se a tag está no ar:

```bash
curl -s https://adotevica.com.br/ | grep -o '<meta name="google-site-verification"[^>]*>'
```

### Título da página inicial no Google

O título que aparece na aba do navegador, no Google e na prévia de links (WhatsApp, redes) **não é** o título do topo da página: ele vem do Yoast.

| Onde aparece | Valor atual | Onde editar |
|---|---|---|
| `<title>`, `og:title` e dados estruturados | Adote Vi.Ca - Centro de Reabilitação e Adoção Responsável | **Páginas → Página Principal → Editar** (editor do WordPress, não o Elementor) → quadro **Yoast SEO → Aparência de pesquisa → Título de SEO** |
| Meta descrição (texto cinza no Google) | Somos uma empresa privada com o objetivo de conectar animais resgatados… | No mesmo lugar, campo **Meta descrição** |
| H1 (título visível no topo) | Adote Vi.Ca — Centro de REABILITAÇÃO e Adoção | Elementor (seção 3.3, "Hero da página inicial") |

O título configurado em **Yoast SEO → Configurações** é só um modelo geral; como a página inicial é uma página fixa, vale o campo da própria página. Depois de salvar, clique em **Atualizar**, limpe o cache do WP Rocket e confira:

```bash
curl -s https://adotevica.com.br/ | grep -o '<title>[^<]*</title>'
```

Prévias de links já compartilhados podem continuar antigas por um tempo (o WhatsApp guarda a prévia em cache).

### Depois de mudar um conteúdo importante

O Google pode continuar mostrando o texto antigo nos resultados por dias. Para acelerar:

1. No Search Console, use **Inspecionar URL** (barra do topo) com `https://adotevica.com.br/`.
2. Clique em **Solicitar indexação** e espere a confirmação.
3. Para acompanhar, veja o campo **Último rastreamento** na mesma inspeção, ou pesquise no Google por `site:adotevica.com.br <trecho removido>`.

Se depois de uma semana o resultado ainda mostrar o texto antigo, peça a remoção em **Remoções → Remoções de conteúdo desatualizado**.

### Para mudar a verificação ou o sitemap

- **Outra conta precisa de acesso:** em **Configurações → Usuários e permissões**, adicione o e-mail. Não é preciso verificar de novo.
- **Trocar para uma propriedade de domínio** (cobre `http`, `https` e `www` de uma vez): crie uma propriedade do tipo **Domínio** (`adotevica.com.br`) e verifique com o registro **TXT** que o Google indicar, adicionado no **DNS da Cloudflare**.
- **O site ganhar páginas novas:** o Yoast as inclui no `sitemap_index.xml` automaticamente. Não é preciso reenviar o sitemap.

---

## 7. Site estático (Astro) — em migração

A página inicial foi reconstruída em código na pasta `site/`, com o mesmo visual, os mesmos textos e as mesmas regras do site em WordPress. O objetivo é deixar de depender do Elementor (edição visual), do Duplicator (trazer a produção para a máquina) e do upload de zip, e publicar automaticamente a cada push.

**Situação:** o site novo roda localmente e passa nas verificações, mas **ainda não está no ar**. A produção continua sendo o WordPress até a troca do DNS (seção 7.5).

### 7.1 Como está organizado

```text
site/
├── src/
│   ├── pages/index.astro         ← página inicial (só monta as seções na ordem)
│   ├── pages/404.astro           ← página "não encontrada"
│   ├── pages/sitemap_index.xml.ts← sitemap no mesmo endereço do Yoast
│   ├── components/*.astro        ← uma seção por arquivo: Hero, QuemAdota, Diferenciais,
│   │                               Processo, ImportanteSaber, Protocolos, Custos,
│   │                               Compromissos, Parceiros, NaMidia, Faq,
│   │                               ProntoParaAdotar, Rodape
│   ├── layouts/Base.astro        ← <head> (SEO, Tag Manager, ícones) e botão de telefone
│   ├── data/site.ts              ← título, descrição, telefone, endereço, IDs do Google
│   └── styles/global.css         ← fontes, cores, botões e regras comuns
├── public/                       ← arquivos copiados como estão
│   ├── img/                      ← imagens (as mesmas dos uploads do WordPress)
│   ├── fonts/                    ← TT Hazelnuts e Intro Script
│   └── robots.txt
├── firebase.json                 ← Firebase Hosting: cache, redirecionamentos, cabeçalhos
└── scripts/
    ├── verificar.mjs             ← regras que bloqueiam a publicação (7.4)
    ├── capturas.mjs              ← capturas de tela em 6 tamanhos de tela
    ├── converter-webp.mjs        ← converte PNG/JPG para WebP
    └── comparar.js               ← compara medidas com o site antigo (migração)
```

Cada seção tem o texto no próprio arquivo, em HTML simples, e o CSS logo abaixo, no bloco `<style>` (vale só para aquela seção). Os breakpoints são os mesmos do Elementor: celular até 767px, tablet de 768 a 1024px, computador a partir de 1025px.

As seções que no Elementor existiam duas vezes (uma para computador e outra para tablet/celular: "Quem adota muda vidas" e "ONGs e protetores parceiros") agora são **uma só**, e o CSS reorganiza a ordem dos blocos. Um texto não pode mais ficar diferente entre os aparelhos.

Os atributos `data-ref="…"` são os IDs dos elementos do Elementor equivalentes. Servem para comparar medidas com o site antigo durante a migração e podem ser removidos depois.

### 7.2 Rodar localmente

O Node 22 está instalado no Ubuntu, em `~/.local/node` (sem sudo). Para usar no terminal:

```bash
export PATH="$HOME/.local/node/bin:$PATH"
```

```bash
cd ~/adote-vica/site && npm install
```

```bash
npm run dev
```

O site abre em `http://localhost:4321` e se atualiza sozinho a cada arquivo salvo. Não precisa de Docker nem de banco de dados.

Para gerar a versão final e rodar as verificações (o mesmo que o GitHub faz):

```bash
npm run build && npm run check
```

### 7.3 Mudar um texto, uma imagem ou o telefone

| O que | Onde |
|---|---|
| Texto de uma seção | `site/src/components/<Seção>.astro` |
| Telefone, endereço, título e descrição do Google | `site/src/data/site.ts` (vale para o site inteiro) |
| Perguntas frequentes | `site/src/components/Faq.astro` (lista no topo do arquivo) |
| Reportagens "Na mídia" | `site/src/components/NaMidia.astro` (lista no topo do arquivo) |
| Imagem nova | coloque em `site/public/img/` e use `/img/nome.webp` |

Ao colar texto vindo de e-mail ou documento, confira que não vieram aspas curvas ou espaços estranhos. Não use `R$`: a verificação bloqueia a publicação (ver "Valores de serviços", seção 3.3).

### 7.4 Verificações automáticas (GitHub Actions)

A cada push na `main` e a cada pull request que mexa em `site/`, o GitHub roda `.github/workflows/site.yml`:

1. **Gera o site** (`npm run build`).
2. **Verifica** (`scripts/verificar.mjs`). Qualquer erro bloqueia:
   - valor em reais (`R$ 123`) em qualquer arquivo — exigência do jurídico;
   - página sem exatamente um `<h1>`, sem `<title>`, sem descrição ou sem `canonical`;
   - Google Tag Manager (`GTM-5QDXJBK2`) ou verificação do Search Console ausentes;
   - link de telefone `(11) 3885-0749` ausente;
   - imagem sem `alt`, link interno quebrado ou âncora (`#contato`, `#compromissos`) inexistente.
3. **Tira capturas de tela** da página inteira em celular (375 e 740 deitado), tablet (768 e 1024 deitado), notebook (1366) e computador (1440), e falha se aparecer **rolagem horizontal** em algum deles. As capturas ficam para download por 14 dias na execução (aba *Actions* → execução → *Artifacts* → `capturas`).
4. **Publica no Firebase Hosting**, só se os passos anteriores passarem:
   - **pull request:** uma prévia num endereço temporário (válido por 7 dias), que o GitHub comenta no próprio pull request;
   - **push na `main`:** o site oficial.

   A autenticação no Google Cloud é sem chave (Workload Identity Federation, seção 7.5 B). Enquanto as variáveis `FIREBASE_PROJECT_ID`, `GCP_WIF_PROVIDER` e `GCP_SERVICE_ACCOUNT` não existirem no repositório, este passo é pulado e só as verificações rodam.

Localmente, as capturas precisam de bibliotecas do sistema que pedem senha de administrador, uma vez só: `sudo npx playwright install-deps chromium` dentro de `site/`. Depois: `npm run preview` em um terminal e `node scripts/capturas.mjs` em outro.

### 7.5 Colocar no ar (Firebase Hosting, Google Cloud) — passos pendentes

O site é hospedado no **Firebase Hosting**, o serviço de sites estáticos do Google Cloud. Todo projeto Firebase é um projeto do Google Cloud, aparece no mesmo console e na mesma conta de cobrança. O plano gratuito (Spark) comporta este site: 10 GB de armazenamento e 360 MB de transferência por dia. O HTTPS do domínio é emitido automaticamente.

Feitos uma vez:

**A. Criar o projeto**

1. Em [console.firebase.google.com](https://console.firebase.google.com) → **Criar um projeto**. Pode ser um projeto novo ou um projeto do Google Cloud que já exista.
2. Desligue o Google Analytics: o site já usa o Tag Manager.
3. Anote o **ID do projeto**, que aparece abaixo do nome (por exemplo, `adote-vica-1a2b3`).
4. No menu **Hosting** → **Vamos começar** → avance as telas sem rodar os comandos: a publicação é feita pelo GitHub.

**B. Dar ao GitHub permissão para publicar (sem chave)**

O GitHub não usa chave de conta de serviço. Organizações novas no Google Cloud bloqueiam a criação de chaves (política `iam.disableServiceAccountKeyCreation`), e é melhor que continue assim. Em vez de chave, o site usa **Workload Identity Federation**: a cada execução, o GitHub prova ao Google que é o repositório `gpterruya/adote-vica` e recebe um acesso temporário à conta de serviço `github-deploy`. Não há chave para vazar nem segredo guardado no GitHub.

1. No [Google Cloud Console](https://console.cloud.google.com), com o projeto do passo A selecionado, abra o **Cloud Shell**: o ícone `>_` no canto superior direito. É um terminal no navegador, já autenticado na sua conta.
2. Cole o bloco abaixo, trocando só o ID do projeto na primeira linha. Ele pode ser rodado de novo sem problema: o que já existir é mantido.

   ```bash
   PROJETO=adote-vica-1a2b3            # ← o ID do projeto (passo A.3)
   REPO=gpterruya/adote-vica
   NUMERO=$(gcloud projects describe "$PROJETO" --format='value(projectNumber)')
   CONTA="github-deploy@$PROJETO.iam.gserviceaccount.com"

   gcloud services enable iamcredentials.googleapis.com sts.googleapis.com \
     firebasehosting.googleapis.com --project "$PROJETO"

   # conta de serviço que publica o site, com só os papéis necessários
   gcloud iam service-accounts create github-deploy --project "$PROJETO" \
     --display-name "GitHub: publica o site" 2>/dev/null || true
   for PAPEL in roles/firebasehosting.admin roles/serviceusage.apiKeysViewer \
                roles/run.viewer roles/serviceusage.serviceUsageConsumer; do
     gcloud projects add-iam-policy-binding "$PROJETO" --condition=None \
       --member "serviceAccount:$CONTA" --role "$PAPEL" >/dev/null
   done

   # "pool" de identidades do GitHub, aceitando só este repositório
   gcloud iam workload-identity-pools create github --project "$PROJETO" \
     --location global --display-name "GitHub" 2>/dev/null || true
   gcloud iam workload-identity-pools providers create-oidc adote-vica \
     --project "$PROJETO" --location global --workload-identity-pool github \
     --display-name "adote-vica" \
     --issuer-uri "https://token.actions.githubusercontent.com" \
     --attribute-mapping "google.subject=assertion.sub,attribute.repository=assertion.repository" \
     --attribute-condition "assertion.repository == '$REPO'" 2>/dev/null || true

   # o repositório pode agir como a conta de serviço
   gcloud iam service-accounts add-iam-policy-binding "$CONTA" --project "$PROJETO" \
     --role roles/iam.workloadIdentityUser \
     --member "principalSet://iam.googleapis.com/projects/$NUMERO/locations/global/workloadIdentityPools/github/attribute.repository/$REPO" >/dev/null

   echo "FIREBASE_PROJECT_ID = $PROJETO"
   echo "GCP_SERVICE_ACCOUNT = $CONTA"
   echo "GCP_WIF_PROVIDER    = projects/$NUMERO/locations/global/workloadIdentityPools/github/providers/adote-vica"
   ```

3. No GitHub, em **Settings** → **Secrets and variables** → **Actions** → aba **Variables** → **New repository variable**, crie as três variáveis com os valores impressos no fim do bloco: `FIREBASE_PROJECT_ID`, `GCP_SERVICE_ACCOUNT` e `GCP_WIF_PROVIDER`. São identificadores, não senhas: sozinhos não dão acesso a nada. Por isso ficam em *Variables*, não em *Secrets*.

Se você chegou a criar a conta `github-deploy` pelo console antes, o bloco aproveita a conta existente. Nenhuma chave foi criada, porque a política bloqueou.

**C. Conferir e publicar**

1. Abra um pull request que mexa em `site/`, ou rode de novo as verificações de um pull request aberto (**Actions** → execução → **Re-run all jobs**). O GitHub comenta no pull request um endereço de prévia (`…--pr-N-….web.app`).
2. Confira a prévia em celular, tablet e computador.
3. Junte o pull request na `main`. O site oficial vai para `https://<ID-do-projeto>.web.app`, ainda sem o domínio.

**D. Trocar o domínio** (é isto que coloca o site novo no ar)

1. No Firebase: **Hosting** → **Adicionar domínio personalizado** → `adotevica.com.br`. Depois `www.adotevica.com.br`, marcando para **redirecionar** para `adotevica.com.br`.
2. O Firebase mostra os registros DNS necessários (um **TXT** de verificação e registros **A**). Cadastre-os no **DNS da Cloudflare**:
   - nos registros do Firebase, com o proxy **desligado** (nuvem cinza, "DNS only"). Com a nuvem laranja, o Firebase não consegue emitir o certificado HTTPS;
   - os registros **A** do Firebase **substituem** os que hoje apontam para a Hostinger (`adotevica.com.br` e `www`);
   - **não mexa** em registros de e-mail (**MX**, SPF/DKIM em **TXT**) nem na verificação do Search Console.
3. O certificado HTTPS leva de alguns minutos a algumas horas. Enquanto isso, o domínio pode mostrar aviso de certificado.
4. **Para voltar atrás:** restaure na Cloudflare os registros A antigos (os da Hostinger). Anote-os antes de trocar.
5. **Google Search Console:** nada muda (mesmo domínio, mesma verificação por meta tag, mesmo `sitemap_index.xml`). Peça a indexação da página inicial depois da troca.
6. Mantenha a Hostinger e o WordPress parados por algumas semanas, como reserva, antes de cancelar.

Depois disso, publicar é só fazer push na `main`: o GitHub gera, verifica e publica em poucos minutos. Se alguma verificação falhar, nada é publicado e o site continua na versão anterior.

**Para desfazer uma publicação:** Firebase → **Hosting** → **Histórico de versões** → versão anterior → **Reverter**.

### 7.6 O que muda em relação ao WordPress

- **Não há mais editor visual.** Textos e layout são alterados nos arquivos (seção 7.3) e publicados com push.
- **O Duplicator, o Docker e o plugin `vica-custom` deixam de ser necessários** depois da troca: o Git passa a ser a fonte da verdade. Até lá, continuam valendo as seções 1 a 6.
- **Formulários, blog ou área de login** não existem hoje no site. Se um dia forem necessários, dá para acrescentar ao Astro, ou usar um editor simples que grava no Git (Decap CMS, Pages CMS).

## Problemas comuns

| Sintoma | Causa provável / solução |
|---|---|
| `localhost:8080` não abre | Docker Desktop fechado ou containers parados. Abra o Docker Desktop e rode `docker compose up -d`. |
| `docker: command not found` no Ubuntu | Integração do WSL desativada. Ative em **Docker Desktop → Settings → Resources → WSL integration → Ubuntu**. |
| Site lento (vários segundos por página) | O projeto está numa pasta do Windows (`/mnt/c`, `/mnt/d`). Ele precisa ficar em `~/adote-vica`, dentro do Ubuntu. |
| WordPress não consegue enviar imagens nem atualizar plugins | Permissões. Rode o comando do passo 4 da seção 1. |
| `Permission denied` ao copiar arquivos para `duplicator/` | A pasta pertence ao `www-data`. Use `sudo cp`. |
| O CSS alterado não aparece | Cache do navegador. Recarregue com `Ctrl+F5`. O plugin versiona o CSS pela data do arquivo, então isso é raro. |
| Nenhuma edição do plugin aparece no local (nem PHP, nem CSS) | O plugin foi instalado pelo painel local e o container perdeu a ligação com o repositório. Rode `docker compose up -d --force-recreate web` (seção 3.2). |
| SVG otimizado não aparece (local ou produção) | Cache de elementos do Elementor (até 24 h). Local: comando da seção 3.4, passo 3. Produção: **Elementor → Ferramentas → Limpar arquivos e dados**. |
| Página nova ou URL nova responde 404 inesperadamente | O plugin responde 404 em `/category/…` e `/e-floating-buttons/…` para visitantes, e redireciona `/author/…` para a home. Se o site passar a ter blog, ajuste isso em `vica-custom.php`. |
| A regra não se aplica a um elemento | O ID do Elementor mudou, ou falta especificidade no seletor. Inspecione o elemento e compare o `data-id`. |
| O Duplicator reclama de `ZipArchive` | Imagem antiga. Reconstrua com `docker compose build --no-cache web`. |
| Search Console diz que a propriedade não está mais verificada | O código de verificação saiu do Yoast (ou o Yoast foi desativado). Cole o código de novo em **Yoast SEO → Configurações → Conexões do site → Google**, limpe o cache do WP Rocket e clique em **Verificar** (seção 6). |
| Docker Desktop mostra erro de WSL ao abrir, depois de um `wsl --update` | Atualize o Docker Desktop para a versão mais recente. **Não** use "Reset to factory defaults" nem "Clean / Purge data": essas opções apagam o banco local. |
