#!/usr/bin/env python3
"""Read cached OpnForm OCI metadata only; never pull, build, publish or run images."""
import argparse,datetime,hashlib,json,pathlib,re,subprocess
INDEX='application/vnd.oci.image.index.v1+json'
MANIFEST='application/vnd.oci.image.manifest.v1+json'
CONFIG='application/vnd.oci.image.config.v1+json'
TAGS={'backend':'opnform-preparation-api:operations-local','frontend':'opnform-preparation-client:amd64'}
def verify_blob(descriptor, read):
 digest=descriptor.get('digest','')
 if not re.fullmatch(r'sha256:[0-9a-f]{64}',digest):raise ValueError('Invalid OCI digest')
 size=descriptor.get('size')
 if not isinstance(size,int) or size<1 or size>2*1024*1024:raise ValueError('Unbounded OCI metadata descriptor')
 blob=read(digest)
 if len(blob)!=size or 'sha256:'+hashlib.sha256(blob).hexdigest()!=digest:raise ValueError('OCI metadata size/hash mismatch')
 return json.loads(blob)
def resolve(root, read):
 document=verify_blob(root,read)
 if document.get('mediaType')!=root.get('mediaType') or document.get('schemaVersion')!=2:raise ValueError('Unexpected OCI root type/version')
 if root['mediaType']==INDEX:
  candidates=[d for d in document.get('manifests',[]) if d.get('platform',{}).get('architecture')=='amd64' and d.get('platform',{}).get('os')=='linux' and d.get('annotations',{}).get('vnd.docker.reference.type')!='attestation-manifest']
  if len(candidates)!=1:raise ValueError('Exactly one Linux amd64 runtime manifest required')
  runtime=candidates[0]
  others=[{'digest':d.get('digest'),'platform':d.get('platform'),'reference_type':d.get('annotations',{}).get('vnd.docker.reference.type')} for d in document.get('manifests',[]) if d is not runtime]
  manifest=verify_blob(runtime,read)
 elif root['mediaType']==MANIFEST:
  runtime=root;manifest=document;others=[]
 else:raise ValueError('Only OCI image index or image manifest can identify a runtime')
 if runtime.get('mediaType')!=MANIFEST or manifest.get('mediaType')!=MANIFEST or manifest.get('schemaVersion')!=2:raise ValueError('Invalid runtime manifest')
 config_desc=manifest.get('config',{})
 if config_desc.get('mediaType')!=CONFIG:raise ValueError('Expected runtime image configuration')
 config=verify_blob(config_desc,read)
 if config.get('architecture')!='amd64' or config.get('os')!='linux':raise ValueError('Runtime configuration platform mismatch')
 settings=config.get('config',{})
 return {'root_descriptor':root,'runtime_manifest_descriptor':runtime,'config_descriptor':config_desc,
         'platform':'linux/amd64','other_index_entries':others,'metadata_chain_hash_verified':True,
         'layer_count':len(manifest.get('layers',[])),'layers_rehashed':False,
         'runtime_settings':{'user':settings.get('User'),'working_directory':settings.get('WorkingDir'),
             'entrypoint':settings.get('Entrypoint'),'cmd':settings.get('Cmd'),
             'environment_variable_names':[x.split('=',1)[0] for x in settings.get('Env',[])],
             'source_revision_label':settings.get('Labels',{}).get('org.opencontainers.image.revision'),
             'source_label':settings.get('Labels',{}).get('org.opencontainers.image.source')},
         'attestations_trusted_or_reverified':False}
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--output',type=pathlib.Path);args=parser.parse_args()
 address='/run/containerd/containerd.sock'
 if not pathlib.Path(address).is_socket():raise RuntimeError('Expected local Docker containerd socket absent; no remote/registry fallback')
 def read(digest):
  return subprocess.check_output(['ctr','--address',address,'--namespace','moby','content','get',digest],timeout=15)
 images={}
 for component,tag in TAGS.items():
  record=json.loads(subprocess.check_output(['docker','image','inspect',tag],text=True,timeout=15))[0]
  root=record.get('Descriptor',{})
  if not root:raise RuntimeError('Docker did not expose a typed OCI descriptor')
  images[component]={'local_tag':tag,'engine_image_id':record['Id'],**resolve(root,read)}
 report={'checked_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'status':'LOCAL_METADATA_VERIFIED; NOT_A_RELEASE','images':images,'registry_contacted':False,'images_run_or_changed':False,'secrets_or_raw_config_exported':False}
 rendered=json.dumps(report,indent=2)+'\n'
 if args.output:args.output.write_text(rendered)
 else:print(rendered,end='')
if __name__=='__main__':main()
