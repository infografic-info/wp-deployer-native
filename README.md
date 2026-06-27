# WP Deployer

Boilerplate para deploy e gerenciamento de instalações WordPress com DDEV (desenvolvimento) e EasyEngine (produção/staging).

## Funcionalidades

- Inicialização automática do WordPress no `ddev start`
- Deploy via [Deployer](https://deployer.org/) com cadeia de tarefas automatizada
- Provisionamento de ambientes EasyEngine (criação de site, shared wp-config, primeiro deploy)
- Instalação de scripts de manutenção remotos (backup, restore)
- Suporte a múltiplos ambientes com `.env` por stage
- Tasks de deploy fornecidas pelo pacote [`infografic/wp-deployer-core`]

## Pré-requisitos

- [PHP 8.1+](https://www.php.net/)
- [Composer](https://getcomposer.org/)
- [DDEV](https://ddev.com/)

## Configuração de ambiente

O projeto usa arquivos `.env` por stage. Copie `.env.example` para `.env` e preencha os valores:

```sh
cp .env.example .env
```

| Arquivo | Uso |
|---|---|
| `.env` | Valores locais / desenvolvimento (base, não versionar) |
| `.env.production` | Sobrescreve `.env` para produção (não versionar) |
| `.env.staging` | Sobrescreve `.env` para staging (não versionar) |

O stage é detectado automaticamente pelo argumento do `dep` (ex: `ddev exec dep deploy production`) ou pela variável `DEPLOY_ENV=production`.

### `.env` — base / desenvolvimento local

```sh
# ----------------------------------------------------------------------------
# Tipo de projeto e stack de produção
# ----------------------------------------------------------------------------
# native | bedrock
PROJECT_TYPE=native
# easyengine
PROD_STACK=easyengine

# ----------------------------------------------------------------------------
# Pacote de deploy (infografic/wp-deployer-core)
# ----------------------------------------------------------------------------
DEPLOY_PACKAGE_NAME=infografic/wp-deployer-core
DEPLOY_PACKAGE_VERSION=0.2.0
DEPLOY_TEMPLATES_VERSION=0.2.0

# ----------------------------------------------------------------------------
# WordPress bootstrap local (usado por .ddev/commands/web/install-wp-if-needed)
# ----------------------------------------------------------------------------
WP_TITLE="Meu Site"
WP_ADMIN_USER=admin
WP_ADMIN_PASSWORD=change-me
WP_ADMIN_EMAIL=admin@example.com
WP_TIMEZONE=America/Sao_Paulo
WP_LANG=pt_BR
```

### `.env.production` — sobrescritas de produção

Crie este arquivo com apenas os valores que diferem do `.env` base:

```sh
PROD_IP=0.0.0.0
PROD_PORT=2232
PROD_DOMAIN=example.com
MGMT_USER=root

# ----------------------------------------------------------------------------
# EasyEngine — provisionamento (ee:provision / ee:site:create)
# ----------------------------------------------------------------------------
# Versão do PHP a usar no container do site (padrão: 8.3)
EE_PHP_VERSION=8.3
# Forçar banco de dados local no container EasyEngine (true | false)
EE_LOCAL_DB=false

# Banco de dados de PRODUÇÃO — usado por ee:site:create e provision:generate-shared-env
# Deixar em branco para banco gerenciado pelo EasyEngine
PROD_DB_NAME=''
PROD_DB_USER=''
PROD_DB_PASSWORD=''
PROD_DB_HOST=''
PROD_DB_PREFIX='wp_'

# ----------------------------------------------------------------------------
# Backblaze B2 — backup remoto via Duplicati
# ----------------------------------------------------------------------------
B2_APPLICATION_KEY=""
B2_APPLICATION_KEY_ID=""
```

### `.env.staging` — sobrescritas de staging

```sh
STAGING_IP=0.0.0.0
STAGING_PORT=2232
STAGING_DOMAIN=staging.example.com
STAGING_MGMT_IP=0.0.0.0
MGMT_USER=root

# IP do mgmt host para staging em deploy local (CI usa 10.0.0.1 por padrão)
STAGING_MGMT_IP=10.0.0.1
```

## Desenvolvimento local

```sh
cp .env.example .env   # preencha WP_ADMIN_PASSWORD no mínimo
ddev start
```

No `ddev start`, o projeto executa em sequência:

1. `composer-install-if-needed` — instala dependências se necessário
2. `import-seed` — importa `init/data/db.sql.gz` se existir
3. `install-wp-if-needed` — executa `wp core install` se o WordPress ainda não estiver instalado
4. `import-uploads` — extrai `init/data/uploads.tar.gz` se existir

Se `init/data/db.sql.gz` existir, a instalação via WP-CLI é ignorada.

### Prefixo de tabelas customizado

Para instalar o WordPress com um prefixo de tabelas diferente do padrão (`wp_`), crie o arquivo `.ddev/.env` no seu ambiente local e defina:

```sh
DB_PREFIX=custom_
```

O DDEV injeta essa variável no container web antes do `ddev start`, e o `install-wp-if-needed` usa o valor durante a instalação. O arquivo `.ddev/.env` é local e não deve ser versionado.

### Gerando dados de inicialização

Para criar o padrão de importação do projeto (banco + uploads):

```sh
ddev generate-init-data
```

O comando exporta e salva em `init/data/`:
- `db.sql` e `db.sql.gz`
- `uploads.tar.gz`
- `webp-express.tar.gz` (se o diretório existir)

## Deploy

> **Pré-requisito:** o ambiente EasyEngine no servidor precisa estar provisionado antes do primeiro deploy. Consulte a seção [Provisionamento EasyEngine](#provisionamento-easyengine) abaixo. A configuração do servidor em si (instalação do EasyEngine, acesso SSH, usuários) não é coberta por este repositório.

O deploy é feito via Deployer. As configurações de hosts ficam em `deploy/config.php`.

```sh
ddev exec dep deploy production
ddev exec dep deploy staging
```

### Cadeia de deploy

```
deploy:validate:env → deploy:version:report
backup:files → backup:db
→ deploy:update_code      (git archive → upload via SSH)
→ deploy:upload_vendors   (vendor/ + WP core pré-compilados → upload)
→ wp:core:update-db
→ wp:cache:flush          (+ symlink Redis object-cache.php)
→ ee:site:restart
→ ee:site:clean
→ wp:config:lock
```

> O Composer **não roda no servidor**. Os artefatos (`vendor/`, plugins, WP core) são compilados localmente ou no CI runner e enviados como parte do deploy.

Em caso de falha, `deploy:unlock` é executado automaticamente. Rollback com restore opcional:

```sh
ddev exec dep rollback production                        # rollback simples
RESTORE_ON_ROLLBACK=1 ddev exec dep rollback production  # rollback + restore de arquivos/banco
```

### Pré-requisito antes do deploy

O `composer install` é executado automaticamente pelo DDEV no `ddev start`. Para deploys manuais basta:

```sh
ddev exec dep deploy production
```

### CI/CD — GitHub Actions

> **Atenção:** os arquivos `.github/workflows/production.yml` e `.github/workflows/staging.yml` são exemplos de referência. Eles precisam ser adaptados à estrutura do seu projeto, ao tipo de runner utilizado (self-hosted ou hospedado pelo GitHub) e à forma como o seu servidor gerencia autenticação SSH e segredos.

Os workflows são acionados em push para `main` ou manualmente (`workflow_dispatch`) e rodam em runners self-hosted com as tags `deployer, production` e `deployer, staging`.

#### Segredos obrigatórios (`Settings → Secrets → Actions`)

| Segredo | Descrição |
|---|---|
| `COMPOSER_AUTH` | JSON de autenticação do Composer (credenciais de repositórios privados) |
| `PROD_IP` | IP do servidor de produção |
| `PROD_PORT` | Porta SSH do servidor de produção |
| `STAGING_IP` | IP do servidor de staging |
| `STAGING_PORT` | Porta SSH do servidor de staging |

#### Variáveis obrigatórias (`Settings → Variables → Actions`)

| Variável | Descrição |
|---|---|
| `PROD_DOMAIN` | Domínio de produção |
| `STAGING_DOMAIN` | Domínio de staging |

## Provisionamento EasyEngine

> **Escopo:** estas tasks assumem que o EasyEngine já está instalado e acessível no servidor via SSH. A preparação do servidor (sistema operacional, instalação do EasyEngine, configuração de acesso SSH) não é coberta por este repositório.

Para provisionar um novo site do zero:

```sh
ddev exec dep ee:provision production
ddev exec dep ee:provision staging
```

O `ee:provision` executa três etapas em sequência:

1. **`ee:provision:prepare`** — `ee:site:create` + `provision:configure-deploy-target` + `provision:setup-shared-wpconfig` (Native) ou `provision:generate-shared-env` (Bedrock)
2. **`ee:provision:deploy`** — primeiro `ddev exec dep deploy <stage>`
3. **`ee:provision:finalize`** — `backup:scripts` + `ddev:generate-init-data` + `init:data` + `ee:site:clean`

Cada etapa pode ser reexecutada individualmente se necessário.

### Tasks de provisionamento individuais

```sh
ddev exec dep ee:site:create production
ddev exec dep provision:configure-deploy-target production
ddev exec dep provision:setup-shared-wpconfig production   # Native WP
ddev exec dep provision:generate-shared-env production     # Bedrock
```

## Tasks de manutenção

```sh
# Importação de dados (init/data/)
ddev exec dep init:data production                # banco + uploads + webp-express
ddev exec dep init:db production                  # só banco
ddev exec dep init:db:replace-urls production     # substitui URLs DDEV → produção
ddev exec dep init:uploads production             # só uploads

# Segurança do wp-config.php (Native WP)
ddev exec dep wp:config:lock production
ddev exec dep wp:config:unlock production

# Nginx
ddev exec dep nginx:custom-config production      # garante include de shared/nginx.conf

# Scripts de backup no servidor
ddev exec dep backup:scripts production           # baixa backup-db.sh, backup-files.sh, restore.sh

# Backup Duplicati
ddev exec dep duplicati:backup:register production  # registra tarefa e cron diário
ddev exec dep backup:run production                 # executa backup completo
```

## Estrutura do projeto

```
deploy.php                    # Deployer entrypoint — carrega config.php e o pacote core
deploy/
  bootstrap.php               # carregamento de .env por stage
  config.php                  # hosts (production, staging) e configurações Deployer
vendor/infografic/wp-deployer-core/
  src/helpers.php             # funções auxiliares (ee_shell, run_on_management_host, assert_*)
  src/providers/easyengine.php
  src/tasks/
    deploy.php                # cadeia de deploy e hooks
    provisioning.php          # ee:provision e tasks de provisionamento
    maintenance.php           # init:data, nginx:custom-config
    backup.php                # backup:*, duplicati:*
    wordpress.php             # wp:core:update-db, wp:cache:flush, wp:config:lock/unlock
.ddev/
  commands/host/              # generate-init-data, set-composer-auth
  commands/web/               # install-wp-if-needed, import-seed, import-uploads
init/data/                    # seeds de banco e uploads (não versionados)
web/                          # instalação WordPress (core gitignored; wp-content versionado seletivamente)
```

## Licença

Consulte o arquivo `LICENSE` para mais informações.
