#!/usr/bin/env python3
"""Targeted offline preflight reproduction. No server, cache or database is used."""
import argparse,json,pathlib,subprocess
ROOT=pathlib.Path(__file__).resolve().parents[2]
ENV={'APP_ENV':'production','APP_KEY':'base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA=',
     'JWT_SECRET':'fictitious-migration-guard-secret-32-characters','SELF_HOSTED':'false','APP_DEBUG':'false',
     'DB_CONNECTION':'pgsql','QUEUE_CONNECTION':'redis','REDIS_HOST':'queue.fixture.invalid',
     'REDIS_CACHE_HOST':'cache.fixture.invalid','REDIS_QUEUE_RETRY_AFTER':'3700','TRUSTED_PROXIES':'127.0.0.1',
     'CACHE_DRIVER':'dynamodb','SESSION_DRIVER':'dynamodb','AWS_DEFAULT_REGION':'eu-west-3',
     'AWS_ACCESS_KEY_ID':'fictional-key','AWS_SECRET_ACCESS_KEY':'fictional-secret'}
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--output',type=pathlib.Path,required=True);args=parser.parse_args();checks=[]
 for name,store,expected in [('default-dynamodb',None,0),('explicit-dynamodb','dynamodb',0),('redis-override','redis',78),('file-override','file',78)]:
  cmd=['docker','run','--rm','--pull=never','--network','none','-v',str(ROOT/'deploy/scaleway/preflight.php')+':/fixture-preflight.php:ro']
  for src,dst in [('api/config/cache.php','/app/config/cache.php'),('api/config/session.php','/app/config/session.php'),('api/config/hosted-state.php','/app/config/hosted-state.php'),('api/app/Service/Deployment/HostedStateGuard.php','/app/app/Service/Deployment/HostedStateGuard.php')]:
   cmd+=['-v',str(ROOT/src)+':'+dst+':ro']
  for k,v in {**ENV,**({'SESSION_STORE':store} if store else {})}.items():cmd+=['-e',k+'='+v]
  p=subprocess.run(cmd+['--entrypoint','php','opnform-preparation-api:operations-local','/fixture-preflight.php'],capture_output=True,text=True,timeout=30)
  if p.returncode!=expected:raise RuntimeError(name+' unexpected exit '+str(p.returncode)+': '+p.stderr[-200:])
  checks.append({'case':name,'exit':p.returncode,'passed':True})
 report={'dataset':'fictional-only','network':'none','checks':checks,'passed':True,'overlay':'source preflight read-only on cached local runtime; no rebuild/deploy','production_or_dynamodb_requests':False}
 args.output.write_text(json.dumps(report,indent=2)+'\n');print(json.dumps(report,indent=2))
if __name__=='__main__':main()
