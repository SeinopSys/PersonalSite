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
                @php $categoryColorValue = $categoryColors->get($selectedSource->category)?->color ?? \App\Models\ConnectionSourceCategory::DEFAULT_COLOR; @endphp
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

                <h5 class="h6 mt-3">Connections met via this source</h5>
                @if($sourceEdges->isEmpty())
                    <p class="text-muted small mb-1">No connections linked to this source yet.</p>
                @else
                <ul class="list-group mb-2" style="max-width:480px">
                    @foreach($sourceEdges as $edge)
                    <li class="list-group-item d-flex align-items-center gap-2">
                        <span class="flex-grow-1 text-truncate">{{ $edge->fromConnection->name }}</span>
                        <form method="PUT" action="/connections/{{ $edge->from_connection_id }}/edges/{{ $edge->id }}" class="d-flex gap-1 align-items-center">
                            @csrf
                            @method('PUT')
                            <select name="target_id" class="form-select form-select-sm" style="max-width:160px">
                                <option hidden value="" selected>Move to…</option>
                                @foreach($sources->where('id', '!=', $selectedSource->id) as $otherSource)
                                    <option value="{{ $otherSource->id }}">{{ $otherSource->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Move</button>
                        </form>
                        <form method="POST" action="/connections/{{ $edge->from_connection_id }}/edges/{{ $edge->id }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger">Unlink</button>
                        </form>
                    </li>
                    @endforeach
                </ul>
                @error('target_id')
                    <div class="text-danger small mt-1">{{ $message }}</div>
                @enderror
                @endif
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
        "Introduced/met through" links are directional; "know each other" links have no particular direction —
        both are managed per-connection below and show up in the graph on your <a href="/dashboard">dashboard</a>.
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
        gets a "met via" edge to that source), one-way links become "introduced/met through" edges, bi-directional
        links become "know each other" edges.
    </p>

    <h3 id="highlight-links" class="mt-4">Calendar highlight links</h3>
    <p class="text-muted small">
        Connections show a badge below when linked to one of your calendar highlight tokens. Auto-linking matches
        any unlinked connection's name against your highlight tokens' words; if a connection matches more than one
        token it's left for you to pick manually, but if it matches none at all, a brand new token (named after the
        connection) is created and linked automatically.
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
                <form method="POST" action="/connections/{{ $selected->id }}" class="mb-4">
                    @csrf
                    @method('PUT')
                    <div class="d-flex flex-wrap gap-2 mb-2">
                        <div>
                            <label class="form-label small mb-1">Name</label>
                            <input type="text" name="name" class="form-control form-control-sm" style="max-width:220px"
                                   maxlength="1000" required value="{{ $selected->name }}">
                        </div>
                    </div>

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

                <fieldset class="border rounded p-3 mb-3">
                    <legend class="fs-6 fw-semibold w-auto px-2 mb-0">Calendar highlight</legend>
                    @if(!$selected->highlightToken)
                        <form method="POST" action="/connections/{{ $selected->id }}/highlight-token/link" class="d-flex gap-2 align-items-center mb-2">
                            @csrf
                            <select name="highlight_token_id" class="form-select form-select-sm" style="max-width:220px" required>
                                <option value="">Select an existing token…</option>
                                @foreach($highlightTokens as $token)
                                    <option value="{{ $token->id }}">{{ $token->label ?? '(unlabelled)' }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Link</button>
                        </form>
                        <form method="POST" action="/connections/{{ $selected->id }}/create-highlight">
                            @csrf
                            <button type="submit" class="btn btn-sm btn-outline-secondary">Create calendar highlight for this connection</button>
                        </form>
                    @else
                        @php $ht = $selected->highlightToken; @endphp
                        <p class="text-muted small">
                            @if($ht->archived)<span class="fa fa-archive me-1"></span>@endif
                            Label is kept in sync with this connection's name — no separate rename needed here.
                        </p>

                        <div class="mb-2">
                            <label class="form-label mb-1 small fw-semibold">Token</label>
                            <div class="d-flex align-items-center gap-2">
                                <code class="text-break small">{{ $ht->token_base64 }}</code>
                                <button type="button" class="btn btn-sm btn-outline-secondary copy-token-btn" data-token="{{ $ht->token_base64 }}">Copy</button>
                                <form method="POST" action="/connections/{{ $selected->id }}/highlight-token/regenerate">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-warning"
                                            onclick="return confirm('Regenerate this token? Anyone using the old token will lose access.')">Regenerate</button>
                                </form>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label mb-1 small fw-semibold">Words</label>
                            @if($ht->words->isEmpty())
                                <p class="text-muted small mb-1">No words yet.</p>
                            @else
                            <div class="d-flex flex-wrap gap-2 mb-2">
                                @foreach($ht->words as $word)
                                <span class="badge bg-secondary d-flex align-items-center gap-1">
                                    {{ $word->word }}
                                    <form method="POST" action="/connections/{{ $selected->id }}/highlight-token/words/{{ $word->id }}" class="d-inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="btn-close btn-close-white" style="font-size:0.6rem" aria-label="Remove word"></button>
                                    </form>
                                </span>
                                @endforeach
                            </div>
                            @endif
                            <form method="POST" action="/connections/{{ $selected->id }}/highlight-token/words" class="d-flex gap-2">
                                @csrf
                                <input type="text" name="word" class="form-control form-control-sm @error('word') is-invalid @enderror"
                                       style="max-width:200px" placeholder="Add word…" maxlength="255" required value="{{ old('word') }}">
                                <button type="submit" class="btn btn-sm btn-outline-primary">Add</button>
                            </form>
                            @error('word')
                                <div class="text-danger small mt-1">{{ $message }}</div>
                            @enderror
                        </div>

                        <div class="d-flex gap-2">
                            <form method="POST" action="/connections/{{ $selected->id }}/highlight-token/toggle-archive">
                                @csrf
                                <button type="submit" class="btn btn-sm {{ $ht->archived ? 'btn-warning' : 'btn-outline-secondary' }}">
                                    {{ $ht->archived ? 'Unarchive' : 'Archive' }}
                                </button>
                            </form>
                            <form method="POST" action="/connections/{{ $selected->id }}/highlight-token/unlink">
                                @csrf
                                <button type="submit" class="btn btn-sm btn-outline-secondary">Unlink</button>
                            </form>
                            <form method="POST" action="/connections/{{ $selected->id }}/highlight-token">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-outline-danger"
                                        onclick="return confirm('Delete this highlight token entirely? Anyone using it will lose access.')">Delete token</button>
                            </form>
                        </div>
                    @endif
                </fieldset>

                <fieldset class="border rounded p-3 mb-3">
                    <legend class="fs-6 fw-semibold w-auto px-2 mb-0">Connections</legend>
                    <p class="text-muted small">
                        How you met this person (a source, or introduced/met through someone) and who they know.
                        There's no limit on how many of either kind you can add.
                    </p>

                    @php
                        $oneWayEdges = $selected->edgesFrom->where('type', \App\Models\ConnectionEdge::TYPE_ONE_WAY);
                    @endphp
                    @if($oneWayEdges->isEmpty() && $mutualEdges->isEmpty())
                        <p class="text-muted small mb-2">None recorded.</p>
                    @else
                    <ul class="list-group mb-3" style="max-width:420px">
                        @foreach($oneWayEdges as $edge)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>{{ $edge->toSource ? 'Met via ' . $edge->toSource->name : 'Introduced/met through ' . $edge->toConnection->name }}</span>
                            <form method="POST" action="/connections/{{ $selected->id }}/edges/{{ $edge->id }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-close" aria-label="Remove link"></button>
                            </form>
                        </li>
                        @endforeach
                        @foreach($mutualEdges as $edge)
                        @php $other = $edge->from_connection_id === $selected->id ? $edge->toConnection : $edge->fromConnection; @endphp
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span>Know each other: {{ $other->name }}</span>
                            <form method="POST" action="/connections/{{ $selected->id }}/edges/{{ $edge->id }}">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn-close" aria-label="Remove link"></button>
                            </form>
                        </li>
                        @endforeach
                    </ul>
                    @endif

                    <div class="mb-3">
                        <label class="form-label mb-1 small fw-semibold">Add "met via" a source</label>
                        <form method="POST" action="/connections/{{ $selected->id }}/edges" class="d-flex gap-2 align-items-center">
                            @csrf
                            <input type="hidden" name="target_type" value="source">
                            <input type="hidden" name="type" value="{{ \App\Models\ConnectionEdge::TYPE_ONE_WAY }}">
                            <select name="target_id" class="form-select form-select-sm" style="max-width:220px" required>
                                <option value="">Add source…</option>
                                @foreach($sources as $source)
                                    <option value="{{ $source->id }}">{{ $source->name }}</option>
                                @endforeach
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Add</button>
                        </form>
                    </div>

                    <div>
                        <label class="form-label mb-1 small fw-semibold">Add a connection link</label>
                        <form method="POST" action="/connections/{{ $selected->id }}/edges" class="d-flex gap-2 align-items-center">
                            @csrf
                            <input type="hidden" name="target_type" value="connection">
                            <select name="target_id" class="form-select form-select-sm" style="max-width:200px" required>
                                <option value="">Add connection…</option>
                                @foreach($connectionList->where('id', '!=', $selected->id) as $person)
                                    <option value="{{ $person->id }}">{{ $person->name }}</option>
                                @endforeach
                            </select>
                            <select name="type" class="form-select form-select-sm" style="max-width:200px" required>
                                <option value="{{ \App\Models\ConnectionEdge::TYPE_ONE_WAY }}">Introduced/met through</option>
                                <option value="{{ \App\Models\ConnectionEdge::TYPE_BI_DIRECTIONAL }}">Know each other</option>
                            </select>
                            <button type="submit" class="btn btn-sm btn-outline-primary">Add</button>
                        </form>
                    </div>
                    @error('target_id')
                        <div class="text-danger small mt-1">{{ $message }}</div>
                    @enderror
                </fieldset>

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
