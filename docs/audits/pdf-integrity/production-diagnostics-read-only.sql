-- PREPARED ONLY: production execution requires explicit approval.
-- Use a read-only account; run section 1 first and stop on schema differences.
-- November 2026 is an example period to review before execution.
-- No DDL, migrations, secrets, or data changes.
START TRANSACTION READ ONLY;

-- 1. Environment and deployed column contract.
SELECT DATABASE() AS database_name, VERSION() AS database_version, @@session.time_zone AS session_timezone;
SELECT TABLE_NAME, COLUMN_NAME, DATA_TYPE, IS_NULLABLE FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ('MASTER_ACCOUNT','FINANCE_TRANSACTION','FINANCE_INVOICE','FINANCE_PAYMENT','MASTER_SCORE','MASTER_STUDENT','MASTER_CLASS','MASTER_TEACHER','MASTER_SCHEDULE','MASTER_PAYROLL','MASTER_OVERTIME','MASTER_DOCUMENT')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- 2. Duplicate business IDs and ambiguous ID/code account references.
SELECT Transaction_ID, COUNT(*) AS duplicate_count FROM FINANCE_TRANSACTION GROUP BY Transaction_ID HAVING COUNT(*) > 1 LIMIT 200;
SELECT Account_ID, COUNT(*) AS duplicate_count FROM MASTER_ACCOUNT GROUP BY Account_ID HAVING COUNT(*) > 1 LIMIT 200;
SELECT Account_Code, COUNT(*) AS duplicate_count FROM MASTER_ACCOUNT WHERE NULLIF(TRIM(Account_Code),'') IS NOT NULL GROUP BY Account_Code HAVING COUNT(*) > 1 LIMIT 200;
SELECT a.Account_ID, a.Account_Code, b.Account_ID AS conflicting_id FROM MASTER_ACCOUNT a JOIN MASTER_ACCOUNT b
ON BINARY TRIM(a.Account_Code) = BINARY TRIM(b.Account_ID) WHERE BINARY a.Account_ID <> BINARY b.Account_ID LIMIT 200;

-- 3. Orphans and inactive accounts. Inactive masters can be valid historical references.
SELECT t.Transaction_ID, t.Account_ID, t.Transaction_Date, t.Amount FROM FINANCE_TRANSACTION t LEFT JOIN MASTER_ACCOUNT a
ON BINARY TRIM(t.Account_ID) = BINARY TRIM(a.Account_ID) OR BINARY TRIM(t.Account_ID) = BINARY TRIM(a.Account_Code)
WHERE a.Account_ID IS NULL LIMIT 200;
SELECT t.Transaction_ID, a.Account_ID, a.Is_Active FROM FINANCE_TRANSACTION t JOIN MASTER_ACCOUNT a
ON BINARY TRIM(t.Account_ID) = BINARY TRIM(a.Account_ID) OR BINARY TRIM(t.Account_ID) = BINARY TRIM(a.Account_Code)
WHERE UPPER(TRIM(CAST(a.Is_Active AS CHAR))) IN ('0','FALSE') LIMIT 200;

-- 4. Independent ledger arithmetic. Inspect every Type/status bucket first.
SELECT Type, Is_Active, COUNT(*) AS records, SUM(Amount) AS amount FROM FINANCE_TRANSACTION
WHERE Transaction_Date >= '2026-11-01' AND Transaction_Date < '2026-12-01' GROUP BY Type, Is_Active;
SELECT
SUM(CASE WHEN Transaction_Date < '2026-11-01' THEN CASE
 WHEN LOWER(TRIM(Type)) IN ('income','pemasukan','masuk','revenue','pendapatan') THEN Amount
 WHEN LOWER(TRIM(Type)) IN ('expense','pengeluaran','keluar','cost','biaya','beban') THEN -Amount ELSE 0 END ELSE 0 END) AS opening_balance,
SUM(CASE WHEN Transaction_Date >= '2026-11-01' AND Transaction_Date < '2026-12-01' AND LOWER(TRIM(Type)) IN ('income','pemasukan','masuk','revenue','pendapatan') THEN Amount ELSE 0 END) AS income,
SUM(CASE WHEN Transaction_Date >= '2026-11-01' AND Transaction_Date < '2026-12-01' AND LOWER(TRIM(Type)) IN ('expense','pengeluaran','keluar','cost','biaya','beban') THEN Amount ELSE 0 END) AS expense
FROM FINANCE_TRANSACTION WHERE UPPER(TRIM(COALESCE(CAST(Is_Active AS CHAR),'1'))) NOT IN ('0','FALSE');
SELECT Transaction_ID, Transaction_Date, Type, Amount FROM FINANCE_TRANSACTION
WHERE Transaction_Date IS NULL OR Amount IS NULL OR Amount < 0
OR LOWER(TRIM(COALESCE(Type,''))) NOT IN ('income','pemasukan','masuk','revenue','pendapatan','expense','pengeluaran','keluar','cost','biaya','beban') LIMIT 200;

-- 5. Different transaction IDs can still post the same business event twice.
SELECT Reference_Type, Reference_ID, COUNT(*) AS ledger_rows, SUM(Amount) AS amount FROM FINANCE_TRANSACTION
WHERE LOWER(TRIM(Reference_Type)) IN ('payment','paymentreversal','payroll')
AND UPPER(TRIM(COALESCE(CAST(Is_Active AS CHAR),'1'))) NOT IN ('0','FALSE')
GROUP BY Reference_Type, Reference_ID HAVING COUNT(*) > 1 LIMIT 200;

-- 6. Accepted payment versus ledger net, preserving payment/reversal pairs.
SELECT p.Payment_ID, p.Status, p.Amount_Paid, COALESCE(l.cash_net,0) AS ledger_net,
CASE WHEN LOWER(TRIM(p.Status)) = 'verified' THEN p.Amount_Paid ELSE 0 END AS expected_net
FROM FINANCE_PAYMENT p LEFT JOIN (
 SELECT Reference_ID, SUM(CASE WHEN LOWER(TRIM(Reference_Type)) = 'payment' THEN Amount ELSE -Amount END) AS cash_net
 FROM FINANCE_TRANSACTION WHERE LOWER(TRIM(Reference_Type)) IN ('payment','paymentreversal')
 AND UPPER(TRIM(COALESCE(CAST(Is_Active AS CHAR),'1'))) NOT IN ('0','FALSE') GROUP BY Reference_ID
) l ON BINARY p.Payment_ID = BINARY l.Reference_ID
WHERE COALESCE(l.cash_net,0) <> CASE WHEN LOWER(TRIM(p.Status)) = 'verified' THEN p.Amount_Paid ELSE 0 END LIMIT 200;
SELECT p.Payment_ID, p.Invoice_ID FROM FINANCE_PAYMENT p LEFT JOIN FINANCE_INVOICE i
ON BINARY p.Invoice_ID = BINARY i.Invoice_ID WHERE NULLIF(TRIM(p.Invoice_ID),'') IS NOT NULL AND i.Invoice_ID IS NULL LIMIT 200;
SELECT i.Invoice_ID, i.Amount, i.Status, COALESCE(p.verified_paid,0) AS verified_paid, i.Amount - COALESCE(p.verified_paid,0) AS arithmetic_remaining
FROM FINANCE_INVOICE i LEFT JOIN (
 SELECT Invoice_ID, SUM(Amount_Paid) AS verified_paid FROM FINANCE_PAYMENT WHERE LOWER(TRIM(Status)) = 'verified'
 AND UPPER(TRIM(COALESCE(CAST(Is_Active AS CHAR),'1'))) NOT IN ('0','FALSE') GROUP BY Invoice_ID
) p ON BINARY p.Invoice_ID = BINARY i.Invoice_ID
WHERE i.Amount < COALESCE(p.verified_paid,0) OR (LOWER(TRIM(i.Status)) = 'paid' AND i.Amount <> COALESCE(p.verified_paid,0)) LIMIT 200;
-- Education caps, collectibility, Draft/Cancelled and ledger Type require canonical
-- FinanceReconciliationService review before treating candidates as proven corruption.

-- 7. Missing student references, null/invalid historical metadata and unscheduled classes.
SELECT sc.Score_ID, sc.Student_ID, sc.Subject_ID, sc.Schedule_ID, sc.Created_At,
CASE WHEN JSON_VALID(sc.Evaluation_Details) THEN JSON_UNQUOTE(JSON_EXTRACT(sc.Evaluation_Details,'$.assessment_date')) ELSE NULL END AS assessment_date,
CASE WHEN JSON_VALID(sc.Evaluation_Details) THEN JSON_UNQUOTE(JSON_EXTRACT(sc.Evaluation_Details,'$.class_id')) ELSE NULL END AS recorded_class_id
FROM MASTER_SCORE sc LEFT JOIN MASTER_STUDENT st ON BINARY sc.Student_ID = BINARY st.Student_ID
WHERE st.Student_ID IS NULL OR sc.Created_At IS NULL OR (sc.Evaluation_Details IS NOT NULL AND NOT JSON_VALID(sc.Evaluation_Details)) LIMIT 200;
SELECT Score_ID, COUNT(*) AS duplicate_count FROM MASTER_SCORE GROUP BY Score_ID HAVING COUNT(*) > 1 LIMIT 200;
SELECT c.Class_ID, c.Homeroom_Teacher_ID, COUNT(DISTINCT s.Schedule_ID) AS teaching_schedules, COUNT(DISTINCT st.Student_ID) AS students
FROM MASTER_CLASS c LEFT JOIN MASTER_SCHEDULE s ON BINARY c.Class_ID = BINARY s.Class_ID
LEFT JOIN MASTER_STUDENT st ON BINARY c.Class_ID = BINARY st.Class_ID
GROUP BY c.Class_ID,c.Homeroom_Teacher_ID HAVING COUNT(DISTINCT s.Schedule_ID) = 0 LIMIT 200;

-- 8. Legacy archive metadata loss. Recovery requires retained files/backups.
SELECT Document_ID, Module, Reference_ID, Status, Generated_Date,
CASE WHEN JSON_VALID(Content) THEN JSON_UNQUOTE(JSON_EXTRACT(Content,'$.format')) ELSE NULL END AS metadata_format
FROM MASTER_DOCUMENT WHERE Content IS NULL OR NOT JSON_VALID(Content)
OR (JSON_VALID(Content) AND COALESCE(JSON_UNQUOTE(JSON_EXTRACT(Content,'$.format')),'') <> 'wms.document.v1') LIMIT 200;
SELECT Payroll_ID, Period, Payroll_Period, Base_Salary, Total_Allowances, Total_Deductions, Net_Salary
FROM MASTER_PAYROLL WHERE Net_Salary <> COALESCE(Base_Salary,0) + COALESCE(Total_Allowances,0) - COALESCE(Total_Deductions,0) LIMIT 200;
-- Bonus/overtime/tax details must be reconciled before concluding a payroll mismatch.
COMMIT;
