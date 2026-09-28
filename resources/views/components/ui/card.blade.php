{{--
    Card / panel with optional header (title, subtitle, icon, actions slot) and footer slot.
    Pass :padded="false" when the body is a table or list that should run edge to edge.
--}}
@props([
    'title' => null,
    'subtitle' => null,
    'icon' => null,
    'padded' => true,
    'bodyClass' => '',
])

<section {{ $attributes->class('boq-card') }}>
    @if($title || isset($actions) || isset($header))
        <div class="boq-card-header">
            @isset($header)
                {{ $header }}
            @else
                <div class="min-w-0">
                    <h2 class="boq-card-title">
                        @if($icon)
                            <i class="fas {{ $icon }}" aria-hidden="true"></i>
                        @endif
                        {{ $title }}
                    </h2>

                    @if($subtitle)
                        <p class="boq-card-subtitle">{{ $subtitle }}</p>
                    @endif
                </div>
            @endisset

            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">
                    {{ $actions }}
                </div>
            @endisset
        </div>
    @endif

    @if($padded)
        <div class="boq-card-body {{ $bodyClass }}">
            {{ $slot }}
        </div>
    @else
        {{ $slot }}
    @endif

    @isset($footer)
        <div class="boq-card-footer">
            {{ $footer }}
        </div>
    @endisset
</section>
