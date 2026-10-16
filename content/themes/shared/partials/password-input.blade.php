<div class="input-wrap">
    <input class="input" id="{{ $id }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete ?? 'current-password' }}"{{ !empty($minlength) ? ' minlength=' . $minlength : '' }}{{ !empty($maxlength) ? ' maxlength=' . $maxlength : '' }} required>
    <button type="button" class="reveal" data-toggle-password="{{ $id }}" aria-label="Show password" aria-pressed="false">
        <span class="eye-on">{!! icon('eye') !!}</span><span class="eye-off">{!! icon('eye-slash') !!}</span>
    </button>
</div>
