# Adote Vi.Ca — ambiente local

Cópia local do site [adotevica.com.br](https://adotevica.com.br) (WordPress + Elementor) rodando em Docker, e o plugin **`vica-custom`**, onde ficam as customizações de front-end versionadas no Git.

- **Produção:** Hostinger (plano Single Web Hosting, sem SSH) com DNS na Cloudflare
- **Local:** PHP 8.3 + Apache e MariaDB 10.6, em `http://localhost:8080`

---

## Como o projeto está organizado

```text
adote-vica/
├── plugins/vica-custom/   ← código próprio (versionado)
│   ├── vica-custom.php
│   └── assets/css/style.css
├── duplicator/            ← WordPress restaurado (NÃO versionado)
├── dist/                  ← zips gerados para deploy (NÃO versionado)
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

---

## Requisitos

- [Docker Desktop](https://www.docker.com/products/docker-desktop/)
- Git
- Um backup do site gerado pelo plugin **Duplicator** em produção. São dois arquivos: `*_archive.zip` e `*_installer.php`.

---

## 1. Primeira instalação (restaurar o site localmente)

1. Clone o repositório:

   ```powershell
   git clone https://github.com/gpterruya/adote-vica.git
   cd adote-vica
   ```

2. Crie a pasta `duplicator/` e coloque nela os dois arquivos do backup do Duplicator:

   ```text
   duplicator/
   ├── AAAAMMDD_adotevica_..._archive.zip
   └── AAAAMMDD_adotevica_..._installer.php
   ```

3. Suba os containers. A primeira vez demora, porque a imagem do PHP é construída:

   ```powershell
   docker compose up -d
   ```

4. Abra o instalador no navegador, trocando pelo nome real do arquivo:

   ```text
   http://localhost:8080/AAAAMMDD_adotevica_..._installer.php
   ```

5. No instalador, use estes dados do banco:

   | Campo | Valor |
   |---|---|
   | Action | Empty Database |
   | Host | `db` |
   | Database | `wordpress` |
   | User | `wordpress` |
   | Password | `wordpress` |

   Pode aparecer um aviso de collation `utf8mb4_unicode_520_ci` não suportada. Ele pode ser ignorado.

6. Conclua a instalação. O site passa a abrir em `http://localhost:8080` e o painel em `http://localhost:8080/wp-admin`, com o mesmo login de produção.

7. O Duplicator desativa o **WP Rocket** na instalação. Deixe-o desativado no ambiente local.

8. Ative o plugin **Vi.Ca Custom** em *Plugins* no painel local.

> Essas credenciais de banco servem só para o ambiente local no Docker. Elas não são as de produção.

---

## 2. Uso no dia a dia

```powershell
docker compose up -d      # liga o ambiente
docker compose down       # desliga (o banco é mantido no volume db_data)
docker compose logs web   # logs do Apache/PHP
```

> **Lentidão:** no Windows, o WordPress é lido direto de `D:\` pelo Docker Desktop, e cada página pode levar de 15 a 40 segundos para carregar. A primeira carga depois de ligar o ambiente é a mais lenta. Mover o projeto para dentro do WSL2 resolve.

---

## 3. Editar o front-end (plugin `vica-custom`)

O plugin carrega `assets/css/style.css` **depois** do CSS do Elementor, então as regras dele sobrescrevem o que foi configurado no editor. A pasta `plugins/vica-custom/` é montada dentro do WordPress local pelo `docker-compose.yml`, e cada alteração salva aparece ao recarregar a página.

1. Edite `plugins/vica-custom/assets/css/style.css`.
2. Recarregue `http://localhost:8080` e teste nas larguras relevantes:
   - Mobile: 375 px (retrato) e 667–812 px (paisagem)
   - Tablet: 768 px (retrato) e 1024 px (paisagem)
   - Desktop: 1180, 1280, 1366, 1440 e 1920 px
3. Confira se não há rolagem lateral colando no console do navegador (F12):

   ```js
   document.documentElement.scrollWidth - document.documentElement.clientWidth // deve ser 0
   ```

4. Faça o commit.

### Como mirar um elemento do Elementor

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

---

## 4. Publicar em produção

O plano da Hostinger não tem SSH, então o deploy é feito pelo próprio painel do WordPress.

1. Gere o zip a partir do último commit:

   ```powershell
   git archive --format=zip --prefix=vica-custom/ -o dist/vica-custom.zip HEAD:plugins/vica-custom
   ```

   Use `git archive` em vez do `Compress-Archive` do Windows: o `Compress-Archive` do PowerShell 5.1 grava os caminhos com `\`, o que pode quebrar a extração no servidor Linux.

2. Em `https://adotevica.com.br/wp-admin`, vá em **Plugins → Adicionar novo → Enviar plugin**, selecione `dist/vica-custom.zip` e clique em **Instalar agora**.
   - Na **primeira vez**, clique em **Ativar**.
   - Nas **próximas**, o WordPress avisa que o plugin já existe. Escolha **Substituir a versão atual pela enviada**.
3. Se o WP Rocket estiver ativo em produção, **limpe o cache** (*WP Rocket → Limpar cache*).
4. Confira o site em produção, nas mesmas larguras do teste local.

**Para desfazer:** desative o plugin em *Plugins*. Nada no Elementor é alterado por ele.

---

## 5. Atualizar a cópia local a partir da produção

Quando o conteúdo de produção mudar, por exemplo com edições no Elementor, textos ou imagens novas:

1. Em produção, gere um novo backup no **Duplicator** e baixe o `archive.zip` e o `installer.php`.
2. Coloque os dois arquivos em `duplicator/` e rode o instalador de novo (passo 1, itens 4 a 8).

O plugin `vica-custom` não é afetado, porque vive fora de `duplicator/` e é montado por cima.

---

## Problemas comuns

| Sintoma | Causa provável / solução |
|---|---|
| `localhost:8080` não abre | Docker parado. Rode `docker compose up -d` e aguarde, porque a primeira carga é lenta. |
| O CSS alterado não aparece | Cache do navegador. Recarregue com `Ctrl+F5`. O plugin versiona o CSS pela data do arquivo, então isso é raro. |
| A regra não se aplica a um elemento | O ID do Elementor mudou, ou falta especificidade no seletor. Inspecione o elemento e compare o `data-id`. |
| O Duplicator reclama de `ZipArchive` | Imagem antiga. Reconstrua com `docker compose build --no-cache web`. |
