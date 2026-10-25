#!/usr/bin/env python3
"""Targeted single-Redis recovery admission tests; fictional data, Unix sockets, no build."""
import argparse,datetime,hashlib,json,pathlib,shutil,subprocess,tempfile,time,uuid
ROOT=pathlib.Path(__file__).resolve().parents[2]
def run(*args,check=True,**kwargs):
 p=subprocess.run(args,capture_output=True,text=True,timeout=180,**kwargs)
 if check and p.returncode:raise RuntimeError(p.stdout[-7000:]+p.stderr[-2000:])
 return p

def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--output-dir',type=pathlib.Path,required=True);parser.add_argument('--filter');parser.add_argument('--unified',action='store_true',default=True);parser.add_argument('--both-state-files',action='store_true');args=parser.parse_args();args.output_dir.mkdir(parents=True,exist_ok=True)
 name='opnform-state-'+uuid.uuid4().hex[:10];volume=name+'-sockets';containers=[]
 image='opnform-scaleway-api-tests:local';redis='redis:7.4-bookworm';inputs=['api/config/cache.php','api/config/session.php','api/config/database.php','api/config/hosted-state.php','api/config/jwt.php','api/app/Service/Deployment/HostedStateGuard.php','api/app/Service/Deployment/RedisStateTopology.php','api/app/Service/Deployment/UnifiedRedisJwtStorage.php','api/app/Service/Deployment/DynamoStateCodec.php','api/app/Service/Deployment/StateRedisCodec.php','api/app/Service/Deployment/DynamoStateImporter.php','api/app/Service/Deployment/HostedStateReceiptLifecycle.php','api/tests/fixtures/HostedStateBootstrap.php','api/tests/fixtures/HostedStateImportWorker.php','api/tests/fixtures/HostedStateAofFixture.php','api/tests/Unit/Deployment/HostedStateGuardTest.php','api/tests/Unit/Deployment/UnifiedRedisStateTest.php','deploy/scaleway/preflight.php']
 hashes={f:hashlib.sha256((ROOT/f).read_bytes()).hexdigest() for f in inputs};vm_code='import shutil,pathlib,json; m={l.split(":")[0]:l.split(":",1)[1].strip() for l in pathlib.Path("/proc/meminfo").read_text().splitlines()};print(json.dumps({"docker_free_bytes":shutil.disk_usage("/var/lib/docker").free,"available_mib":int(m["MemAvailable"].split()[0])//1024}))'
 capacity=json.loads(run('colima','ssh','--','python3','-c',vm_code).stdout);capacity['host_free_bytes']=shutil.disk_usage(ROOT).free
 if capacity['host_free_bytes']<2*1024**3 or capacity['docker_free_bytes']<1024**3 or capacity['available_mib']<2048:raise RuntimeError('Insufficient margin for owned small fixtures; no cleanup attempted')
 image=json.loads(run('docker','image','inspect',image).stdout)[0]['Id']
 report={'started_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'source_sha256':hashes,'network':'none; private Unix sockets only','client_data_or_provider_calls':False,'build_performed':False,'pest_filter':args.filter,'resource_limits':{'redis_each':'128 MiB / 0.25 CPU','php_test':'384 MiB / 1 CPU'},'fixture_roles':{'topology':'one Redis/four logical DBs' if args.unified else 'historical three Redis','policy':'AOF/always/noeviction, bounded32MiB'},'image':image,'fake_receipt_signer_and_revision':True,'capacity_before':capacity}
 def docker(*args,**kw):return run('docker',*args,**kw)
 try:
  docker('volume','create',volume)
  docker('run','--rm','--pull=never','--network','none','--user','0','--mount','type=volume,src='+volume+',dst=/fixture-state','--entrypoint','sh',redis,'-c','chmod 777 /fixture-state')
  for role in (['state'] if args.unified else ['state','cache','queue']):
   c=name+'-'+role;containers.append(c)
   docker('run','-d','--memory','128m','--cpus','0.25','--name',c,'--pull=never','--network','none','--mount','type=volume,src='+volume+',dst=/fixture-state',redis,'redis-server','--port','0','--unixsocket','/fixture-state/'+role+'.sock','--unixsocketperm','777','--save','','--appendonly','yes' if role in ['state','queue'] else 'no','--appendfsync','always','--maxmemory','32mb','--maxmemory-policy','allkeys-lru' if role=='cache' else 'noeviction')
   end=time.monotonic()+20
   while time.monotonic()<end:
    if docker('exec',c,'redis-cli','-s','/fixture-state/'+role+'.sock','ping',check=False).stdout.strip()=='PONG':break
    time.sleep(.2)
   else:raise RuntimeError('Own Redis socket did not start')
  parent=pathlib.Path.home()/'.codex/tmp';parent.mkdir(parents=True,exist_ok=True)
  with tempfile.TemporaryDirectory(prefix=name+'-',dir=parent) as temp:
   temp=pathlib.Path(temp);temp.chmod(0o755)
   run('openssl','genpkey','-algorithm','RSA','-pkeyopt','rsa_keygen_bits:2048','-out',str(temp/'private.pem'))
   run('openssl','pkey','-in',str(temp/'private.pem'),'-pubout','-out',str(temp/'public.pem'))
   (temp/'revision').write_text('c'*40)
   for f in temp.iterdir():f.chmod(0o444)
   mounts=['--mount','type=volume,src='+volume+',dst=/fixture-state','--mount','type=bind,src='+str(temp)+',dst=/fixture-identity,readonly','--mount','type=bind,src='+str(temp/'revision')+',dst=/app-revision,readonly']
   for f in inputs:
    dest='/app/'+f[4:] if f.startswith('api/') else '/opt/opnform/preflight.php'
    mounts+=['--mount','type=bind,src='+str(ROOT/f)+',dst='+dest+',readonly']
   base=['docker','run','--memory','384m','--cpus','1','--rm','--pull=never','--network','none','--user','33:33','--env','OPNFORM_HOSTED_STATE_FIXTURES=true','--tmpfs','/app/vendor/pestphp/pest/.temp:mode=1777',*mounts,'--entrypoint','php',image]
   result=run(*base,'vendor/bin/pest',*(['tests/Unit/Deployment/UnifiedRedisStateTest.php','tests/Unit/Deployment/HostedStateGuardTest.php'] if args.both_state_files else ['tests/Unit/Deployment/UnifiedRedisStateTest.php' if args.unified else 'tests/Unit/Deployment/HostedStateGuardTest.php']),'--no-coverage','--colors=never','--do-not-cache-result',*(['--filter',args.filter] if args.filter else []),check=False)
   (args.output_dir/'pest-output.log').write_text(result.stdout+result.stderr);report['pest_exit']=result.returncode
   if result.returncode:raise RuntimeError((result.stdout+result.stderr)[-7000:])
   report['pest_summary']=[x.strip() for x in result.stdout.splitlines() if 'Tests:' in x or 'Duration:' in x]
   run(*base,'tests/fixtures/HostedStateAofFixture.php','write')
   docker('kill','--signal','KILL',name+'-state')
   stopped=json.loads(docker('inspect',name+'-state').stdout)[0]['State'];assert stopped['ExitCode']==137 and not stopped['Running']
   docker('start',name+'-state');end=time.monotonic()+20
   while time.monotonic()<end:
    if docker('exec',name+'-state','redis-cli','-s','/fixture-state/state.sock','ping',check=False).stdout.strip()=='PONG':break
    time.sleep(.2)
   else:raise RuntimeError('Owned AOF Redis did not recover')
   result=run(*base,'tests/fixtures/HostedStateAofFixture.php','verify')
   report['redis_daemon_sigkill_aof']={'confirmed_exit_code':stopped['ExitCode'],'checks':json.loads(result.stdout),'scope':'same local container retained /data; SIGKILL process only, not host/power-loss or independent backup restore'}
   env={'APP_ENV':'production','APP_KEY':'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=','JWT_SECRET':'fictional-only-runtime-migration-signing-key','SELF_HOSTED':'false','APP_DEBUG':'false','DB_CONNECTION':'pgsql','QUEUE_CONNECTION':'redis','REDIS_HOST':'/fixture-state/queue.sock','REDIS_PORT':'0','REDIS_CACHE_HOST':'/fixture-state/cache.sock','REDIS_CACHE_PORT':'0','REDIS_STATE_HOST':'/fixture-state/state.sock','REDIS_STATE_PORT':'0','REDIS_AUTH_HOST':'/fixture-state/state.sock','REDIS_AUTH_PORT':'0','REDIS_QUEUE_RETRY_AFTER':'3700','TRUSTED_PROXIES':'127.0.0.1'}
   cases=[]
   for case,settings,expected in [('historical-dynamodb',{'CACHE_DRIVER':'dynamodb','SESSION_DRIVER':'dynamodb'},0),('historical-override-refused',{'CACHE_DRIVER':'dynamodb','SESSION_DRIVER':'dynamodb','SESSION_STORE':'redis'},78),('redis-empty-without-receipt-refused',{'OPNFORM_STATE_MODE':'redis'},78)]:
    flags=[x for k,v in {**env,**settings}.items() for x in ['--env',k+'='+v]]
    result=run('docker','run','--rm','--pull=never','--network','none','--user','33:33',*mounts,*flags,'--entrypoint','php',image,'/opt/opnform/preflight.php',check=False)
    cases.append({'case':case,'exit':result.returncode,'expected':expected});assert result.returncode==expected,(case,result.returncode,result.stdout,result.stderr)
   report['preflight_cases']=cases
   for f in inputs:
    result=run(*base,'-l','/app/'+f[4:] if f.startswith('api/') else '/opt/opnform/preflight.php');assert 'No syntax errors' in result.stdout
   report['php_syntax_files']=len(inputs)
   report['source_unchanged']=all(hashlib.sha256((ROOT/f).read_bytes()).hexdigest()==h for f,h in hashes.items());assert report['source_unchanged']
   report['status']='TARGETED_SOURCE_OVERLAY_CHECKS_PASSED; NOT_IMAGE_OR_REAL_STATE_QUALIFICATION'
 finally:
  for c in reversed(containers):docker('rm','-f','-v',c,check=False)
  docker('volume','rm',volume,check=False)
  report['own_containers_removed']=not any(docker('ps','-aq','--filter','name='+c).stdout.strip() for c in containers)
  report['own_volume_removed']=not docker('volume','ls','-q','--filter','name='+volume).stdout.strip()
  report['finished_at']=datetime.datetime.now(datetime.timezone.utc).isoformat();(args.output_dir/'proof.json').write_text(json.dumps(report,indent=2)+'\n')
 print(json.dumps(report,indent=2))
if __name__=='__main__':main()
