"""Extract actual response PDFs; optional dependencies stay in the audit workspace."""
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[2] / 'tmp/pdf-audit-python'))
from pypdf import PdfReader

sys.stdout.reconfigure(encoding='utf-8')
reader = PdfReader(sys.argv[1])
print('\n'.join(page.extract_text() or '' for page in reader.pages))
