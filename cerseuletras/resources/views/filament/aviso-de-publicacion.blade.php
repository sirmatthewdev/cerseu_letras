{{-- Solo cuando algo falla, y en todas las páginas: quien edita un taller no
     pasa por el escritorio y, sin esto, seguiría guardando cambios que nadie ve. --}}
@php
    $publicacion = \App\Support\EstadoDePublicacion::actual();
@endphp

@if (\App\Support\EstadoDePublicacion::hayProblema($publicacion) && ! request()->routeIs('filament.admin.pages.dashboard'))
    @php($vista = \App\Support\EstadoDePublicacion::presentar($publicacion))

    <x-filament::callout
        :color="$vista['color']"
        :icon="$vista['icono']"
        :heading="$vista['titulo']"
        description="Los cambios que guardes ahora tampoco se verán hasta que se resuelva."
        class="mb-6"
    >
        <x-slot name="controls">
            <x-filament::button
                tag="a"
                :href="\Filament\Pages\Dashboard::getUrl()"
                :color="$vista['color']"
                size="sm"
            >
                Ver detalle
            </x-filament::button>
        </x-slot>
    </x-filament::callout>
@endif
