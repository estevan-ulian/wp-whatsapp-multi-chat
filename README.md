# WhatsApp Multi Chat - Plugin para WordPress

O **WhatsApp Multi Chat** é um plugin para WordPress que adiciona um botão flutuante elegante ao seu site. Ao clicar no botão, uma janela é exibida com uma lista de contas de atendimento via WhatsApp. O gerenciamento das contas e a aparência do botão são configurados diretamente no painel administrativo do WordPress.

É a solução ideal para empresas que possuem múltiplos atendentes ou departamentos e desejam centralizar o contato via WhatsApp em um único widget no site.

---

## Funcionalidades
- **Múltiplas contas:** cadastre diversos atendentes/departamentos com seus respectivos números de WhatsApp.
- **Botão flutuante personalizável:** configure cores, textos e a aparência do botão flutuante para combinar com a identidade visual do site.
- **Janela de atendimento:** exibição amigável e moderna dos contatos cadastrados ao clicar no botão.
- **Painel de opções (Admin):** interface intuitiva no admin do WordPress para gerenciar as configurações, contas e design.
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
  - Script PHP customizado (`Build.php`) gerencia as automações de compilação.

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
├── vendor/                    # Dependências instaladas pelo Composer (PHPUnit, Mockery, etc.)
├── build-config.json          # Configuração do build
└── Build.php                  # Script de automação do build do projeto
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
Para gerar o build completo do plugin (PHP + Frontend), execute o script PHP customizado na raiz do projeto:
```bash
composer build
```

---

## Internacionalização
O plugin está pronto para tradução e compatível com **WPML**. Os arquivos de linguagem (`.pot`) estão no diretório `/languages/`. Utilize ferramentas como Poedit, Loco Translate ou WPML para traduzi-lo para idiomas adicionais.