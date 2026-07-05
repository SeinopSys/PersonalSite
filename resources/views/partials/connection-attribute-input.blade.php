{{-- Expects: $definition, $currentValue (mixed|null). Bare input(s), no <form> - part of the parent
     connection detail form, submitted as attributes[{{ $definition->id }}] alongside everything else. --}}
@switch($definition->type)
    @case('number')
        <input type="number" name="attributes[{{ $definition->id }}]" class="form-control form-control-sm"
               @if(isset($definition->options['min'])) min="{{ $definition->options['min'] }}" @endif
               @if(isset($definition->options['max'])) max="{{ $definition->options['max'] }}" @endif
               @if(isset($definition->options['step'])) step="{{ $definition->options['step'] }}" @endif
               value="{{ $currentValue }}">
        @break

    @case('numeric_range')
        <div class="d-flex align-items-center gap-2">
            <input type="range" name="attributes[{{ $definition->id }}]" class="form-range"
                   min="{{ $definition->options['min'] ?? 0 }}"
                   max="{{ $definition->options['max'] ?? 100 }}"
                   step="{{ $definition->options['step'] ?? 1 }}"
                   value="{{ $currentValue ?? $definition->options['min'] ?? 0 }}"
                   oninput="this.nextElementSibling.textContent = this.value">
            <output>{{ $currentValue ?? $definition->options['min'] ?? 0 }}</output>
        </div>
        @break

    @case('enum')
        <select name="attributes[{{ $definition->id }}]" class="form-select form-select-sm">
            <option value="">—</option>
            @foreach($definition->options['choices'] ?? [] as $choice)
                <option value="{{ $choice }}" {{ (string)$currentValue === (string)$choice ? 'selected' : '' }}>{{ $choice }}</option>
            @endforeach
        </select>
        @break

    @case('radio')
        <div class="d-flex flex-wrap gap-2">
            @foreach($definition->options['choices'] ?? [] as $choice)
                <div class="form-check form-check-inline m-0">
                    <input type="radio" class="form-check-input" name="attributes[{{ $definition->id }}]"
                           id="attr-{{ $definition->id }}-{{ $loop->index }}"
                           value="{{ $choice }}" {{ (string)$currentValue === (string)$choice ? 'checked' : '' }}>
                    <label class="form-check-label small" for="attr-{{ $definition->id }}-{{ $loop->index }}">{{ $choice }}</label>
                </div>
            @endforeach
        </div>
        @break

    @case('textarea')
        <textarea name="attributes[{{ $definition->id }}]" class="form-control form-control-sm" rows="2">{{ $currentValue }}</textarea>
        @break

    @case('boolean')
        {{-- Hidden fallback before the checkbox: when unchecked, only "0" is submitted for this name;
             when checked, the browser sends both and the checkbox's "1" (later in the DOM) wins. --}}
        <input type="hidden" name="attributes[{{ $definition->id }}]" value="0">
        <input type="checkbox" class="form-check-input" name="attributes[{{ $definition->id }}]" value="1"
               {{ $currentValue ? 'checked' : '' }}>
        @break

    @case('date')
        <input type="date" name="attributes[{{ $definition->id }}]" class="form-control form-control-sm" value="{{ $currentValue }}">
        @break

    @default
        <input type="text" name="attributes[{{ $definition->id }}]" class="form-control form-control-sm" value="{{ $currentValue }}">
@endswitch
