@extends('layouts.container')

@section('panel-body')
    <h2>{{ __('global.connections') }}</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @error('file')
        <div class="alert alert-danger">{{ $message }}</div>
    @enderror

    <h3 id="sources">Sources</h3>
    <p class="text-muted small">Where/how you generally meet people — platforms, games, events, places.</p>
    @php $connectionParam = $selected ? '&connection=' . $selected->id : ''; @endphp
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <form method="POST" action="/connections/sources" class="d-flex gap-2 mb-3">
                @csrf
                <input type="text" name="name" class="form-control form-control-sm @error('name') is-invalid @enderror"
                       placeholder="New source name" maxlength="255" required value="{{ old('name') }}">
                <button type="submit" class="btn btn-sm btn-primary">Add</button>
            </form>
            @error('name')
                <div class="text-danger small mb-2">{{ $message }}</div>
            @enderror
            @if($sources->isEmpty())
                <p class="text-muted small">No sources yet.</p>
            @else
            <div class="list-group" style="max-height:360px;overflow-y:auto">
                @foreach($sources as $source)
                <a href="/connections?source={{ $source->id }}{{ $connectionParam }}#sources"
                   class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ optional($selectedSource)->id === $source->id ? 'active' : '' }}">
                    @php $listColor = $categoryColors->get($source->category)?->color; @endphp
                    @if($listColor)
                        <span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:{{ $listColor }}"></span>
                    @endif
                    <span class="flex-grow-1 text-truncate">{{ $source->name }}</span>
                </a>
                @endforeach
            </div>
            @endif
        </div>

        <div class="col-md-8">
            @if(!$selectedSource)
                <p class="text-muted">Select a source from the list, or add a new one.</p>
            @else
                @php $categoryColorValue = $categoryColors->get($selectedSource->category)?->color ?? '#993366'; @endphp
                <form method="POST" action="/connections/sources/{{ $selectedSource->id }}" class="d-flex gap-2 flex-wrap align-items-end mb-2">
                    @csrf
                    @method('PUT')
                    <div>
                        <label class="form-label small mb-1">Name</label>
                        <input type="text" name="name" class="form-control form-control-sm" style="max-width:200px"
                               maxlength="255" required value="{{ $selectedSource->name }}">
                    </div>
                    <div>
                        <label class="form-label small mb-1">Category</label>
                        <input type="text" name="category" class="form-control form-control-sm" style="max-width:160px"
                               maxlength="100" value="{{ $selectedSource->category }}">
                    </div>
                    <div>
                        <label class="form-label small mb-1">Category color</label>
                        <div class="d-flex gap-2">
                            <input type="color" name="color" class="form-control form-control-color form-control-sm"
                                   value="{{ $categoryColorValue }}"
                                   oninput="this.nextElementSibling.value=this.value">
                            <input type="text" class="form-control form-control-sm" style="max-width:90px"
                                   pattern="#[0-9a-fA-F]{6}" maxlength="7" value="{{ $categoryColorValue }}"
                                   oninput="if(/^#[0-9a-fA-F]{6}$/.test(this.value)) this.previousElementSibling.value=this.value">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                </form>
                <form method="POST" action="/connections/sources/{{ $selectedSource->id }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('Remove this source? Connections referencing it will keep their other data.')">Delete</button>
                </form>
            @endif
        </div>
    </div>

    <h3 id="attributes">Custom attributes</h3>
    <p class="text-muted small">Define your own fields to track for each connection.</p>
    @if($attributeDefinitions->isEmpty())
        <p class="text-muted small mb-2">No custom attributes yet.</p>
    @else
    <div class="d-flex flex-column gap-2 mb-3" style="max-width:640px">
        @foreach($attributeDefinitions as $def)
        <div class="border rounded p-2">
            <form method="POST" action="/connections/attributes/{{ $def->id }}">
                @csrf
                @method('PUT')
                <div class="d-flex gap-2 flex-wrap align-items-start">
                    <input type="text" name="label" class="form-control form-control-sm" style="max-width:200px"
                           maxlength="255" required value="{{ $def->label }}">
                    <select name="type" class="form-select form-select-sm attribute-type-select" style="max-width:160px">
                        @foreach($attributeTypes as $type)
                            <option value="{{ $type }}" {{ $def->type === $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                    <div class="attribute-type-options" data-for="number">
                        <div class="d-flex gap-2">
                            <input type="number" name="min" class="form-control form-control-sm" style="width:90px" placeholder="Min" value="{{ $def->options['min'] ?? '' }}">
                            <input type="number" name="max" class="form-control form-control-sm" style="width:90px" placeholder="Max" value="{{ $def->options['max'] ?? '' }}">
                            <input type="number" name="step" class="form-control form-control-sm" style="width:90px" placeholder="Step" value="{{ $def->options['step'] ?? '' }}">
                        </div>
                    </div>
                    <div class="attribute-type-options" data-for="numeric_range">
                        <div class="d-flex gap-2">
                            <input type="number" name="min" class="form-control form-control-sm" style="width:90px" placeholder="Min" value="{{ $def->options['min'] ?? '' }}">
                            <input type="number" name="max" class="form-control form-control-sm" style="width:90px" placeholder="Max" value="{{ $def->options['max'] ?? '' }}">
                            <input type="number" name="step" class="form-control form-control-sm" style="width:90px" placeholder="Step" value="{{ $def->options['step'] ?? 1 }}">
                        </div>
                    </div>
                    <div class="attribute-type-options" data-for="enum,radio">
                        <input type="text" name="choices_raw" class="form-control form-control-sm" style="width:260px"
                               placeholder="Comma-separated options" value="{{ implode(', ', $def->options['choices'] ?? []) }}">
                    </div>
                    <button type="submit" class="btn btn-sm btn-outline-secondary">Save</button>
                </div>
            </form>
            <form method="POST" action="/connections/attributes/{{ $def->id }}" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-sm btn-outline-danger mt-1"
                        onclick="return confirm('Delete this attribute and all its values on every connection?')">Delete</button>
            </form>
        </div>
        @endforeach
    </div>
    @endif

    <h4 class="h6">Add attribute</h4>
    <form method="POST" action="/connections/attributes" class="mb-4">
        @csrf
        <div class="d-flex gap-2 flex-wrap align-items-start">
            <input type="text" name="label" class="form-control form-control-sm @error('label') is-invalid @enderror"
                   style="max-width:200px" placeholder="Label" maxlength="255" required value="{{ old('label') }}">
            <select name="type" class="form-select form-select-sm attribute-type-select" style="max-width:160px">
                @foreach($attributeTypes as $type)
                    <option value="{{ $type }}" {{ old('type') === $type ? 'selected' : '' }}>{{ $type }}</option>
                @endforeach
            </select>
            <div class="attribute-type-options" data-for="number">
                <div class="d-flex gap-2">
                    <input type="number" name="min" class="form-control form-control-sm" style="width:90px" placeholder="Min">
                    <input type="number" name="max" class="form-control form-control-sm" style="width:90px" placeholder="Max">
                    <input type="number" name="step" class="form-control form-control-sm" style="width:90px" placeholder="Step">
                </div>
            </div>
            <div class="attribute-type-options" data-for="numeric_range">
                <div class="d-flex gap-2">
                    <input type="number" name="min" class="form-control form-control-sm" style="width:90px" placeholder="Min" required>
                    <input type="number" name="max" class="form-control form-control-sm" style="width:90px" placeholder="Max" required>
                    <input type="number" name="step" class="form-control form-control-sm" style="width:90px" placeholder="Step" value="1">
                </div>
            </div>
            <div class="attribute-type-options" data-for="enum,radio">
                <input type="text" name="choices_raw" class="form-control form-control-sm" style="width:260px"
                       placeholder="Comma-separated options">
            </div>
            <button type="submit" class="btn btn-sm btn-outline-primary">Add attribute</button>
        </div>
        @error('type')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
        @error('label')
            <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
    </form>

    <h3 id="graph" class="mt-4">Connection graph</h3>
    <p class="text-muted small">
        "Introduced by" is a directed "met through" link, set per-connection below. Mutual links ("they know each
        other", no clear direction) are also managed per-connection below. Both show up in the graph on your
        <a href="/dashboard">dashboard</a>.
    </p>

    <form method="POST" action="/connections/import-connman" enctype="multipart/form-data" class="d-flex gap-2 align-items-center mb-4">
        @csrf
        <input type="file" name="file" class="form-control form-control-sm" accept=".json" style="max-width:220px" required>
        <button type="submit" class="btn btn-sm btn-outline-danger"
                onclick="return confirm('This replaces ALL of your existing connections and links with the contents of this ConnMan file. Custom attribute values and manually-added connections will be deleted. Continue?')">
            Import ConnMan file (replaces all connections)
        </button>
    </form>
    <p class="text-muted small mt-n3 mb-4">
        Bulk import from a <a href="https://github.com/WentTheFox/ConnMan" target="_blank">ConnMan</a> network export —
        a distinct file format from the JSON export/import below. Unlike that one, this <strong>replaces</strong> your
        entire connections list each time (meant to be re-run against fresh exports of the same network): "person"
        entries become connections (matched by name), "group" entries become sources (a person linked to a group
        gets that group as their "met via" source), one-way links set "introduced by", bi-directional links become
        mutual links.
    </p>

    <h3 id="highlight-links" class="mt-4">Calendar highlight links</h3>
    <p class="text-muted small">
        Connections show a badge below when linked to one of your calendar highlight tokens. Try to auto-link any
        unlinked connections by matching their name against your highlight tokens' words — ambiguous matches (more
        than one token) are left for you to pick manually.
    </p>
    <form method="POST" action="/connections/auto-link-highlights" class="mb-4">
        @csrf
        <button type="submit" class="btn btn-sm btn-outline-secondary">Auto-link highlight tokens</button>
    </form>

    <h3 id="connections-list" class="mt-4">Your connections</h3>
    <div class="row g-3">
        <div class="col-md-4">
            <form method="POST" action="/connections" class="d-flex gap-2 mb-3">
                @csrf
                <input type="text" name="name" class="form-control form-control-sm" placeholder="New connection name"
                       maxlength="1000" required value="{{ old('name') }}">
                <button type="submit" class="btn btn-sm btn-primary">Add</button>
            </form>
            @if($connectionList->isEmpty())
                <p class="text-muted small">No connections yet.</p>
            @else
            @php $sourceParam = $selectedSource ? '&source=' . $selectedSource->id : ''; @endphp
            <div class="list-group" style="max-height:520px;overflow-y:auto">
                @foreach($connectionList as $item)
                <a href="/connections?connection={{ $item->id }}{{ $sourceParam }}#connection-detail"
                   class="list-group-item list-group-item-action d-flex align-items-center gap-2 {{ optional($selected)->id === $item->id ? 'active' : '' }}">
                    @if($item->archived)<span class="fa fa-archive small" title="Archived"></span>@endif
                    <span class="flex-grow-1 text-truncate">{{ $item->name }}</span>
                    @if($item->highlight_token_id)<span class="fa fa-calendar-check small" title="Linked to calendar highlight"></span>@endif
                </a>
                @endforeach
            </div>
            @endif
        </div>

        <div class="col-md-8" id="connection-detail">
            @if(!$selected)
                <p class="text-muted">Select a connection from the list, or add a new one.</p>
            @else
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h4 class="mb-0">
                        {{ $selected->name }}
                        @if($selected->archived)<span class="badge bg-secondary fw-normal ms-2">Archived</span>@endif
                    </h4>
                    <div class="d-flex gap-2">
                        <form method="POST" action="/connections/{{ $selected->id }}/archive">
                            @csrf
                            <button type="submit" class="btn btn-sm {{ $selected->archived ? 'btn-warning' : 'btn-outline-secondary' }}">
                                {{ $selected->archived ? 'Unarchive' : 'Archive' }}
                            </button>
                        </form>
                        <form method="POST" action="/connections/{{ $selected->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger"
                                    onclick="return confirm('Delete this connection and all its data?')">Delete</button>
                        </form>
                    </div>
                </div>

                @php $valuesByDefinition = $selected->attributeValues->keyBy('attribute_definition_id'); @endphp
                @unless($selected->highlight_token_id)
                <form id="create-highlight-{{ $selected->id }}" method="POST" action="/connections/{{ $selected->id }}/create-highlight" class="d-none">
                    @csrf
                </form>
                @endunless
                <form method="POST" action="/connections/{{ $selected->id }}" class="mb-4">
                    @csrf
                    @method('PUT')
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <div>
                            <label class="form-label small mb-1">Name</label>
                            <input type="text" name="name" class="form-control form-control-sm" style="max-width:220px"
                                   maxlength="1000" required value="{{ $selected->name }}">
                        </div>
                        <div>
                            <label class="form-label small mb-1">Met via</label>
                            <select name="source_id" class="form-select form-select-sm" style="max-width:180px">
                                <option value="">—</option>
                                @foreach($sources as $source)
                                    <option value="{{ $source->id }}" {{ $selected->source_id === $source->id ? 'selected' : '' }}>{{ $source->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1">Introduced by</label>
                            <select name="introduced_by_connection_id" class="form-select form-select-sm" style="max-width:180px">
                                <option value="">—</option>
                                @foreach($connectionList->where('id', '!=', $selected->id) as $person)
                                    <option value="{{ $person->id }}" {{ $selected->introduced_by_connection_id === $person->id ? 'selected' : '' }}>{{ $person->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="form-label small mb-1">Met on</label>
                            <input type="date" name="met_at" class="form-control form-control-sm" style="max-width:160px"
                                   value="{{ $selected->met_at_date?->format('Y-m-d') }}">
                        </div>
                        <div>
                            <label class="form-label small mb-1">Calendar highlight</label>
                            <select name="highlight_token_id" class="form-select form-select-sm" style="max-width:200px">
                                <option value="">No linked calendar highlight</option>
                                @foreach($highlightTokens as $token)
                                    <option value="{{ $token->id }}" {{ $selected->highlight_token_id === $token->id ? 'selected' : '' }}>{{ $token->label ?? '(unlabelled)' }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    @unless($selected->highlight_token_id)
                    <p class="mb-2">
                        <button type="submit" form="create-highlight-{{ $selected->id }}" class="btn btn-sm btn-outline-secondary">Create calendar highlight for this connection</button>
                    </p>
                    @endunless

                    @if($attributeDefinitions->isNotEmpty())
                    <label class="form-label mb-1 small fw-semibold">Custom attributes</label>
                    <div class="d-flex flex-column gap-2 mb-3">
                        @foreach($attributeDefinitions as $def)
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-muted small" style="min-width:140px">{{ $def->label }}</span>
                            @include('partials.connection-attribute-input', [
                                'definition' => $def,
                                'currentValue' => $valuesByDefinition->get($def->id)?->typed_value,
                            ])
                        </div>
                        @endforeach
                    </div>
                    @endif

                    <button type="submit" class="btn btn-sm btn-primary">Save</button>
                </form>

                <div class="mb-3">
                    <label class="form-label mb-1 small fw-semibold">Mutual connections</label>
                    @if($mutualEdges->isEmpty())
                        <p class="text-muted small mb-1">No mutual links yet.</p>
                    @else
                    <ul class="list-group mb-2" style="max-width:360px">
                        @foreach($mutualEdges as $edge)
                        @php $other = $edge->connection_a_id === $selected->id ? $edge->connectionB : $edge->connectionA; @endphp
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $other->name }}</span>
                            <form method="POST" action="/connections/graph-edges/{{ $edge->id }}">
                                @csrf
                                @method('DELETE')
                                <input type="hidden" name="return_to" value="{{ $selected->id }}">
                                <button type="submit" class="btn-close" aria-label="Remove link"></button>
                            </form>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                    <form method="POST" action="/connections/graph-edges" class="d-flex gap-2 align-items-center">
                        @csrf
                        <input type="hidden" name="connection_a_id" value="{{ $selected->id }}">
                        <select name="connection_b_id" class="form-select form-select-sm" style="max-width:220px" required>
                            <option value="">Link to…</option>
                            @foreach($connectionList->where('id', '!=', $selected->id) as $person)
                                <option value="{{ $person->id }}">{{ $person->name }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn btn-sm btn-outline-primary">Link</button>
                    </form>
                    @error('connection_b_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </div>

                @if($selected->highlightToken)
                <div class="mb-1">
                    <label class="form-label mb-1 small fw-semibold">Recent/upcoming calendar matches</label>
                    <div class="connection-events" data-connection-id="{{ $selected->id }}">
                        <p class="text-muted small">Loading…</p>
                    </div>
                </div>
                @endif
            @endif
        </div>
    </div>

    <div class="d-flex gap-2 align-items-center flex-wrap mt-4">
        <a href="{{ $connectionList->isEmpty() ? '#' : '/connections/export' }}"
           class="btn btn-outline-secondary btn-sm {{ $connectionList->isEmpty() ? 'disabled' : '' }}"
           @if($connectionList->isEmpty()) aria-disabled="true" tabindex="-1" @endif>Export JSON</a>
        <form method="POST" action="/connections/import" enctype="multipart/form-data" class="d-flex gap-2 align-items-center">
            @csrf
            <input type="file" name="file" class="form-control form-control-sm" accept=".json" style="max-width:220px" required>
            <label class="form-check-label small d-flex align-items-center gap-1">
                <input type="checkbox" name="replace" value="1" class="form-check-input">
                Replace existing
            </label>
            <button type="submit" class="btn btn-sm btn-outline-primary">Import JSON</button>
        </form>
    </div>
    <p class="text-muted small mt-1">The exported file contains your connections' names, notes, and other personal details in plain text. Store it securely.</p>
@endsection
