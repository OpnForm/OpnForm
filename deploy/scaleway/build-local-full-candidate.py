#!/usr/bin/env python3
"""Build current OpnForm sources with the real Dockerfiles, locally, no publication."""
import argparse,datetime,hashlib,json,os,pathlib,shutil,subprocess,tempfile,tarfile
ROOT=pathlib.Path(__file__).resolve().parents[2]
DENY_DIRS={'.git','node_modules','.nuxt','.output','coverage','dist','.idea','.vscode','__pycache__'}
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--output-dir',type=pathlib.Path,required=True);parser.add_argument('--platform',choices=['linux/amd64','linux/arm64'],default='linux/amd64');args=parser.parse_args();args.output_dir.mkdir(parents=True,exist_ok=True)
 if shutil.disk_usage(ROOT).free<12*1024**3:raise RuntimeError('Less than12GiB host margin; no cleanup attempted')
 capacity=json.loads(subprocess.check_output(['colima','ssh','--','python3','-c','import shutil,pathlib,json; m={l.split(":")[0]:l.split(":",1)[1].strip() for l in pathlib.Path("/proc/meminfo").read_text().splitlines()};print(json.dumps({"docker_free_bytes":shutil.disk_usage("/var/lib/docker").free,"available_mib":int(m["MemAvailable"].split()[0])//1024}))'],text=True));capacity['host_free_bytes']=shutil.disk_usage(ROOT).free
 if capacity['docker_free_bytes']<10*1024**3 or capacity['available_mib']<4096:raise RuntimeError('Insufficient VM build margin; no cleanup attempted')
 started=datetime.datetime.now(datetime.timezone.utc).isoformat()
 with tempfile.TemporaryDirectory(prefix='opnform-full-context-') as td:
  context=pathlib.Path(td);manifest={}
  def copy_file(src):
   rel=src.relative_to(ROOT);dest=context/rel
   if src.is_symlink():raise RuntimeError('Source symlink requires explicit review: '+str(rel))
   if not src.is_file():return
   dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(src,dest);manifest[str(rel)]=hashlib.sha256(src.read_bytes()).hexdigest()
  roots=['api/app','api/bootstrap','api/config','api/database','api/public','api/resources','api/routes','client','deploy/scaleway']
  singles=['api/artisan','api/composer.json','api/composer.lock','.dockerignore']
  def allowed(name):
   parts=pathlib.PurePosixPath(name).parts
   return (name in singles or any(name.startswith(r+'/') for r in roots)) and not any(x in DENY_DIRS for x in parts) and not any(x.startswith('.env') for x in parts) and parts[-1] not in {'.npmrc','.netrc','.sentryclirc','.DS_Store'} and not name.endswith(('.log','.pem','.key'))
  base_commit=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()
  cmd=['git','archive',base_commit,*[r for r in roots if r!='deploy/scaleway'],*singles]
  archive=subprocess.Popen(cmd,cwd=ROOT,stdout=subprocess.PIPE)
  with tarfile.open(fileobj=archive.stdout,mode='r|') as tar:
   for member in tar:
    if not allowed(member.name):continue
    if member.issym() or member.islnk():raise RuntimeError('Tracked source link requires review: '+member.name)
    if not member.isfile():continue
    dest=context/member.name;dest.parent.mkdir(parents=True,exist_ok=True)
    with tar.extractfile(member) as src,dest.open('wb') as dst:shutil.copyfileobj(src,dst)
    dest.chmod(member.mode & 0o777)
  if archive.wait()!=0:raise RuntimeError('Git archive failed')
  def overlays():
   changed=subprocess.check_output(['git','diff','--name-only','-z','HEAD','--','api','client','deploy/scaleway','.dockerignore'],cwd=ROOT).decode().split('\0')
   added=subprocess.check_output(['git','ls-files','--others','--exclude-standard','-z','--','api','client','deploy/scaleway'],cwd=ROOT).decode().split('\0')
   return sorted({n for n in changed+added if n and allowed(n)})
  overlay_files=overlays();overlay_hashes={}
  for name in overlay_files:
   src=ROOT/name
   if not src.exists():
    (context/name).unlink(missing_ok=True);overlay_hashes[name]=None
   else:
    copy_file(src);overlay_hashes[name]=hashlib.sha256(src.read_bytes()).hexdigest()
  manifest={str(f.relative_to(context)):hashlib.sha256(f.read_bytes()).hexdigest() for f in context.rglob('*') if f.is_file()}
  # Cached config may embed secrets. Only repository ignore files are accepted.
  if any(k.startswith('api/bootstrap/cache/') and not k.endswith('/.gitignore') for k in manifest):raise RuntimeError('Unexpected cached app config in build context')
  snapshot=hashlib.sha256(json.dumps(manifest,sort_keys=True).encode()).hexdigest();revision='unpublished-'+snapshot[:20]
  tags={'backend':'opnform-preparation-api:full-local-'+args.platform.split('/')[1]+'-'+snapshot[:12],'frontend':'opnform-preparation-client:full-local-'+args.platform.split('/')[1]+'-'+snapshot[:12]}
  report={'started_at':started,'capacity_before':capacity,'platform':args.platform,'status':'BUILDING','source_snapshot_sha256':snapshot,'source_revision_marker':revision,'real_release_commit':None,'source_base_commit':base_commit,'snapshot_method':'Git HEAD archive plus all tracked worktree changes and untracked source overlays; ignored secrets/dependencies excluded','overlay_hashes':overlay_hashes,'source_hashes':manifest,'sanitized_context_files':len(manifest),'secret_or_live_data_inputs':False,'tags':tags,'build_kind':'Real source Dockerfiles; ordinary build/dependency cache; no runtime source layer','dependency_network':'Public dependency downloads allowed; final Nuxt build RUN network=none','published':False,'remote_build_or_ci':False,'builds':{}}
  def save(): (args.output_dir/'proof.json').write_text(json.dumps(report,indent=2)+'\n')
  save()
  for component in ['backend','frontend']:
   dockerfile='deploy/scaleway/Dockerfile.'+('api' if component=='backend' else 'client')
   cmd=['docker','buildx','build','--load','--pull=false','--platform',args.platform,'--file',str(context/dockerfile),'--target','production','--build-arg','SOURCE_COMMIT='+revision,'--tag',tags[component],'--metadata-file',str(args.output_dir/(component+'-metadata.json')),str(context)]
   with (args.output_dir/(component+'-build.log')).open('w') as log:
    p=subprocess.run(cmd,stdout=log,stderr=subprocess.STDOUT)
   if p.returncode:report['status']='BUILD_FAILED_'+component;save();raise RuntimeError(component+' build failed; see scoped log, no cleanup performed')
   image=json.loads(subprocess.check_output(['docker','image','inspect',tags[component]],text=True))[0]
   report['builds'][component]={k:image.get(k) for k in ['Id','Descriptor','Architecture','Os','Created']};assert image.get('Architecture')==args.platform.split('/')[1]
   report['builds'][component]['revision_label']=image.get('Config',{}).get('Labels',{}).get('org.opencontainers.image.revision');save()
  report['worktree_inputs_still_match']=subprocess.check_output(['git','rev-parse','HEAD'],cwd=ROOT,text=True).strip()==base_commit and overlays()==overlay_files and all((not (ROOT/f).exists() if h is None else (ROOT/f).is_file() and hashlib.sha256((ROOT/f).read_bytes()).hexdigest()==h) for f,h in overlay_hashes.items())
  if not report['worktree_inputs_still_match']:report['status']='SOURCE_CHANGED_DURING_BUILD';save();raise RuntimeError('Worktree inputs changed; do not qualify as current source')
  report['status']='LOCAL_FULL_DOCKERFILE_BUILD_COMPLETE';report['finished_at']=datetime.datetime.now(datetime.timezone.utc).isoformat();save();print(json.dumps({k:report[k] for k in ['status','source_snapshot_sha256','source_revision_marker','tags','builds']},indent=2))
if __name__=='__main__':main()
