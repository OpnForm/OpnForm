<?php
// Test program outside /app. Uses only the candidate image's application/vendor.
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use Illuminate\Support\Facades\Cache;
use App\Service\Deployment\DynamoStateImporter;
use App\Service\Deployment\HostedStateGuard;
use App\Service\Deployment\HostedStateReceiptLifecycle;
use App\Service\Deployment\RedisStateTopology;
function checkState(bool $condition, string $label): void {
    if (! $condition) { throw new RuntimeException('Fixture failed: '.$label); }
    $GLOBALS['checks'][] = $label;
}
function blocked(callable $callback): bool {
    try { $callback(); return false; } catch (RuntimeException) { return true; }
}
function snapshot(): array {
    $expires = time() + 600;
    $user = new App\Models\User(); $user->id = 42;
    $token = app('tymon.jwt')->fromSubject($user);
    $payload = app('tymon.jwt')->setToken($token)->getPayload();
    $records = [['session',str_repeat('b',40),serialize(['fictional-user'=>42])],
        ['jwt-revocation',$payload->get('jti'),['valid_until'=>time()-1]],
        ['context','fixture-once','one-use'],['quota','fixture-quota',3],['cache','fixture-expired','expired']];
    $rows=[];
    foreach($records as $index=>[$kind,$key,$value]) {
        $rows[]=['kind'=>$kind,'item'=>['key'=>['S'=>'fictional_opnform:'.$key],
            'value'=>is_int($value)?['N'=>(string)$value]:['S'=>serialize($value)],
            'expires_at'=>['N'=>(string)($index===4?time()-1:$expires)]]];
    }
    $snapshot=['schema'=>'opnform-dynamodb-state-snapshot/v1','namespace'=>config('hosted-state.namespace'),
        'target_configuration_sha256'=>app(HostedStateGuard::class)->configurationFingerprint(),
        'source_fenced_at'=>time(),'source_prefix'=>'fictional_opnform:',
        'package'=>['laravel'=>Composer\InstalledVersions::getVersion('laravel/framework'),
            'php_serialize'=>PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION,
            'composer_lock_sha256'=>hash_file('sha256','/app/composer.lock')],
        'attributes'=>['key'=>'key','value'=>'value','expiration'=>'expires_at'],'rows'=>$rows];
    file_put_contents('/fixtures/state-snapshot.json',json_encode($snapshot,JSON_THROW_ON_ERROR));
    file_put_contents('/fixtures/state-witness.json',json_encode(['token'=>$token,'jti'=>$payload->get('jti'),
        'session'=>str_repeat('b',40),'expiration'=>$expires],JSON_THROW_ON_ERROR));
    return $snapshot;
}
function importer(): DynamoStateImporter {return app(DynamoStateImporter::class);}
function stateKey(string $key): string {return Cache::store('auth')->getStore()->getPrefix().$key;}
function resetOwnedFixture(): void {
 if(!str_starts_with(config('database.redis.default.host'),'opnform-fixture-') || config('hosted-state.namespace')!=='opnform-image-fixture')throw new RuntimeException('Not the isolated synthetic fixture');
 foreach(['state','auth','cache','default'] as $name)app('redis')->connection($name)->flushdb();
}
function killWorker(): void {
    $path='/tmp/opnform-image-import-worker.php';
    file_put_contents($path, <<<'WORKER'
<?php
require '/app/vendor/autoload.php'; $a=require '/app/bootstrap/app.php';
$a->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
try {
 $s=json_decode(file_get_contents('/fixtures/state-snapshot.json'),true,flags:JSON_THROW_ON_ERROR);
 app(App\Service\Deployment\DynamoStateImporter::class)->import($s,function($index){
  if($index===1){file_put_contents('/fixtures/worker-committed','1');while(true){usleep(10000);}}
 }); exit(0);
} catch(RuntimeException){exit(78);}
WORKER);
    @unlink('/fixtures/worker-committed');
    $p=proc_open([PHP_BINARY,$path],[['pipe','r'],['file','/tmp/fixture-worker.log','a'],['file','/tmp/fixture-worker.log','a']],$pipes);
    fclose($pipes[0]); $didKill=false; $end=microtime(true)+20;
    try {
        do { $status=proc_get_status($p);
            if(is_file('/fixtures/worker-committed')) {
                checkState(posix_kill($status['pid'],SIGKILL),'SIGKILL delivered after acknowledged commit');
                do{usleep(10000);$status=proc_get_status($p);}while($status['running']&&microtime(true)<$end);
                checkState(!$status['running']&&$status['signaled']&&$status['termsig']===SIGKILL,'worker termination is signal9');
                $didKill=true;break;
            }
            if(!$status['running']){break;} usleep(10000);
        } while(microtime(true)<$end);
        checkState($didKill,'worker reached real SIGKILL path');
    } finally {
        $status=proc_get_status($p);if($status['running'])posix_kill($status['pid'],SIGKILL);
        proc_close($p);@unlink($path);@unlink('/fixtures/worker-committed');
    }
}
$GLOBALS['checks']=[]; $stage=$argv[1]??''; $state=app('redis')->connection('state');$auth=app('redis')->connection('auth');
if($stage==='import') {
    $servers=app(RedisStateTopology::class)->assertReady();
    checkState(count($servers)===4&&count(array_unique($servers))===1,'one Redis process and four logical database bindings');
    checkState(config('session.connection')==='auth'&&config('cache.stores.redis.connection')==='state','effective sessions and cache use state');
    $state->command('config',['SET','maxmemory-policy','allkeys-lru']);
    checkState(blocked(fn()=>app(RedisStateTopology::class)->assertReady()),'evicting state rejected');
    $state->command('config',['SET','maxmemory-policy','noeviction']);
    foreach(['clean','consume','overwrite','lost-ledger'] as $scenario) {
        resetOwnedFixture(); $s=snapshot(); importer()->begin($s); killWorker();
        $expiration=$auth->pexpiretime(stateKey(str_repeat('b',40)));
        if($scenario==='consume')Cache::store('auth')->pull(str_repeat('b',40));
        if($scenario==='overwrite')Cache::store('auth')->put(str_repeat('b',40),'new-after-kill',600);
        if($scenario==='lost-ledger'){[, $ledger]=importer()->keys();$state->del($ledger);Cache::store('auth')->pull(str_repeat('b',40));}
        if($scenario==='clean') {
            $r=importer()->import($s);checkState($r['imported']==='4'&&$r['expired']==='1','counts and expired row retained');
            importer()->import($s);checkState($auth->pexpiretime(stateKey(str_repeat('b',40)))===$expiration,'absolute expiry unchanged on resume and replay');
            checkState(Cache::get('fixture-expired')===null,'expired source row never imported');
        } else {
            checkState(blocked(fn()=>importer()->import($s)),'resume blocked for '.$scenario);
            checkState(Cache::store('auth')->get(str_repeat('b',40))===($scenario==='overwrite'?'new-after-kill':null),'no resurrection or overwrite for '.$scenario);
            checkState(blocked(fn()=>importer()->seal($s)),'dirty import cannot seal for '.$scenario);
        }
    }
    resetOwnedFixture();$s=snapshot();importer()->begin($s);importer()->import($s);importer()->seal($s);
    $w=json_decode(file_get_contents('/fixtures/state-witness.json'),true);
    $session=(new Illuminate\Session\SessionManager(app()))->driver();$session->setId($w['session']);$session->start();
    checkState($session->get('fictional-user')===42,'real Laravel session reads imported payload');
    $rejected=false;try{app('tymon.jwt')->setToken($w['token'])->getPayload();}catch(Tymon\JWTAuth\Exceptions\TokenBlacklistedException){$rejected=true;}
    checkState($rejected,'real signed JWT rejected by actual blacklist provider');
    checkState(Cache::increment('fixture-quota')===4,'imported quota increments through actual store');
    checkState(app('redis')->connection('cache')->dbsize()===0&&app('redis')->connection('default')->dbsize()===0,'state stays out of disposable cache and queue');
    $w['run_id']=$state->info('server')['run_id'];$w['session_expiration']=$auth->pexpiretime(stateKey($w['session']));
    $w['jwt_expiration']=$auth->pexpiretime(stateKey(Cache::store('auth')->tags('tymon.jwt')->taggedItemKey($w['jti'])));
    file_put_contents('/fixtures/state-witness.json',json_encode($w,JSON_THROW_ON_ERROR));
}
if($stage==='receipt'||$stage==='renew-receipt'||$stage==='restore-receipt') {
    $s=json_decode(file_get_contents('/fixtures/state-snapshot.json'),true);
    $w=json_decode(file_get_contents('/fixtures/state-witness.json'),true);
    [$epoch]=importer()->keys();$epoch=$state->hgetall($epoch);
    $receipt=['schema'=>'opnform-state-recovery/v1','phase'=>$stage==='receipt'?'initial-recovery':'recovery-renewal',
        'redis_run_id'=>$state->info('server')['run_id'],'source'=>'dynamodb','cutover_approved'=>true,
        'migration_id'=>'fictional-image-migration','source_fenced_at'=>$s['source_fenced_at'],
        'verified_at'=>time(),'valid_until'=>time()+120,'revision'=>trim(file_get_contents('/app-revision')),
        'source_manifest_sha256'=>$epoch['manifest'],'configuration_sha256'=>app(HostedStateGuard::class)->configurationFingerprint(),
        'source_unexpired_count'=>4,'imported_count'=>4,'unknown_count'=>0,
        'evidence'=>array_fill_keys(['identity','source_fence','sessions','ttl','revocations','atomicity','durability','rollback'],str_repeat('b',64)),
        'probes'=>[
            ['kind'=>'session','key'=>$w['session'],'expires_at'=>$w['expiration'],'value_sha256'=>hash('sha256',serialize(Cache::store('auth')->get($w['session'])))],
            ['kind'=>'jwt-revocation','key'=>$w['jti'],'expires_at'=>$w['expiration'],'value_sha256'=>hash('sha256',serialize(app('tymon.jwt.provider.storage')->get($w['jti'])))],
        ]];
    if($stage!=='receipt')$receipt['previous_receipt_sha256']=$epoch['accepted_receipt'];
    if($stage==='restore-receipt')$receipt['evidence']['redis_restore']=str_repeat('d',64);
    echo json_encode($receipt,JSON_THROW_ON_ERROR);exit(0);
}
if($stage==='publish'||$stage==='renew'||$stage==='restore-publish') {
    app(HostedStateReceiptLifecycle::class)->publish();app(HostedStateGuard::class)->assertReady();
    checkState(blocked(fn()=>app(HostedStateReceiptLifecycle::class)->publish()),'receipt replay fails its compare-and-swap');
    if($stage==='publish')Cache::pull('fixture-once');
    checkState(Cache::get('fixture-once')===null,'receipt publication never recreates consumed context');
    $s=json_decode(file_get_contents('/fixtures/state-snapshot.json'),true);
    checkState(blocked(fn()=>importer()->import($s)),'admitted import cannot replay source');
}
if($stage==='saturation') {
    Illuminate\Support\Facades\Queue::connection('redis')->push('FictionalFixtureHandler',[]);
    checkState(app('redis')->connection('default')->llen('queues:default')===1,'actual Laravel queue producer uses queue DB');
    $memory=$state->info('memory');$state->command('config',['SET','maxmemory',(string)($memory['used_memory']+100000)]);
    $oom=false;try{Cache::store('volatile')->put('too-large',str_repeat('x',3000000),120);}catch(RedisException $e){$oom=str_contains($e->getMessage(),'OOM');}
    checkState($oom,'shared memory saturation raises an explicit OOM error');
    checkState((int)Cache::get('fixture-quota')===4,'state quota remains after cache write saturation');
    $w=json_decode(file_get_contents('/fixtures/state-witness.json'),true);
    checkState(Cache::store('auth')->get($w['session'])!==null,'auth session remains after cache saturation');
    checkState(app('redis')->connection('default')->llen('queues:default')===1,'queued work remains after saturation');
    checkState($state->info('stats')['evicted_keys']===0,'no protected key eviction under saturation');
    $state->command('config',['SET','maxmemory','32mb']);
}
if($stage==='after-aof') {
    $w=json_decode(file_get_contents('/fixtures/state-witness.json'),true);
    checkState($state->info('server')['run_id']!==$w['run_id'],'Redis daemon run_id changes after SIGKILL');
    $session=(new Illuminate\Session\SessionManager(app()))->driver();$session->setId($w['session']);$session->start();
    checkState($session->get('fictional-user')===42,'AOF retains actual imported session');
    $rejected=false;try{app('tymon.jwt')->setToken($w['token'])->getPayload();}catch(Tymon\JWTAuth\Exceptions\TokenBlacklistedException){$rejected=true;}
    checkState($rejected,'AOF retains actual JWT revocation');
    checkState(Cache::get('fixture-once')===null,'AOF preserves consumed context absence');
    checkState((int)Cache::get('fixture-quota')===4,'AOF retains quota increment');
    checkState($auth->pexpiretime(stateKey($w['session']))===$w['session_expiration'],'AOF preserves absolute session expiry');
    checkState($auth->pexpiretime(stateKey(Cache::store('auth')->tags('tymon.jwt')->taggedItemKey($w['jti'])))===$w['jwt_expiration'],'AOF preserves absolute JWT expiry');
    checkState(blocked(fn()=>app(HostedStateGuard::class)->assertReady()),'old signed admission refuses restarted Redis');
}
echo json_encode(['stage'=>$stage,'checks'=>$GLOBALS['checks'],'php'=>PHP_VERSION,
    'laravel'=>Composer\InstalledVersions::getVersion('laravel/framework'),
    'jwt_auth'=>Composer\InstalledVersions::getVersion('tymon/jwt-auth'),'phpredis'=>phpversion('redis')],JSON_THROW_ON_ERROR).PHP_EOL;
