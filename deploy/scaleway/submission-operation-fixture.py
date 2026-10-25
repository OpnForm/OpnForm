#!/usr/bin/env python3
"""Actual Laravel job + PostgreSQL. Disposable local network-none containers only."""
import concurrent.futures, json, os, pathlib, subprocess, sys, time, uuid
IMAGE=os.environ.get('OPNFORM_OPERATION_IMAGE','opnform-preparation-api:operations-local')
if IMAGE != 'opnform-preparation-api:operations-local':
    raise SystemExit('Only explicitly built local preparation image is allowed')
HERE=pathlib.Path(__file__).resolve().parent
SUFFIX=uuid.uuid4().hex[:12]
PG='opnform-operation-pg-'+SUFFIX
REDIS='opnform-operation-redis-'+SUFFIX
VOL='opnform-operation-socket-'+SUFFIX
LABEL='io.opnform.synthetic-operation='+SUFFIX
checks=[]
def run(args, expected=0):
    p=subprocess.run(args,text=True,capture_output=True,timeout=120)
    if p.returncode!=expected:
        raise RuntimeError(f'Fixture subprocess failed ({p.returncode}, expected {expected}): {p.stdout[-5000:]} {p.stderr[:5000]}')
    return p.stdout.strip()
def check(ok, name):
    if not ok: raise AssertionError(name)
    checks.append(name)
    print(json.dumps({'passed':True,'check':name}),flush=True)
def app(mode,key='11111111-2222-4333-8444-555555555555',expected=0,**env):
    args=['docker','run','--rm','--pull=never','--network','none','--label',LABEL,
          '--mount','type=volume,src='+VOL+',dst=/fixture-pg,readonly',
          '-v',str(HERE/'submission-operation-fixture.php')+':/fixture.php:ro',
          '-e','OPNFORM_SYNTHETIC_OPERATION_FIXTURE=network-none-local-only',
          '-e','JWT_SECRET=synthetic-only-operation-fixture-secret-do-not-use-elsewhere','-e','APP_ENV=local','-e','APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
          '-e','APP_URL=http://fixture.invalid','-e','LOG_CHANNEL=stderr','-e','DB_CONNECTION=pgsql',
          '-e','DB_HOST=/fixture-pg','-e','DB_DATABASE=operation_fixture','-e','DB_USERNAME=postgres',
          '-e','DB_PASSWORD=','-e','CACHE_STORE=array','-e','CACHE_DRIVER=array','-e','QUEUE_CONNECTION=sync',
          '-e','REDIS_HOST=/fixture-pg/redis.sock','-e','REDIS_PORT=0','-e','REDIS_PREFIX=operation_fixture_',
          '-e','MAIL_MAILER=array','-e','SESSION_DRIVER=array','-e','SELF_HOSTED=true']
    for k,v in env.items(): args+=['-e',k+'='+str(v)]
    # Tini reaps the PHP child so SIGKILL does not strand container processes.
    args+=['--entrypoint','/usr/bin/tini',IMAGE,'--','php','/fixture.php',mode,key]
    return run(args,expected)
def state(key,**expected):
    app('assert',key,FIXTURE_EXPECT=json.dumps(expected))
    check(True,'durable state '+key[-4:]+' '+json.dumps(expected,sort_keys=True))
def key(n): return '11111111-2222-4333-8444-'+str(n).zfill(12)
def parallel(mode,k):
    with concurrent.futures.ThreadPoolExecutor(max_workers=8) as pool:
        list(pool.map(lambda _:app(mode,k),range(8)))
try:
    run(['docker','volume','create','--label',LABEL,VOL])
    run(['docker','run','-d','--pull=never','--network','none','--name',PG,'--label',LABEL,
         '--read-only','--tmpfs','/var/lib/postgresql/data:rw,size=512m','--tmpfs','/tmp:rw,size=32m',
         '--mount','type=volume,src='+VOL+',dst=/var/run/postgresql',
         '-e','POSTGRES_HOST_AUTH_METHOD=trust','-e','POSTGRES_DB=operation_fixture',
         'postgres:16-bookworm','postgres','-c','listen_addresses=','-c','max_connections=40'])
    for _ in range(60):
        p=subprocess.run(['docker','exec',PG,'pg_isready','-U','postgres','-d','operation_fixture'],capture_output=True)
        if p.returncode==0:break
        time.sleep(.5)
    else:raise RuntimeError('Disposable PostgreSQL did not become ready')
    check(run(['docker','inspect',PG,'--format','{{.HostConfig.NetworkMode}}'])=='none','PostgreSQL network none')
    run(['docker','run','-d','--pull=never','--network','none','--name',REDIS,'--label',LABEL,
         '--read-only','--tmpfs','/data:rw,size=32m','--mount','type=volume,src='+VOL+',dst=/fixture-pg',
         'redis:7.4-bookworm','redis-server','--port','0','--unixsocket','/fixture-pg/redis.sock','--unixsocketperm','777','--save',''])
    for _ in range(30):
        p=subprocess.run(['docker','exec',REDIS,'redis-cli','-s','/fixture-pg/redis.sock','ping'],capture_output=True)
        if p.returncode==0:break
        time.sleep(.2)
    else:raise RuntimeError('Disposable Redis did not become ready')
    print(app('seed'),flush=True)
    parallel('job',key(1));state(key(1),operations=1,rows=1,events=1,state='dispatched',total_rows=1)
    app('job',key(1));state(key(1),rows=1,events=1,total_rows=1)
    app('job',key(1),expected=1,FIXTURE_VALUE='DIFFERENT SYNTHETIC BODY');state(key(1),rows=1,events=1,total_rows=1)
    app('job',key(2),expected=137,FIXTURE_CRASH='before-commit')
    state(key(2),operations=0,rows=0,events=0,total_rows=1)
    app('job',key(2));state(key(2),rows=1,events=1,state='dispatched',total_rows=2)
    app('job',key(3),expected=137,FIXTURE_CRASH='after-commit')
    state(key(3),rows=1,events=0,state='pending',total_rows=3)
    app('job',key(3));state(key(3),rows=1,events=1,state='dispatched',total_rows=3)
    app('job',key(4),expected=137,FIXTURE_CRASH='after-commit');app('sweep')
    state(key(4),rows=1,events=1,state='dispatched',total_rows=4)
    app('job',key(5),FIXTURE_EVENT_FAILURE='1');app('job',key(5));app('sweep')
    state(key(5),rows=1,events=1,state='ambiguous',total_rows=5)
    parallel('effect',key(1));state(key(1),effects=1,effect_state='invoked')
    app('effect',key(2),FIXTURE_EFFECT_FAILURE='1');app('effect',key(2));app('sweep')
    state(key(2),effects=1,effect_state='ambiguous')
    app('effect',key(3),expected=137,FIXTURE_CRASH='effect');state(key(3),effects=1,effect_state='invoking')
    app('sweep',FIXTURE_AGE_CLAIMS='1');app('effect',key(3));state(key(3),effects=1,effect_state='ambiguous')
    for _ in range(2):app('job',key(6),FIXTURE_LEGACY='1')
    state('queue:'+key(6),rows=1,events=1,state='dispatched',total_rows=6)
    first=json.loads(app('job',key(7),FIXTURE_PARTIAL='1'))['submission_id']
    state(key(7),rows=1,events=0,state='none',total_rows=7)
    app('job',key(8),FIXTURE_SUBMISSION_ID=first)
    app('job',key(7),FIXTURE_PARTIAL='1')
    app('job',key(9),FIXTURE_SUBMISSION_ID=first,FIXTURE_ADMIN='1')
    state(key(9),rows=1,events=1,state='dispatched',total_rows=7,value='SYNTHETIC '+key(9))
    app('job',key(10),expected=137,FIXTURE_CRASH='event')
    state(key(10),rows=1,events=1,state='dispatching',total_rows=8)
    app('job',key(10));app('sweep',FIXTURE_AGE_CLAIMS='1')
    state(key(10),rows=1,events=1,state='ambiguous',total_rows=8)
    app('dispatch',key(11));app('worker',expected=137,FIXTURE_CRASH='queue-after-outbox')
    state(key(11),rows=1,events=1,state='dispatched',total_rows=9)
    reserved=json.loads(app('queue-state'))
    check(reserved['reserved']==1 and reserved['ready']==0,'actual submission worker killed before Redis ACK; reservation retained')
    time.sleep(4.2)
    app('worker')
    state(key(11),rows=1,events=1,state='dispatched',total_rows=9)
    retried=json.loads(app('queue-state'))
    check(retried['reserved']==0 and retried['ready']==0,'actual submission reservation ACKed after retry')
    attempts=retried['attempts']
    check(len(attempts)==2 and attempts[0]['attempt']==1 and attempts[1]['attempt']==2 and attempts[0]['uuid']==attempts[1]['uuid'],'actual submission transport UUID stable across SIGKILL and attempt2')
    app('dispatch',key(12),FIXTURE_LEGACY='1');app('worker',expected=137,FIXTURE_CRASH='queue-after-outbox')
    time.sleep(4.2);app('worker')
    legacy=json.loads(app('queue-state'))['attempts'][-2:]
    check(legacy[0]['uuid']==legacy[1]['uuid'] and legacy[1]['attempt']==2,'actual legacy payload stable Redis UUID across crash')
    state('queue:'+legacy[0]['uuid'],rows=1,events=1,state='dispatched',total_rows=10)
    report={'dataset':'synthetic-only','image':IMAGE,'image_id':run(['docker','image','inspect',IMAGE,'--format','{{.Id}}']),
            'postgres_image_id':run(['docker','image','inspect','postgres:16-bookworm','--format','{{.Id}}']),
            'checks':checks,'passed':True,'network':'none; shared private Unix socket only',
            'production_touched':False,'external_delivery_verified':False}
    if len(sys.argv)>1:pathlib.Path(sys.argv[1]).write_text(json.dumps(report,indent=2)+'\n')
    print(json.dumps(report),flush=True)
finally:
    # Only remove exact UUID-scoped resources created above. No global prune.
    left=subprocess.run(['docker','ps','-aq','--filter','label='+LABEL],capture_output=True,text=True).stdout.split()
    if left:subprocess.run(['docker','rm','-f',*left],capture_output=True)
    subprocess.run(['docker','rm','-f',PG],capture_output=True)
    subprocess.run(['docker','volume','rm',VOL],capture_output=True)
