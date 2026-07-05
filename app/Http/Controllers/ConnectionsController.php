<?php

namespace App\Http\Controllers;

use App\Models\CalendarHighlightToken;
use App\Models\CalendarHighlightWord;
use App\Models\Connection;
use App\Models\ConnectionAttributeDefinition;
use App\Models\ConnectionAttributeValue;
use App\Models\ConnectionEdge;
use App\Models\ConnectionSource;
use App\Models\ConnectionSourceCategory;
use App\Services\AvailabilityService;
use App\Util\ConnectionAttributeValidator;
use App\Util\JSON;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConnectionsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $user = Auth::user();

        // Lightweight alphabetical picker list. `name` is encrypted, so it can't be ORDER BY'd in SQL -
        // sort in PHP after decryption instead. Only the selected connection below gets its relations
        // eager-loaded, so rendering the list itself stays cheap regardless of how many connections exist.
        $connectionList = $user->connections()->get(['id', 'name', 'archived', 'highlight_token_id'])
            ->sortBy(fn(Connection $c) => mb_strtolower($c->name))
            ->values();

        $selected = null;
        $mutualEdges = collect();
        $selectedId = $request->query('connection');

        if ($selectedId) {
            $selected = $user->connections()
                ->with(['source', 'introducedBy', 'attributeValues.definition', 'highlightToken'])
                ->find($selectedId);

            if ($selected) {
                $mutualEdges = ConnectionEdge::where('user_id', $user->id)
                    ->where(function ($q) use ($selectedId) {
                        $q->where('connection_a_id', $selectedId)->orWhere('connection_b_id', $selectedId);
                    })
                    ->with(['connectionA', 'connectionB'])
                    ->get();
            }
        }

        // Plaintext taxonomy - name can be ORDER BY'd in SQL directly, unlike connections' encrypted name.
        $sources = $user->connectionSources()->orderBy('name')->get();
        $selectedSource = $request->query('source') ? $sources->firstWhere('id', $request->query('source')) : null;

        $attributeDefinitions = $user->connectionAttributeDefinitions()->orderBy('sort_order')->orderBy('label')->get();
        $highlightTokens = $user->highlightTokens()->orderBy('label')->get();

        $categoryColors = ConnectionSourceCategory::where('user_id', $user->id)->get()->keyBy('name');

        return view('connections', [
            'title'                => __('global.connections'),
            'js'                   => ['connections'],
            'connectionList'       => $connectionList,
            'selected'             => $selected,
            'mutualEdges'          => $mutualEdges,
            'sources'              => $sources,
            'selectedSource'       => $selectedSource,
            'attributeDefinitions' => $attributeDefinitions,
            'attributeTypes'       => ConnectionAttributeDefinition::TYPES,
            'highlightTokens'      => $highlightTokens,
            'categoryColors'       => $categoryColors,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateConnection($request);

        $connection = Connection::create(array_merge($validated, [
            'user_id' => Auth::id(),
        ]));

        return redirect('/connections?connection=' . $connection->id . '#connection-detail')->with('success', 'Connection created.');
    }

    public function update(Request $request, string $id)
    {
        $connection = Connection::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $validated = $this->validateConnection($request);
        if (($validated['introduced_by_connection_id'] ?? null) === $id) {
            return back()->withErrors(['introduced_by_connection_id' => 'A connection cannot be introduced by itself.'])->withInput();
        }

        // Custom attribute values are submitted alongside the core fields as attributes[definition_id],
        // one consolidated form/request instead of a separate save action per attribute. Validate all of
        // them before persisting anything, so a single bad value doesn't leave a half-applied update.
        $definitions = ConnectionAttributeDefinition::where('user_id', Auth::id())->get()->keyBy('id');
        $attributesToSave = [];
        foreach ($request->input('attributes', []) as $definitionId => $rawValue) {
            $definition = $definitions->get($definitionId);
            if (!$definition) continue;

            if ($rawValue === null || $rawValue === '') {
                $attributesToSave[$definitionId] = null;
                continue;
            }

            [$typedValue, $error] = ConnectionAttributeValidator::validate($definition, $rawValue);
            if ($error) {
                return back()->withErrors(['attributes' => $error])->withInput();
            }
            $attributesToSave[$definitionId] = $typedValue;
        }

        DB::transaction(function () use ($connection, $validated, $attributesToSave) {
            $connection->fill($validated);
            $connection->save();

            foreach ($attributesToSave as $definitionId => $typedValue) {
                if ($typedValue === null) {
                    ConnectionAttributeValue::where('connection_id', $connection->id)
                        ->where('attribute_definition_id', $definitionId)
                        ->delete();
                } else {
                    ConnectionAttributeValue::updateOrCreate(
                        ['connection_id' => $connection->id, 'attribute_definition_id' => $definitionId],
                        ['user_id' => Auth::id(), 'value' => JSON::Encode($typedValue)]
                    );
                }
            }
        });

        return redirect('/connections?connection=' . $connection->id . '#connection-detail')->with('success', 'Connection updated.');
    }

    private function validateConnection(Request $request): array
    {
        $validated = $request->validate([
            'name'              => 'required|string|max:1000',
            'met_at'            => 'nullable|date',
            'source_id'         => [
                'nullable',
                Rule::exists('connection_sources', 'id')->where('user_id', Auth::id()),
            ],
            'introduced_by_connection_id' => [
                'nullable',
                Rule::exists('connections', 'id')->where('user_id', Auth::id()),
            ],
            'highlight_token_id' => [
                'nullable',
                Rule::exists('calendar_highlight_tokens', 'id')->where('user_id', Auth::id()),
            ],
        ]);

        if (!empty($validated['met_at'])) {
            $validated['met_at'] = Carbon::parse($validated['met_at'])->toDateString();
        }

        return $validated;
    }

    public function archive(string $id)
    {
        $connection = Connection::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $connection->archived = !$connection->archived;
        $connection->save();

        $msg = $connection->archived ? 'Connection archived.' : 'Connection unarchived.';
        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', $msg);
    }

    public function destroy(string $id)
    {
        Connection::where('id', $id)->where('user_id', Auth::id())->firstOrFail()->delete();

        return redirect('/connections')->with('success', 'Connection deleted.');
    }

    /** Quick-create a new calendar highlight token (labeled/worded after this connection) and link it. */
    public function createHighlightForConnection(string $id)
    {
        $connection = Connection::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        if ($connection->highlight_token_id) {
            return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Already linked to a calendar highlight.');
        }

        $userId = Auth::id();
        $label = $connection->name;

        $token = CalendarHighlightToken::create([
            'user_id' => $userId,
            'token'   => CalendarHighlightToken::generateToken(),
            'label'   => $label,
        ]);

        if (!CalendarHighlightWord::where('user_id', $userId)->where('word', $label)->exists()) {
            CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $userId, 'word' => $label]);
        }

        $connection->highlight_token_id = $token->id;
        $connection->save();

        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Calendar highlight created and linked.');
    }

    /** Recent/upcoming calendar events matched against the connection's linked highlight token's words. */
    public function events(string $id): JsonResponse
    {
        $connection = Connection::where('id', $id)->where('user_id', Auth::id())->with('highlightToken.words')->firstOrFail();
        $user = Auth::user();

        if (!$connection->highlightToken || !$user->calendar_url) {
            return response()->json(['events' => []]);
        }

        $words = $connection->highlightToken->words->pluck('word')->toArray();

        try {
            $service = new AvailabilityService();
            $tz = $user->timezone ?? 'UTC';
            $rangeStart = Carbon::now($tz)->subDays(30)->startOfDay();
            $rangeEnd = Carbon::now($tz)->addDays(30)->endOfDay();

            $ics = $service->fetchIcs($user->calendar_url);
            $allEvents = $service->parseIcsEvents($ics, $rangeStart, $rangeEnd, $tz);
            $matched = $service->matchEventsByWords($allEvents, $words);
            usort($matched, fn($a, $b) => $a['start'] <=> $b['start']);

            return response()->json(['events' => array_map(fn($e) => [
                'name'  => $e['name'],
                'start' => $e['start']->format('Y-m-d H:i'),
                'end'   => $e['end']->format('Y-m-d H:i'),
            ], $matched)]);
        } catch (\Exception) {
            return response()->json(['error' => 'fetch_failed'], 503);
        }
    }

    /**
     * Anonymized graph data for the dashboard visualization: no names, just node ids (colored by their
     * source category, if any) and edges. "introduced" edges point from the introducer to the person
     * they introduced; "mutual" edges (from connection_edges) carry no direction.
     */
    public function graph(): JsonResponse
    {
        $userId = Auth::id();

        $categoryColors = ConnectionSourceCategory::where('user_id', $userId)->pluck('color', 'name');

        $nodes = Connection::where('user_id', $userId)->with('source')->orderBy('id')->get(['id', 'source_id'])
            ->map(function (Connection $c) use ($categoryColors) {
                $category = $c->source?->category;
                return [
                    'id'    => $c->id,
                    'color' => $category ? ($categoryColors[$category] ?? null) : null,
                ];
            })
            ->values();

        $introducedEdges = Connection::where('user_id', $userId)
            ->whereNotNull('introduced_by_connection_id')
            ->orderBy('id')
            ->get(['id', 'introduced_by_connection_id'])
            ->map(fn(Connection $c) => ['from' => $c->introduced_by_connection_id, 'to' => $c->id, 'kind' => 'introduced'])
            ->values();

        $mutualEdges = ConnectionEdge::where('user_id', $userId)
            ->orderBy('id')
            ->get(['connection_a_id', 'connection_b_id'])
            ->map(fn(ConnectionEdge $e) => ['from' => $e->connection_a_id, 'to' => $e->connection_b_id, 'kind' => 'mutual'])
            ->values();

        return response()->json([
            // Deterministic per user, so the client-side layout simulation produces the same graph on
            // every reload instead of a fresh random scatter each time.
            'seed'  => crc32($userId),
            'nodes' => $nodes,
            'edges' => $introducedEdges->concat($mutualEdges)->values(),
        ]);
    }

    public function storeGraphEdge(Request $request)
    {
        $validated = $request->validate([
            'connection_a_id' => [
                'required',
                Rule::exists('connections', 'id')->where('user_id', Auth::id()),
            ],
            'connection_b_id' => [
                'required',
                Rule::exists('connections', 'id')->where('user_id', Auth::id()),
            ],
        ]);

        if ($validated['connection_a_id'] === $validated['connection_b_id']) {
            return back()->withErrors(['connection_b_id' => 'A connection cannot be linked to itself.'])->withInput();
        }

        $exists = ConnectionEdge::where('user_id', Auth::id())
            ->where(function ($q) use ($validated) {
                // Passing an array to orWhere() ORs the individual keys together instead of ANDing them
                // like where(array) does - nested closures are required to get (a=X AND b=Y) OR (a=Y AND b=X).
                $q->where(fn($q2) => $q2->where('connection_a_id', $validated['connection_a_id'])->where('connection_b_id', $validated['connection_b_id']))
                    ->orWhere(fn($q2) => $q2->where('connection_a_id', $validated['connection_b_id'])->where('connection_b_id', $validated['connection_a_id']));
            })
            ->exists();
        if ($exists) {
            return back()->withErrors(['connection_b_id' => 'These two connections are already linked.'])->withInput();
        }

        ConnectionEdge::create(array_merge($validated, ['user_id' => Auth::id()]));

        return redirect('/connections?connection=' . $validated['connection_a_id'] . '#connection-detail')->with('success', 'Connections linked.');
    }

    public function destroyGraphEdge(Request $request, string $id)
    {
        ConnectionEdge::where('id', $id)->where('user_id', Auth::id())->firstOrFail()->delete();

        $returnTo = $request->input('return_to');
        return redirect($returnTo ? '/connections?connection=' . $returnTo : '/connections')->with('success', 'Link removed.');
    }

    public function autoLinkHighlightTokens()
    {
        [$linked, $ambiguous] = $this->autoLinkHighlightTokensForUser(Auth::id());

        return redirect('/connections')->with('success',
            "Auto-link complete: $linked connection(s) linked; $ambiguous had more than one matching token and were left for manual linking."
        );
    }

    /**
     * Best-effort auto-link: for every connection with no highlight token yet, check whether exactly one
     * of the user's highlight tokens has a word matching the connection's name (case-insensitive, either
     * containing the other). Ambiguous matches (more than one token) are left alone for manual linking.
     * Connections that already have a highlight token are never touched or reconsidered here.
     *
     * @param  bool  $renameToLabel  When a match is found, also rename the connection to the token's
     *                               label (if it has one) - used by the ConnMan import, where the
     *                               token's label is often the person's "real" preferred name, whereas
     *                               ConnMan's own name field may just be a handle/nickname. The manual
     *                               "Auto-link" button leaves names untouched (default false).
     * @return array{0: int, 1: int} [linked count, ambiguous count]
     */
    private function autoLinkHighlightTokensForUser(string $userId, bool $renameToLabel = false): array
    {
        $tokens = CalendarHighlightToken::where('user_id', $userId)->with('words')->get();

        $linked = 0;
        $ambiguous = 0;

        Connection::where('user_id', $userId)->whereNull('highlight_token_id')->get()->each(function (Connection $connection) use ($tokens, $renameToLabel, &$linked, &$ambiguous) {
            $name = mb_strtolower($connection->name);
            $matches = $tokens->filter(function (CalendarHighlightToken $token) use ($name) {
                return $token->words->contains(function ($word) use ($name) {
                    $w = mb_strtolower($word->word);
                    return $w !== '' && (str_contains($name, $w) || str_contains($w, $name));
                });
            });

            if ($matches->count() === 1) {
                $token = $matches->first();
                $connection->highlight_token_id = $token->id;
                if ($renameToLabel && !empty($token->label)) {
                    $connection->name = $token->label;
                }
                $connection->save();
                $linked++;
            } elseif ($matches->count() > 1) {
                $ambiguous++;
            }
        });

        return [$linked, $ambiguous];
    }

    public function storeSource(Request $request)
    {
        $validated = $request->validate([
            'name'     => [
                'required', 'string', 'max:255',
                Rule::unique('connection_sources')->where('user_id', Auth::id()),
            ],
            'category' => 'nullable|string|max:100',
            'color'    => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $source = ConnectionSource::create([
            'user_id'  => Auth::id(),
            'name'     => $validated['name'],
            'category' => $validated['category'] ?? null,
        ]);
        $this->saveCategoryColor($validated);

        return redirect('/connections?source=' . $source->id . '#sources')->with('success', 'Source added.');
    }

    public function updateSource(Request $request, string $id)
    {
        $source = ConnectionSource::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'name'     => [
                'required', 'string', 'max:255',
                Rule::unique('connection_sources')->where('user_id', Auth::id())->ignore($source->id),
            ],
            'category' => 'nullable|string|max:100',
            'color'    => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
        ]);

        $source->name = $validated['name'];
        $source->category = $validated['category'] ?? null;
        $source->save();
        $this->saveCategoryColor($validated);

        return redirect('/connections?source=' . $source->id . '#sources')->with('success', 'Source updated.');
    }

    /** Upserts the color for a source's category, set directly from that source's own edit/add form. */
    private function saveCategoryColor(array $validated): void
    {
        if (empty($validated['category']) || empty($validated['color'])) {
            return;
        }

        ConnectionSourceCategory::updateOrCreate(
            ['user_id' => Auth::id(), 'name' => $validated['category']],
            ['color' => $validated['color']]
        );
    }

    public function destroySource(string $id)
    {
        ConnectionSource::where('id', $id)->where('user_id', Auth::id())->firstOrFail()->delete();

        return redirect('/connections#sources')->with('success', 'Source removed.');
    }

    public function storeAttributeDefinition(Request $request)
    {
        $validated = $request->validate([
            'label'      => [
                'required', 'string', 'max:255',
                Rule::unique('connection_attribute_definitions')->where('user_id', Auth::id()),
            ],
            'type'       => ['required', 'string', Rule::in(ConnectionAttributeDefinition::TYPES)],
            'min'         => 'nullable|numeric',
            'max'         => 'nullable|numeric',
            'step'        => 'nullable|numeric',
            'choices_raw' => 'nullable|string|max:2000',
            'sort_order'  => 'nullable|integer',
        ]);

        [$options, $error] = $this->buildAttributeOptions($validated);
        if ($error) {
            return back()->withErrors(['type' => $error])->withInput();
        }

        ConnectionAttributeDefinition::create([
            'user_id'    => Auth::id(),
            'label'      => $validated['label'],
            'type'       => $validated['type'],
            'options'    => $options,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return redirect('/connections#attributes')->with('success', 'Attribute created.');
    }

    public function updateAttributeDefinition(Request $request, string $id)
    {
        $definition = ConnectionAttributeDefinition::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'label'      => [
                'required', 'string', 'max:255',
                Rule::unique('connection_attribute_definitions')->where('user_id', Auth::id())->ignore($definition->id),
            ],
            'type'       => ['required', 'string', Rule::in(ConnectionAttributeDefinition::TYPES)],
            'min'         => 'nullable|numeric',
            'max'         => 'nullable|numeric',
            'step'        => 'nullable|numeric',
            'choices_raw' => 'nullable|string|max:2000',
            'sort_order'  => 'nullable|integer',
        ]);

        [$options, $error] = $this->buildAttributeOptions($validated);
        if ($error) {
            return back()->withErrors(['type' => $error])->withInput();
        }

        $definition->label = $validated['label'];
        $definition->type = $validated['type'];
        $definition->options = $options;
        $definition->sort_order = $validated['sort_order'] ?? 0;
        $definition->save();

        return redirect('/connections#attributes')->with('success', 'Attribute updated.');
    }

    private function buildAttributeOptions(array $validated): array
    {
        switch ($validated['type']) {
            case 'number':
                return [array_filter([
                    'min'  => $validated['min'] ?? null,
                    'max'  => $validated['max'] ?? null,
                    'step' => $validated['step'] ?? null,
                ], fn($v) => $v !== null), null];

            case 'numeric_range':
                if (!isset($validated['min']) || !isset($validated['max'])) {
                    return [null, 'A numeric range attribute requires both a minimum and a maximum.'];
                }
                return [[
                    'min'  => $validated['min'],
                    'max'  => $validated['max'],
                    'step' => $validated['step'] ?? 1,
                ], null];

            case 'enum':
            case 'radio':
                $choices = array_values(array_filter(
                    array_map('trim', explode(',', $validated['choices_raw'] ?? '')),
                    fn($c) => $c !== ''
                ));
                if (count($choices) < 2) {
                    return [null, 'Provide at least two comma-separated options to choose from.'];
                }
                return [['choices' => $choices], null];

            case 'text':
            case 'textarea':
            case 'boolean':
                return [null, null];

            default:
                return [null, 'Unknown attribute type.'];
        }
    }

    public function destroyAttributeDefinition(string $id)
    {
        ConnectionAttributeDefinition::where('id', $id)->where('user_id', Auth::id())->firstOrFail()->delete();

        return redirect('/connections#attributes')->with('success', 'Attribute removed.');
    }


    public function exportConnections()
    {
        $user = Auth::user();

        $connections = $user->connections()
            ->with(['source', 'introducedBy', 'attributeValues.definition', 'highlightToken'])
            ->get();

        $data = [
            'sources'                => $user->connectionSources()->get()->map(fn($s) => [
                'name'     => $s->name,
                'category' => $s->category,
            ])->values()->toArray(),
            'attribute_definitions'  => $user->connectionAttributeDefinitions()->orderBy('sort_order')->get()->map(fn($d) => [
                'label'      => $d->label,
                'type'       => $d->type,
                'options'    => $d->options,
                'sort_order' => $d->sort_order,
            ])->values()->toArray(),
            'connections' => $connections->map(fn(Connection $c) => [
                'name'              => $c->name,
                'met_at'            => $c->met_at,
                'source'            => $c->source?->name,
                'introduced_by_name' => $c->introducedBy?->name,
                'archived'          => $c->archived,
                'created_at'        => $c->created_at->toIso8601String(),
                'attribute_values'  => $c->attributeValues->map(fn($v) => [
                    'attribute_label' => $v->definition->label,
                    'value'           => $v->typed_value,
                ])->values()->toArray(),
                'highlight_token_label' => $c->highlightToken?->label,
            ])->values()->toArray(),
        ];

        return response()->streamDownload(
            function () use ($data) {
                echo JSON::Encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            },
            'connections.json',
            ['Content-Type' => 'application/json']
        );
    }

    public function importConnections(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:json,txt|max:10240']);
        $replace = (bool)$request->input('replace');

        $contents = file_get_contents($request->file('file')->getRealPath());
        $data = JSON::Decode($contents);

        if (!is_array($data) || !isset($data['connections']) || !is_array($data['connections'])) {
            return back()->withErrors(['file' => 'Invalid JSON: expected an object with a "connections" array.']);
        }

        $userId = Auth::id();
        $imported = 0;
        $skipped = 0;

        DB::transaction(function () use ($data, $userId, $replace, &$imported, &$skipped) {
            if ($replace) {
                Connection::where('user_id', $userId)->delete();
            }

            // Upsert sources by name
            $sourcesByName = [];
            foreach (ConnectionSource::where('user_id', $userId)->get() as $s) {
                $sourcesByName[$s->name] = $s;
            }
            foreach ($data['sources'] ?? [] as $item) {
                if (!is_array($item) || empty($item['name']) || !is_string($item['name'])) continue;
                if (isset($sourcesByName[$item['name']])) continue;
                $source = ConnectionSource::create([
                    'user_id'  => $userId,
                    'name'     => substr($item['name'], 0, 255),
                    'category' => isset($item['category']) && is_string($item['category']) ? substr($item['category'], 0, 100) : null,
                ]);
                $sourcesByName[$source->name] = $source;
            }

            // Upsert attribute definitions by label
            $definitionsByLabel = [];
            foreach (ConnectionAttributeDefinition::where('user_id', $userId)->get() as $d) {
                $definitionsByLabel[$d->label] = $d;
            }
            foreach ($data['attribute_definitions'] ?? [] as $item) {
                if (!is_array($item) || empty($item['label']) || !is_string($item['label'])) continue;
                if (isset($definitionsByLabel[$item['label']])) continue;
                if (!isset($item['type']) || !in_array($item['type'], ConnectionAttributeDefinition::TYPES, true)) {
                    $skipped++;
                    continue;
                }
                $definition = ConnectionAttributeDefinition::create([
                    'user_id'    => $userId,
                    'label'      => substr($item['label'], 0, 255),
                    'type'       => $item['type'],
                    'options'    => is_array($item['options'] ?? null) ? $item['options'] : null,
                    'sort_order' => is_int($item['sort_order'] ?? null) ? $item['sort_order'] : 0,
                ]);
                $definitionsByLabel[$definition->label] = $definition;
            }

            $tokensByLabel = CalendarHighlightToken::where('user_id', $userId)->whereNotNull('label')->get()->keyBy('label');

            // Pass 1: create all connections (without introduced_by links, since a referenced
            // connection may not exist yet if it appears later in the file).
            $connectionsByName = [];
            foreach (Connection::where('user_id', $userId)->get() as $c) {
                $connectionsByName[$c->name] = $c;
            }
            $pendingIntroducedBy = [];

            foreach ($data['connections'] as $item) {
                if (!is_array($item) || empty($item['name']) || !is_string($item['name'])) {
                    $skipped++;
                    continue;
                }

                $sourceId = null;
                if (!empty($item['source']) && is_string($item['source']) && isset($sourcesByName[$item['source']])) {
                    $sourceId = $sourcesByName[$item['source']]->id;
                }

                $createdAt = null;
                if (!empty($item['created_at']) && is_string($item['created_at'])) {
                    try { $createdAt = Carbon::parse($item['created_at']); } catch (\Exception) {}
                }

                $highlightTokenId = null;
                if (!empty($item['highlight_token_label']) && is_string($item['highlight_token_label']) && isset($tokensByLabel[$item['highlight_token_label']])) {
                    $highlightTokenId = $tokensByLabel[$item['highlight_token_label']]->id;
                }

                $connection = Connection::create([
                    'user_id'           => $userId,
                    'name'              => $item['name'],
                    'met_at'            => is_string($item['met_at'] ?? null) ? $item['met_at'] : null,
                    'source_id'         => $sourceId,
                    'highlight_token_id' => $highlightTokenId,
                    'archived'          => !empty($item['archived']),
                    'created_at'        => $createdAt ?? now(),
                ]);
                $connectionsByName[$connection->name] = $connection;

                if (!empty($item['introduced_by_name']) && is_string($item['introduced_by_name'])) {
                    $pendingIntroducedBy[$connection->id] = $item['introduced_by_name'];
                }

                foreach ($item['attribute_values'] ?? [] as $v) {
                    if (!is_array($v) || empty($v['attribute_label']) || !isset($definitionsByLabel[$v['attribute_label']])) {
                        $skipped++;
                        continue;
                    }
                    $definition = $definitionsByLabel[$v['attribute_label']];
                    [$typedValue, $error] = ConnectionAttributeValidator::validate($definition, $v['value'] ?? '');
                    if ($error) {
                        $skipped++;
                        continue;
                    }
                    ConnectionAttributeValue::create([
                        'connection_id'            => $connection->id,
                        'attribute_definition_id'  => $definition->id,
                        'user_id'                  => $userId,
                        'value'                    => JSON::Encode($typedValue),
                    ]);
                }

                $imported++;
            }

            // Pass 2: now that every connection in the file exists, resolve introduced_by_name
            // references (which may point at a connection that appeared later in the file).
            foreach ($pendingIntroducedBy as $connectionId => $introducedByName) {
                if (!isset($connectionsByName[$introducedByName])) {
                    $skipped++;
                    continue;
                }
                $introducerId = $connectionsByName[$introducedByName]->id;
                if ($introducerId === $connectionId) {
                    $skipped++;
                    continue;
                }
                Connection::where('id', $connectionId)->update(['introduced_by_connection_id' => $introducerId]);
            }
        });

        return redirect('/connections')->with('success', "Import complete: $imported connections imported, $skipped items skipped.");
    }

    /**
     * Import a ConnMan (github.com/WentTheFox/ConnMan) network export: {"people": [...], "connections": [...]}.
     * This is additive to the main JSON import/export above (a distinct file format/button), but it fully
     * replaces the connections list itself: it's meant to be re-run against fresh exports of the same
     * ConnMan network, so existing connections and edges (not sources/attributes) are wiped first.
     *   - people with type "person" become Connection rows (matched/created by name, like the main import).
     *   - people with type "group" become ConnectionSource rows (category "group").
     *   - a "one-way" connection is a "met through" relationship: it sets introduced_by_connection_id on
     *     the `from` connection to the `to` connection (only if not already set).
     *   - a "bi-directional" connection means "they know each other" with no clear direction: it becomes a
     *     connection_edges row instead.
     *   - a connection touching a group (either direction) sets the person's source_id to that group's
     *     ConnectionSource, rather than being imported as an introduction/mutual link.
     *   - a connection between two groups has no equivalent here and is skipped.
     */
    public function importConnman(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:json,txt|max:10240']);

        $contents = file_get_contents($request->file('file')->getRealPath());
        $data = JSON::Decode($contents);

        if (!is_array($data) || !isset($data['people']) || !is_array($data['people']) || !isset($data['connections']) || !is_array($data['connections'])) {
            return back()->withErrors(['file' => 'Invalid JSON: expected a ConnMan export with "people" and "connections" arrays.']);
        }

        $userId = Auth::id();
        $importedPeople = 0;
        $importedGroups = 0;
        $importedIntroductions = 0;
        $importedMutual = 0;
        $importedSourceLinks = 0;
        $skippedEdges = 0;

        DB::transaction(function () use (
            $data, $userId, &$importedPeople, &$importedGroups, &$importedIntroductions,
            &$importedMutual, &$importedSourceLinks, &$skippedEdges
        ) {
            // ConnMan import replaces the connections list wholesale: it's meant to be re-run against
            // fresh exports of the same network, so stale connections/edges from a previous ConnMan
            // import (or manual entries) are cleared first rather than merged. Deleting connections
            // cascades to their attribute values, common events, and connection_edges rows.
            Connection::where('user_id', $userId)->delete();

            $connectionsByName = [];
            $sourcesByName = [];
            foreach (ConnectionSource::where('user_id', $userId)->get() as $s) {
                $sourcesByName[$s->name] = $s;
            }

            // person id (from the ConnMan file) => ['kind' => 'connection'|'source', 'model' => Connection|ConnectionSource]
            $byConnmanId = [];

            foreach ($data['people'] as $person) {
                if (!is_array($person) || empty($person['id']) || empty($person['name'])) {
                    continue;
                }

                if (($person['type'] ?? null) === 'person') {
                    if (isset($connectionsByName[$person['name']])) {
                        $connection = $connectionsByName[$person['name']];
                    } else {
                        $connection = Connection::create(['user_id' => $userId, 'name' => $person['name']]);
                        $connectionsByName[$connection->name] = $connection;
                        $importedPeople++;
                    }
                    $byConnmanId[$person['id']] = ['kind' => 'connection', 'model' => $connection];
                } else {
                    if (isset($sourcesByName[$person['name']])) {
                        $source = $sourcesByName[$person['name']];
                    } else {
                        $source = ConnectionSource::create(['user_id' => $userId, 'name' => $person['name'], 'category' => 'group']);
                        $sourcesByName[$source->name] = $source;
                        $importedGroups++;
                    }
                    $byConnmanId[$person['id']] = ['kind' => 'source', 'model' => $source];
                }
            }

            foreach ($data['connections'] as $edge) {
                if (!is_array($edge) || empty($edge['from']) || empty($edge['to'])) {
                    $skippedEdges++;
                    continue;
                }

                $from = $byConnmanId[$edge['from']] ?? null;
                $to = $byConnmanId[$edge['to']] ?? null;
                if (!$from || !$to || $from['model']->id === $to['model']->id) {
                    $skippedEdges++;
                    continue;
                }

                // A group on either end: link the person to that group as their "met via" source.
                if ($from['kind'] === 'source' || $to['kind'] === 'source') {
                    if ($from['kind'] === $to['kind']) {
                        // Both groups - nothing to attach the source to.
                        $skippedEdges++;
                        continue;
                    }
                    /** @var Connection $person */
                    $person = $from['kind'] === 'connection' ? $from['model'] : $to['model'];
                    /** @var ConnectionSource $source */
                    $source = $from['kind'] === 'source' ? $from['model'] : $to['model'];
                    if ($person->source_id) {
                        $skippedEdges++;
                        continue;
                    }
                    $person->source_id = $source->id;
                    $person->save();
                    $importedSourceLinks++;
                    continue;
                }

                $fromConn = $from['model'];
                $toConn = $to['model'];

                if (($edge['type'] ?? null) === 'one-way') {
                    if ($fromConn->introduced_by_connection_id) {
                        $skippedEdges++;
                        continue;
                    }
                    $fromConn->introduced_by_connection_id = $toConn->id;
                    $fromConn->save();
                    $importedIntroductions++;
                    continue;
                }

                $exists = ConnectionEdge::where('user_id', $userId)
                    ->where(function ($q) use ($fromConn, $toConn) {
                        // Passing an array to orWhere() ORs the individual keys together instead of ANDing
                        // them like where(array) does - nested closures are required to get
                        // (a=X AND b=Y) OR (a=Y AND b=X) instead of the much broader (a=X AND b=Y) OR a=Y OR b=X.
                        $q->where(fn($q2) => $q2->where('connection_a_id', $fromConn->id)->where('connection_b_id', $toConn->id))
                            ->orWhere(fn($q2) => $q2->where('connection_a_id', $toConn->id)->where('connection_b_id', $fromConn->id));
                    })
                    ->exists();
                if ($exists) {
                    $skippedEdges++;
                    continue;
                }

                ConnectionEdge::create(['user_id' => $userId, 'connection_a_id' => $fromConn->id, 'connection_b_id' => $toConn->id]);
                $importedMutual++;
            }
        });

        [$autoLinked, $autoLinkAmbiguous] = $this->autoLinkHighlightTokensForUser($userId, renameToLabel: true);

        return redirect('/connections#graph')->with('success',
            "ConnMan import complete: $importedPeople people, $importedGroups sources, $importedIntroductions introductions, "
            . "$importedMutual mutual links, $importedSourceLinks source links imported; $skippedEdges edges skipped. "
            . "Auto-linked $autoLinked connection(s) to highlight tokens ($autoLinkAmbiguous ambiguous)."
        );
    }
}
