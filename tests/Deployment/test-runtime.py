#!/usr/bin/env python3
"""Current single-Redis image qualification on disposable fictional data; no application/vendor overlays."""
import argparse
import base64
import json
import ipaddress
import os
from pathlib import Path
import subprocess
import tempfile
import time
import uuid


def run(*command, check=True, env=None, input=None, timeout=90):
    result = subprocess.run(command, capture_output=True, text=True, input=input,
                            env=env, timeout=timeout)
    if check and result.returncode:
        raise RuntimeError(f'{command[0]} failed ({result.returncode}): {result.stderr[-1500:]} {result.stdout[-1500:]}')
    return result


def wait_for(check, label, seconds=90):
    end = time.monotonic() + seconds
    while time.monotonic() < end:
        if check():
            return
        time.sleep(0.5)
    raise RuntimeError(f'Timed out: {label}')


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--api-image', default='opnform-scaleway-api:local')
    parser.add_argument('--client-image', default='opnform-scaleway-client:local')
    parser.add_argument('--skip-client', action='store_true')
    parser.add_argument('--host-firewall-guard', action='store_true', help='Linux root only: deny fixture access to host and other networks')
    parser.add_argument('--output-dir',type=Path,required=True)
    args = parser.parse_args()
    args.output_dir.mkdir(parents=True,exist_ok=True)
    prefix = 'opnform-fixture-' + uuid.uuid4().hex[:10]
    names = []
    passed = []
    network_created = False
    firewall_table = None
    image_checks=[]

    def docker(*command, **kwargs):
        return run('docker', *command, **kwargs)

    def record(label):
        passed.append(label)
        print('PASS ' + label, flush=True)

    def start(suffix, image, *options, command=(), environment=None):
        name = prefix + '-' + suffix
        # Register before execution so cleanup also covers ambiguous run results.
        names.append(name)
        flags = []
        for key in (environment or {}):
            flags += ['--env', key]
        docker('run', '-d', '--pull=never', '--name', name, '--network', prefix, '--dns', '127.0.0.1',
               *flags, *options, image, *command,
               env={**os.environ, **(environment or {})})
        return name

    try:
        ids = docker('network', 'ls', '-q').stdout.split()
        existing = json.loads(docker('network', 'inspect', *ids).stdout)
        used = [ipaddress.ip_network(config['Subnet']) for net in existing
                for config in (net.get('IPAM', {}).get('Config') or []) if config.get('Subnet')]
        candidates = [ipaddress.ip_network(f'10.254.{index}.0/24') for index in range(240, 255)]
        subnet = next((net for net in candidates if not any(
            net.version == other.version and net.overlaps(other) for other in used)), None)
        if subnet is None:
            raise RuntimeError('No non-overlapping local fixture subnet available')
        docker('network', 'create', '--internal', '--subnet', str(subnet), prefix)
        network_created = True
        inspect = json.loads(docker('network', 'inspect', prefix).stdout)[0]
        assert inspect['Internal'] is True
        if args.host_firewall_guard:
            firewall_table = 'opnform_fixture_' + prefix.rsplit('-', 1)[1]
            bridge = 'br-' + inspect['Id'][:12]
            rules = f'''add table inet {firewall_table}
add chain inet {firewall_table} input_guard {{ type filter hook input priority -30; policy accept; }}
add chain inet {firewall_table} forward_guard {{ type filter hook forward priority -30; policy accept; }}
add rule inet {firewall_table} input_guard ct state established,related accept
add rule inet {firewall_table} input_guard iifname "{bridge}" drop
add rule inet {firewall_table} forward_guard iifname "{bridge}" oifname != "{bridge}" drop
'''
            run('nft', '-f', '-', input=rules)
        record('network is internal; no public ports')
        db = start('db', 'postgres:16-bookworm', '--tmpfs', '/var/lib/postgresql/data',
                   environment={'POSTGRES_PASSWORD': 'fictional-local-only', 'POSTGRES_DB': 'opnform_fixture'})
        queue = start('redis', 'redis:7.4-bookworm', command=('redis-server','--appendonly','yes','--appendfsync','always','--maxmemory','32mb','--maxmemory-policy','noeviction'))
        state=cache=queue
        wait_for(lambda: docker('exec', db, 'pg_isready', '-U', 'postgres', check=False).returncode == 0, 'Postgres')
        fixture_root = Path.home() / '.codex' / 'tmp'
        fixture_root.mkdir(parents=True, exist_ok=True)
        with tempfile.TemporaryDirectory(prefix='opnform-fixtures-', dir=fixture_root) as directory:
            fixture = Path(directory)
            fixture.chmod(0o777)
            private = run('openssl', 'genpkey', '-algorithm', 'RSA', '-pkeyopt', 'rsa_keygen_bits:2048').stdout
            public = run('openssl', 'pkey', '-pubout', input=private).stdout
            environment = {
                'APP_ENV': 'production', 'TRUSTED_PROXIES': '127.0.0.1', 'APP_DEBUG': 'false', 'SELF_HOSTED': 'false',
                'APP_KEY': 'base64:' + base64.b64encode(os.urandom(32)).decode(),
                'JWT_SECRET': 'fictional-runtime-test-signing-secret-at-least-32-characters',
                'FRONT_API_SECRET': base64.b64encode(os.urandom(48)).decode(),
                'PASSPORT_PRIVATE_KEY': private, 'PASSPORT_PUBLIC_KEY': public,
                'APP_URL': 'http://api.fixture.test', 'FRONT_URL': 'http://client.fixture.test',
                'DB_CONNECTION': 'pgsql', 'DB_HOST': db, 'DB_PORT': '5432',
                'DB_DATABASE': 'opnform_fixture', 'DB_USERNAME': 'postgres', 'DB_PASSWORD': 'fictional-local-only',
                'QUEUE_CONNECTION': 'redis', 'REDIS_HOST': queue, 'REDIS_QUEUE': 'custom-fixture', 'REDIS_QUEUE_RETRY_AFTER': '3700',
                'REDIS_CACHE_HOST': cache, 'REDIS_STATE_HOST':state,'REDIS_AUTH_HOST':state,'REDIS_DB':'0','REDIS_CACHE_DB':'1','REDIS_AUTH_DB':'2','REDIS_STATE_DB':'3','REDIS_PREFIX':'opnform_fixture:', 'OPNFORM_STATE_MODE':'redis-fresh', 'CACHE_DRIVER': 'redis',
                'MAIL_MAILER': 'log', 'FILESYSTEM_DRIVER': 'local', 'WORKER_COUNT': '1',
                'OPNFORM_ANONYMOUS_TELEMETRY_DISABLED': 'true', 'SESSION_DRIVER': 'redis','SESSION_STORE':'auth','SESSION_CONNECTION':'auth',
            }
            mount = ('--mount', f'type=bind,src={fixture},dst=/fixtures')

            def app(suffix, role, *options, override=None, command=()):
                return start(suffix, args.api_image, *mount, *options,
                             environment={**environment, 'APP_ROLE': role, **(override or {})}, command=command)

            probe = app('probe', 'api', '--entrypoint', 'sleep', command=('infinity',))
            script = "foreach ([['1.1.1.1',443],['169.254.169.254',80],['[2606:4700:4700::1111]',443],['host.docker.internal',80],['8.8.8.8',53],['1.1.1.1',25],['example.com',443]] as [$h,$p]) { if (@fsockopen($h,$p,$e,$s,1)) {fwrite(STDERR,'Unexpected egress');exit(1);} }"
            docker('exec', probe, 'php', '-r', script)
            docker('exec', probe, 'php', '-r', "if (@gethostbyname('example.com') !== 'example.com') exit(1);")
            if args.host_firewall_guard:
                gateway = str(subnet.network_address + 1)
                docker('exec', probe, 'php', '-r', f"if (@fsockopen('{gateway}',22,$e,$s,1)) exit(1);")
            record('IPv4 IPv6 metadata host-gateway SMTP DNS and external HTTPS egress probes denied')
            for suffix, proxies in [('missing-proxy', ''), ('wildcard-proxy', '*')]:
                bad_proxy = app(suffix, 'api', override={'TRUSTED_PROXIES': proxies})
                wait_for(lambda: docker('inspect', '-f', '{{.State.Running}}', bad_proxy).stdout.strip() == 'false', 'proxy guard')
                assert docker('inspect', '-f', '{{.State.ExitCode}}', bad_proxy).stdout.strip() == '78'
            record('missing or wildcard trusted proxies block startup')
            invalid = app('invalid', 'jobs', override={'REDIS_QUEUE_RETRY_AFTER': '90'})
            wait_for(lambda: docker('inspect', '-f', '{{.State.Running}}', invalid).stdout.strip() == 'false', 'invalid preflight')
            assert docker('inspect', '-f', '{{.State.ExitCode}}', invalid).stdout.strip() == '78'
            record('short Redis reservation blocks worker startup')
            source_proof=json.loads((args.output_dir/'proof.json').read_text())
            manifest={('/app/'+f[4:]):h for f,h in source_proof['source_hashes'].items() if f.startswith('api/')}
            for filename,target in [('health.php','/opt/opnform/health.php'),('preflight.php','/opt/opnform/preflight.php'),('scheduler.php','/opt/opnform/scheduler.php'),('entrypoint.sh','/usr/local/bin/entrypoint.sh'),('role-health.sh','/usr/local/bin/role-health.sh')]:
                manifest[target]=source_proof['source_hashes']['deploy/scaleway/'+filename]
            hash_script='$m=json_decode(stream_get_contents(STDIN),true);foreach($m as $f=>$h){if(!is_file($f)||hash_file("sha256",$f)!==$h){fwrite(STDERR,"Runtime hash mismatch: ".$f);exit(1);}}echo count($m);'
            matched=int(docker('exec','-i',probe,'php','-r',hash_script,input=json.dumps(manifest)).stdout)
            assert docker('exec',probe,'cat','/app-revision').stdout==source_proof['source_revision_marker']
            assert docker('exec',probe,'php','-r',"echo PHP_VERSION.'|'.ini_get('session.upload_progress.enabled');").stdout.strip()=='8.3.36|0'
            assert 'session.upload_progress.enabled => Off' in docker('exec',probe,'php-fpm','-i').stdout
            record('actual backend image matches '+str(matched)+' frozen runtime source hashes and revision')
            image_checks.append({'runtime_source_hashes_matched':matched,'image':docker('inspect','-f','{{.Image}}',probe).stdout.strip()})
            # Exercise the actual production profile with disposable dependencies.
            fresh = app('fresh-production-preflight', 'api', '--entrypoint', 'php',
                        override={'APP_ENV': 'production', 'OPNFORM_STATE_MODE': 'redis-fresh'},
                        command=('/opt/opnform/preflight.php',))
            wait_for(lambda: docker('inspect', '-f', '{{.State.Running}}', fresh).stdout.strip() == 'false', 'fresh Redis production preflight')
            assert docker('inspect', '-f', '{{.State.ExitCode}}', fresh).stdout.strip() == '0'
            docker('exec', state, 'redis-cli', 'CONFIG', 'SET', 'maxmemory-policy', 'allkeys-lru')
            unsafe = app('unsafe-fresh-production', 'api', '--entrypoint', 'php',
                         override={'APP_ENV': 'production', 'OPNFORM_STATE_MODE': 'redis-fresh'},
                         command=('/opt/opnform/preflight.php',))
            wait_for(lambda: docker('inspect', '-f', '{{.State.Running}}', unsafe).stdout.strip() == 'false', 'unsafe fresh Redis policy rejection')
            assert docker('inspect', '-f', '{{.State.ExitCode}}', unsafe).stdout.strip() == '78'
            docker('exec', state, 'redis-cli', 'CONFIG', 'SET', 'maxmemory-policy', 'noeviction')
            environment['OPNFORM_STATE_MODE'] = 'redis-fresh'
            environment['APP_ENV'] = 'production'
            record('production Laravel Redis bindings boot; unsafe eviction is rejected')

            api = app('api', 'api')
            wait_for(lambda: docker('exec', api, 'role-health.sh', check=False).returncode == 0, 'API dependencies')
            count = docker('exec', db, 'psql', '-U', 'postgres', '-d', 'opnform_fixture', '-tAc',
                           "SELECT count(*) FROM pg_tables WHERE schemaname='public'").stdout.strip()
            assert count == '0', count
            record('API startup performs no migrations')
            migration = app('migration', 'migrate')
            wait_for(lambda: docker('exec', migration, 'test', '-f', '/tmp/opnform-migration-ok', check=False).returncode == 0, 'migration gate')
            record('explicit Migration role validates OAuth keys and schema')
            docker('exec', api, 'php', 'artisan', 'db:seed', '--class=E2ETestSeeder', '--force', '--no-interaction')
            record('synthetic E2E forms and submissions seeded')
            settings = "require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();echo json_encode([config('database.redis.default.host'),config('database.redis.cache.host'),config('queue.connections.redis.retry_after')]);"
            assert json.loads(docker('exec', api, 'php', '-r', settings).stdout) == [queue, cache, 3700]
            record('queue/cache/auth/state share one endpoint with separate logical DBs and 3700s reservation')
            smoke = "require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$f=App\\Models\\Forms\\Form::where('title','Product feedback')->firstOrFail();if(App\\Models\\Forms\\FormSubmission::count()!==6)exit(1);$t=auth('api')->attempt(['email'=>'e2e@example.test','password'=>'Abcd@1234']);if(!$t)exit(2);echo $f->slug;"
            slug = docker('exec', api, 'php', '-r', smoke).stdout.strip()
            assert slug
            response = docker('exec', api, 'curl', '-fsS', f'http://127.0.0.1:8080/forms/{slug}').stdout
            assert 'Product feedback' in response
            record('real HTTP form read and synthetic JWT login work')
            form_data = json.loads(response)
            form_data = form_data.get('data', form_data)
            values = {'Full name': 'Migration Fixture', 'Email address': 'migration@example.test',
                      'Satisfaction': 5, 'Feedback': 'Synthetic isolated runtime submission'}
            payload = {field['id']: values[field['name']] for field in form_data['properties'] if field['name'] in values}
            submitted = docker('exec', api, 'curl', '-fsS', '-H', 'Content-Type: application/json',
                               '-H', 'Accept: application/json', '-d', json.dumps(payload),
                               f'http://127.0.0.1:8080/forms/{slug}/answer').stdout
            assert 'Form submission saved' in submitted
            (fixture / 'pixel.png').write_bytes(base64.b64decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAusB9Y9ZlS8AAAAASUVORK5CYII='))
            uploaded = json.loads(docker('exec', api, 'curl', '-fsS', '-H', 'Accept: application/json',
                                        '-F', 'file=@/fixtures/pixel.png', 'http://127.0.0.1:8080/upload-file').stdout)
            assert uploaded.get('uuid') and uploaded.get('key', '').startswith('tmp/')
            record('public HTTP submission accepted and PNG upload stored on isolated local disk')
            (fixture / 'redis-runtime.php').write_bytes(Path(__file__).with_name('redis-runtime.php').read_bytes())
            docker('exec', api, 'php', '/fixtures/redis-runtime.php')
            record('standard Laravel cache, session, locks and queued work survive explicit saturation without eviction')

            (fixture / 'queue.php').write_text('''<?php
require '/app/vendor/autoload.php';
$a=require '/app/bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$job=Illuminate\\Queue\\CallQueuedClosure::create(function () {
 file_put_contents('/fixtures/job-started','1');
 $end=microtime(true)+4;while(microtime(true)<$end){usleep(100000);}
 file_put_contents('/fixtures/job-finished','1');
});dispatch($job);
''')
            (fixture / 'retry.php').write_text('''<?php
require '/app/vendor/autoload.php';
$a=require '/app/bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();
$job=Illuminate\\Queue\\CallQueuedClosure::create(function () {
 $path='/fixtures/retry-count';$count=is_file($path)?(int)file_get_contents($path):0;
 file_put_contents($path,++$count);
 if($count<3)throw new RuntimeException('Synthetic retryable failure');
 file_put_contents('/fixtures/retry-finished','1');
});$job->tries=null;dispatch($job);
''')
            docker('exec', api, 'php', '/fixtures/retry.php')
            jobs = app('jobs', 'jobs')
            wait_for(lambda: (fixture / 'retry-finished').exists(), 'third worker attempt')
            assert (fixture / 'retry-count').read_text() == '3'
            assert '--tries=3' in docker('exec', jobs, 'pgrep', '-af', 'artisan queue:work redis').stdout
            record('worker consumes the configured custom queue and retries a synthetic job through success')
            docker('exec', api, 'php', '/fixtures/queue.php')
            wait_for(lambda: (fixture / 'job-started').exists(), 'queued synthetic job')
            docker('stop', '-t', '15', jobs)
            assert (fixture / 'job-finished').read_text() == '1'
            assert docker('inspect', '-f', '{{.State.ExitCode}}', jobs).stdout.strip() == '0'
            stored = docker('exec', db, 'psql', '-U', 'postgres', '-d', 'opnform_fixture', '-tAc',
                            'SELECT count(*) FROM form_submissions').stdout.strip()
            assert stored == '7', stored
            record('queued public submission persisted before worker shutdown')
            record('SIGTERM drains an actual queued job before worker exit')
            revoked = "require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();$t=auth('api')->login(App\\Models\\User::firstOrFail());auth('api')->setToken($t)->logout();echo $t;"
            token = docker('exec', api, 'php', '-r', revoked).stdout.strip()
            verify = "require 'vendor/autoload.php';$a=require 'bootstrap/app.php';$a->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap();exit(auth('api')->setToken(getenv('FIXTURE_REVOKED_TOKEN'))->check()?1:0);"
            docker('exec', '--env', 'FIXTURE_REVOKED_TOKEN=' + token, api, 'php', '-r', verify)
            record('revoked JWT remains rejected across processes sharing the same state store')

            scheduler = app('scheduler', 'scheduler')
            wait_for(lambda:docker('exec',scheduler,'test','-f','/tmp/opnform-scheduler-heartbeat',check=False).returncode==0,'actual scheduler heartbeat')
            second = app('scheduler-second', 'scheduler')
            wait_for(lambda:docker('inspect','-f','{{.State.Running}}',second).stdout.strip()=='false','second actual scheduler rejection')
            assert docker('inspect','-f','{{.State.ExitCode}}',second).stdout.strip()=='75'
            docker('stop','-t','20',scheduler)
            assert docker('inspect','-f','{{.State.ExitCode}}',scheduler).stdout.strip()=='0'
            record('actual unmodified artisan scheduler holds Postgres singleton and exits cleanly on SIGTERM')
            bad = app('bad-migration', 'migrate',override={'PASSPORT_PRIVATE_KEY':'fictional-invalid-key','PASSPORT_PUBLIC_KEY':'fictional-invalid-key'})
            wait_for(lambda:'Migration gate failed' in docker('logs',bad).stdout+docker('logs',bad).stderr,'invalid OAuth migration gate')
            assert docker('exec',bad,'role-health.sh',check=False).returncode!=0
            record('actual migration role refuses invalid fictional OAuth keys without application overlay')
            if not args.skip_client:
                client = start('client', args.client_image, environment={
                    'NUXT_PRIVATE_API_BASE': f'http://{api}:8080',
                    'NUXT_API_SECRET': environment['FRONT_API_SECRET'],
                    'NUXT_PUBLIC_API_BASE': 'http://api.fixture.test',
                    'NUXT_PUBLIC_APP_URL': 'http://client.fixture.test',
                })
                wait_for(lambda: docker('exec', client, 'node', '-e', "fetch('http://127.0.0.1:3000/login').then(r=>process.exit(r.status===200?0:1)).catch(()=>process.exit(1))", check=False).returncode == 0, 'frontend SSR')
                release_check = "fetch('http://127.0.0.1:3000/release.json').then(async r=>{if(r.status!==200||(await r.json()).revision!==process.argv[1])process.exit(1)}).catch(()=>process.exit(1))"
                docker('exec', client, 'node', '-e', release_check, source_proof['source_revision_marker'])
                record('frontend serves its exact build revision through the public release endpoint')
                targets = ['https://1.1.1.1', 'http://169.254.169.254', 'https://example.com', 'http://[2606:4700:4700::1111]']
                if args.host_firewall_guard:
                    targets.append('http://' + str(subnet.network_address + 1) + ':22')
                javascript = "const urls=JSON.parse(process.argv[1]);Promise.all(urls.map(u=>fetch(u,{signal:AbortSignal.timeout(1500)}).then(()=>{throw Error('Unexpected egress '+u)},()=>true))).catch(e=>{console.error(e.message);process.exitCode=1})"
                docker('exec', client, 'node', '-e', javascript, json.dumps(targets))
                docker('exec', client, 'node', '-e', "require('node:dns').promises.lookup('example.com').then(()=>process.exit(1),()=>process.exit(0))")
                record('exact frontend image serves SSR login and denies external/metadata/host egress')
                login = json.loads(docker('exec', api, 'curl', '-fsS', '-H', 'Content-Type: application/json', '-H', 'Accept: application/json', '-H', 'User-Agent: FixtureBrowser', '-d', json.dumps({'email': 'e2e@example.test', 'password': 'Abcd@1234'}), 'http://127.0.0.1:8080/login').stdout)
                browser_token = login['token']
                ssr_php = '$c=curl_init(getenv("FIXTURE_CLIENT_URL"));curl_setopt_array($c,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_HTTPHEADER=>["Cookie: opnform_token=".getenv("FIXTURE_BROWSER_JWT")],CURLOPT_TIMEOUT=>30]);$s=curl_exec($c);$status=curl_getinfo($c,CURLINFO_RESPONSE_CODE);exit($status===200&&is_string($s)&&str_contains($s,"Product feedback")&&!str_contains($s,"This form is temporarily unavailable")?0:1);'
                ssr = docker('exec', '--env', 'FIXTURE_BROWSER_JWT=' + browser_token, '--env', 'FIXTURE_CLIENT_URL=http://' + client + ':3000/forms/' + slug, api, 'php', '-r', ssr_php)
                docker('exec', api, 'curl', '-fsS', '-H', 'Accept: application/json', '-H', 'User-Agent: FixtureBrowser', '-H', 'Authorization: Bearer ' + browser_token, 'http://127.0.0.1:8080/user')
                record('shared private SSR secret preserves browser JWT across Nuxt server requests')

    except Exception:
        for name in names:
            if name.endswith(('-api', '-invalid', '-migration', '-jobs', '-bad-migration', '-client')):
                log = docker('logs', '--tail', '12', name, check=False)
                print(f'FIXTURE DIAGNOSTIC {name}\n{log.stdout}\n{log.stderr}', flush=True)
        raise
    finally:
        runtime_inventory=[]
        for name in names:
            result=docker('inspect',name,check=False)
            if result.returncode:continue
            inspected=json.loads(result.stdout)[0]
            mounts=[{'type':m['Type'],'destination':m['Destination']} for m in inspected.get('Mounts',[])]
            assert not any(m['destination']=='/app' or m['destination'].startswith('/app/') or m['destination'].startswith('/opt/opnform') for m in mounts)
            assert not inspected['HostConfig'].get('PortBindings')
            runtime_inventory.append({'name':name,'image_id':inspected['Image'],'mounts':mounts,'public_ports':False})
        for name in reversed(names):
            docker('rm', '-f', '-v', name, check=False)
        if network_created:
            docker('network', 'rm', prefix, check=False)
        if firewall_table:
            run('nft', 'delete', 'table', 'inet', firewall_table, check=False)
        report={'passed':passed,'backend_image':args.api_image,'frontend_image':args.client_image,'image_checks':image_checks,'runtime_inventory':runtime_inventory,'application_or_vendor_overlays':False,'actual_pair_revision_verified_separately':True,'execution':('native AMD64 runner' if __import__('platform').machine() in ('x86_64','amd64') else 'local ARM host running linux/amd64 under emulation'),'powerloss_or_native_RPO_or_real_identity_qualified':False,'containers_removed':all(not docker('ps','-aq','--filter','name=^'+n+'$').stdout.strip() for n in names),'network_removed':not docker('network','ls','-q','--filter','name=^'+prefix+'$').stdout.strip()}
        (args.output_dir/'runtime-proof.json').write_text(json.dumps(report,indent=2)+'\n')
        print(json.dumps({'passed':passed,'containers_removed':report['containers_removed'],'network_removed':report['network_removed']}),flush=True)


if __name__ == '__main__':
    main()
