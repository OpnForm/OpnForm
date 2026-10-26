#!/usr/bin/env python3
"""Private outbound-only OpnForm controller. Installed code is never downloaded/executed."""
import argparse
import fcntl
import importlib.util
import json
import os
from pathlib import Path
import subprocess
import tempfile

CHANNEL = 'ghcr.io/opnform/hosted-release:production'
spec = importlib.util.spec_from_file_location('promotion', Path(__file__).with_name('coolify-service-release.py'))
promotion = importlib.util.module_from_spec(spec)
spec.loader.exec_module(promotion)


def eligible_run():
    current = promotion.github('/repos/OpnForm/OpnForm/commits/main')['sha']
    runs = promotion.github('/repos/OpnForm/OpnForm/actions/workflows/ci-cd.yml/runs?branch=main&event=push&status=success&per_page=10')['workflow_runs']
    for run in runs:
        if run['head_sha'] != current or run.get('name') != 'CI/CD Pipeline':
            continue
        artifacts = promotion.github('/repos/OpnForm/OpnForm/actions/runs/' + str(run['id']) + '/artifacts')['artifacts']
        if any(a['name'] == 'opnform-coolify-release' and not a.get('expired') for a in artifacts):
            return run
    return None


def fetch_release(directory):
    promotion.execute(['docker', 'pull', CHANNEL], timeout=120)
    # Bind extraction to the pulled image ID, never to a subsequently moved tag.
    image = json.loads(promotion.execute(['docker', 'image', 'inspect', CHANNEL], text=True))[0]
    container = promotion.execute(['docker', 'create', '--network', 'none', image['Id'], '/not-executed'], text=True).strip()
    try:
        path = directory / 'release.json'
        promotion.execute(['docker', 'cp', container + ':/release.json', str(path)])
        if path.stat().st_size > 65536:
            raise ValueError('Oversized release descriptor')
        release = json.loads(path.read_text())
        promotion.validate_release(release)
        if release['automatic_deployment'] is not True:
            raise ValueError('Release is not eligible for automatic deployment')
        if image['Config'].get('Labels', {}).get('org.opencontainers.image.revision') != release['sha']:
            raise ValueError('Deployment channel revision mismatch')
        return release, path
    finally:
        promotion.execute(['docker', 'rm', container])


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument('--target', required=True, type=Path)
    parser.add_argument('--state-dir', required=True, type=Path)
    parser.add_argument('--check-only', action='store_true')
    args = parser.parse_args()
    os.umask(0o077)
    args.state_dir.mkdir(parents=True, exist_ok=True)
    with (args.state_dir / 'controller.lock').open('w') as handle:
        fcntl.flock(handle, fcntl.LOCK_EX | fcntl.LOCK_NB)
        target = json.loads(args.target.read_text())
        promotion.validate_target(target)
        state_file = args.state_dir / 'state.json'
        state = json.loads(state_file.read_text()) if state_file.exists() else {}
        if state.get('status') in ('deploying', 'needs-inspection'):
            raise RuntimeError('Prior deployment requires inspection; no automatic retry')
        run = eligible_run()
        if not run:
            print('No eligible main release; production unchanged.')
            return
        sequence = [run['id'], run['run_attempt']]
        if state.get('sequence') == sequence:
            return
        with tempfile.TemporaryDirectory(prefix='release-', dir=args.state_dir) as temporary:
            release, path = fetch_release(Path(temporary))
            if [release['run_id'], release['run_attempt']] != sequence or release['sha'] != run['head_sha']:
                print('Waiting for the matching successful main deployment channel.')
                return
            promotion.verify_ci(release)
            promotion.controller(target, release)
            promotion.check_current(target)
            if args.check_only:
                print('Eligible main release and private target verified; no deployment requested.')
                return
            state = {'sequence': sequence, 'sha': release['sha'], 'status': 'deploying'}
            state_file.write_text(json.dumps(state) + '\n')
            journal = args.state_dir / ('deployment-' + str(run['id']) + '-' + str(run['run_attempt']) + '.json')
            try:
                subprocess.run(['python3', str(Path(__file__).with_name('coolify-service-release.py')),
                    str(path), '--target', str(args.target), '--apply', '--allow-service-restart',
                    '--journal', str(journal)], check=True)
                state['status'] = 'success'
                state_file.write_text(json.dumps(state) + '\n')
            except Exception:
                if not journal.exists() and promotion.github('/repos/OpnForm/OpnForm/commits/main')['sha'] != release['sha']:
                    state['status'] = 'superseded-before-selection'
                    state_file.write_text(json.dumps(state) + '\n')
                    return
                state['status'] = 'needs-inspection'
                state_file.write_text(json.dumps(state) + '\n')
                raise RuntimeError('Deployment stopped; inspect private journal before retry') from None


if __name__ == '__main__':
    main()
