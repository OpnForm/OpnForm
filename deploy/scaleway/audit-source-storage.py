#!/usr/bin/env python3
"""Bounded metadata inventory, aggregate output only. No object bodies or writes."""
import argparse,datetime,json,os,pathlib,subprocess
ACCOUNT='650556641073';REGION='eu-west-3';BUCKET='opnforms-prod'
ALLOWED={('sts','get-caller-identity'),('s3api','get-bucket-versioning'),('s3api','get-bucket-location'),('s3api','list-objects-v2'),('s3api','list-object-versions'),('s3api','list-multipart-uploads')}
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--output',type=pathlib.Path,required=True);args=parser.parse_args();calls=[]
 def aws(service,operation,*flags):
  if (service,operation) not in ALLOWED:raise RuntimeError('Not a metadata allowlisted operation')
  calls.append(service+':'+operation)
  t0=datetime.datetime.now(datetime.timezone.utc).isoformat()
  p=subprocess.run(['aws','--profile','julien','--region',REGION,'--no-cli-pager','--cli-connect-timeout','8','--cli-read-timeout','20','--output','json',service,operation,*flags],text=True,capture_output=True,timeout=600,env={**os.environ,'AWS_MAX_ATTEMPTS':'1'})
  if p.returncode:raise RuntimeError(service+':'+operation+' failed; '+p.stderr[-300:])
  t1=datetime.datetime.now(datetime.timezone.utc).isoformat();return {'start':t0,'end':t1,'data':json.loads(p.stdout or '{}')}
 identity=aws('sts','get-caller-identity')
 if identity['data'].get('Account')!=ACCOUNT:raise RuntimeError('Wrong account; stop before S3 metadata')
 report={'started_at':datetime.datetime.now(datetime.timezone.utc).isoformat(),'account_verified':ACCOUNT,'region':REGION,'bucket':BUCKET,'read_only':True,'bodies_read':0,'keys_or_upload_ids_exported':False,'current_scan_max_items':300000,'version_scan_max_items':400000,'writes':0}
 for op in ['get-bucket-versioning','get-bucket-location']:
  report[op]=aws('s3api',op,'--bucket',BUCKET)
 # JMESPath runs after CLI's bounded JSON pagination. Only aggregates leave the process.
 queries={
  'list-objects-v2':('300000','{Count:length(Contents || `[]`),Bytes:sum(Contents[].Size || `[]`),HasMore:NextToken != `null`}'),
  'list-object-versions':('400000','{VersionCount:length(Versions || `[]`),VersionBytes:sum(Versions[].Size || `[]`),CurrentCount:length(Versions[?IsLatest==`true`] || `[]`),CurrentBytes:sum(Versions[?IsLatest==`true`].Size || `[]`),NoncurrentCount:length(Versions[?IsLatest==`false`] || `[]`),NoncurrentBytes:sum(Versions[?IsLatest==`false`].Size || `[]`),DeleteMarkerCount:length(DeleteMarkers || `[]`),LatestDeleteMarkerCount:length(DeleteMarkers[?IsLatest==`true`] || `[]`),HasMore:NextToken != `null`}'),
  'list-multipart-uploads':('1000','{UploadCount:length(Uploads || `[]`),HasMore:NextToken != `null`}')}
 for op,(limit,query) in queries.items():
  print(json.dumps({'phase':op,'started':True,'max_items':int(limit)}),flush=True)
  report[op]=aws('s3api',op,'--bucket',BUCKET,'--max-items',limit,'--page-size','1000','--query',query)
  print(json.dumps({'phase':op,**report[op]}),flush=True)
  args.output.write_text(json.dumps({**report,'status':'PARTIAL_PROGRESS','calls':calls},indent=2)+'\n')
 report['finished_at']=datetime.datetime.now(datetime.timezone.utc).isoformat();report['calls']=calls;report['bounded_scans_complete']=all(not report[op]['data']['HasMore'] for op in queries);report['status']='BOUNDED_AGGREGATE_METADATA_COMPLETE' if report['bounded_scans_complete'] else 'ITEM_CAP_REACHED; NOT_COMPLETE'
 report['limits']=['Source producers remain active; listing is not an atomic frozen manifest','No keys, payloads, customer file bytes, tags, ACLs, checksums or parts lists exported','Version/current scans may differ while writes occur; multipart byte size not collected','Normal AWS metadata request charges may apply; no resource creation or paid service activation']
 args.output.write_text(json.dumps(report,indent=2)+'\n');print(json.dumps({'status':report['status'],'complete':report['bounded_scans_complete']}))
if __name__=='__main__':main()
