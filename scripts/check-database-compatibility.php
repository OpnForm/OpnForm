<?php

// Run separately from PHPUnit: its general suite deliberately enforces SQLite.
use App\Models\Forms\Form;
use App\Models\User;
use App\Models\Workspace;
use App\Service\Forms\McpFormManagementService;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
    echo "PASS {$message}\n";
}

try {
    $expected = $argv[1] ?? '';
    check(in_array($expected, ['pgsql', 'mysql'], true), 'Expected PDO driver explicitly supplied');
    check(getenv('APP_ENV') === 'database-compatibility', 'Dedicated compatibility environment required');
    check(getenv('DB_CONNECTION') === $expected && getenv('DB_DATABASE') === 'opnform_compat_'.$expected, 'Explicit disposable connection supplied');
    check(! getenv('DATABASE_URL'), 'Environment database URL absent');
    check(! is_file(__DIR__.'/../api/bootstrap/cache/config.php'), 'Cached environment configuration absent');
    require __DIR__.'/../api/vendor/autoload.php';
    $app = require __DIR__.'/../api/bootstrap/app.php';
    $app->make(ConsoleKernel::class)->bootstrap();
    check(app()->environment('database-compatibility'), 'Effective application environment verified');
    $connection = DB::connection();
    check($connection->getConfig('url') === null || $connection->getConfig('url') === '', 'Database URL override absent');
    check($connection->getDatabaseName() === 'opnform_compat_'.$expected, 'Dedicated disposable database required');
    $pdo = $connection->getPdo();
    check($pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === $expected, 'Actual PDO driver matches '.$expected);
    $actualDatabase = $pdo->query($expected === 'pgsql' ? 'SELECT current_database()' : 'SELECT DATABASE()')->fetchColumn();
    check($actualDatabase === 'opnform_compat_'.$expected, 'Server-selected database matches disposable target');
    echo json_encode(['php' => PHP_VERSION, 'pdo_driver' => $pdo->getAttribute(PDO::ATTR_DRIVER_NAME), 'server_version' => $pdo->getAttribute(PDO::ATTR_SERVER_VERSION), 'database' => $actualDatabase], JSON_THROW_ON_ERROR)."\n";
    check(Schema::getTables() === [], 'Database is empty before migrations');
    check(Artisan::call('migrate', ['--force' => true]) === 0, 'All real migrations completed');
    check(DB::table('migrations')->count() === count(glob(__DIR__.'/../api/database/migrations/*.php')), 'Every repository migration recorded');

    // Actual application models exercise casts, relations, JSON predicates and locks.
    $user = User::factory()->create(['email' => 'compat@example.test', 'password' => Illuminate\Support\Facades\Hash::make('Compatibility-1234!')]);
    $workspace = Workspace::factory()->create(['plan_overrides' => ['tier' => 'business']]);
    $workspace->users()->attach($user, ['role' => User::ROLE_ADMIN]);
    $form = Form::factory()->forWorkspace($workspace)->createdBy($user)->create([
        'properties' => [['id' => 'feedback', 'name' => 'Feedback', 'type' => 'text', 'required' => true]],
        'settings' => ['auto_next' => false],
    ]);
    check($form->fresh()->properties[0]['id'] === 'feedback', 'JSON cast round trip');
    check(Form::where('settings->auto_next', false)->whereKey($form->id)->exists(), 'JSON boolean predicate');
    DB::transaction(function () use ($form) {
        Form::whereKey($form->id)->lockForUpdate()->firstOrFail()->update(['title' => 'Committed compatibility form']);
    });
    check($form->fresh()->title === 'Committed compatibility form', 'Locked transaction commits');
    try {
        DB::transaction(function () use ($form) {
            $form->update(['title' => 'Must roll back']);
            throw new RuntimeException('intentional rollback');
        });
    } catch (RuntimeException $error) {
        check($error->getMessage() === 'intentional rollback', 'Expected rollback exception');
    }
    check($form->fresh()->title === 'Committed compatibility form', 'Transaction rollback restores row');
    try {
        DB::transaction(fn () => User::factory()->create(['email' => $user->email]));
        throw new RuntimeException('Unique constraint was not enforced');
    } catch (QueryException $error) {
        check(in_array($error->errorInfo[0], ['23000', '23505'], true), 'Unique constraint enforced by server');
    }
    try {
        DB::transaction(fn () => DB::table('pdf_templates')->insert([
            'form_id' => $form->id + 100000, 'filename' => 'qa.pdf', 'original_filename' => 'qa.pdf',
            'file_path' => 'qa.pdf', 'file_size' => 1,
        ]));
        throw new RuntimeException('Foreign key was not enforced');
    } catch (QueryException $error) {
        check(in_array($error->errorInfo[0], ['23000', '23503'], true), 'Foreign key enforced by server');
    }

    // Use a real local HTTP server so each request has the normal fresh PHP lifecycle.
    $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
    openssl_pkey_export($key, $privateKey);
    $publicKey = openssl_pkey_get_details($key)['key'];
    $server = new Symfony\Component\Process\Process(
        [PHP_BINARY, '-S', '127.0.0.1:18081', '-t', 'public', 'public/index.php'],
        __DIR__.'/../api',
        ['PASSPORT_PRIVATE_KEY' => $privateKey, 'PASSPORT_PUBLIC_KEY' => $publicKey],
    );
    $server->start();
    register_shutdown_function(static fn () => $server->stop());
    $ready = false;
    for ($attempt = 0; $attempt < 50; $attempt++) {
        $socket = @fsockopen('127.0.0.1', 18081, $errorCode, $errorMessage, 0.1);
        if ($socket !== false) {
            fclose($socket);
            $ready = true;
            break;
        }
        check($server->isRunning(), 'Compatibility HTTP server remains running');
        usleep(100000);
    }
    check($ready, 'Compatibility HTTP server ready');
    function requestJson(string $method, string $path, array $data, int $status, ?string $token = null): array
    {
        $curl = curl_init('http://127.0.0.1:18081'.$path);
        $headers = ['Accept: '.($path === '/mcp' ? 'application/json, text/event-stream' : 'application/json'), 'Content-Type: application/json'];
        if ($token !== null) {
            $headers[] = 'Authorization: Bearer '.$token;
        }
        curl_setopt_array($curl, [
            CURLOPT_CUSTOMREQUEST => $method, CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20,
            CURLOPT_POSTFIELDS => json_encode($data, JSON_THROW_ON_ERROR),
        ]);
        $body = curl_exec($curl);
        $actual = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        if ($body === false) {
            throw new RuntimeException(curl_error($curl));
        }
        curl_close($curl);
        $result = json_decode($body, true, flags: JSON_THROW_ON_ERROR);
        if ($actual !== $status) {
            echo json_encode(['message' => $result['message'] ?? null, 'errors' => $result['errors'] ?? null], JSON_THROW_ON_ERROR)."\n";
        }
        check($actual === $status, "HTTP {$method} {$path} returns {$status} (actual {$actual})");

        return $result;
    }
    requestJson('GET', '/open/workspaces', [], 401);
    requestJson('POST', '/login', ['email' => $user->email, 'password' => 'wrong'], 422);
    $login = requestJson('POST', '/login', ['email' => $user->email, 'password' => 'Compatibility-1234!'], 200);
    check(is_string($login['token'] ?? null), 'JWT login returns token');
    $workspaces = requestJson('GET', '/open/workspaces', [], 200, $login['token']);
    check($workspaces[0]['id'] === $workspace->id, 'Authenticated workspace read');
    $tools = requestJson('POST', '/mcp', ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list', 'params' => []], 200);
    check(in_array('list_forms', array_column($tools['result']['tools'], 'name'), true), 'MCP tool catalog serves account tools');
    $call = ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/call', 'params' => ['name' => 'list_forms', 'arguments' => new stdClass]];
    $challenge = requestJson('POST', '/mcp', $call, 200);
    check(($challenge['result']['isError'] ?? false) === true && str_contains($challenge['result']['_meta']['mcp/www_authenticate'][0] ?? '', 'mcp:use'), 'MCP ToolInvoker preserves OAuth challenge');
    requestJson('POST', '/mcp', $call, 401, $login['token']);

    requestJson('POST', '/forms/'.$form->slug.'/answer', ['feedback' => 'Database-backed answer'], 200);
    $submission = $form->submissions()->firstOrFail();
    check($submission->data['feedback'] === 'Database-backed answer', 'Public submission persisted as JSON');
    $submission->update(['data' => ['feedback' => 'Updated answer']]);
    check($submission->fresh()->data['feedback'] === 'Updated answer' && $submission->versions()->exists(), 'Submission update and version history persisted');
    $submission->delete();
    check(! $form->submissions()->exists(), 'Submission delete persisted');

    $management = app(McpFormManagementService::class);
    check($management->form($user, $form->id)->id === $form->id, 'MCP authorized form lookup');
    $outsider = User::factory()->create();
    try {
        $management->form($outsider, $form->id);
        throw new RuntimeException('MCP allowed another account to read the form');
    } catch (Illuminate\Validation\ValidationException $error) {
        check(isset($error->errors()['form_id']), 'MCP cross-account lookup denied');
    }
    requestJson('POST', '/mcp-oauth/session', ['authorization_request_token' => str_repeat('a', 64)], 401);
    requestJson('POST', '/oauth/register', ['client_name' => 'Bad redirect', 'redirect_uris' => ['http://localhost.attacker.example/callback']], 400);
    check(DB::table('oauth_clients')->count() === 0, 'Rejected OAuth registration did not persist');
    $client = requestJson('POST', '/oauth/register', ['client_name' => 'Compatibility client', 'redirect_uris' => ['http://localhost:3210/callback']], 201);
    check($client['scope'] === 'mcp:use' && $client['token_endpoint_auth_method'] === 'none', 'MCP scope and public PKCE client response');
    check(DB::table('oauth_clients')->where('id', $client['client_id'])->exists(), 'Passport client persisted on actual database');
    echo "Database compatibility checks passed for {$expected}.\n";

} catch (Throwable $error) {
    fwrite(STDERR, get_class($error).': '.$error->getMessage()."\n");
    exit(1);
}
