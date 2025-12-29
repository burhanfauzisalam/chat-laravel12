<?php

namespace App\Http\Controllers;

use App\Models\Message;
use Illuminate\Http\Client\Response as HttpClientResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class DataAssistantController extends Controller
{
    public function chat(Request $request)
    {
        $user = $request->user();
        $currentUsername = $user?->username ?? null;

        $data = $request->validate([
            'topic' => ['required', 'string'],
        ]);

        $topic = $data['topic'];

        $apiKey = config('services.gemini.api_key');
        $model = config('services.gemini.model', 'gemini-2.5-flash');

        if (in_array($model, ['gemini-1.5-flash', 'gemini-1.5-flash-latest'], true)) {
            $model = 'gemini-2.5-flash';
        }

        if (!$apiKey) {
            return response()->json([
                'message' => 'Gemini API key is not configured.',
            ], 500);
        }

        $latestMessageQuery = Message::where('topic', $topic);

        if ($currentUsername) {
            $latestMessageQuery->where('sender', $currentUsername);
        }

        $latestMessage = $latestMessageQuery
            ->orderByDesc('id')
            ->first();

        if (!$latestMessage) {
            return response()->json([
                'message' => 'No messages found for this topic.',
            ], 422);
        }

        $nlQuery = trim((string) ($latestMessage->text ?? ''));

        if ($nlQuery === '') {
            return response()->json([
                'message' => 'Natural language query is empty.',
            ], 422);
        }

        $schema = $this->buildDatabaseSchema();
        $trainingInstructions = $this->getTrainingInstructions();

        $sqlQuery = $this->generateSqlQuery($apiKey, $model, $nlQuery, $schema, $trainingInstructions);

        if (!$sqlQuery) {
            return response()->json([
                'message' => 'Failed to generate SQL query.',
            ], 500);
        }

        if (!$this->isSafeSelectQuery($sqlQuery)) {
            return response()->json([
                'message' => 'Generated SQL query is not allowed for safety reasons. Only SELECT/WITH queries are permitted.',
                'details' => [
                    'sql_query' => $sqlQuery,
                ],
            ], 400);
        }

        $results = $this->executeSqlQuery($sqlQuery);

        if ($results === null) {
            return response()->json([
                'message' => 'Failed to execute SQL query.',
                'details' => [
                    'sql_query' => $sqlQuery,
                ],
            ], 500);
        }

        $text = $this->formatResultMessage($nlQuery, $sqlQuery, $results);

        $sender = $currentUsername ? 'DataBot@' . $currentUsername : 'DataBot';

        $message = Message::create([
            'sender' => $sender,
            'text' => $text,
            'topic' => $topic,
        ]);

        $avatarUrl = asset('assets/img/avatars/4.png');

        return response()->json([
            'id' => $message->id,
            'sender' => $message->sender,
            'text' => $message->text,
            'topic' => $message->topic,
            'created_at' => $message->created_at,
            'attachment_url' => null,
            'attachment_name' => null,
            'attachment_type' => null,
            'attachment_size' => null,
            'avatar_url' => $avatarUrl,
        ]);
    }

    protected function buildDatabaseSchema(): array
    {
        try {
            $database = DB::getDatabaseName();

            if (!$database) {
                return [];
            }

            $columns = DB::table('information_schema.columns')
                ->select('table_name', 'column_name', 'data_type')
                ->where('table_schema', $database)
                ->orderBy('table_name')
                ->orderBy('ordinal_position')
                ->get();

            $foreignKeys = DB::table('information_schema.key_column_usage')
                ->select('table_name', 'column_name', 'referenced_table_name', 'referenced_column_name')
                ->where('table_schema', $database)
                ->whereNotNull('referenced_table_name')
                ->get();

            $tables = [];

            foreach ($columns as $column) {
                $tableName = (string) $column->table_name;

                if (!isset($tables[$tableName])) {
                    $tables[$tableName] = [
                        'columns' => [],
                    ];
                }

                $tables[$tableName]['columns'][] = [
                    'name' => (string) $column->column_name,
                    'type' => (string) $column->data_type,
                ];
            }

            $relations = [];

            foreach ($foreignKeys as $fk) {
                $relations[] = [
                    'table' => (string) $fk->table_name,
                    'column' => (string) $fk->column_name,
                    'references_table' => (string) $fk->referenced_table_name,
                    'references_column' => (string) $fk->referenced_column_name,
                ];
            }

            return [
                'database' => $database,
                'tables' => $tables,
                'relations' => $relations,
            ];
        } catch (\Throwable $e) {
            Log::warning('Failed to build database schema for DataAssistant', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    protected function getTrainingInstructions(): ?string
    {
        $trainingTopic = config('services.dataassistant.training_topic');

        if (!$trainingTopic) {
            return null;
        }

        try {
            $messages = Message::where('topic', $trainingTopic)
                ->orderByDesc('id')
                ->take(50)
                ->get(['text'])
                ->reverse()
                ->values();

            if ($messages->isEmpty()) {
                return null;
            }

            $lines = [];

            foreach ($messages as $message) {
                $text = trim((string) ($message->text ?? ''));

                if ($text === '') {
                    continue;
                }

                $lines[] = '- ' . $text;
            }

            if (empty($lines)) {
                return null;
            }

            return implode("\n", $lines);
        } catch (\Throwable $e) {
            Log::warning('DataAssistant: failed to load training instructions', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function generateSqlQuery(string $apiKey, string $model, string $nlQuery, array $schema, ?string $trainingInstructions = null): ?string
    {
        $schemaJson = json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        $prompt = "Anda adalah asisten AI yang mengubah pertanyaan dalam bahasa Indonesia menjadi kueri SQL yang valid.\n"
            . "Berikut adalah skema database (dalam JSON):\n"
            . $schemaJson . "\n\n"
            . "Instruksi:\n"
            . "- Semua data percakapan dan history ada di table messages.\n"
            . "- room sama dengan topic.\n"
            . "- Gunakan sintaks SQL MySQL standar.\n"
            . "- Hanya hasilkan SATU kueri SQL dalam bentuk teks biasa.\n"
            . "- Jangan berikan penjelasan tambahan.\n"
            . "- Jangan bungkus dalam markdown atau blok kode.\n";

        if ($trainingInstructions) {
            $prompt .= "- Selain itu, berikut instruksi tambahan yang harus selalu diikuti ketika membuat kueri:\n"
                . $trainingInstructions . "\n";
        }

        $prompt .= "\nPertanyaan pengguna:\n"
            . $nlQuery . "\n\n"
            . "Kueri SQL:";

        $contents = [
            [
                'role' => 'user',
                'parts' => [
                    [
                        'text' => $prompt,
                    ],
                ],
            ],
        ];

        $endpoint = sprintf(
            'https://generativelanguage.googleapis.com/v1beta/models/%s:generateContent',
            $model,
        );

        try {
            /** @var HttpClientResponse $response */
            $response = Http::timeout(30)
                ->withHeaders([
                    'x-goog-api-key' => $apiKey,
                    'Content-Type' => 'application/json',
                ])
                ->post($endpoint, [
                    'contents' => $contents,
                ]);

            if (!$response->successful()) {
                $body = $response->json();

                $errorMessage = is_array($body)
                    ? ($body['error']['message'] ?? $body['message'] ?? 'Failed to call Gemini API for SQL generation.')
                    : 'Failed to call Gemini API for SQL generation.';

                Log::warning('DataAssistant: Gemini API error when generating SQL', [
                    'message' => $errorMessage,
                    'details' => $body,
                ]);

                return null;
            }

            $data = $response->json();

            $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;

            if (!is_string($text) || $text === '') {
                return null;
            }

            $rawSql = trim($text);

            $sql = $this->cleanGeneratedSql($rawSql);

            if ($sql === '') {
                return null;
            }

            return $sql;
        } catch (\Throwable $e) {
            Log::error('DataAssistant: error when calling Gemini API for SQL generation', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function cleanGeneratedSql(string $rawSql): string
    {
        $sql = html_entity_decode($rawSql, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $patterns = [
            '/<think>.*?<\/think>/is',
            '/```sql/i',
            '/```/i',
            '/^sql\s*/i',
            '/Kueri SQL\s*:?\s*/i',
        ];

        foreach ($patterns as $pattern) {
            $sql = preg_replace($pattern, '', $sql);
        }

        $sql = trim($sql);

        $semicolonPos = Str::of($sql)->position(';');
        if ($semicolonPos !== null && $semicolonPos > 0) {
            $sql = substr($sql, 0, $semicolonPos + 1);
        }

        return trim($sql);
    }

    protected function isSafeSelectQuery(string $sql): bool
    {
        $normalized = Str::lower(trim($sql));
        $normalized = preg_replace('/\s+/', ' ', $normalized);

        if (!Str::startsWith($normalized, ['select ', 'with '])) {
            return false;
        }

        $forbidden = [
            ' insert ',
            ' update ',
            ' delete ',
            ' drop ',
            ' alter ',
            ' truncate ',
            ' create ',
            ' grant ',
            ' revoke ',
        ];

        foreach ($forbidden as $keyword) {
            if (Str::contains($normalized, $keyword)) {
                return false;
            }
        }

        return true;
    }

    protected function executeSqlQuery(string $sql): ?array
    {
        try {
            $rows = DB::select($sql);

            return array_map(static function ($row) {
                return (array) $row;
            }, $rows);
        } catch (\Throwable $e) {
            Log::error('DataAssistant: Failed to execute SQL query', [
                'sql' => $sql,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    protected function formatResultMessage(string $nlQuery, string $sqlQuery, array $rows): string
    {
        $lines = [];

        $lines[] = 'Pertanyaan:';
        $lines[] = $nlQuery;
        $lines[] = '';
        $lines[] = 'Kueri SQL yang dihasilkan:';
        $lines[] = $sqlQuery;
        $lines[] = '';

        if (empty($rows)) {
            $lines[] = 'Hasil:';
            $lines[] = '(Tidak ada baris data yang cocok.)';
        } else {
            $lines[] = 'Hasil (maksimum ' . count($rows) . ' baris ditampilkan):';

            $headers = array_keys($rows[0]);
            $lines[] = implode(' | ', $headers);
            $lines[] = str_repeat('-', strlen($lines[count($lines) - 1]));

            foreach ($rows as $row) {
                $values = [];

                foreach ($headers as $header) {
                    $value = $row[$header] ?? null;

                    if ($value === null) {
                        $values[] = 'NULL';
                    } elseif (is_scalar($value)) {
                        $values[] = (string) $value;
                    } else {
                        $values[] = json_encode($value, JSON_UNESCAPED_UNICODE);
                    }
                }

                $lines[] = implode(' | ', $values);
            }
        }

        return implode("\n", $lines);
    }
}
