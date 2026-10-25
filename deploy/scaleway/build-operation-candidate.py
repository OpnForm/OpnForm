#!/usr/bin/env python3
"""Build a private local source layer on the cached preparation runtime, offline."""
import hashlib,json,pathlib,shutil,subprocess,sys,tempfile
ROOT=pathlib.Path(__file__).resolve().parents[2]
BASE='opnform-scaleway-api:local'
TAG='opnform-preparation-api:operations-local'
FILES=['app/Jobs/Form/StoreFormSubmissionJob.php','app/Models/Forms/SubmissionOperation.php',
'app/Service/Forms/SubmissionOperationService.php','app/Events/Forms/FormSubmitted.php',
'app/Listeners/Forms/NotifyFormSubmission.php','app/Console/Commands/DispatchSubmissionOutbox.php',
'app/Console/Kernel.php','database/migrations/2026_10_17_200900_create_submission_operations_table.php',
'app/Http/Controllers/Content/SignedStorageUrlController.php','config/database.php','config/filesystems.php','config/queue.php']
def run(args):return subprocess.check_output(args,text=True).strip()
if shutil.disk_usage(ROOT).free<4*1024**3:raise SystemExit('At least 4 GiB free required; no pruning performed')
base_id=run(['docker','image','inspect',BASE,'--format','{{.Id}}']) # fail before build if cache absent
manifest={f:hashlib.sha256((ROOT/'api'/f).read_bytes()).hexdigest() for f in FILES}
fingerprint=hashlib.sha256(json.dumps(manifest,sort_keys=True).encode()).hexdigest()
with tempfile.TemporaryDirectory(prefix='opnform-operation-build-') as td:
 p=pathlib.Path(td)
 for f in FILES:
  dest=p/'api'/f;dest.parent.mkdir(parents=True,exist_ok=True);shutil.copy2(ROOT/'api'/f,dest)
 (p/'Dockerfile').write_text('FROM '+BASE+'\nCOPY --chown=www-data:www-data api/ /app/\nLABEL io.opnform.preparation="submission-operation-synthetic-only" io.opnform.source-manifest="'+fingerprint+'"\n')
 subprocess.run(['docker','build','--network=none','--pull=false','-t',TAG,td],check=True)
php='foreach (json_decode(getenv("FIXTURE_SOURCE_MANIFEST"),true) as $f=>$h) {if (hash_file("sha256","/app/".$f)!==$h) {fwrite(STDERR,"Source mismatch: ".$f);exit(1);}} echo "source hashes verified";'
run(['docker','run','--rm','--pull=never','--network','none','-e','FIXTURE_SOURCE_MANIFEST='+json.dumps(manifest),'--entrypoint','php',TAG,'-r',php])
report={'image':TAG,'image_id':run(['docker','image','inspect',TAG,'--format','{{.Id}}']),
'base_image_id':base_id,'architecture':run(['docker','image','inspect',TAG,'--format','{{.Architecture}}']),
'source_manifest_sha256':fingerprint,'source_hashes':manifest,'all_source_hashes_match':True,
'build_network':'none','published':False,'limit':'Local cached-runtime source-layer rebuild; native amd64 rebuild remains required'}
if len(sys.argv)>1:pathlib.Path(sys.argv[1]).write_text(json.dumps(report,indent=2)+'\n')
print(json.dumps(report,indent=2))
