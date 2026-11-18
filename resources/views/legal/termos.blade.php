{{-- Original draft, informed by ArtStation Terms of Service, Behance Community Guidelines
     and ANPD's Direitos dos Titulares. Review operator identification and legal text before publication.
     Sources: https://www.artstation.com/tos ; https://www.behance.net/misc/community ;
     https://www.gov.br/anpd/pt-br/assuntos/titular-de-dados/direito-dos-titulares --}}
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Termos de Uso — MeuPortfólio</title>
    <link href="{{ asset('css/navbar.css') }}" rel="stylesheet">
    <link href="{{ asset('css/legal.css') }}" rel="stylesheet">
</head>
<body>
@include('Components.navbarbootstrap')
<main class="legal-page">
    <h1>Termos de Uso</h1>
    <p class="legal-version">MeuPortfólio · Versão {{ \App\Support\TermosUso::VERSAO }} · Atualização: 18/11/2026</p>
    <section>
        <h2>1. A plataforma e o aceite</h2>
        <p>O MeuPortfólio é um projeto desenvolvido sem finalidade lucrativa, destinado à divulgação de trabalhos artísticos e à aproximação entre artistas e clientes. O cadastro e os recursos atualmente oferecidos são gratuitos. A plataforma não é agência de contratação, empregadora, representante dos usuários nem parte dos serviços negociados entre eles.</p>
        <p>A criação de conta exige a leitura e a concordância expressa com estes termos. Registramos a versão aceita e a data do aceite. Esse aceite não equivale a autorizar a divulgação da localização, que depende de uma escolha separada e facultativa.</p>
    </section>
    <section>
        <h2>2. Cadastro e segurança da conta</h2>
        <p>Informe dados verdadeiros e atualizados, utilize apenas a identidade que tem direito de representar e mantenha sua senha em sigilo. Você é responsável pelo uso da conta e deve informar à administração qualquer suspeita de acesso indevido. Quando a legislação exigir representação ou assistência para contratar, o uso deverá observar essas condições.</p>
    </section>
    <section>
        <h2>3. Dados, imagens e demais conteúdos dos usuários</h2>
        <p>O usuário é responsável pela exatidão, licitude, autoria e atualização dos dados, fotos, descrições, links, avaliações e demais conteúdos que inserir. Antes de publicar, deve obter as autorizações necessárias, inclusive de pessoas retratadas, titulares de direitos autorais e responsáveis legais quando aplicável.</p>
        <p>A plataforma não verifica previamente todos os conteúdos nem garante a veracidade das informações dos perfis. Não assume responsabilidade por dados falsos, conteúdos ilícitos ou violações praticadas por usuários ou terceiros, ressalvadas as responsabilidades e os deveres que a legislação obrigatoriamente atribua à plataforma. Essa disposição não afasta deveres próprios de segurança, proteção de dados, atendimento a determinações legais ou outras obrigações aplicáveis.</p>
        <p>Não publique documentos pessoais, endereços privados ou dados de outras pessoas sem fundamento legal. São proibidos fraude, assédio, discriminação, ameaças, exploração de menores, conteúdos ilegais, plágio, spam e tentativas de comprometer a segurança do sistema.</p>
    </section>
    <section>
        <h2>4. Direitos sobre os trabalhos</h2>
        <p>A autoria e a titularidade dos trabalhos permanecem com seus respectivos titulares. Ao enviar conteúdo, você autoriza, de forma não exclusiva e limitada ao funcionamento da plataforma, seu armazenamento, processamento técnico, exibição nas áreas públicas escolhidas e inclusão nas exportações solicitadas. Não ocorre transferência da propriedade intelectual à plataforma.</p>
        <p>A visualização de um trabalho não autoriza terceiros a copiá-lo, comercializá-lo ou reutilizá-lo sem permissão. A remoção do conteúdo interrompe sua exibição normal, observadas as necessidades de retenção legal e as rotinas técnicas de cópia de segurança.</p>
    </section>
    <section>
        <h2>5. Orçamentos e relações entre usuários</h2>
        <p>Preços, prazos, pagamentos, materiais, execução e condições dos trabalhos são de responsabilidade das partes envolvidas. A plataforma não garante a contratação, a entrega, a qualidade dos serviços ou a solvência de um usuário. Avaliações expressam experiências e opiniões de seus autores e não constituem certificação pela plataforma. Permanecem preservados os direitos legalmente assegurados às partes.</p>
    </section>
    <section>
        <h2>6. Privacidade e localização opcional</h2>
        <p>Os dados de cadastro são utilizados para criar e administrar contas, autenticar usuários, operar portfólios, solicitações e notificações e proteger o serviço. CPF/CNPJ, data de nascimento, credenciais e registros de aceite não são destinados à divulgação pública. Dados de contato e conteúdos inseridos nas áreas públicas do perfil podem ser vistos por visitantes; não inclua informações que não deseja divulgar.</p>
        <p>A localização é desativada por padrão. Ao marcar “Compartilhar minha localização”, você autoriza a exibição pública do endereço informado e do ponto geográfico do seu trabalho; perfis de artistas também podem aparecer no mapa. Prefira indicar o local profissional, e não sua residência, quando forem diferentes.</p>
        <p>Você pode retirar essa autorização ao editar o perfil. Ao salvar a opção desativada, seu ponto deixa de ser disponibilizado no mapa, as coordenadas são removidas do cadastro e o endereço detalhado fica oculto aos visitantes. A cidade continua opcional e pode ser usada nas buscas por portfólios. A retirada não apaga cópias que terceiros tenham obtido legitimamente enquanto os dados estavam públicos.</p>
        <p>Quando acionadas as funções de localização, consultas de CEP e endereço utilizam BrasilAPI e serviços baseados em OpenStreetMap/Nominatim, sujeitos às suas próprias políticas. Evite incluir dados pessoais desnecessários nessas consultas.</p>
    </section>
    <section>
        <h2>7. Seus direitos e o contato com a administração</h2>
        <p>Nos termos da legislação aplicável, você pode solicitar informações sobre o tratamento dos seus dados, acesso, correção, eliminação quando cabível e revogação de consentimentos. O atendimento pode exigir confirmação proporcional da sua identidade. Alguns registros podem precisar ser conservados para cumprir obrigações legais, segurança e exercício regular de direitos, pelo período necessário à finalidade correspondente.</p>
        <p>Pedidos sobre privacidade, contas, direitos autorais ou conteúdos irregulares devem identificar o perfil ou conteúdo envolvido e ser encaminhados ao responsável:</p>
        <p><strong>Responsável:</strong> {{ $visualSistema->termosResponsavel() ?: 'Administração do MeuPortfólio' }}.</p>
        @if($visualSistema->termosContatoEmail())
            <p><strong>Contato:</strong> <a href="mailto:{{ str_replace('%40', '@', rawurlencode($visualSistema->termosContatoEmail())) }}">{{ $visualSistema->termosContatoEmail() }}</a>.</p>
        @else
            <p class="legal-notice">O canal de contato ainda não foi configurado. A administração deve informar um contato válido antes da disponibilização pública deste serviço.</p>
        @endif
    </section>
    <section>
        <h2>8. Disponibilidade, medidas e alterações</h2>
        <p>Podem ocorrer manutenção, falhas ou indisponibilidade. Guarde cópias próprias dos seus trabalhos e documentos importantes. A administração poderá restringir contas ou conteúdos em caso de violação destes termos, risco à segurança ou exigência legal, com análise e comunicação quando cabíveis e oportunidade de contato para contestação.</p>
        <p>Alterações relevantes serão apresentadas de forma clara, com atualização da versão e novo aceite quando necessário. Estes termos são regidos pela legislação brasileira e não limitam direitos ou garantias que não possam ser afastados por acordo, inclusive os relativos à proteção de dados e, quando aplicáveis, à defesa do consumidor.</p>
    </section>
</main>
@include('Components.footer')
</body>
</html>
