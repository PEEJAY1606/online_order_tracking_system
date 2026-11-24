<?php
require 'db.php'; require 'functions.php'; require_admin();
$rows=$pdo->query('SELECT order_number,customer_name,customer_email,product_name,quantity,order_date,status,tracking_note,updated_at FROM orders ORDER BY order_date DESC')->fetchAll();
log_activity($pdo, 'Exported orders', count($rows) . ' order records to CSV');
header('Content-Type: text/csv; charset=utf-8'); header('Content-Disposition: attachment; filename="ordertrack-orders-' . date('Y-m-d') . '.csv"');
$out=fopen('php://output','w'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['Order Number','Customer Name','Customer Email','Product','Quantity','Order Date','Status','Tracking Note','Last Updated']);
foreach($rows as $row) fputcsv($out,array_values($row)); fclose($out); exit;
