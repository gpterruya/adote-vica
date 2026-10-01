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
| Ajustes de layout sobre o Elementor (overflow no desktop, hero no celular deitado, cards de "Custos" no tablet, coração de "ONGs"…) | `assets/css/style.css` | Carregado **depois** do CSS do Elementor, então sobrescreve o editor. |
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
| Docker Desktop mostra erro de WSL ao abrir, depois de um `wsl --update` | Atualize o Docker Desktop para a versão mais recente. **Não** use "Reset to factory defaults" nem "Clean / Purge data": essas opções apagam o banco local. |
