"""Build reviewable inventory/evidence indexes from source and retained test PDFs."""
import csv
import json
import re
import subprocess
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
OUT = ROOT / 'docs/audits/pdf-integrity'
routes = json.loads((OUT / 'routes.json').read_text())
by_name = {r['name']: r for r in routes}
source = (ROOT/'tests/Feature/GlobalPdfIntegrityTest.php').read_text(encoding='utf-8')
lists = re.findall(r"'[^']+' => \['([^']+)', '([^']+)', (\d+)\]", source.split('public static function lists(): array')[1].split('#[DataProvider')[0])
tables = dict(zip(
 ['users','departments','positions','employees','teachers','programs','students','companies','modules','accounts','transactions','invoices','payments','payrolls','hr.leaves','hr.overtimes','audit','batches','classes','assessments','scores','subjects','assignments','notifications','activity'],
 ['MASTER_USER + MASTER_ROLE','MASTER_DEPARTMENT + MASTER_EMPLOYEE','MASTER_POSITION + MASTER_DEPARTMENT','MASTER_EMPLOYEE + MASTER_USER + department/position','MASTER_TEACHER + MASTER_USER + MASTER_EMPLOYEE','MASTER_PROGRAM','MASTER_STUDENT + program/batch/class','MASTER_COMPANY','MASTER_MODULE','MASTER_ACCOUNT','FINANCE_TRANSACTION + account/source repositories','FINANCE_INVOICE + FINANCE_PAYMENT + payer','FINANCE_PAYMENT + invoice/payer','MASTER_PAYROLL + MASTER_EMPLOYEE','MASTER_LEAVE + MASTER_EMPLOYEE','MASTER_OVERTIME + MASTER_EMPLOYEE','MASTER_AUDIT_LOG + MASTER_USER','MASTER_BATCH + MASTER_PROGRAM','MASTER_CLASS + batch/program/teacher','ASSESSMENTS + class/subject/teacher','MASTER_SCORE + student/assessment/assignment','MASTER_SUBJECT + MASTER_PROGRAM','MASTER_ASSIGNMENT + class/subject/teacher','MASTER_NOTIFICATION (current user)','MASTER_AUDIT_LOG (role/user scope)']))
tables['announcements']='MASTER_ANNOUNCEMENT (managed visibility)'
filters = {'users':'search, role, status','departments':'search, status','positions':'search, department, status','employees':'search, department, position, status, date_from/to (Join_Date/Created_At)','teachers':'search, teaching, status','students':'search, program, batch, class, status, registration date','invoices':'search/payer, type, status','payments':'search/payer, payment date','payrolls':'search/employee, date, role scope','scores':'search/name, category, business assessment date','subjects':'search, status','activity':'keyword, module, role/user','audit':'all history; current_page = latest 100'}
rows=[]
def add(name, route, db, scope, artifact, count, tokens, totals='N/A', template='pdf.generic_table', controller=None):
    rt=by_name.get(route,{})
    action=controller or rt.get('action','')
    module='Student' if route.startswith('student.') else ('Teacher' if route.startswith('teacher.') else (action.split('Controllers\\')[-1].split('\\')[0] if 'Controllers\\' in action else 'Automation'))
    paths={d:ROOT/'tmp/pdf-integrity'/d/(artifact+'.txt') for d in ['sqlite','mysql']}
    texts={d:p.read_text(encoding='utf-8') if p.exists() else '' for d,p in paths.items()}
    expected_count=int(count)
    checks={}
    for d,text in texts.items():
        found=re.search(r'Total Data\s*:\s*(\d+)\b',text)
        count_match=(int(found[1])==expected_count) if found else bool(text and all(t.casefold() in text.casefold() for t in tokens))
        checks[d]=bool(text and count_match and all(t.casefold() in text.casefold() for t in tokens))
    rows.append({'module':module,'name':name,'route':route,'uri':rt.get('uri','service invocation / intercepted email'),'controller':action.replace('App\\Http\\Controllers\\',''),'source':db,'filter':scope,'template':template,'source_records':expected_count,'pdf_records':expected_count if all(checks.values()) else 'UNVERIFIED','count_method':'metadata Total Data or single-document identity; see test assertions','expected_totals':totals,'pdf_totals':totals if all(checks.values()) else 'UNVERIFIED','status':'PASS fixture' if all(checks.values()) else 'FAIL / missing evidence','evidence':artifact,'checks':checks,'tokens':tokens})

for prefix,identity,count in lists:
    values=[identity]
    totals='N/A'
    if prefix=='transactions': values+=['100.000','500.000','1.250.000','1.500.000']; totals='Nominal baris 100.000; 500.000; 1.250.000; 1.500.000'
    if prefix=='payrolls': values+=['3.000.000','100.000','3.100.000']; totals='Pokok 3.000.000; potongan 100.000; net 3.100.000'
    if prefix=='invoices': values+=['2.000.000','1.500.000']; totals='Tagihan 2.000.000; sisa 1.500.000'
    if prefix=='payments': values+=['500.000']; totals='500.000'
    if prefix=='scores': values+=['88,5']; totals='Nilai 88,5'
    add('Daftar '+prefix,prefix+'.export-pdf',tables[prefix],filters.get(prefix,'search/status sesuai controller')+'; export all/today/range/current_page; sort/orientation',prefix,count,values,totals)
add('Jadwal','schedules.export-pdf','MASTER_SCHEDULE + class/subject/teacher','search; creation date (recurring schedule); unpaginated','schedules',1,['Kelas Audit','Bahasa Audit','Guru Audit'])
docs=[
 ('Invoice resmi','invoices.pdf','FINANCE_INVOICE + FINANCE_PAYMENT + student','INV-AUDIT','invoices-pdf-detail',['INV-AUDIT','Siswa Audit','2.000.000','500.000','1.500.000'],'2.000.000 / paid 500.000 / remaining 1.500.000','official_invoice'),
 ('Bukti pembayaran','payments.receipt','FINANCE_PAYMENT + invoice + receiving account','PAY-AUDIT','payments-receipt-detail',['PAY-AUDIT','Siswa Audit','500.000'],'500.000','official_receipt'),
 ('Slip gaji','payrolls.pdf','MASTER_PAYROLL + MASTER_EMPLOYEE','PRL-AUDIT; HR/Finance scope','payrolls-pdf-detail',['Pegawai Audit','3.000.000','200.000','100.000','3.100.000'],'3.000.000 + 200.000 - 100.000 = 3.100.000','official_payslip'),
 ('Persetujuan cuti','leaves.pdf','MASTER_LEAVE + employee','LEV-AUDIT','leaves-pdf-detail',['Pegawai Audit','CUTI-AUDIT','2 Hari'],'Durasi 2 hari','official_leave_approval'),
 ('Persetujuan lembur','overtimes.pdf','MASTER_OVERTIME + employee','OVT-AUDIT','overtimes-pdf-detail',['Pegawai Audit','LEMBUR-AUDIT','50.000'],'2 jam x 25.000 = 50.000','official_overtime_approval'),
 ('Invoice siswa','student.billing.invoice-pdf','FINANCE_INVOICE + payment + authenticated student','own invoice INV-AUDIT','student-billing-invoice-pdf-detail',['Siswa Audit','2.000.000','1.500.000'],'2.000.000 / remaining 1.500.000','official_invoice'),
 ('Bukti pembayaran siswa','student.billing.payment-receipt','FINANCE_PAYMENT + authenticated student','own payment PAY-AUDIT','student-billing-payment-receipt-detail',['Siswa Audit','500.000'],'500.000','official_receipt'),
 ('Presensi Academic','attendances.export-pdf','MASTER_ATTENDANCE + class roster','date, class, status, search','attendances-export-pdf-detail',['Siswa Audit','Kelas Audit','Hadir','08:00'],'Hadir 1','academic_attendance_report'),
 ('Presensi Teacher','teacher.workspace.reports.attendances-pdf','MASTER_ATTENDANCE + authorized managed roster','teacher class/date scope','teacher-workspace-reports-attendances-pdf-detail',['Siswa Audit','Kelas Audit','Hadir'],'Hadir 1','generic_table'),
 ('Presensi Student','student.export-attendances-pdf','MASTER_ATTENDANCE + authenticated student','own student','student-export-attendances-pdf-detail',['Siswa Audit','Hadir'],'Hadir 1','student_attendance_report'),
]
for name,route,db,scope,artifact,tokens,total,view in docs: add(name,route,db,scope,artifact,1,tokens,total,'pdf.'+view)
for variant in ['harian','mingguan','bulanan','rentang']:
    add('Presensi '+variant,'attendance.reports.pdf','MASTER_ATTENDANCE + class/student roster','report_type='+variant+'; class; student optional; business dates','attendance-reports-pdf-'+variant,1,['Siswa Audit','Kelas Audit'],'Hadir 1','pdf.attendance_report')
for mode in ['student','class']:
    add('Nilai Teacher '+mode,'teacher.workspace.reports.scores-pdf','MASTER_SCORE + managed student/class + subject','mode='+mode+'; entity; all_periods/date; subject/category','teacher-'+mode,1,['Siswa Audit','Kelas Audit','88,5','Semua Periode'],'Nilai 88,5','pdf.teacher_score_report')
add('Nilai Student','student.export-scores-pdf','MASTER_SCORE + authenticated student','own identity','student-scores',1,['Siswa Audit','88,5'],'Nilai 88,5','pdf.student_score_report')
add('Arus kas','reports.finance.export-pdf','FINANCE_TRANSACTION + MASTER_ACCOUNT','business dates, account ID/code, category; independent opening/period sums','cash-flow',3,['TRX-500','TRX-1250','TRX-1500','100.000','1.750.000','1.500.000','250.000','350.000'],'Opening 100.000; income 1.750.000; expense 1.500.000; net 250.000; closing 350.000')
add('Piutang','reports.finance.export-pdf','FINANCE_INVOICE + verified FINANCE_PAYMENT + payer','report_type=outstanding; type/student/company; creation date','outstanding',1,['INV-AUDIT','2.000.000','500.000','1.500.000'],'2.000.000 - 500.000 = 1.500.000')
add('Alumni pilihan','alumni.export-selected-pdf','MASTER_ALUMNI + program/batch/class','selected alumni_ids, scoped records','alumni-selected',1,['Alumni Terpilih','ALM000001'],'N/A','pdf.selected_alumni')
for mode in ['manual','student']:
    for typ in ['invoice','kwitansi']:
        val='2.000.000' if mode=='student' and typ=='invoice' else '500.000'
        add('Smart '+mode+' '+typ,'finance.smart_generator.pdf','Request manual' if mode=='manual' else 'Canonical InvoiceService + verified payments + student','doc_type/source_type/source_id; manual line arithmetic or canonical invoice','smart-'+mode+'-'+typ,1,['Klien Audit' if mode=='manual' else 'Siswa Audit',val],val,'pdf.smart_generator_'+typ)
add('QR presensi permanen','attendance.qr.pdf','MASTER_PERMANENT_QR + lifecycle/window configuration','authorized QR_ID; validity window','permanent-qr',1,['QR Kelas Audit','CLS-AUDIT'],'N/A','attendance.qr.print')
for typ,db,controller,tokens,total in [
 ('Certificate','MASTER_STUDENT + MASTER_PROGRAM','StudentService::updateStudent → DocumentAutomationService',['Siswa Audit','Program Audit'],'N/A'),
 ('AcademicReport','MASTER_STUDENT + MASTER_SCORE + assessment/assignment','StudentService::updateStudent → DocumentAutomationService',['Siswa Audit','Program Audit','88,5'],'Nilai 88,5'),
 ('Payroll','MASTER_PAYROLL + MASTER_EMPLOYEE','PayrollService::updateStatus → DocumentAutomationService',['Pegawai Audit','PRL-AUDIT','3.100.000'],'Net 3.100.000')]:
    add('Arsip otomatis '+typ,'service:'+typ,db,'persisted business identity; version/hash; private storage','automatic-'+typ,1,tokens,total,{'Certificate':'pdf.certificate','AcademicReport':'pdf.academic_report','Payroll':'pdf.official_payslip'}[typ],controller)
for typ in ['invoice','kwitansi']:
    add('Lampiran email '+typ,'finance.smart_generator.send_email','SmartGenerator payload → EmailDeliveryService','Mail::raw intercepted, no email sent','email-'+typ,1,['EMAIL-AUDIT','Penerima Audit','500.000'],'500.000','pdf.smart_generator_'+typ,'Finance\\SmartGeneratorController@sendEmail → EmailDeliveryService')

(OUT/'matrix.json').write_text(json.dumps(rows,ensure_ascii=False,indent=2),encoding='utf-8')
fields=['module','name','route','controller','source','filter','template','source_records','pdf_records','expected_totals','pdf_totals','status','evidence']
with (OUT/'matrix.csv').open('w',encoding='utf-8-sig',newline='') as f:
    writer=csv.DictWriter(f,fieldnames=fields,extrasaction='ignore');writer.writeheader();writer.writerows(rows)
lines=['# Inventaris dan bukti PDF','',f'{len(rows)} alur/varian aktif (jenis bisnis dan konteks akses dihitung terpisah). Preview/print yang berbagi payload dicatat di routes.json, bukan dihitung sebagai jenis bisnis baru.','',
'PASS fixture hanya berarti identitas/jumlah/angka pada fixture yang dicantumkan cocok di SQLite dan MySQL. Ini bukan sertifikasi semua variasi atau data produksi.','',
'| Modul | PDF / alur | Route | Controller / service | Sumber | Filter | Status |','|---|---|---|---|---|---|---|']
for r in rows: lines.append('| '+' | '.join(str(r[k]).replace('|','/').replace('\\',' / ') for k in ['module','name','route','controller','source','filter','status'])+' |')
lines+=['','## Rekonsiliasi per alur','','Jumlah pada dokumen individual berarti satu entitas/dokumen. Daftar menggunakan metadata Total Data; detail juga diperiksa lewat identitas dan nominal pada teks PDF sebenarnya. Nominal N/A berarti laporan tidak menampilkan total uang.','',
'| PDF | Record sumber | Record PDF | Angka sumber | Angka PDF | Bukti PDF / teks MySQL |','|---|---:|---:|---|---|---|']
for r in rows:
    a=r['evidence'];link=f'[PDF](../../../tmp/pdf-integrity/mysql/{a}.pdf) / [teks](../../../tmp/pdf-integrity/mysql/{a}.txt)'
    lines.append('| '+' | '.join([r['name'],str(r['source_records']),str(r['pdf_records']),r['expected_totals'],r['pdf_totals'],link])+' |')
(OUT/'inventory.md').write_text('\n'.join(lines)+'\n',encoding='utf-8')

# Explicit template inventory, including inactive legacy views and shared layouts.
templates=sorted({*list((ROOT/'resources/views/pdf').rglob('*.blade.php')),*[p for p in (ROOT/'resources/views').rglob('*.blade.php') if p.name=='pdf.blade.php'],ROOT/'resources/views/attendance/qr/print.blade.php',ROOT/'resources/views/hr/payroll/slip.blade.php'})
live={r['template'] for r in rows}
components={'pdf.layout','pdf.report_layout','pdf.print_layout','pdf.components.header','pdf.components.footer'}
lines=['# Semua template PDF dan cetak','','| Template | Klasifikasi |','|---|---|']
for p in templates:
    view=str(p.relative_to(ROOT/'resources/views')).replace('\\','/').replace('.blade.php','').replace('/','.')
    state='Aktif; bukti di inventory.md' if view in live else ('Layout/komponen bersama' if view in components else 'Dormant: tidak ada call site aktif ditemukan; tidak dinyatakan PASS')
    lines.append(f'| {view} | {state} |')
(OUT/'templates.md').write_text('\n'.join(lines)+'\n',encoding='utf-8')
print(json.dumps({'flows':len(rows),'templates':len(templates),'pass_fixture':sum(r['status']=='PASS fixture' for r in rows),'missing':[r['evidence'] for r in rows if r['status']!='PASS fixture']}))
