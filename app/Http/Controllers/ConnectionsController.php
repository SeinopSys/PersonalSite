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
use App\Util\UploadUtil;
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
                ->with(['attributeValues.definition', 'highlightToken.words', 'edgesFrom.toConnection', 'edgesFrom.toSource'])
                ->find($selectedId);

            if ($selected) {
                // A "know each other" edge has no particular direction, so it may have been stored with
                // this connection on either side - it isn't necessarily in edgesFrom.
                $mutualEdges = ConnectionEdge::where('user_id', $user->id)
                    ->where('type', ConnectionEdge::TYPE_BI_DIRECTIONAL)
                    ->where(function ($q) use ($selectedId) {
                        $q->where('from_connection_id', $selectedId)->orWhere('to_connection_id', $selectedId);
                    })
                    ->with(['fromConnection', 'toConnection'])
                    ->get();
            }
        }

        // Plaintext taxonomy - name can be ORDER BY'd in SQL directly, unlike connections' encrypted name.
        $sources = $user->connectionSources()->orderBy('name')->get();
        $selectedSource = $request->query('source') ? $sources->firstWhere('id', $request->query('source')) : null;

        // Connections "met via" the selected source - a source is always the "to" side of a one_way edge,
        // so the connection is always on from_connection_id here (a source can never be the "from" side).
        $sourceEdges = collect();
        if ($selectedSource) {
            $sourceEdges = ConnectionEdge::where('user_id', $user->id)
                ->where('to_source_id', $selectedSource->id)
                ->with('fromConnection')
                ->get()
                ->sortBy(fn(ConnectionEdge $e) => mb_strtolower($e->fromConnection->name))
                ->values();
        }

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
            'sourceEdges'          => $sourceEdges,
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

            // The linked highlight token (if any) has no separate "label" input of its own here - it's
            // always kept in sync with the connection's name whenever the connection is saved.
            if ($connection->highlight_token_id) {
                CalendarHighlightToken::where('id', $connection->highlight_token_id)
                    ->where('user_id', Auth::id())
                    ->update(['label' => $connection->name]);
            }

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
        return $request->validate([
            'name' => 'required|string|max:1000',
        ]);
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
            'user_id'  => $userId,
            'token'    => CalendarHighlightToken::generateToken(),
            'label'    => $label,
            'archived' => true,
        ]);

        if (!CalendarHighlightWord::where('user_id', $userId)->where('word', $label)->exists()) {
            CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $userId, 'word' => $label]);
        }

        $connection->highlight_token_id = $token->id;
        $connection->save();

        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Calendar highlight created and linked.');
    }

    public function linkExistingHighlightToken(Request $request, string $id)
    {
        $connection = Connection::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'highlight_token_id' => [
                'required',
                Rule::exists('calendar_highlight_tokens', 'id')->where('user_id', Auth::id()),
            ],
        ]);

        $connection->highlight_token_id = $validated['highlight_token_id'];
        $connection->save();

        // No separate "label" input for the token - it's always kept in sync with the connection's name.
        CalendarHighlightToken::where('id', $validated['highlight_token_id'])
            ->where('user_id', Auth::id())
            ->update(['label' => $connection->name]);

        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Linked to existing highlight token.');
    }

    public function unlinkHighlightToken(string $id)
    {
        $connection = Connection::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $connection->highlight_token_id = null;
        $connection->save();

        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Highlight token unlinked.');
    }

    /** @return array{0: Connection, 1: CalendarHighlightToken} */
    private function connectionWithLinkedHighlightToken(string $id): array
    {
        $connection = Connection::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        $token = $connection->highlight_token_id
            ? CalendarHighlightToken::where('id', $connection->highlight_token_id)->where('user_id', Auth::id())->first()
            : null;
        abort_if(!$token, 404);

        return [$connection, $token];
    }

    public function regenerateConnectionHighlightToken(string $id)
    {
        [, $token] = $this->connectionWithLinkedHighlightToken($id);
        $token->token = CalendarHighlightToken::generateToken();
        $token->save();

        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Token regenerated.');
    }

    public function toggleConnectionHighlightArchived(string $id)
    {
        [, $token] = $this->connectionWithLinkedHighlightToken($id);
        $token->archived = !$token->archived;
        $token->save();

        $msg = $token->archived ? 'Token archived.' : 'Token unarchived.';
        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', $msg);
    }

    public function destroyConnectionHighlightToken(string $id)
    {
        [, $token] = $this->connectionWithLinkedHighlightToken($id);
        $token->delete();

        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Highlight token deleted.');
    }

    public function storeConnectionHighlightWord(Request $request, string $id)
    {
        [$connection, $token] = $this->connectionWithLinkedHighlightToken($id);

        $validated = $request->validate(['word' => 'required|string|max:255']);
        $word = $validated['word'];
        $userId = Auth::id();

        if (CalendarHighlightWord::where('user_id', $userId)->where('word', $word)->exists()) {
            return back()->withErrors(['word' => "\"$word\" is already used in one of your highlight groups."])->withInput();
        }

        CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $userId, 'word' => $word]);

        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Word added.');
    }

    public function destroyConnectionHighlightWord(string $id, string $wordId)
    {
        [, $token] = $this->connectionWithLinkedHighlightToken($id);

        CalendarHighlightWord::where('id', $wordId)->where('token_id', $token->id)->firstOrFail()->delete();

        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Word removed.');
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
     * Anonymized graph data for the dashboard visualization: no names, just node ids and edges.
     * Connections are plain "person" nodes. Each source a connection was "met via" is its own hub node
     * (colored/iconed from its category, if any) with an edge from every connection that lists it - so a
     * source with many connections renders as a single node with many edges into it, not a dot repeated
     * per connection. One-way edges point from the introduced person to whoever/whatever they were
     * introduced through; bi-directional edges ("know each other") carry no direction.
     */
    public function graph(): JsonResponse
    {
        $userId = Auth::id();

        $categoryColors = ConnectionSourceCategory::where('user_id', $userId)->pluck('color', 'name');

        $connectionNodes = Connection::where('user_id', $userId)->orderBy('id')->get(['id'])
            ->map(fn(Connection $c) => ['id' => $c->id, 'type' => 'connection', 'color' => null, 'icon' => null]);

        $connectionEdges = ConnectionEdge::where('user_id', $userId)->whereNotNull('to_connection_id')
            ->orderBy('id')
            ->get(['from_connection_id', 'to_connection_id', 'type'])
            ->map(fn(ConnectionEdge $e) => [
                'from' => $e->from_connection_id,
                'to'   => $e->to_connection_id,
                'kind' => $e->type === ConnectionEdge::TYPE_BI_DIRECTIONAL ? 'mutual' : 'introduced',
            ]);

        $sourceEdgeRows = ConnectionEdge::where('user_id', $userId)->whereNotNull('to_source_id')
            ->with('toSource')->orderBy('id')->get();

        $sourceNodesById = [];
        $sourceEdges = collect();
        foreach ($sourceEdgeRows as $edge) {
            $source = $edge->toSource;
            if (!$source) continue;

            if (!isset($sourceNodesById[$source->id])) {
                $category = $source->category;
                $sourceNodesById[$source->id] = [
                    'id'    => $source->id,
                    'type'  => 'source',
                    'color' => $category ? ($categoryColors[$category] ?? null) : null,
                    'icon'  => $source->icon_url,
                ];
            }

            $sourceEdges->push([
                'from' => $edge->from_connection_id,
                'to'   => $source->id,
                'kind' => 'introduced',
            ]);
        }

        return response()->json([
            // Deterministic per user, so the client-side layout simulation produces the same graph on
            // every reload instead of a fresh random scatter each time.
            'seed'  => crc32($userId),
            'nodes' => $connectionNodes->concat(array_values($sourceNodesById))->values(),
            'edges' => $connectionEdges->concat($sourceEdges)->values(),
        ]);
    }

    /**
     * Add a relationship edge from a connection to either another connection or a source. There's no
     * limit on how many of these a connection can have and no "primary" one - every relationship
     * (met via, introduced through, know each other) is just a row here, ConnMan-style.
     */
    public function storeEdge(Request $request, string $id)
    {
        $connection = Connection::where('id', $id)->where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'target_type' => ['required', Rule::in(['connection', 'source'])],
            'target_id'   => ['required', 'string'],
            'type'        => ['required', Rule::in(ConnectionEdge::TYPES)],
        ]);

        $attrs = ['user_id' => Auth::id(), 'from_connection_id' => $connection->id, 'type' => $validated['type']];

        if ($validated['target_type'] === 'connection') {
            if ($validated['target_id'] === $id) {
                return back()->withErrors(['target_id' => 'A connection cannot be linked to itself.'])->withInput();
            }
            $target = Connection::where('id', $validated['target_id'])->where('user_id', Auth::id())->first();
            if (!$target) {
                return back()->withErrors(['target_id' => 'Connection not found.'])->withInput();
            }
            $attrs['to_connection_id'] = $target->id;
        } else {
            $target = ConnectionSource::where('id', $validated['target_id'])->where('user_id', Auth::id())->first();
            if (!$target) {
                return back()->withErrors(['target_id' => 'Source not found.'])->withInput();
            }
            $attrs['to_source_id'] = $target->id;
        }

        $duplicateQuery = ConnectionEdge::where('user_id', Auth::id())->where('type', $validated['type']);
        if ($validated['type'] === ConnectionEdge::TYPE_BI_DIRECTIONAL && $validated['target_type'] === 'connection') {
            // "Know each other" is symmetric - it doesn't matter which side is stored as from/to.
            $duplicateQuery->where(function ($q) use ($attrs) {
                $q->where(fn($q2) => $q2->where('from_connection_id', $attrs['from_connection_id'])->where('to_connection_id', $attrs['to_connection_id']))
                    ->orWhere(fn($q2) => $q2->where('from_connection_id', $attrs['to_connection_id'])->where('to_connection_id', $attrs['from_connection_id']));
            });
        } else {
            $duplicateQuery->where('from_connection_id', $attrs['from_connection_id']);
            if (isset($attrs['to_connection_id'])) $duplicateQuery->where('to_connection_id', $attrs['to_connection_id']);
            if (isset($attrs['to_source_id'])) $duplicateQuery->where('to_source_id', $attrs['to_source_id']);
        }
        if ($duplicateQuery->exists()) {
            return back()->withErrors(['target_id' => 'This connection already exists.'])->withInput();
        }

        ConnectionEdge::create($attrs);

        return redirect('/connections?connection=' . $id . '#connection-detail')->with('success', 'Connection added.');
    }

    public function destroyEdge(string $id, string $edgeId)
    {
        ConnectionEdge::where('id', $edgeId)->where('user_id', Auth::id())
            ->where(function ($q) use ($id) {
                $q->where('from_connection_id', $id)->orWhere('to_connection_id', $id);
            })
            ->firstOrFail()->delete();

        // Used both from a connection's own detail page and from a source's "linked connections" list -
        // back() returns to whichever of those the request came from instead of assuming one.
        return back()->with('success', 'Connection removed.');
    }

    /**
     * Re-point a "met via" edge at a different source - used from a source's "linked connections" list to
     * move a connection to another source without having to unlink and re-add it.
     */
    public function updateEdge(Request $request, string $id, string $edgeId)
    {
        $edge = ConnectionEdge::where('id', $edgeId)->where('user_id', Auth::id())
            ->where('from_connection_id', $id)
            ->whereNotNull('to_source_id')
            ->firstOrFail();

        $validated = $request->validate([
            'target_id' => [
                'required',
                Rule::exists('connection_sources', 'id')->where('user_id', Auth::id()),
            ],
        ]);

        $duplicate = ConnectionEdge::where('user_id', Auth::id())
            ->where('id', '!=', $edge->id)
            ->where('from_connection_id', $id)
            ->where('to_source_id', $validated['target_id'])
            ->exists();
        if ($duplicate) {
            return back()->withErrors(['target_id' => 'This connection already exists.'])->withInput();
        }

        $edge->update(['to_source_id' => $validated['target_id']]);

        return back()->with('success', 'Connection updated.');
    }

    public function autoLinkHighlightTokens()
    {
        [$linked, $created, $ambiguous] = $this->autoLinkHighlightTokensForUser(Auth::id(), createIfUnmatched: true);

        return redirect('/connections')->with('success',
            "Auto-link complete: $linked connection(s) linked to an existing token, $created new token(s) created; "
            . "$ambiguous had more than one matching token and were left for manual linking."
        );
    }

    /**
     * Best-effort auto-link: for every connection with no highlight token yet, check whether exactly one
     * of the user's highlight tokens has a word matching the connection's name (case-insensitive, either
     * containing the other). Ambiguous matches (more than one token) are left alone for manual linking.
     * Connections that already have a highlight token are never touched or reconsidered here.
     *
     * @param  bool  $renameToLabel     When a match is found, also rename the connection to the token's
     *                                  label (if it has one) - used by the ConnMan import, where the
     *                                  token's label is often the person's "real" preferred name, whereas
     *                                  ConnMan's own name field may just be a handle/nickname. The manual
     *                                  "Auto-link" button leaves names untouched (default false).
     * @param  bool  $createIfUnmatched When a connection has no matching token at all (not even an
     *                                  ambiguous one), create a brand new token/word pair for it (named
     *                                  after the connection) and link that instead of leaving it unlinked.
     *                                  Only the manual "Auto-link" button opts into this - ConnMan import
     *                                  leaves genuinely unmatched connections alone, since importing e.g.
     *                                  150 people would otherwise spam 150 new tokens as a side effect.
     * @return array{0: int, 1: int, 2: int} [linked count, created count, ambiguous count]
     */
    private function autoLinkHighlightTokensForUser(string $userId, bool $renameToLabel = false, bool $createIfUnmatched = false): array
    {
        $tokens = CalendarHighlightToken::where('user_id', $userId)->with('words')->get();

        $linked = 0;
        $created = 0;
        $ambiguous = 0;

        Connection::where('user_id', $userId)->whereNull('highlight_token_id')->get()->each(function (Connection $connection) use ($tokens, $renameToLabel, $createIfUnmatched, $userId, &$linked, &$created, &$ambiguous) {
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
            } elseif ($createIfUnmatched) {
                $label = $connection->name;
                $token = CalendarHighlightToken::create([
                    'user_id'  => $userId,
                    'token'    => CalendarHighlightToken::generateToken(),
                    'label'    => $label,
                    'archived' => true,
                ]);
                if (!CalendarHighlightWord::where('user_id', $userId)->where('word', $label)->exists()) {
                    CalendarHighlightWord::create(['token_id' => $token->id, 'user_id' => $userId, 'word' => $label]);
                }
                $connection->highlight_token_id = $token->id;
                $connection->save();
                $tokens->push($token->load('words'));
                $created++;
            }
        });

        return [$linked, $created, $ambiguous];
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
            'name'        => [
                'required', 'string', 'max:255',
                Rule::unique('connection_sources')->where('user_id', Auth::id())->ignore($source->id),
            ],
            'category'    => 'nullable|string|max:100',
            'color'       => 'nullable|regex:/^#[0-9a-fA-F]{6}$/',
            'icon'        => 'nullable|image|max:2048',
            'remove_icon' => 'nullable|boolean',
        ]);

        $source->name = $validated['name'];
        $source->category = $validated['category'] ?? null;

        if (isset($validated['icon'])) {
            UploadUtil::deleteConnectionSourceIcon($source->icon_path);
            $source->icon_path = UploadUtil::saveConnectionSourceIcon($validated['icon']);
        } elseif (!empty($validated['remove_icon'])) {
            UploadUtil::deleteConnectionSourceIcon($source->icon_path);
            $source->icon_path = null;
        }

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
        $source = ConnectionSource::where('id', $id)->where('user_id', Auth::id())->firstOrFail();
        UploadUtil::deleteConnectionSourceIcon($source->icon_path);
        $source->delete();

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
            case 'date':
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
            ->with(['attributeValues.definition', 'highlightToken', 'edgesFrom.toConnection', 'edgesFrom.toSource'])
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
                'name'             => $c->name,
                'archived'         => $c->archived,
                'created_at'       => $c->created_at->toIso8601String(),
                'attribute_values' => $c->attributeValues->map(fn($v) => [
                    'attribute_label' => $v->definition->label,
                    'value'           => $v->typed_value,
                ])->values()->toArray(),
                'highlight_token_label' => $c->highlightToken?->label,
                'edges' => $c->edgesFrom->map(fn(ConnectionEdge $e) => [
                    'type'        => $e->type,
                    'target_kind' => $e->to_source_id ? 'source' : 'connection',
                    'target_name' => $e->to_source_id ? $e->toSource->name : $e->toConnection->name,
                ])->values()->toArray(),
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

            // Pass 1: create all connections (without edges, since a referenced connection may not
            // exist yet if it appears later in the file).
            $connectionsByName = [];
            foreach (Connection::where('user_id', $userId)->get() as $c) {
                $connectionsByName[$c->name] = $c;
            }
            $pendingEdges = [];

            foreach ($data['connections'] as $item) {
                if (!is_array($item) || empty($item['name']) || !is_string($item['name'])) {
                    $skipped++;
                    continue;
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
                    'user_id'            => $userId,
                    'name'               => $item['name'],
                    'highlight_token_id' => $highlightTokenId,
                    'archived'           => !empty($item['archived']),
                    'created_at'         => $createdAt ?? now(),
                ]);
                $connectionsByName[$connection->name] = $connection;

                if (!empty($item['edges']) && is_array($item['edges'])) {
                    $pendingEdges[$connection->id] = $item['edges'];
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
                        'connection_id'           => $connection->id,
                        'attribute_definition_id' => $definition->id,
                        'user_id'                 => $userId,
                        'value'                   => JSON::Encode($typedValue),
                    ]);
                }

                $imported++;
            }

            // Pass 2: now that every connection in the file exists, resolve edge target names (which may
            // point at a connection that appeared later in the file).
            foreach ($pendingEdges as $connectionId => $edges) {
                foreach ($edges as $edge) {
                    if (!is_array($edge) || empty($edge['target_name']) || !is_string($edge['target_name'])
                        || !in_array($edge['type'] ?? null, ConnectionEdge::TYPES, true)) {
                        $skipped++;
                        continue;
                    }

                    $attrs = ['user_id' => $userId, 'from_connection_id' => $connectionId, 'type' => $edge['type']];
                    if (($edge['target_kind'] ?? 'connection') === 'source') {
                        if (!isset($sourcesByName[$edge['target_name']])) {
                            $skipped++;
                            continue;
                        }
                        $attrs['to_source_id'] = $sourcesByName[$edge['target_name']]->id;
                    } else {
                        if (!isset($connectionsByName[$edge['target_name']]) || $connectionsByName[$edge['target_name']]->id === $connectionId) {
                            $skipped++;
                            continue;
                        }
                        $attrs['to_connection_id'] = $connectionsByName[$edge['target_name']]->id;
                    }
                    ConnectionEdge::create($attrs);
                }
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
     *   - a connection between two people becomes a connection_edges row: "one-way" maps to a one_way edge
     *     (met/introduced through, directional), "bi-directional" maps to a bi_directional edge (know each
     *     other, no particular direction). There's no limit on how many of either a person can have.
     *   - a connection touching a group (either direction) becomes a one_way edge from the person to that
     *     group's ConnectionSource - a source doesn't "know" the person back, so it's always one-way
     *     regardless of the edge's own type in the file.
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
        $importedEdges = 0;
        $skippedEdges = 0;

        DB::transaction(function () use (
            $data, $userId, &$importedPeople, &$importedGroups, &$importedEdges, &$skippedEdges
        ) {
            // ConnMan import replaces the connections list wholesale: it's meant to be re-run against
            // fresh exports of the same network, so stale connections/edges from a previous ConnMan
            // import (or manual entries) are cleared first rather than merged. Deleting connections
            // cascades to their attribute values and connection_edges rows.
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

            // Groups default to the "group" source category - give it a visible default color (unless
            // the user already picked one) so group-linked nodes aren't indistinguishable from ungrouped
            // ones in the dashboard graph.
            if (ConnectionSource::where('user_id', $userId)->where('category', 'group')->exists()) {
                ConnectionSourceCategory::firstOrCreate(['user_id' => $userId, 'name' => 'group'], ['color' => ConnectionSourceCategory::DEFAULT_COLOR]);
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

                // A group on either end: the person "met via" that group.
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

                    ConnectionEdge::create([
                        'user_id'            => $userId,
                        'from_connection_id' => $person->id,
                        'to_source_id'       => $source->id,
                        'type'               => ConnectionEdge::TYPE_ONE_WAY,
                    ]);
                    $importedEdges++;
                    continue;
                }

                $fromConn = $from['model'];
                $toConn = $to['model'];
                $type = ($edge['type'] ?? null) === 'one-way' ? ConnectionEdge::TYPE_ONE_WAY : ConnectionEdge::TYPE_BI_DIRECTIONAL;

                $exists = ConnectionEdge::where('user_id', $userId)
                    ->where('type', $type)
                    ->where(function ($q) use ($fromConn, $toConn, $type) {
                        if ($type === ConnectionEdge::TYPE_BI_DIRECTIONAL) {
                            // Passing an array to orWhere() ORs the individual keys together instead of
                            // ANDing them like where(array) does - nested closures are required to get
                            // (from=X AND to=Y) OR (from=Y AND to=X).
                            $q->where(fn($q2) => $q2->where('from_connection_id', $fromConn->id)->where('to_connection_id', $toConn->id))
                                ->orWhere(fn($q2) => $q2->where('from_connection_id', $toConn->id)->where('to_connection_id', $fromConn->id));
                        } else {
                            $q->where('from_connection_id', $fromConn->id)->where('to_connection_id', $toConn->id);
                        }
                    })
                    ->exists();
                if ($exists) {
                    $skippedEdges++;
                    continue;
                }

                ConnectionEdge::create([
                    'user_id'            => $userId,
                    'from_connection_id' => $fromConn->id,
                    'to_connection_id'   => $toConn->id,
                    'type'               => $type,
                ]);
                $importedEdges++;
            }
        });

        [$autoLinked, , $autoLinkAmbiguous] = $this->autoLinkHighlightTokensForUser($userId, renameToLabel: true);

        return redirect('/connections#graph')->with('success',
            "ConnMan import complete: $importedPeople people, $importedGroups sources, $importedEdges edges imported; "
            . "$skippedEdges edges skipped. Auto-linked $autoLinked connection(s) to highlight tokens ($autoLinkAmbiguous ambiguous)."
        );
    }
}
