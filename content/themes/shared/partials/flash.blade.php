@if (flash_get('status'))
    <div class="alert alert-success" role="status">{!! icon('success') !!}<div>{{ flash_get('status') }}</div></div>
@endif
@if (flash_get('error'))
    <div class="alert alert-error" role="alert">{!! icon('error') !!}<div>{{ flash_get('error') }}</div></div>
@endif
