-- ------------------------------------------------------------------
-- SRS 1.3 "Credit Period": service bills are due 7 days after they are issued.
-- New service invoices get their due_date from models/Billing.php; this fills
-- it in for service invoices created before the rule existed.
-- Safe to run more than once (only touches rows that have no due date).
-- ------------------------------------------------------------------

UPDATE `invoice`
SET `due_date` = DATE(`date_issued`) + INTERVAL 7 DAY
WHERE `invoice_type` = 'Service'
  AND `due_date` IS NULL;
