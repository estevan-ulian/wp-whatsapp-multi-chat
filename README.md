# WhatsApp Multi Chat - Plugin para WordPress

O **WhatsApp Multi Chat** é um plugin para WordPress que adiciona um botão flutuante elegante ao seu site. Ao clicar no botão, uma janela é exibida com uma lista de contas de atendimento via WhatsApp. O gerenciamento das contas e a aparência do botão são configurados diretamente no painel administrativo do WordPress.

É a solução ideal para empresas que possuem múltiplos atendentes ou departamentos e desejam centralizar o contato via WhatsApp em um único widget no site.

---
## Instalação
Faça download da versão mais recente na seção de [releases](https://github.com/estevan-ulian/wp-whatsapp-multi-chat/releases), instale e ative-o em seu site WordPress.

---

## Funcionalidades
- **Múltiplas contas:** cadastre diversos atendentes/departamentos com seus respectivos números de WhatsApp.
- **Botão flutuante personalizável:** configure cores, textos e a aparência do botão flutuante para combinar com a identidade visual do site.
- **Janela de atendimento:** exibição amigável e moderna dos contatos cadastrados ao clicar no botão.
- **Painel de opções (Admin):** interface intuitiva no admin do WordPress para gerenciar as configurações, contas e design.
- **Regras de exibição:** escolha onde o widget aparece com regras Exibir/Ocultar por página inicial, arquivos (incluindo um termo específico), tipos de post, páginas específicas, busca e 404.
- **Horários de funcionamento:** horário geral e horário próprio por conta; o widget mostra quem está online agora e quando cada atendente volta, no fuso do site.
- **Suporte multilíngue (WPML):** integração e arquivos `.po`/`.mo` para tradução de strings.

---

## Arquitetura e Tecnologias
O plugin foi desenvolvido estruturando o back-end (PHP) e o front-end (React/Preact com Vite) de maneira desacoplada e modular, visando performance e facilidade de manutenção.

- **Backend (PHP):**
  - Orientado a objetos (OOP), utilizando o padrão **Singleton** (ex: `WP_WhatsApp_Multi_Chat_Plugin`, `WP_WhatsApp_Multi_Chat_Accounts`).
  - Ganchos (Hooks) nativos do WordPress (`add_action`, `add_filter`) para integração leve e segura.
  - Testes unitários com **PHPUnit** e Mockery (arquivos em `/tests/`).
  
- **Frontend (Preact + Vite + TypeScript):**
  - O widget do usuário final é renderizado via **Preact** (alternativa ultraleve ao React), garantindo performance (Zero bloat).
  - Animações com **Motion**.
  - O build é orquestrado pelo **Vite**, e os assets minificados são gerados em `assets/frontend/`.

- **Testes e Build:**
  - `phpunit.xml` / `composer.json` provêm ferramentas de qualidade de código.
  - O script `bin/build.sh` gera o pacote instalável (`composer build`). A pipeline de release usa o mesmo script.

---

## Estrutura de Pastas

```
wp-whatsapp-multi-chat/
├── wp-whatsapp-multi-chat.php # Arquivo principal (Ponto de entrada do plugin)
├── includes/                  # Classes PHP (Regras de negócio, Opções, View, Utilidades)
├── frontend/                  # Código-fonte (Preact + Vite + TypeScript) para o widget no site
│   ├── src/                   # Componentes (app.tsx, main.tsx) e estilos (app.css)
│   ├── package.json           # Dependências npm
│   └── vite.config.ts         # Configuração de build do Front-end
├── assets/                    # Arquivos estáticos (CSS/JS compilados e imagens)
│   ├── admin/                 # Scripts e Estilos para o Painel do WP
│   └── frontend/              # Output do build do Preact (Arquivos injetados no frontend do WP)
├── languages/                 # Arquivos de tradução (.po, .mo, .pot)
├── tests/                     # Testes Unitários PHP (PHPUnit)
├── vendor/                    # Dependências do Composer (Plugin Update Checker; PHPUnit e Brain Monkey em dev)
├── bin/build.sh               # Gera o zip instalável do plugin (local e na pipeline de release)
├── .distignore                # Arquivos que ficam fora do zip instalável
└── .github/workflows/         # Pipeline de release (publica o zip nas releases do GitHub)
```

---

## Como Desenvolver

### Pré-requisitos
- Node.js (v18+) e npm/yarn/pnpm
- Composer
- Ambiente WordPress local (ex: LocalWP)

### Frontend (Preact)
1. Navegue até a pasta `frontend/`: `cd frontend`
2. Instale as dependências: `npm install`
3. Como os arquivos do plugin chamam o build em `assets/frontend`, gere o build com: `npm run build`
4. Para desenvolvimento com hot-reload local no componente, use `npm run dev`.

### Backend (PHP e Testes)
1. Na raiz do projeto, instale os pacotes: `composer install`
2. Rode os testes unitários: `composer test`

### Build

#### Frontend
Para gerar a build do frontend, execute:
```bash
composer build:frontend
```
Ou,
```bash
cd /frontend && npm run build
```

#### Plugin Completo
Para gerar o zip instalável (frontend compilado + dependências PHP de produção), execute na raiz do projeto:
```bash
composer build
```
O arquivo é gerado em `build/wp-whatsapp-multi-chat.zip`, com a pasta raiz `wp-whatsapp-multi-chat/`. É o mesmo pacote que a pipeline publica nas releases, então dá para testar localmente exatamente o que os sites vão instalar.

- O conteúdo do zip é tudo que não estiver listado no `.distignore`. Ao criar um arquivo de desenvolvimento na raiz do projeto, adicione-o ao `.distignore` para que ele não vá para os sites.
- As dependências de produção são instaladas numa cópia temporária, então o `vendor/` do projeto (com o PHPUnit) não é alterado.
- Requer `bash`, `rsync` e `zip` (já presentes no macOS e no Linux; no Windows, use WSL ou Git Bash).

---

## Atualizações automáticas

Os sites que usam o plugin recebem o aviso de nova versão e o botão **"Atualizar agora"** no painel do WordPress, como um plugin do wordpress.org. As versões vêm das [releases do GitHub](https://github.com/estevan-ulian/wp-whatsapp-multi-chat/releases), por meio da biblioteca [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker).

- O WordPress verifica novas versões automaticamente a cada 12 horas. Para verificar na hora, use o link **"Check for updates"** na linha do plugin, na tela **Plugins**.
- O site instala o arquivo `wp-whatsapp-multi-chat.zip` anexado à release, que já traz os assets compilados e as dependências. O zip de código-fonte gerado pelo GitHub não serve, porque não tem nenhum dos dois.
- Sites com a versão 1.0.0 ainda não têm o atualizador. Por isso precisam instalar manualmente, uma única vez, a primeira versão que o inclui. Depois disso as atualizações chegam pelo painel.

### Como publicar uma nova versão

1. Registre as mudanças no `CHANGELOG.md`, em uma seção `## [X.Y.Z]`. Esse texto aparece em "Ver detalhes" no painel dos sites.
2. Atualize a versão em `wp-whatsapp-multi-chat.php`, **nos dois lugares**, com o mesmo valor:
   - no cabeçalho: ` * Version: X.Y.Z`
   - na constante: `define('WP_WHATSAPP_MULTI_CHAT_VERSION', 'X.Y.Z');`
3. Faça o commit e envie para a `main`:
   ```bash
   git commit -am "chore: release vX.Y.Z"
   git push origin main
   ```
4. Crie a tag e envie:
   ```bash
   git tag vX.Y.Z
   git push origin vX.Y.Z
   ```

O workflow `.github/workflows/release.yml` gera o pacote e publica a release com o zip anexado. O job **falha** se a tag (sem o `v`), o cabeçalho `Version:` e a constante não forem iguais. Isso evita anunciar aos sites uma versão que o código não tem. Se a tag foi criada com a versão errada, corrija o arquivo e recrie a tag:
```bash
git tag -d vX.Y.Z && git push origin :refs/tags/vX.Y.Z
```

### Se o repositório se tornar privado

Cada site precisa de um token para baixar as releases. Adicione ao `wp-config.php` do site (nunca ao repositório):

```php
define('WP_WHATSAPP_MULTI_CHAT_GITHUB_TOKEN', 'github_pat_...');
```

Use um token **fine-grained**, restrito apenas a este repositório, com a permissão **Contents: Read-only** e mais nenhuma. Assim, um token que vaze de um site só permite ler este plugin.

---

## Internacionalização
O plugin está pronto para tradução e compatível com **WPML**. Os arquivos de linguagem (`.pot`) estão no diretório `/languages/`. Utilize ferramentas como Poedit, Loco Translate ou WPML para traduzi-lo para idiomas adicionais.