#!/usr/bin/env python3
"""Private promotion for the five-role Coolify Compose service.

The default is a read-only plan. The installed main-release controller applies
successful main releases with --apply --allow-service-restart.
"""
import argparse
import base64
import fcntl
import hashlib
import ipaddress
import inspect
import json
import os
from pathlib import Path
import re
import subprocess
import time
import urllib.request

ROLES = ('api', 'frontend', 'jobs', 'scheduler', 'migrate')


def native_image_name(image):
    """Docker archives retain tags, not registry RepoDigests, across engines."""
    package, digest = image.split('@sha256:')
    role = package.rsplit('/', 1)[-1]
    return 'opnform-' + role + ':sha256-' + digest


def image_fingerprint(meta):
    # Image IDs can denote a config or an OCI index depending on the engine.
    # Bind the actual layers and runtime configuration instead of comparing types.
    record = {name: meta[name] for name in ('Architecture', 'Os', 'RootFS')}
    # Classic Docker adds legacy container-creation fields that containerd omits.
    # Missing and null OCI defaults have the same runtime meaning.
    record['Config'] = {name: meta['Config'].get(name) for name in (
        'Env', 'Entrypoint', 'Cmd', 'User', 'WorkingDir', 'Labels', 'ExposedPorts',
        'Volumes', 'StopSignal', 'Healthcheck', 'Shell')}
    return hashlib.sha256(json.dumps(record, sort_keys=True).encode()).hexdigest()


def execute(args, timeout=120, **kwargs):
    result = subprocess.run(args, capture_output=True, timeout=timeout, **kwargs)
    if result.returncode:
        raise RuntimeError(args[0] + ' failed; remote output withheld')
    return result.stdout


def validate_release(release):
    if (release.get('schema') != 'opnform-coolify-service-release/v1'
            or release.get('repository') != 'OpnForm/OpnForm'
            or release.get('ref') != 'refs/heads/main'
            or not re.fullmatch('[0-9a-f]{40}', release.get('sha', ''))
            or type(release.get('automatic_deployment')) is not bool):
        raise ValueError('A validated OpnForm main release is required')
    for name in ('run_id', 'run_attempt'):
        if type(release.get(name)) is not int or release[name] <= 0:
            raise ValueError('Missing CI run identity')
    if set(release.get('images', {})) != {'backend', 'frontend'}:
        raise ValueError('A complete image pair is required')
    for role, image in release['images'].items():
        if not re.fullmatch(re.escape('ghcr.io/opnform/hosted-' + role + '@sha256:') + '[0-9a-f]{64}', image):
            raise ValueError('Refusing a foreign or mutable image reference')


def validate_target(target):
    if (target.get('product') != 'opnform'
            or not re.fullmatch('[a-z0-9]{20,32}', target.get('service_uuid', ''))):
        raise ValueError('Explicit private OpnForm service allowlist required')
    for name in ('server_id', 'project_id', 'team_id'):
        if type(target.get(name)) is not int or target[name] < 0:
            raise ValueError('Invalid private resource allowlist')
    for name in ('controller_alias', 'native_alias'):
        if not re.fullmatch('[a-zA-Z0-9_-]+', target.get(name, '')):
            raise ValueError('Invalid SSH alias')
    if ipaddress.ip_address(target['native_address']) not in ipaddress.ip_network('100.64.0.0/10'):
        raise ValueError('The allowlisted native server must use Tailscale')
    config = Path(target['ssh_config'])
    if not config.is_absolute() or not config.is_file() or config.stat().st_mode & 0o077:
        raise ValueError('Use a private absolute SSH config file')
    for role in ('backend', 'frontend'):
        if not re.fullmatch('sha256:[0-9a-f]{64}', target.get('expected_current_images', {}).get(role, '')):
            raise ValueError('Pin the currently running image identities before promotion')
    for name in ('api_url', 'frontend_url'):
        expected = 'https://api.opnform.com' if name == 'api_url' else 'https://opnform.com'
        if target.get(name) != expected:
            raise ValueError('Refusing another product domain')


def ssh(target, alias):
    base = ['ssh', '-F', target['ssh_config'], '-o', 'BatchMode=yes', '-o',
            'StrictHostKeyChecking=yes', '-o', 'UpdateHostKeys=no', '-o',
            'ForwardAgent=no', '-o', 'IdentitiesOnly=yes', alias]
    effective = execute(base[:-1] + ['-G', alias], text=True)
    options = dict(line.split(' ', 1) for line in effective.splitlines() if ' ' in line)
    try:
        address = ipaddress.ip_address(options['hostname'])
        if address not in ipaddress.ip_network('100.64.0.0/10'):
            raise ValueError('SSH must remain on Tailscale')
        if alias == target['native_alias'] and str(address) != target['native_address']:
            raise ValueError('Native SSH alias differs from the allowlisted server address')
    except (KeyError, ValueError):
        raise ValueError('SSH must use an explicitly pinned Tailscale address') from None
    return base


def verify_ci(release):
    run = github('/repos/OpnForm/OpnForm/actions/runs/' + str(release['run_id']))
    if (run.get('name') != 'CI/CD Pipeline' or run.get('head_sha') != release['sha']
            or run.get('head_branch') != 'main' or run.get('event') != 'push'
            or run.get('status') != 'completed' or run.get('conclusion') != 'success'
            or run.get('run_attempt') != release['run_attempt']):
        raise ValueError('The exact main revision needs a fully successful CI run')
    current = github('/repos/OpnForm/OpnForm/commits/main')
    if current['sha'] != release['sha']:
        raise ValueError('A newer main revision exists; do not silently deploy stale code')


def github(path):
    token = os.environ.get('GH_TOKEN')
    if not token:
        return json.loads(execute(['gh', 'api', path], text=True))
    request = urllib.request.Request('https://api.github.com' + path, headers={
        'Authorization': 'Bearer ' + token, 'Accept': 'application/vnd.github+json',
        'User-Agent': 'opnform-production-release'})
    with urllib.request.urlopen(request, timeout=30) as response:
        return json.load(response)


def controller(target, release, apply=False, finish=False):
    payload = base64.b64encode(json.dumps({'target': target, 'release': release,
        'native_images': {role: native_image_name(image) for role, image in release['images'].items()},
        'apply': apply, 'finish': finish}).encode()).decode()
    program = r'''<?php
require '/var/www/html/vendor/autoload.php';
$app=require '/var/www/html/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$x=json_decode(base64_decode('__PAYLOAD__'),true,512,JSON_THROW_ON_ERROR);$t=$x['target'];
$s=App\Models\Service::where('uuid',$t['service_uuid'])->sole();
if($s->server_id!==$t['server_id']||$s->environment->project->id!==$t['project_id']||$s->environment->project->team_id!==$t['team_id'])throw new RuntimeException('Resource allowlist mismatch');
if($s->server->ip!==$t['native_address'])throw new RuntimeException('Native address mismatch');
$raw=json_decode($s->docker_compose_raw,true,512,JSON_THROW_ON_ERROR);
$roles=['api','frontend','jobs','scheduler','migrate'];
if(array_diff($roles,array_keys($raw['services']))||array_diff(array_keys($raw['services']),$roles))throw new RuntimeException('Unexpected service roles');
$dir='/var/www/html/storage/app/opnform-release-history/'.$s->uuid;$lease=$dir.'/.promotion-active';
if($x['finish']){
 $active=json_decode(file_get_contents($lease),true,512,JSON_THROW_ON_ERROR);
 if($active['sha']!==$x['release']['sha'])throw new RuntimeException('Promotion lease differs');
 if(!unlink($lease))throw new RuntimeException('Cannot release promotion lease');
}elseif(file_exists($lease))throw new RuntimeException('Prior promotion needs inspection');
if($x['apply']){
 if(!is_dir($dir)&&!mkdir($dir,0700,true))throw new RuntimeException('Cannot save previous configuration');
 $handle=fopen($lease,'x');if($handle===false)throw new RuntimeException('Another promotion acquired the service');
 chmod($lease,0600);fwrite($handle,json_encode(['sha'=>$x['release']['sha'],'started_at'=>time()]));fclose($handle);
 $file=$dir.'/'.$x['release']['sha'].'.json';
 if(file_exists($file))throw new RuntimeException('Prior promotion needs inspection');
 if(file_put_contents($file,$s->docker_compose_raw,LOCK_EX)===false)throw new RuntimeException('Cannot save previous configuration');chmod($file,0600);
 foreach($roles as$r)$raw['services'][$r]['image']=$x['native_images'][$r==='frontend'?'frontend':'backend'];
 $s->docker_compose_raw=json_encode($raw,JSON_THROW_ON_ERROR);$s->save();$s->parse();$s->saveComposeConfigs();
}
echo json_encode(['service_uuid'=>$s->uuid,'server_id'=>$s->server_id,'images_selected'=>$x['apply'],'containers_restarted'=>false]);
'''.replace('__PAYLOAD__', payload)
    command = (['docker', 'exec', '-i', 'coolify', 'php'] if target.get('controller_local') is True
               else ssh(target, target['controller_alias']) + ['docker exec -i coolify php'])
    return json.loads(execute(command, input=program, text=True))


def native(target, program):
    return json.loads(execute(ssh(target, target['native_alias']) + ['python3 -'], input=program, text=True))


def check_current(target):
    payload = json.dumps({'uuid': target['service_uuid'], 'expected': target['expected_current_images']})
    return native(target, """import subprocess,json
x=json.loads(__PAYLOAD__);roles=['api','frontend','jobs','scheduler']
rows=json.loads(subprocess.check_output(['docker','inspect',*[r+'-'+x['uuid']for r in roles]]))
for r in rows:
 role=r['Name'].lstrip('/').split('-')[0];component='frontend'if role=='frontend'else'backend'
 assert r['Image']==x['expected'][component],'Live image identity drifted'
 assert r['State']['Running']and r['State'].get('Health',{}).get('Status')=='healthy','Live role is not healthy'
print(json.dumps({'current_roles_healthy':True,'current_image_identities_match':True}))
""".replace('__PAYLOAD__', repr(payload)))


def stage_images(target, release):
    for image in release['images'].values():
        execute(['docker', 'pull', '--platform', 'linux/amd64', image], timeout=900)
        meta = json.loads(execute(['docker', 'image', 'inspect', image], text=True))[0]
        if (meta['Config']['Labels'].get('org.opencontainers.image.revision') != release['sha']
                or meta['Architecture'] != 'amd64' or meta['Os'] != 'linux'):
            raise ValueError('Image revision differs from the reviewed release')
        selected = native_image_name(image)
        execute(['docker', 'tag', image, selected])
        source = subprocess.Popen(['docker', 'save', selected], stdout=subprocess.PIPE, stderr=subprocess.DEVNULL)
        try:
            loaded = subprocess.run(ssh(target, target['native_alias']) + ['docker load'],
                                    stdin=source.stdout, capture_output=True, timeout=1200)
            source.stdout.close()
            if source.wait(timeout=30) or loaded.returncode:
                raise RuntimeError('Private image staging failed; live containers unchanged')
        finally:
            if source.poll() is None:
                source.terminate()
        expected = image_fingerprint(meta)
        observed = native(target, 'import subprocess,json,hashlib\n' + inspect.getsource(image_fingerprint)
            + "\nx=json.loads(subprocess.check_output(['docker','image','inspect'," + repr(selected)
            + "]))[0]\nprint(json.dumps({'fingerprint':image_fingerprint(x)}))\n")
        if observed['fingerprint'] != expected:
            raise ValueError('Staged image layers/configuration differ from the registry image')


def remember_current_images(target, release, path):
    """Advance the private image pins only after every role and public gate passed."""
    references = {role: native_image_name(image) for role, image in release['images'].items()}
    payload = repr(json.dumps({'uuid': target['service_uuid'], 'images': references}))
    images = native(target, """import json,subprocess
x=json.loads(__PAYLOAD__)
expected={role:json.loads(subprocess.check_output(['docker','image','inspect',image]))[0]['Id']for role,image in x['images'].items()}
for role in ['api','frontend','jobs','scheduler']:
 row=json.loads(subprocess.check_output(['docker','inspect',role+'-'+x['uuid']]))[0]
 assert row['State']['Running']and row['State'].get('Health',{}).get('Status')=='healthy'
 assert row['Image']==expected['frontend'if role=='frontend'else'backend']
print(json.dumps(expected))
""".replace('__PAYLOAD__', payload))
    updated = {**target, 'expected_current_images': images}
    temporary = path.with_suffix('.next')
    temporary.write_text(json.dumps(updated, indent=2) + '\n')
    temporary.chmod(0o600)
    temporary.replace(path)


def promote(target, release, journal):
    payload = repr(json.dumps({'uuid': target['service_uuid'], 'sha': release['sha']}))
    program = r'''import subprocess,json,pathlib,time
x=json.loads(__PAYLOAD__);base=pathlib.Path('/data/coolify/services')/x['uuid'];assert base.is_dir()
compose=['docker','compose','--project-directory',str(base),'--env-file',str(base/'.env'),'-f',str(base/'docker-compose.yml')]
def run(args):
 p=subprocess.run(args,capture_output=True,text=True,timeout=4000)
 if p.returncode:raise RuntimeError('Role operation failed; inspect private Docker logs')
 return p.stdout.strip()
def healthy(name,seconds=240):
 end=time.monotonic()+seconds
 while time.monotonic()<end:
  row=json.loads(run(['docker','inspect',name]))[0]
  if row['State'].get('Health',{}).get('Status')=='healthy':return
  if not row['State']['Running']:raise RuntimeError('Role exited before health')
  time.sleep(3)
 raise RuntimeError('Health timeout; inspect before retry')
migration='opnform-release-migrate-'+x['sha'][:16]
run(compose+['--profile','schema-migration','run','-d','--no-deps','--pull','never','--name',migration,'migrate'])
healthy(migration,1800)
# Migration readiness is a marker; its process intentionally keeps sleeping.
run(['docker','stop','--time','20',migration]);run(['docker','rm',migration])
for role in ['jobs','scheduler']:
 run(['docker','stop','--time','3700',role+'-'+x['uuid']])
for role,profile in [('api','application-prepared'),('frontend','application-prepared'),('jobs','writers-approved'),('scheduler','writers-approved')]:
 run(compose+['--profile',profile,'up','-d','--no-deps','--pull','never',role]);healthy(role+'-'+x['uuid'])
print(json.dumps({'roles_healthy':True,'revision':x['sha']}))
'''.replace('__PAYLOAD__', payload)
    # The long wait is confined to an explicitly requested promotion, never CI/merge.
    result = subprocess.run(ssh(target, target['native_alias']) + ['python3 -'], input=program,
                            text=True, capture_output=True, timeout=12000)
    if result.returncode:
        raise RuntimeError('Promotion stopped; inspect journal and private role logs before retry')
    journal['runtime'] = json.loads(result.stdout)


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('release', type=Path)
    parser.add_argument('--target', required=True, type=Path)
    parser.add_argument('--apply', action='store_true')
    parser.add_argument('--allow-service-restart', action='store_true')
    parser.add_argument('--journal', type=Path)
    args = parser.parse_args()
    release = json.loads(args.release.read_text()); target = json.loads(args.target.read_text())
    validate_release(release); validate_target(target); verify_ci(release)
    controller(target, release); check_current(target)
    if not args.apply:
        print(json.dumps({'plan_only': True, 'sha': release['sha'], 'service': target['service_uuid'],
                          'images': release['images'], 'roles': ROLES, 'live_containers_unchanged': True}))
        return
    if not args.allow_service_restart or not args.journal:
        parser.error('Promotion requires --allow-service-restart and a private --journal')
    os.umask(0o077)
    with args.journal.with_suffix('.lock').open('w') as lock:
        fcntl.flock(lock, fcntl.LOCK_EX | fcntl.LOCK_NB)
        if args.journal.exists():
            raise RuntimeError('A prior promotion journal exists; reconcile it before retry')
        stage_images(target, release)
        verify_ci(release)
        check_current(target)
        journal = {'sha': release['sha'], 'started_at': time.time(), 'status': 'selecting-images',
                   'previous_images': target['expected_current_images']}
        def save():
            args.journal.write_text(json.dumps(journal, indent=2) + '\n')
        save()
        try:
            controller(target, release, apply=True)
            journal['status'] = 'migrating-and-promoting'; save()
            promote(target, release, journal)
            for base, route in [(target['api_url'], '/healthz'), (target['frontend_url'], '/release.json')]:
                with urllib.request.urlopen(base + route, timeout=20) as response:
                    if json.load(response).get('revision') != release['sha']:
                        raise RuntimeError('Public revision does not match the selected release')
            remember_current_images(target, release, args.target)
            controller(target, release, finish=True)
            journal['status'] = 'success'; journal['finished_at'] = time.time(); save()
        except Exception:
            journal['status'] = 'needs-inspection'; save()
            raise
        if target.get('controller_local') is True:
            # Only this controller's just-staged references; never native images
            # or a shared Docker prune. Images still in use cannot be removed.
            for image in release['images'].values():
                for reference in (native_image_name(image), image):
                    subprocess.run(['docker', 'image', 'rm', reference], capture_output=True, timeout=60)
        print('Selected release is healthy. AWS, DNS, databases and other services were not reconfigured.')


if __name__ == '__main__':
    main()
