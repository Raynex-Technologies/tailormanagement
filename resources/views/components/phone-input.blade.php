@props(['label' => null, 'id' => null, 'required' => false])
@php
    $model = $attributes->wire('model')->value();
    $inputId = $id ?: 'phone-'.str_replace('.', '-', $model);
    $countries = \App\Support\InternationalPhone::callingCodes();
    $codes = array_values(array_unique(array_column($countries, 'code')));
    usort($codes, fn ($a, $b) => strlen($b) <=> strlen($a));
@endphp
<div class="min-w-0 space-y-2" x-data="{
    value: $wire.entangle(@js($model)), code: '+255', national: '', last: null, codes: @js($codes),
    init() { this.read(this.value); this.$watch('value', value => { if (value !== this.last) this.read(value); }); },
    read(value) {
        let text = String(value || '').replace(/[ ()-]/g, '');
        this.code = '+255';
        if (text.startsWith('00')) text = '+' + text.slice(2);
        if (text.startsWith('255')) text = '+' + text;
        if (text.startsWith('+')) {
            const code = this.codes.find(code => text.startsWith(code));
            if (code) { this.code = code; text = text.slice(code.length); }
        }
        this.national = text;
    },
    publish() { this.last = this.national ? this.code + this.national : ''; this.value = this.last; }
}">
    @if ($label)<flux:label :for="$inputId">{{ $label }}</flux:label>@endif
    <div class="grid grid-cols-2 gap-2">
        <flux:select class="min-w-0" x-model="code" x-on:change="publish()" aria-label="{{ $label ?: __('Phone') }} {{ __('country code') }}">
            @foreach ($countries as $country)
                <option value="{{ $country['code'] }}">{{ $country['name'] }} ({{ $country['code'] }})</option>
            @endforeach
        </flux:select>
        <flux:input :id="$inputId" type="tel" inputmode="numeric" pattern="[0-9]+" maxlength="15"
            autocomplete="tel-national" x-model="national" x-on:input="publish()"
            :required="$required" aria-label="{{ $label ?: __('Phone number') }}" placeholder="{{ __('Phone number') }}" />
    </div>
    <flux:error :name="$model" />
</div>
