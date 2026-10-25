#!/usr/bin/env python3
"""Bounded, metadata-only OpnForm SQS audit. Never receives or changes messages."""
import argparse,datetime,json,os,pathlib,subprocess
ACCOUNT='650556641073'
REGION='eu-west-3'
QUEUE='OpnForm-api-production'
URL=f'https://sqs.{REGION}.amazonaws.com/{ACCOUNT}/{QUEUE}'
ARN=f'arn:aws:sqs:{REGION}:{ACCOUNT}:{QUEUE}'
ALLOWED={('sts','get-caller-identity'),('sqs','list-queues'),('sqs','get-queue-attributes'),('lambda','list-event-source-mappings'),('cloudwatch','get-metric-data')}
ATTRS=['QueueArn','ApproximateNumberOfMessages','ApproximateNumberOfMessagesNotVisible','ApproximateNumberOfMessagesDelayed','VisibilityTimeout','DelaySeconds','MessageRetentionPeriod','ReceiveMessageWaitTimeSeconds','RedrivePolicy','RedriveAllowPolicy','CreatedTimestamp','LastModifiedTimestamp']
def main():
 parser=argparse.ArgumentParser(description=__doc__);parser.add_argument('--profile',default='julien',choices=['julien']);parser.add_argument('--output',type=pathlib.Path,required=True);args=parser.parse_args()
 calls=[]
 def aws(service,operation,*flags):
  if (service,operation) not in ALLOWED:raise RuntimeError('Operation not on metadata allowlist')
  calls.append(service+':'+operation)
  p=subprocess.run(['aws','--profile',args.profile,'--region',REGION,'--no-cli-pager','--cli-connect-timeout','8','--cli-read-timeout','15','--output','json',service,operation,*flags],text=True,capture_output=True,timeout=45,env={**os.environ,'AWS_MAX_ATTEMPTS':'1'})
  if p.returncode:raise RuntimeError(service+':'+operation+' failed; '+p.stderr[-350:])
  return json.loads(p.stdout or '{}')
 identity=aws('sts','get-caller-identity')
 if identity.get('Account')!=ACCOUNT:raise RuntimeError('Wrong AWS account; stop before source metadata calls')
 listing=aws('sqs','list-queues','--queue-name-prefix','OpnForm','--max-results','20','--no-paginate')
 if URL not in listing.get('QueueUrls',[]):raise RuntimeError('Expected source queue absent from bounded list')
 attrs=aws('sqs','get-queue-attributes','--queue-url',URL,'--attribute-names',*ATTRS)['Attributes']
 if attrs.get('QueueArn')!=ARN:raise RuntimeError('Wrong queue ARN')
 mappings=aws('lambda','list-event-source-mappings','--event-source-arn',ARN,'--max-items','10','--page-size','10')
 now=datetime.datetime.now(datetime.timezone.utc);start=now-datetime.timedelta(hours=1)
 specs=[('visible','ApproximateNumberOfMessagesVisible','Maximum'),('inflight','ApproximateNumberOfMessagesNotVisible','Maximum'),('delayed','ApproximateNumberOfMessagesDelayed','Maximum'),('age','ApproximateAgeOfOldestMessage','Maximum'),('sent','NumberOfMessagesSent','Sum'),('received','NumberOfMessagesReceived','Sum'),('deleted','NumberOfMessagesDeleted','Sum')]
 queries=[{'Id':key,'MetricStat':{'Metric':{'Namespace':'AWS/SQS','MetricName':name,'Dimensions':[{'Name':'QueueName','Value':QUEUE}]},'Period':300,'Stat':stat},'ReturnData':True} for key,name,stat in specs]
 metrics=aws('cloudwatch','get-metric-data','--metric-data-queries',json.dumps(queries),'--start-time',start.isoformat(),'--end-time',now.isoformat(),'--scan-by','TimestampAscending','--max-datapoints','100','--no-paginate')
 summaries={x['Id']:{'status':x.get('StatusCode'),'points':len(x.get('Values',[])),'max':max(x.get('Values',[]),default=None),'sum':sum(x['Values']) if x.get('Values') else None} for x in metrics.get('MetricDataResults',[])}
 result={'checked_at':now.isoformat(),'account_verified':ACCOUNT,'region':REGION,'queue':QUEUE,'queue_url':URL,'queue_arn':ARN,'queue_listing':listing,'attributes':attrs,'event_source_mappings':mappings,'cloudwatch_window':{'start':start.isoformat(),'end':now.isoformat(),'period_seconds':300,'limit_points':100,'next_token_present':bool(metrics.get('NextToken')),'summaries':summaries},'cloudwatch_raw_metadata':metrics,'calls':calls,'message_bodies_read':0,'queue_mutations':0,'producer_freeze_performed':False,'drain_proven':False,'scope':'Metadata snapshot only. Approximate counters and active producers cannot prove a drained backlog or inventory payload types.'}
 args.output.parent.mkdir(parents=True,exist_ok=True);args.output.write_text(json.dumps(result,indent=2)+'\n')
 print(json.dumps({k:result[k] for k in ['checked_at','queue','attributes','cloudwatch_window','calls','message_bodies_read','queue_mutations','drain_proven']},indent=2))
if __name__=='__main__':main()
