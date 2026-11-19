# Deploy: Vercel + Supabase + Namecheap

Este roteiro prepara uma publicacao nova. Nenhum projeto remoto, push ou registro
DNS foi criado automaticamente. O banco do XAMPP nao foi migrado/apagado.
Google permanece desativado no exemplo de producao.

## Preparacao realizada

- Laravel 12 e dependencias atualizadas, preservando a estrutura da aplicacao.
- Docker multiestagio com PHP 8.4/FrankenPHP, extensoes PostgreSQL e assets compilados.
- Web sem root, raiz HTTP somente `public`, cache/views temporarios em `/tmp`.
- Sessoes criptografadas e cache/locks compartilhados no banco.
- Imagens publicas e anexos privados em discos S3 separados; PDF le o storage configurado.
- Migrations portaveis, tipos obrigatorios repetiveis e nenhum admin/senha padrao.
- SMTP Resend, Sentry com filtro de privacidade, healthcheck e verificacao de deploy.
- CI com testes, auditorias, PostgreSQL e build da imagem sem segredos.

Container Runtime do Vercel esta em beta: confirmar disponibilidade e regras do
Hobby. Planos gratuitos tem cotas e podem pausar. Nao utilizar `vercel-php` antigo.

## 1. Revisar e enviar o repositorio

1. Revisar `git diff`, rodar testes/auditorias e manter o repositorio privado, se apropriado.
2. Nunca adicionar `.env`, backups, uploads ou credenciais ao Git/imagem.
3. O `.env` esteve no historico. Remover o arquivo atual nao apaga commits antigos: rotacionar segredos expostos e planejar limpeza do historico em operacao separada, sem force-push automatico.
4. Fazer commit e push somente dos arquivos revisados para `main`. Esta tarefa nao faz commit/push.

```powershell
composer audit
npm audit
npm run build
php artisan test
node --test tests/JavaScript/*.test.cjs
docker build -t meuportfolio .
```

## 2. Criar Supabase

### Projeto ja criado: onde continuar

Nao e necessario conectar GitHub nem Vercel em Settings > Integrations para este
projeto funcionar. O Laravel acessa PostgreSQL e Storage diretamente pelo backend.
A integracao automatica nao substitui as variaveis `DB_*` e `STORAGE_S3_*` abaixo.
Supabase nao hospeda este aplicativo PHP; Vercel (ou outra hospedagem PHP/Docker)
continua sendo necessario para executar o site.

1. Criar conta com 2FA, organizacao Free e projeto. Separar producao e testes dentro das cotas; previews nunca usam a base de producao.
2. Escolher regiao proxima da hospedagem. Guardar senha administrativa em gerenciador, nao no chat.
3. Manter autenticacao no Laravel: Supabase Auth nao e necessario.
4. Neste projeto, abrir Settings > Data API e desligar **Enable Data API**, ANTES de criar as tabelas. A autenticacao e o acesso ao banco sao do Laravel. Nao ativar Realtime nem criar policies publicas para as tabelas da aplicacao. Caso decida usar Data API futuramente, revisar grants/RLS antes de reativa-la.
5. Baixar o certificado CA do banco para `resources/certificates/supabase-ca.crt`. Somente esse certificado PUBLICO pode entrar na imagem; nunca uma chave privada. Ele nao e o SSL comprado na Namecheap.
6. Em Connect, copiar os dados do transaction pooler para o app (porta 6543), usando `DB_EMULATE_PREPARES=true`. Para migrations, usar conexao direta ou session pooler (5432).

O projeto le `DB_SSLMODE`/`DB_SSLROOTCERT`. Producao exige `verify-full` e a CA
correta. Este repositorio usa `DATABASE_URL` ou campos `DB_HOST`, `DB_PORT`, etc.;
nao copiar `DB_URL` de outro tutorial sem ajustar o config.

## 3. Inicializar o banco e separar permissoes

### 3.1. Conexao administrativa separada do XAMPP

1. Em Supabase > **Connect**, selecionar **Session pooler**, porta `5432`. Copiar host, database e username exatamente como exibidos. A senha e a do banco, nao a da conta Supabase nem uma API key.
2. Em Database > Settings > SSL Configuration, baixar a CA publica e ativar **Enforce SSL on incoming connections**. A alteracao pode reiniciar brevemente o banco.
3. Salvar a CA em `resources/certificates/supabase-ca.crt`.
4. Criar, no editor local, `.env.deploy.local` a partir de `.env.production.example`. Esse arquivo privado ja e ignorado pelo Git. NAO substituir o `.env` do XAMPP e NAO enviar esse arquivo ao chat.
5. Nesse arquivo, preencher a APP_KEY exclusiva de producao e os dados do Session pooler. Gerar a chave uma unica vez com `php artisan key:generate --show`, em terminal privado, e guardar em gerenciador de senhas; nao compartilhar a saida.

Trecho de configuracao para esse arquivo LOCAL (substituir os placeholders):

```dotenv
DATABASE_URL=
DB_CONNECTION=pgsql
DB_HOST=HOST_EXATO_DO_SESSION_POOLER
DB_PORT=5432
DB_DATABASE=postgres
DB_USERNAME=postgres.REFERENCIA_DO_PROJETO
DB_PASSWORD="SENHA_ADMINISTRATIVA_DO_NOVO_BANCO"
DB_SSLMODE=verify-full
DB_SSLROOTCERT=C:/xampp/htdocs/MeuPortfolio/resources/certificates/supabase-ca.crt
DB_EMULATE_PREPARES=false
```

Usar aspas nos valores conforme a sintaxe dotenv, especialmente se contiverem `#`
ou espacos. Com campos separados, nao aplicar URL-encoding a `DB_PASSWORD`.
Nao definir `DATABASE_URL` no sistema operacional: essa variavel sobrepoe os campos.
O caminho Windows da CA e somente local; no Vercel sera `/app/resources/certificates/supabase-ca.crt`.

Abrir um terminal privado na raiz do projeto. Primeiro confirmar que o arquivo
existe, limpar somente o cache de configuracao e INSPECIONAR a conexao:

```powershell
if (-not (Test-Path -LiteralPath .env.deploy.local)) { throw 'Configure .env.deploy.local antes de continuar.' }
php artisan config:clear --env=deploy.local
php artisan db:show --env=deploy.local --database=pgsql
```

Parar se o host nao for o pooler do projeto desejado, se a verificacao TLS falhar
ou se aparecer banco inesperado. Nao resolver erros com `sslmode=disable/require`.
Nao continuar com mensagens de erro. `--env=deploy.local` nao e opcional: sem esse
arquivo/opcao o Laravel pode voltar ao banco local. Nao executar `config:cache`
com essas credenciais administrativas.

### 3.2. Criar tabelas e o primeiro administrador

Depois de confirmar a conexao e validar o fluxo em ambiente de testes separado,
executar no NOVO banco:

```powershell
php artisan migrate --env=deploy.local --database=pgsql --force
php artisan db:seed --env=deploy.local --class=DatabaseSeeder --force
php artisan app:create-admin --env=deploy.local
php artisan migrate:status --env=deploy.local --database=pgsql
```

O comando do admin pede os dados e a senha interativamente. Nunca usar senha padrao,
`migrate:fresh`, `db:wipe` ou rollback numa base com dados. O startup nao migra nem
regenera a APP_KEY. Nenhum desses comandos deve continuar apontando ao XAMPP.

### 3.3. Usuario restrito para a aplicacao

Criar no Supabase um papel `app_runtime` com login e senha propria forte, sem
superuser, criacao de roles/bancos ou BYPASSRLS. Se usar um cliente PostgreSQL,
preferir o editor de roles para definir a senha. Alternativamente, criar o papel
sem senha no SQL Editor e definir sua senha pelo prompt `\password app_runtime`
de uma sessao psql conectada ao projeto como administrador:

```sql
CREATE ROLE app_runtime LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOINHERIT NOBYPASSRLS;
```

O comando de criacao e feito somente uma vez. `\password` e um comando do psql,
NAO do SQL Editor. Nao deixar senhas em consultas salvas, scripts, historico de
terminal ou Git. Nao conceder associacao aos papeis `postgres`/`service_role`.
Depois das migrations, conceder apenas as permissoes necessarias no SQL Editor:

```sql
GRANT USAGE ON SCHEMA public TO app_runtime;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO app_runtime;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO app_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO app_runtime;
ALTER DEFAULT PRIVILEGES FOR ROLE postgres IN SCHEMA public
    GRANT USAGE, SELECT ON SEQUENCES TO app_runtime;
```

Trocar `postgres` pelo verdadeiro proprietario das migrations quando diferente.
Conferir o username desse papel no pooler (normalmente `app_runtime.REFERENCIA`).
Somente essa credencial restrita fica no ambiente web; a administrativa fica fora.
Se utilizar schema privado/RLS no papel runtime, adaptar grants/policies e testar.
Conferir que `app_runtime` nao pode criar objetos no schema `public`; caso herde
esse acesso de `PUBLIC`, revisar os grants desse projeto antes de continuar.
Nao alterar os schemas gerenciados `auth`, `storage` ou `extensions`.

Ao configurar o Vercel, usar Transaction pooler, porta `6543`, username
`app_runtime.REFERENCIA_DO_PROJETO`, senha desse novo papel e
`DB_EMULATE_PREPARES=true`. Nao usar a senha administrativa no ambiente web.

Dados do MySQL exigem trabalho separado: backup, conversao de tipos, preservacao
de IDs/FKs, importacao, ajuste de sequencias e conferencia. Um dump MySQL nao pode
ser simplesmente executado no PostgreSQL. Nunca enviar dados reais ao Git.

## 4. Storage persistente

1. Abrir **Storage > New bucket**. Criar `portfolio-publico` com **Public bucket ligado** e `orcamentos-privados` com **Public bucket desligado**. Os nomes devem ser exatamente esses.
2. Em cada bucket, configurar limite de arquivo de **3 MiB (3145728 bytes)**. No publico permitir `image/jpeg`, `image/png`, `image/gif`, `image/webp`. No privado permitir `application/octet-stream`: o backend valida o arquivo original e armazena anexos como download binario, sem execucao no navegador. Uma allowlist privada somente de `application/pdf`/imagens bloquearia os uploads atuais.
3. Em **Storage > S3 configuration / S3 Access Keys**, gerar um par **Access Key ID + Secret Access Key**. Guardar em gerenciador privado. NAO usar `anon`, publishable key ou JWT `service_role` como credenciais S3.
4. Preencher `STORAGE_S3_ENDPOINT`, `STORAGE_S3_REGION`, `STORAGE_S3_KEY` e `STORAGE_S3_SECRET` conforme o painel. Copiar endpoint e regiao exatos, sem adivinhar a regiao pelo nome do projeto.
5. `PUBLIC_STORAGE_URL` e a URL HTTP publica do bucket, NAO o endpoint S3. Formato: `https://REFERENCIA.supabase.co/storage/v1/object/public/portfolio-publico`.
6. Nao configurar URL publica para o disco privado nem leitura anonima de anexos. As chaves S3 geradas tem acesso amplo aos buckets e ignoram RLS: manter somente no backend. Nao criar policies anonimas de INSERT/UPDATE/DELETE nem policies publicas de leitura dos anexos.
7. Conferir que fotos abrem publicamente, mas anexos somente pela rota autenticada, com participante/admin autorizado. Fazer teste anonimo do URL de um anexo conhecido; deve negar acesso ao conteudo. URLs assinadas expiram e continuam sensiveis enquanto validas.

Valores publicos e privados sao configurados assim (segredos somente nos ambientes privados):

```dotenv
PUBLIC_STORAGE_DRIVER=s3
PRIVATE_STORAGE_DRIVER=s3
PUBLIC_STORAGE_BUCKET=portfolio-publico
PRIVATE_STORAGE_BUCKET=orcamentos-privados
STORAGE_S3_ENDPOINT=ENDPOINT_HTTPS_EXATO_DO_PAINEL
STORAGE_S3_REGION=REGIAO_EXATA_DO_PAINEL
STORAGE_S3_KEY=ACCESS_KEY_ID_PRIVADO
STORAGE_S3_SECRET=SECRET_ACCESS_KEY_PRIVADO
PUBLIC_STORAGE_URL=https://REFERENCIA.supabase.co/storage/v1/object/public/portfolio-publico
```

O limite do bucket e por arquivo; o limite do aplicativo no Vercel e de 3 MiB
para a soma dos arquivos por requisicao. Criar buckets nao envia automaticamente
as fotos existentes no XAMPP, e criar as tabelas nao copia usuarios/dados locais.

### Arquivos antigos

Copiar imagens publicas preservando os caminhos `posts/`, `fotos_perfil/` e
`sistema/imagens/`. Os uploads locais nao sao incluidos na imagem.

Antes do corte, auditar anexos no ambiente antigo, com backup e gravacoes pausadas:

```powershell
php artisan storage:privatize-budget-attachments
php artisan storage:privatize-budget-attachments --help
```

O primeiro comando SIMULA. Somente depois da revisao usar `--apply` e o disco
`--source` correto; ele copia, confere hash/tamanho, atualiza referencias e remove a
fonte publica. Falhas/orfaos bloqueiam o corte e exigem revisao. Nunca copiar anexos
para um bucket publico.

### Limite inicial de upload

No Vercel o envio completo fica limitado a **3 MiB no total**, somando todos os
arquivos, para reservar espaco ao multipart. Frontend e backend validam o limite;
uploads locais continuam com seus limites anteriores. Aumentar o PHP nao remove o
teto da hospedagem. Upload direto/presigned para arquivos maiores nao esta
implementado nesta etapa e exige uma evolucao separada de autorizacao/validacao.

O PDF tambem respeita o limite de resposta: 4 MiB por exportacao neste primeiro
deploy. Documentos maiores exibem validacao para selecionar menos itens/exportar
em partes, em vez de falhar silenciosamente no proxy.

## 5. Criar o projeto Vercel

1. Criar conta com 2FA, conectar GitHub e importar somente o repositorio MeuPortfolio.
2. Escolher preset **Container**, Dockerfile na raiz e branch `main`. Nao hospedar `public` como site estatico.
   Usar Root Directory `./` e deixar Build/Output Settings no padrao do Container.
   Antes de importar, confirmar no GitHub que `Dockerfile`, `docker/`, configs,
   migrations, lockfiles e o certificado CA publico estao no commit enviado.
   O aviso "Possible configuration mismatch" pode estar relacionado a arquivos
   ainda ausentes na branch remota; e uma hipotese, nao um diagnostico do painel.
   Se persistir depois do push, verificar o detalhe do aviso/log antes do deploy.
3. Copiar as CHAVES de `.env.production.example` para Environment Variables e preencher os valores privados. Separar Production e Preview.
   Cadastrar explicitamente `PORT=8080` nas variaveis do projeto: o servidor da
   imagem escuta nessa porta e o roteador Vercel precisa receber o mesmo valor
   (o padrao da plataforma e 80). Nao depender apenas do ENV do Dockerfile.
   No primeiro deploy, nao cadastrar APP_URL: a aplicacao usa `https://VERCEL_URL`
   automaticamente. Assim os links de recuperacao apontam ao endereco temporario
   enquanto o DNS proprio ainda nao esta ligado. Confirmar que o Vercel expoe as
   variaveis de sistema. Depois do dominio validado, configurar APP_URL canonica
   apenas em Production; previews continuam com seu proprio endereco.
4. Gerar APP_KEY exclusiva de cada ambiente uma unica vez, em terminal privado (`php artisan key:generate --show`). Guardar e manter entre deploys; nunca mandar ao chat, logs ou build arguments.
5. Preencher banco, CA, buckets e contatos. `TRUSTED_PROXIES=*` pressupoe o proxy gerenciado do Vercel; nao usar com PHP diretamente exposto.
6. Manter debug false, HTTPS, cookies seguros, sessao/cache database e Google desligado. `VERCEL_URL` autoriza o hostname especifico do preview; nao autorizar todos os `*.vercel.app`.
7. Manter `DEPLOYMENT_STRICT=true`. O modo false e exclusivo de QA local e recusado quando VERCEL esta definido.
8. Fazer deploy e conferir `/health`. Ele prova somente que PHP responde; banco/arquivos/e-mail precisam de testes adicionais.

Conectar Supabase pelo marketplace Vercel e opcional. Este projeto nao usa
`NEXT_PUBLIC_SUPABASE_*`; copiar as variaveis do exemplo e mais simples e evita
importar credenciais administrativas desnecessarias. Nao reutilizar o arquivo
local de migrations como ambiente do Vercel. Nao incluir senhas no Dockerfile,
Build Command ou build arguments. A integracao GitHub relevante para publicar
o codigo e a do Vercel com o repositorio.

A imagem nao contem `.env` nem chaves e nao usa `artisan serve`. `/tmp` e
descartavel, nunca armazenamento de usuario. O exemplo usa fila `sync`; nao iniciar
`queue:work` como processo solto no container web. Planejar fila duravel ao crescer.

## 6. Resend SMTP

1. Criar conta, cadastrar dominio de envio e inserir SPF/DKIM fornecidos pelo Resend em Namecheap > Advanced DNS, preservando registros de outros servicos.
2. Configurar DMARC conscientemente, aguardar verificacao e gerar chave de envio com menor escopo disponivel.
3. No Vercel: host `smtp.resend.com`, porta 587, usuario `resend`, senha igual a chave API e `MAIL_ENCRYPTION=tls`. O exemplo ja contem esses campos.
4. Usar remetente no dominio verificado, testar recuperacao de senha ate o final e conferir limites/entrega no painel.

SMTP envia mensagens, nao cria caixa postal de suporte. Configure contato real.
Nao usar `log` nem dominio de teste como substituto de entrega em producao.

## 7. Sentry

1. Criar organizacao Developer e projeto Laravel; guardar DSN em `SENTRY_LARAVEL_DSN`.
2. Release usa `VERCEL_GIT_COMMIT_SHA` por padrao ou `SENTRY_RELEASE` explicito.
3. Executar `php artisan sentry:test` em ambiente controlado, confirmar evento e configurar alertas/cotas.

O filtro preserva tipo do erro, arquivo/linha e release. Omite mensagens brutas,
SQL/parametros, dados de usuario, corpo, cookies, tokens e variaveis locais. O
stderr tambem e filtrado. Tracing, logs do SDK, metrics e replay ficam desligados.
Nao criar rota publica de debug nem ativar coleta de dados sem revisao.

## 8. Dominio e SSL por ultimo

Depois de validar o endereco temporario, adicionar `meuportfolio.ink` e
`www.meuportfolio.ink` em Vercel > Settings > Domains. Manter PremiumDNS da Namecheap
por enquanto. Copiar exatamente A/CNAME/TXT mostrados para ESSE projeto em Advanced
DNS, removendo apenas parking/redirects conflitantes de `@`/`www` e preservando MX/TXT.
Escolher dominio canonico, ajustar APP_URL/APP_TRUSTED_HOSTS e redeploy. O Vercel
normalmente emite HTTPS automaticamente; o certificado comprado fica para revisao
por ultimo, conforme solicitado. Nao liberar cadastros por HTTP.

## 9. Verificacao final e operacao

Para verificar daqui sem trocar o banco do XAMPP, criar `.env.runtime.local`
privado com as variaveis REAIS do ambiente web: papel `app_runtime`, Transaction
pooler, buckets e demais servicos. Usar a URL HTTPS real do deploy em APP_URL e o
caminho Windows da CA. NAO usar a credencial administrativa de `.env.deploy.local`.
Esse arquivo ja e ignorado pelo Git. No terminal privado da raiz:

```powershell
if (-not (Test-Path -LiteralPath .env.runtime.local)) { throw 'Configure .env.runtime.local antes de continuar.' }
php artisan config:clear --env=runtime.local
php artisan deploy:check --env=runtime.local
php artisan deploy:check --env=runtime.local --connections
php artisan storage:check-deployment --env=runtime.local
```

O primeiro verifica configuracao sem imprimir valores secretos; `--connections`
consulta banco/arquivos e audita anexos, sem criar dados. Erros devem ser resolvidos
antes da liberacao. Esses comandos nao devem ser expostos por HTTP.

- Testar cadastro, termos, login/logout, recuperacao de senha, admin e acesso negado a outro perfil/orcamento/anexo.
- Testar fotos/posts/albuns, mapa/privacidade, imagens do admin e PDF; conferir mobile e limite de upload.
- Confirmar persistencia de sessoes/arquivos apos reiniciar e fazer novo deploy.
- Confirmar que `.env`, `.git`, credenciais e excecoes nao sao acessiveis publicamente.
- Fazer backups criptografados do banco E objetos. Backup PostgreSQL nao contem os bytes dos arquivos. Ensaiar restauracao.
- Monitorar cotas e inatividade; nao gerar trafego artificial para contornar regras do Free.
- Reverter deploy web nao desfaz migrations. Planejar schema compativel e backup antes do corte.

## Validacao local em 18/11/2026

- Suite completa: 226 testes PHP e 44 JavaScript aprovados, incluindo a correspondencia da porta Vercel/Container.
- Historico de migrations em PostgreSQL 17: 7 testes, 55 verificacoes.
- Imagem PHP 8.4 construida, executada sem root e com filesystem raiz somente leitura; `/health` saudavel.
- 45 migrations e seed executados em PostgreSQL descartavel; cadastro/login/logout e edicao por HTTP de artista/cliente aprovados.
- Sessoes criptografadas, token CSRF e cache mantidos apos dois reinicios do app.
- Telas de autenticacao verificadas em desktop e mobile, incluindo compilacao Blade no Linux.
- Composer/npm sem alertas de vulnerabilidades na auditoria dessa data.

O ambiente Docker de QA usou modo nao estrito e conexao PostgreSQL interna sem TLS,
somente para esse teste isolado. Isso nao valida a CA, o papel runtime nem o TLS
do futuro Supabase. SMTP/SDKs foram testados sem envio real. Entrega Resend, eventos
Sentry, buckets/privacidade e deploy Vercel continuam pendentes de credenciais.
O workflow foi configurado, mas sua execucao no GitHub depende do futuro push.

## Referencias oficiais

- https://vercel.com/kb/guide/deploy-php-on-vercel-with-docker
- https://vercel.com/docs/functions/runtimes/container
- https://vercel.com/docs/functions/limitations
- https://supabase.com/docs/guides/getting-started/quickstarts/laravel
- https://supabase.com/docs/guides/database/connecting-to-postgres
- https://supabase.com/docs/guides/api/securing-your-api
- https://supabase.com/docs/guides/storage/s3/authentication
- https://resend.com/docs/send-with-smtp
- https://docs.sentry.io/platforms/php/guides/laravel/
