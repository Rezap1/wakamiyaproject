"""Render retained PDF evidence and create a machine-readable layout manifest."""
import hashlib
import json
import sys
from pathlib import Path

root = Path(__file__).resolve().parents[2]
sys.path.insert(0, str(root / 'tmp/pdf-audit-python'))
import pymupdf

source = root / (sys.argv[1] if len(sys.argv) > 1 else 'tmp/pdf-integrity/mysql')
output = source / 'rendered'
output.mkdir(parents=True, exist_ok=True)
manifest = []
thumbs = []
for path in sorted(source.glob('*.pdf')):
    doc = pymupdf.open(path)
    pages = []
    for i, page in enumerate(doc):
        target = output / f'{path.stem}-{i+1}.png'
        page.get_pixmap(matrix=pymupdf.Matrix(1.4, 1.4)).save(target)
        outside = [w[4] for w in page.get_text('words') if w[0] < -1 or w[1] < -1 or w[2] > page.rect.width+1 or w[3] > page.rect.height+1]
        pages.append({'page': i+1, 'image': str(target.relative_to(root)), 'outside_page_words': outside, 'words': len(page.get_text('words'))})
        thumbs.append((path.name+f' p{i+1}', target))
    manifest.append({'pdf': str(path.relative_to(root)), 'sha256': hashlib.sha256(path.read_bytes()).hexdigest(), 'pages': pages})

for offset in range(0, len(thumbs), 9):
    sheet = pymupdf.open()
    page = sheet.new_page(width=1260, height=1800)
    for n, (label, target) in enumerate(thumbs[offset:offset+9]):
        x, y = (n % 3) * 420, (n // 3) * 600
        page.insert_text((x+8,y+15), label[:68], fontsize=9)
        page.insert_image(pymupdf.Rect(x+8,y+24,x+412,y+592), filename=str(target), keep_proportion=True)
    page.get_pixmap().save(output / f'contact-{offset//9+1:02}.png')
(output/'manifest.json').write_text(json.dumps(manifest, indent=2), encoding='utf-8')
print(json.dumps({'documents': len(manifest), 'pages': len(thumbs), 'contact_sheets': (len(thumbs)+8)//9, 'outside_page_words': sum(len(p['outside_page_words']) for d in manifest for p in d['pages'])}))
