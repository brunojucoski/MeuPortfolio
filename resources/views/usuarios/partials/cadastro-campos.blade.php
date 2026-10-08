<div class="auth-form-fields">
    <div class="auth-field auth-field-full">
        <label for="cadastro-nome" class="form-label">Nome</label>
        <input type="text" id="cadastro-nome" name="nome" placeholder="Nome completo" required autocomplete="name"
               class="form-control @error('nome') is-invalid @enderror" value="{{ old('nome') }}">
    </div>

    <div class="auth-field auth-field-full">
        <label for="cadastro-email" class="form-label">E-mail</label>
        <input type="email" id="cadastro-email" name="email" placeholder="E-mail" required autocomplete="email"
               class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
    </div>

    <div class="auth-field">
        <label for="telefone" class="form-label">Telefone</label>
        <input type="text" id="telefone" name="telefone" placeholder="Telefone" maxlength="15" inputmode="numeric" autocomplete="tel"
               class="form-control @error('telefone') is-invalid @enderror" value="{{ old('telefone') }}">
    </div>

    <div class="auth-field">
        <label for="documento" class="form-label">CPF ou CNPJ</label>
        <input type="text" id="documento" name="documento" placeholder="CPF/CNPJ" maxlength="18" inputmode="numeric" required
               class="form-control @error('documento') is-invalid @enderror" value="{{ old('documento') }}">
    </div>

    <div class="auth-field">
        <label for="cadastro-data-nasc" class="form-label">Data de nascimento</label>
        <input type="date" id="cadastro-data-nasc" name="data_nasc" required autocomplete="bday"
               class="form-control @error('data_nasc') is-invalid @enderror" value="{{ old('data_nasc') }}">
    </div>

    <div class="auth-field">
        <label for="cadastro-cep" class="form-label">CEP</label>
        <input type="text" id="cadastro-cep" name="cep" placeholder="00000-000" maxlength="9" inputmode="numeric" autocomplete="postal-code"
               class="form-control @error('cep') is-invalid @enderror" value="{{ old('cep') }}">
    </div>

    <div class="auth-field">
        <label for="cadastro-cidade" class="form-label">Cidade</label>
        <input type="text" id="cadastro-cidade" name="cidade" placeholder="Cidade" autocomplete="address-level2"
               class="form-control @error('cidade') is-invalid @enderror" value="{{ old('cidade') }}">
    </div>

    <div class="auth-field">
        <label for="cadastro-bairro" class="form-label">Bairro</label>
        <input type="text" id="cadastro-bairro" name="bairro" placeholder="Bairro" autocomplete="address-level3"
               class="form-control @error('bairro') is-invalid @enderror" value="{{ old('bairro') }}">
    </div>

    <div class="auth-field auth-field-full">
        <label for="cadastro-endereco" class="form-label">Endereço</label>
        <input type="text" id="cadastro-endereco" name="endereco" placeholder="Rua, avenida ou local de referência" autocomplete="street-address"
               class="form-control @error('endereco') is-invalid @enderror" value="{{ old('endereco') }}">
    </div>

    <input type="hidden" name="latitude" value="{{ old('latitude') }}">
    <input type="hidden" name="longitude" value="{{ old('longitude') }}">

    <div class="auth-field auth-field-full">
        <span class="form-label" id="cadastro-mapa-label">Localização no mapa</span>
        <div class="address-map-card">
            <div class="address-map" data-address-map aria-labelledby="cadastro-mapa-label"></div>
            <p class="address-map-help">Clique no mapa ou arraste o ponto para ajustar sua localização.</p>
        </div>
        <div class="address-map-status" data-address-status aria-live="polite"></div>
    </div>

    <div class="auth-field">
        <label for="cadastro-senha" class="form-label">Senha</label>
        <input type="password" id="cadastro-senha" name="senha" placeholder="Senha (mín. 6 caracteres)" required
               class="form-control @error('senha') is-invalid @enderror" autocomplete="new-password">
    </div>

    <div class="auth-field">
        <label for="cadastro-senha-confirmation" class="form-label">Confirmar senha</label>
        <input type="password" id="cadastro-senha-confirmation" name="senha_confirmation" placeholder="Repita a senha" required
               class="form-control" autocomplete="new-password">
    </div>
</div>
