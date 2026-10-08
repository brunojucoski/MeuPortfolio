<input type="hidden" name="id_categoria_post_portfolio" id="post_modal_id_categoria" value="{{ $categoria?->id ?? '' }}">
<p class="text-muted mb-3">Publicar em: <strong data-post-category-label>{{ $categoria?->nome ?? 'Página principal (sem álbum)' }}</strong></p>
