"""Initialize local-only secrets; preserve every existing nonempty value."""
from pathlib import Path
import secrets
import shutil
root=Path(__file__).resolve().parents[1]
p=root/'.env'
if not p.exists():
    shutil.copyfile(root/'.env.example',p)
lines=p.read_text().splitlines()
for key in ('DB_PASSWORD','DB_ROOT_PASSWORD'):
    indices=[i for i,line in enumerate(lines) if line.startswith(key+'=')]
    if indices:
        if not lines[indices[0]].split('=',1)[1].strip().strip('"\''):
            lines[indices[0]]=key+'='+secrets.token_hex(24)
    else:
        lines.append(key+'='+secrets.token_hex(24))
p.write_text('\n'.join(lines)+'\n')
p.chmod(0o600)
print('Yerel ortam dosyası hazır; mevcut değerler korundu.')
