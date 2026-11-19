@if(\App\Support\MediaStorage::requestLimit() > 0)
    @once
        <script src="{{ asset('js/upload-request-limit.js') }}" data-upload-request-max-bytes="{{ \App\Support\MediaStorage::requestLimit() }}" defer></script>
    @endonce
@endif
