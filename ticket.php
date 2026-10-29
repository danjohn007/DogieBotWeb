<?php
require __DIR__.'/app/bootstrap.php';
require_auth();
require_module('invoices',false);
$id=(int)($_GET['id']??0);
if($id<1){http_response_code(400);exit('Ticket no válido.');}
try{
  $row=db_one($pdo,'SELECT f.mime_type,f.file_size,f.file_data FROM dg_invoice_files f INNER JOIN dg_invoice_requests i ON i.id=f.invoice_id WHERE f.invoice_id=? LIMIT 1',[$id]);
}catch(Throwable $e){
  http_response_code(503);exit('El almacenamiento de tickets todavía no está inicializado. Ejecuta la actualización de Facturación en la base de datos.');
}
if(!$row||$row['file_data']===null){http_response_code(404);exit('No se encontró la imagen del ticket.');}
$mime=(string)($row['mime_type']??'application/octet-stream');
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true))$mime='application/octet-stream';
header('Content-Type: '.$mime);
header('Content-Length: '.strlen($row['file_data']));
header('Content-Disposition: inline; filename="ticket_'.$id.'.'.($mime==='image/png'?'png':($mime==='image/webp'?'webp':'jpg')).'"');
header('Cache-Control: private, no-store, max-age=0');
header('X-Content-Type-Options: nosniff');
echo $row['file_data'];
