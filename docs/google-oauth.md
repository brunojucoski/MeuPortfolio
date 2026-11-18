# Entrar e cadastrar com Google

## 1. Implementacao no projeto

Etapas implementadas:

1. Instalado Laravel Socialite e configurado o provedor em `config/services.php`.
2. Criada a migration `2026_11_18_000002_add_google_id_to_usuarios.php`, com identificador Google opcional e unico. Contas existentes continuam funcionando com senha.
3. Incluidos botoes no login, no cadastro unificado e em Editar perfil para vincular contas existentes. O login interno nao recebe Google.
4. Criado callback com estado OAuth vinculado a sessao, validade de dez minutos, protecao contra reutilizacao e verificacao do e-mail retornado pelo Google.
5. Novas contas completam os campos obrigatorios, escolhem Artista ou Cliente e aceitam os termos antes de serem criadas. Nao e criada conta administrativa. Localizacao continua opcional e desativada por padrao.
6. Mantido o retorno ao artista de origem quando o cadastro comeca em um pedido de orcamento. URLs arbitrarias nao sao aceitas como destino.
7. Contas existentes nao sao vinculadas apenas pela coincidencia de e-mail: entre com a senha e use Editar perfil > Vincular Google, com o mesmo e-mail.
8. Implementados testes de integracao usando o provedor real do Socialite com transporte HTTP simulado; nenhuma conta de teste e criada no banco local real.

O aplicativo armazena o identificador Google, e-mail verificado e nome fornecidos durante o cadastro. Nao importa foto remota e nao armazena tokens de acesso ou de renovacao do Google. Contas novas recebem uma senha local aleatoria e nao divulgada; o usuario pode definir uma senha propria em Editar perfil > Redefinir senha, depois de entrar com Google.

## 2. Criar o cliente no Google Cloud

1. Acesse o [Google Cloud Console](https://console.cloud.google.com/) com a conta responsavel e selecione ou crie um projeto para o MeuPortfolio.
2. Abra Google Auth Platform. Em Branding, informe nome, contato de suporte e contato do desenvolvedor. Configure Audience como External para acesso publico e adicione sua conta em Test users enquanto o aplicativo estiver em testes.
3. Em Data Access, mantenha somente os escopos basicos de identidade: `openid`, `profile` e `email`. Nao e necessario habilitar Gmail, Drive ou Google Maps para este recurso.
4. Em Clients, crie um cliente OAuth do tipo Web application.
5. Em Authorized redirect URIs, cadastre exatamente:

   ```text
   http://127.0.0.1:8002/auth/google/callback
   ```

6. Obtenha o Client ID e o Client Secret. Guarde o segredo somente no ambiente local ou no gerenciador de segredos do servidor. Nao o envie pelo chat nem o coloque no painel administrativo, JavaScript ou controle de versao.

Esse exemplo usa o servidor local na porta 8002. Para usar outra porta, host ou subdiretorio, ajuste o callback tanto no Google quanto no `.env`. `localhost` e `127.0.0.1` nao sao intercambiaveis: inicie e conclua o fluxo no mesmo host. Este fluxo de redirecionamento no servidor nao usa o SDK JavaScript e nao exige Authorized JavaScript origins.

Referencia: [OAuth para aplicacoes web do Google](https://developers.google.com/identity/protocols/oauth2/web-server).

## 3. Configurar o ambiente

Edite o `.env` local, que foi retirado do indice do Git e incluido no `.gitignore`. O arquivo fisico foi preservado. Isso nao remove segredos de commits antigos: se o repositorio ja foi compartilhado, revise o historico e rotacione os segredos expostos.

```dotenv
GOOGLE_LOGIN_ENABLED=true
GOOGLE_CLIENT_ID=SEU_CLIENT_ID.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=SEU_CLIENT_SECRET
GOOGLE_REDIRECT_URI=http://127.0.0.1:8002/auth/google/callback
```

Nao utilize os valores ilustrativos acima como credenciais reais. O `.env.example` contem somente as chaves vazias e o callback de exemplo.

No PowerShell, dentro do projeto:

```powershell
& C:/xampp/php/php.exe artisan config:clear
& C:/xampp/php/php.exe artisan migrate --path=database/migrations/2026_11_18_000002_add_google_id_to_usuarios.php
```

A migration ja foi aplicada no ambiente local durante a implementacao; executa-la novamente nao duplica a coluna. Nao use `migrate:fresh`. Reinicie o servidor de desenvolvimento depois de alterar as variaveis. Se ele nao estiver rodando:

```powershell
& C:/xampp/php/php.exe artisan serve --host=127.0.0.1 --port=8002
```

Sem as tres configuracoes obrigatorias, os botoes permanecem desabilitados e as rotas nao iniciam autenticacao. Para desativar o recurso, defina `GOOGLE_LOGIN_ENABLED=false` e limpe/recrie o cache de configuracao.

## 4. Validacao automatizada

```powershell
& C:/xampp/php/php.exe artisan test --filter=GoogleAuthTest
& C:/xampp/php/php.exe artisan test --filter=AuthFormsDesignTest
node --test tests/JavaScript/*.test.cjs
```

O teste usa SQLite em memoria e Guzzle MockHandler, exercitando troca de codigo e consulta de identidade no Socialite sem chamar o Google. Cobre cadastro de artista e cliente, campos obrigatorios, termos, privacidade, retorno de orcamento, login, vinculacao, colisoes, bloqueio de admin, exclusao logica, CSRF, estado invalido, expiracao, cancelamento e repeticao do callback. Nao substitui o teste de consentimento real abaixo.

## 5. Validacao real apos configurar as credenciais

1. Em janela anonima, abra `http://127.0.0.1:8002/cadastro`, escolha Cliente e clique em Cadastrar com Google sem preencher o formulario manual.
2. Escolha uma conta de teste autorizada no Google. Confirme que retorna a `/cadastro/google`, com nome e e-mail preenchidos, e-mail somente leitura, perfil Cliente e sem campos de senha.
3. Tente concluir sem aceitar os termos: nao deve cadastrar. Preencha CPF/CNPJ e nascimento, aceite os termos e conclua. Confirme sessao ativa e perfil de cliente, sem compartilhamento de localizacao.
4. Saia e entre novamente pelo botao Entrar com Google: nao deve criar outro usuario nem pedir os dados de cadastro novamente.
5. Repita com outra conta e perfil Artista. Confira que nao e possivel cadastrar administrador.
6. Com uma conta local existente, tente Google: deve orientar a entrar com senha. Depois entre com senha, abra Editar perfil e vincule a conta Google com o mesmo e-mail. Saia e teste novamente.
7. Inicie o cadastro a partir da solicitacao de orcamento em um perfil de artista. Ao concluir pelo Google, confirme o retorno a esse mesmo perfil.
8. Cancele no Google e tente voltar/reutilizar um callback: deve aparecer mensagem amigavel, sem login indevido.

Somente apos executar esses passos com credenciais validas e consentimento do usuario e possivel declarar validada a integracao real com o Google. Ate la, a validacao e local e simulada.

## 6. Diagnostico e publicacao

- `redirect_uri_mismatch`: compare esquema, host, porta, caminho e barra final no Google Cloud e no `.env`.
- `invalid_client`: confira se o cliente e Web application, o projeto e o correto e as credenciais nao sao exemplos. Nao compartilhe o segredo em mensagens de erro.
- `access_denied`: confira a configuracao de publico/usuarios de teste ou tente novamente se houve cancelamento.
- Solicitacao expirada/invalida: reinicie pelo botao, mantenha o mesmo host e permita cookies de sessao. O fluxo e valido por dez minutos e uma segunda tentativa invalida a primeira.
- Falha HTTPS/certificado no PHP: configure a cadeia de certificados do PHP/cURL; nao desative a verificacao TLS.
- Producao: HTTPS, `APP_DEBUG=false`, cookies seguros, dominio e callback corretos, contato valido, termos e politica de privacidade revisados, publico do OAuth configurado e requisitos de verificacao do Google atendidos. Execute `composer audit` e trate vulnerabilidades antes da publicacao; esta alteracao nao atualiza todo o framework legado.

Referencias: [Socialite](https://laravel.com/docs/10.x/socialite), [identidade OpenID Connect](https://developers.google.com/identity/openid-connect/openid-connect), [marca do botao Google](https://developers.google.com/identity/branding-guidelines). O icone `public/imgs/google-g.png` foi obtido do arquivo oficial indicado nessa ultima pagina, sem modificar suas cores.
