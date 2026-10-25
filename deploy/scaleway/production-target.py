"""Load only explicitly provisioned production resources; never infer from names."""
import json
import re


def configure(deploy, path):
    deploy.validate_target_configuration()
    target = json.loads(path.read_text())
    if set(target) != {'environment', 'server', 'apps'}:
        raise RuntimeError('Invalid production target schema')
    if target['server'] != deploy.SERVER or target['environment'] == deploy.ENVIRONMENT:
        raise RuntimeError('Production must be isolated on the approved Scaleway server')
    if not isinstance(target['apps'], dict) or set(target['apps']) != set(deploy.APPS):
        raise RuntimeError('Production requires all five application roles')
    ids = list(target['apps'].values())
    if any(not isinstance(value, str) or not re.fullmatch(r'[a-z0-9]{20,32}', value)
           for value in [target['environment'], *ids]):
        raise RuntimeError('Invalid production resource UUID')
    if len(set(ids)) != 5 or set(ids) & set(deploy.APPS.values()):
        raise RuntimeError('Production cannot reuse preview resources')
    deploy.ENVIRONMENT = target['environment']
    deploy.APPS = target['apps']
    deploy.LEGACY_SERVICE = None
    deploy.DOMAINS = {'api': 'https://api.opnform.com', 'frontend': 'https://opnform.com'}
