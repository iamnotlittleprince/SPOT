"""Run PHP tests in the production PHP image and a disposable PostgreSQL.
No host database, published ports, real credentials or persistent volumes are used.
"""
import base64
import os
from pathlib import Path
import secrets
import subprocess
import sys
import time

root = Path(__file__).resolve().parent.parent
output = root / 'output/production-review'
output.mkdir(parents=True, exist_ok=True)
image = sys.argv[1] if len(sys.argv) > 1 else 'spot:production-review'
name = 'spot-review-' + secrets.token_hex(4)
network, database, tests = name + '-net', name + '-db', name + '-tests'
password = secrets.token_urlsafe(24)
key = 'base64:' + base64.b64encode(secrets.token_bytes(32)).decode()

def docker(*args, **kwargs):
    return subprocess.run(['docker', *args], cwd=root, **kwargs)

try:
    docker('network', 'create', network, check=True, stdout=subprocess.DEVNULL)
    docker('run', '-d', '--name', database, '--network', network,
           '--tmpfs', '/var/lib/postgresql/data', '-e', 'POSTGRES_DB=spot',
           '-e', 'POSTGRES_USER=spot', '-e', 'POSTGRES_PASSWORD=' + password,
           'postgres:17-alpine', check=True, stdout=subprocess.DEVNULL)
    for _ in range(60):
        ready = docker('exec', database, 'pg_isready', '-h', '127.0.0.1', '-U', 'spot', '-d', 'spot', stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
        if ready.returncode == 0:
            break
        time.sleep(.5)
    else:
        raise RuntimeError('PostgreSQL temporário não ficou disponível.')
    docker('exec', database, 'psql', '-U', 'spot', '-d', 'spot', '-c', 'CREATE SCHEMA spot_testing;', check=True, stdout=subprocess.DEVNULL)
    command = ['run', '--name', tests, '--network', network]
    for source, target in [('vendor', 'vendor'), ('tests', 'tests'), ('phpunit.xml', 'phpunit.xml')]:
        command += ['--mount', f'type=bind,source={root / source},target=/app/{target},readonly']
    for item in ['APP_ENV=testing', 'APP_KEY=' + key, 'JWT_SECRET=' + secrets.token_hex(32), 'DB_CONNECTION=pgsql', 'DB_HOST=' + database,
                 'DB_PORT=5432', 'DB_DATABASE=spot', 'DB_USERNAME=spot', 'DB_PASSWORD=' + password,
                 'DB_SEARCH_PATH=spot_testing', 'MAIL_MAILER=array', 'SESSION_DRIVER=array', 'CACHE_STORE=array']:
        command += ['-e', item]
    command += ['--entrypoint', 'php', image, 'vendor/bin/phpunit', '--do-not-cache-result', '--log-junit', '/app/storage/container-phpunit.xml']
    with (output / 'container-phpunit.log').open('w') as log:
        result = docker(*command, stdout=log, stderr=subprocess.STDOUT)
    docker('cp', tests + ':/app/storage/container-phpunit.xml', str(output / 'container-phpunit.xml'), check=True)
    print('Container PHP tests exit:', result.returncode)
    sys.exit(result.returncode)
finally:
    for container in [tests, database]:
        docker('rm', '-f', container, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
    docker('network', 'rm', network, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
