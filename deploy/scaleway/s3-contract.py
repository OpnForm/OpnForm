#!/usr/bin/env python3
"""Validate fictional SDK SigV4 vectors offline; never sends an S3 request."""
import argparse
import datetime
import hashlib
import hmac
import json
from pathlib import Path
import re
import urllib.parse

KEY = 'fictional-storage-key'
SECRET = 'fictional-storage-secret'


def headers_for_browser(headers):
    if headers == []:
        headers = {}
    return {name.lower(): ', '.join(value) if isinstance(value, list) else str(value)
            for name, value in headers.items() if name.lower() != 'host' and value is not None}


def valid_signature(url, method, headers, now=None, strict_query_order=True):
    uri = urllib.parse.urlsplit(url)
    params = urllib.parse.parse_qsl(uri.query, keep_blank_values=True)
    query = dict(params)
    if len(query) != len(params):
        return False
    try:
        if query['X-Amz-Algorithm'] != 'AWS4-HMAC-SHA256':
            return False
        credential = query['X-Amz-Credential'].split('/')
        access, day, region, service, terminal = credential
        if access != KEY or service != 's3' or terminal != 'aws4_request':
            return False
        stamp = datetime.datetime.strptime(query['X-Amz-Date'], '%Y%m%dT%H%M%SZ').replace(tzinfo=datetime.timezone.utc)
        now = now or datetime.datetime.now(datetime.timezone.utc)
        age = (now - stamp).total_seconds()
        if age < -300 or age > int(query['X-Amz-Expires']):
            return False
        # Match the locked SDK's canonical context: it sorts header names there,
        # even though its query SignedHeaders list can remain insertion-ordered.
        # inspect() reports that noncanonical query ordering as an open concern.
        supplied_order = query['X-Amz-SignedHeaders'].split(';')
        signed = sorted(supplied_order)
        if strict_query_order and signed != supplied_order:
            return False
        normalized = headers_for_browser(headers)
        normalized['host'] = uri.netloc
        canonical_headers = ''.join(name + ':' + re.sub(r'\s+', ' ', normalized[name].strip()) + '\n'
                                    for name in signed)
        encode = lambda value: urllib.parse.quote(value, safe='-_.~')
        canonical_query = '&'.join(encode(k) + '=' + encode(v) for k, v in sorted(params)
                                   if k != 'X-Amz-Signature')
        canonical_request = '\n'.join([method, uri.path or '/', canonical_query,
            canonical_headers, ';'.join(signed), query.get('X-Amz-Content-Sha256', 'UNSIGNED-PAYLOAD')])
        scope = '/'.join(credential[1:])
        message = '\n'.join(['AWS4-HMAC-SHA256', query['X-Amz-Date'], scope,
                             hashlib.sha256(canonical_request.encode()).hexdigest()])
        signing = ('AWS4' + SECRET).encode()
        for component in [day, region, service, terminal]:
            signing = hmac.new(signing, component.encode(), hashlib.sha256).digest()
        expected = hmac.new(signing, message.encode(), hashlib.sha256).hexdigest()
        return hmac.compare_digest(expected, query['X-Amz-Signature'])
    except (ValueError, KeyError, TypeError):
        return False


def inspect(document, require_corrected=False):
    if document.get('fixture') is not True:
        raise ValueError('Only explicitly fictional vectors supported')
    vectors = document['vectors']
    checks = []

    def require(name, condition):
        if not condition:
            raise ValueError('Storage contract failed: ' + name)
        checks.append(name)

    def signed_at(data):
        query = dict(urllib.parse.parse_qsl(urllib.parse.urlsplit(data['url']).query))
        return datetime.datetime.strptime(query['X-Amz-Date'], '%Y%m%dT%H%M%SZ').replace(tzinfo=datetime.timezone.utc)

    for name in ['private_put', 'public_put', 'metadata_put', 'override_bucket_put',
                 'private_get', 'session_put', 'legacy_put']:
        data = vectors[name]
        require(name + '_signature', valid_signature(data['url'], 'GET' if name == 'private_get' else 'PUT',
                data['headers'], now=signed_at(data) + datetime.timedelta(seconds=1),
                strict_query_order=require_corrected))
    private = vectors['private_put']
    query = dict(urllib.parse.parse_qsl(urllib.parse.urlsplit(private['url']).query))
    require('private_acl', query.get('x-amz-acl') == 'private')
    require('public_acl_explicit', 'x-amz-acl=public-read' in vectors['public_put']['url'])
    require('storage_credentials_isolated', query['X-Amz-Credential'].startswith(KEY + '/')
            and 'fictional-email' not in private['url'])
    require('put_expiry_300s', query['X-Amz-Expires'] == '300')
    get_query = dict(urllib.parse.parse_qsl(urllib.parse.urlsplit(vectors['private_get']['url']).query))
    require('get_expiry_420s', get_query['X-Amz-Expires'] == '420')
    require('unicode_key_encoded', '/r%C3%A9ponse%20%C3%A9.pdf' in vectors['private_get']['url'])
    require('download_disposition', get_query['response-content-disposition'] == 'attachment; filename="fixture.pdf"')
    require('public_cdn_url_separate', vectors['public_asset_url'] == 'https://assets.fixture.invalid/assets/logo.png'
            and urllib.parse.urlsplit(vectors['private_get']['url']).netloc == 's3.fr-par.scw.cloud')
    require('storage_session_only', 'fictional-storage-session' in urllib.parse.unquote(vectors['session_put']['url'])
            and 'fictional-email-session' not in urllib.parse.unquote(vectors['session_put']['url']))
    require('legacy_endpoint_preserved', urllib.parse.urlsplit(vectors['legacy_put']['url']).netloc == 's3.eu-west-3.amazonaws.com')
    require('missing_bucket_refused', vectors['missing_bucket_refused'] is True)
    fresh = signed_at(private) + datetime.timedelta(seconds=1)
    require('path_tamper_rejected', not valid_signature(private['url'].replace('/tmp/', '/other/'), 'PUT', private['headers'], now=fresh))
    modified_headers = dict(private['headers'], **{'x-amz-acl': ['public-read']})
    require('acl_tamper_rejected', not valid_signature(private['url'], 'PUT', modified_headers, now=fresh))
    stamp = datetime.datetime.strptime(query['X-Amz-Date'], '%Y%m%dT%H%M%SZ').replace(tzinfo=datetime.timezone.utc)
    require('expired_link_rejected', not valid_signature(private['url'], 'PUT', private['headers'],
            now=stamp + datetime.timedelta(seconds=301)))
    require('signature_tamper_rejected', not valid_signature(private['url'] + '0', 'PUT', private['headers'], now=fresh))
    normalized = headers_for_browser(private['headers'])
    required_headers = sorted(set(normalized) | set(headers_for_browser(vectors['metadata_put']['headers'])))
    require('browser_host_not_explicit', 'host' not in normalized)
    # Characterize existing behavior without claiming it is a provider policy.
    observations = {
        'content_type_signed': 'content-type' in query['X-Amz-SignedHeaders'].split(';'),
        'request_bucket_override_supported': vectors['override_bucket_put']['bucket'] == 'fixture-other-bucket',
        'required_browser_put_headers': required_headers,
        'public_assets_url_does_not_grant_public_access': True,
        'legacy_url_migration_or_rewrite_still_required': True,
        'noncanonical_signed_header_query_order': [name for name in ['private_put', 'public_put',
            'metadata_put', 'private_get', 'session_put', 'legacy_put']
            if (lambda names: names != sorted(names))(dict(urllib.parse.parse_qsl(
                urllib.parse.urlsplit(vectors[name]['url']).query))['X-Amz-SignedHeaders'].split(';'))],
        'requested_cache_control_returned': 'cache-control' in headers_for_browser(vectors['metadata_put']['headers']),
    }
    if require_corrected:
        require('canonical_signed_header_order', not observations['noncanonical_signed_header_query_order'])
        require('requested_cache_control_preserved', observations['requested_cache_control_returned'])
    return {'fixture': True, 'passed': len(checks), 'checks': checks, 'observations': observations,
            'corrected_contract_required': require_corrected,
            'provider_s3_requests': 0, 'provider_acl_cors_lifecycle_verified': False,
            'client_data': False, 'limit': 'SDK and cryptographic offline contract, not Scaleway interoperability or browser CORS'}


if __name__ == '__main__':
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('vectors', type=Path)
    parser.add_argument('--require-corrected', action='store_true')
    args = parser.parse_args()
    print(json.dumps(inspect(json.loads(args.vectors.read_text()), args.require_corrected), indent=2))
