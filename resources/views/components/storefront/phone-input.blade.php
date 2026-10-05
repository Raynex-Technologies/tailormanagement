@props(['name' => 'phone', 'value' => '', 'required' => false])
@php
    $value = is_string($value) ? $value : '';
    $parts = \App\Support\InternationalPhone::parts($value);
    $code = old($name.'_country_code', $parts['country_code'] ?? '+255');
    $national = old($name.'_national_number', $parts['national_number'] ?? $value);
    $code = is_string($code) ? $code : '+255';
    $national = is_string($national) ? $national : '';
    $inputId = 'phone-'.\Illuminate\Support\Str::random(10);
@endphp
<div data-phone-input>
    <div class="d-flex gap-2">
        <select name="{{ $name }}_country_code" class="form-control" style="width:45%;min-width:0" aria-label="{{ __('Country code') }}" data-phone-code
            onchange="const box=this.closest('[data-phone-input]'); const number=box.querySelector('[data-phone-number]').value; box.querySelector('[data-phone-value]').value=number ? this.value+number : '';">
            @foreach (\App\Support\InternationalPhone::callingCodes() as $country)
                <option value="{{ $country['code'] }}" @selected($code === $country['code'])>{{ $country['name'] }} ({{ $country['code'] }})</option>
            @endforeach
        </select>
        <input id="{{ $inputId }}" name="{{ $name }}_national_number" class="form-control" style="min-width:0;flex:1" type="tel" inputmode="numeric" pattern="[0-9]+" maxlength="15"
            autocomplete="tel-national" aria-label="{{ __('Phone number') }}" value="{{ $national }}" data-phone-number @required($required)
            oninput="const box=this.closest('[data-phone-input]'); box.querySelector('[data-phone-value]').value=this.value ? box.querySelector('[data-phone-code]').value+this.value : '';">
    </div>
    <input type="hidden" name="{{ $name }}" value="{{ $parts['e164'] ?? $value }}" data-phone-value>
</div>
