"""Use GitHub's official archive endpoint for locked commit archives (no version changes)."""
import json,re
from pathlib import Path
p=Path(__file__).resolve().parents[1]/'composer.lock'
s=json.loads(p.read_text())
for package in s.get('packages',[])+s.get('packages-dev',[]):
 d=package.get('dist',{})
 url=d.get('url','')
 m=re.fullmatch(r'https://api.github.com/repos/([^/]+/[^/]+)/zipball/([a-f0-9]+)',url)
 if m:d['url']=f'https://codeload.github.com/{m[1]}/legacy.zip/{m[2]}'
p.write_text(json.dumps(s,indent=4,ensure_ascii=False)+'\n')
