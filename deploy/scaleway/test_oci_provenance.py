import copy
import hashlib
import importlib.util
import json
from pathlib import Path
import unittest

spec=importlib.util.spec_from_file_location('oci',Path(__file__).with_name('oci-provenance.py'))
oci=importlib.util.module_from_spec(spec)
spec.loader.exec_module(oci)

class OciMetadataTests(unittest.TestCase):
    def fixture(self, arch='amd64'):
        blobs={}
        def add(value,kind,**extra):
            raw=json.dumps(value,separators=(',',':')).encode()
            digest='sha256:'+hashlib.sha256(raw).hexdigest();blobs[digest]=raw
            return {'mediaType':kind,'digest':digest,'size':len(raw),**extra}
        config=add({'architecture':arch,'os':'linux','config':{'User':'www-data','Env':['FICTIONAL_SECRET=must-not-export-value']}},oci.CONFIG)
        manifest=add({'schemaVersion':2,'mediaType':oci.MANIFEST,'config':config,'layers':[]},oci.MANIFEST,platform={'os':'linux','architecture':'amd64'})
        attestation=copy.deepcopy(manifest);attestation['platform']={'os':'unknown','architecture':'unknown'};attestation['annotations']={'vnd.docker.reference.type':'attestation-manifest'}
        index=add({'schemaVersion':2,'mediaType':oci.INDEX,'manifests':[attestation,manifest]},oci.INDEX)
        return blobs,index,manifest,config,add

    def test_realistic_index_selects_runtime_not_attestation_and_redacts_env(self):
        blobs,index,manifest,config,_=self.fixture()
        result=oci.resolve(index,blobs.__getitem__)
        self.assertEqual(result['runtime_manifest_descriptor']['digest'],manifest['digest'])
        self.assertEqual(result['config_descriptor']['digest'],config['digest'])
        self.assertNotEqual(index['digest'],config['digest'])
        self.assertNotIn('must-not-export-value',json.dumps(result))
        self.assertEqual(result['runtime_settings']['environment_variable_names'],['FICTIONAL_SECRET'])

    def test_corrupted_child_blob_is_rejected(self):
        blobs,index,manifest,_,_=self.fixture();blobs[manifest['digest']]+=b' '
        with self.assertRaisesRegex(ValueError,'size/hash'):oci.resolve(index,blobs.__getitem__)

    def test_config_architecture_cannot_disagree_with_index(self):
        blobs,index,*_=self.fixture('arm64')
        with self.assertRaisesRegex(ValueError,'platform mismatch'):oci.resolve(index,blobs.__getitem__)

    def test_multiple_matching_runtime_entries_are_rejected(self):
        blobs,index,manifest,_,add=self.fixture()
        index=add({'schemaVersion':2,'mediaType':oci.INDEX,'manifests':[manifest,manifest]},oci.INDEX)
        with self.assertRaisesRegex(ValueError,'Exactly one'):oci.resolve(index,blobs.__getitem__)

    def test_config_digest_cannot_be_used_as_deployment_root(self):
        blobs,_,_,config,_=self.fixture()
        with self.assertRaises(ValueError):oci.resolve(config,blobs.__getitem__)

if __name__=='__main__':unittest.main()
