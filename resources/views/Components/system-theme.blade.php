<style id="system-theme">
    :root {
        --sistema-cor-base: {{ $visualSistema->corBase() }};
        --sistema-cor-secundaria: {{ $visualSistema->corSecundaria() }};
        --roxo-appolo: var(--sistema-cor-base) !important;
        --roxo-claro: var(--sistema-cor-secundaria) !important;
        --vermelho-medio: var(--sistema-cor-secundaria) !important;
    }
</style>
