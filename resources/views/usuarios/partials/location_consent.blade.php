<div class="location-consent">
    <input type="hidden" name="compartilhar_localizacao" value="0">
    <div class="form-check form-switch">
        <input class="form-check-input" type="checkbox" role="switch" name="compartilhar_localizacao" value="1"
               id="{{ $locationConsentId }}" data-location-sharing
               aria-describedby="{{ $locationConsentId }}-help"
               @checked(in_array(old('compartilhar_localizacao', $locationUser?->compartilhar_localizacao ?? false), [1, '1', true], true))>
        <label class="form-check-label" for="{{ $locationConsentId }}">Compartilhar minha localização</label>
    </div>
    <p id="{{ $locationConsentId }}-help">Autorizo a exibição pública do meu endereço e do ponto de localização do meu trabalho. Posso desativar esta opção a qualquer momento. A cidade pode ser informada sem compartilhar o endereço.</p>
    @error('compartilhar_localizacao')<p class="text-danger small mb-0">{{ $message }}</p>@enderror
</div>
