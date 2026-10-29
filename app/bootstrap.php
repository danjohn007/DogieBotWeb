<?php
$configFile=dirname(__DIR__).'/config.php';
if(!is_file($configFile)){http_response_code(500);exit('Falta config.php');}
$config=require $configFile;
date_default_timezone_set($config['timezone']??'America/Mexico_City');
$secure=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
session_name('doggiebot_crm');
session_set_cookie_params(['lifetime'=>0,'path'=>$config['base_path']??'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);
if(session_status()!==PHP_SESSION_ACTIVE)session_start();
header('X-Frame-Options: SAMEORIGIN');header('X-Content-Type-Options: nosniff');header('Referrer-Policy: same-origin');
try{
  $d=$config['db'];
  $pdo=new PDO(sprintf('mysql:host=%s;dbname=%s;charset=%s',$d['host'],$d['name'],$d['charset']??'utf8mb4'),$d['user'],$d['password'],[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
  ]);
}catch(Throwable $e){http_response_code(500);exit('<h2>No se pudo conectar a MySQL</h2><p>Revisa config.php e importa doggiebot.sql.</p>');}

function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
function db_one(PDO $p,string $s,array $a=[]):?array{$q=$p->prepare($s);$q->execute($a);$r=$q->fetch();return $r?:null;}
function db_all(PDO $p,string $s,array $a=[]):array{$q=$p->prepare($s);$q->execute($a);return $q->fetchAll();}
function db_value(PDO $p,string $s,array $a=[]){$q=$p->prepare($s);$q->execute($a);return $q->fetchColumn();}
function csrf_token():string{if(empty($_SESSION['csrf']))$_SESSION['csrf']=bin2hex(random_bytes(32));return $_SESSION['csrf'];}
function csrf_input():string{return '<input type="hidden" name="csrf" value="'.h(csrf_token()).'">';}
function verify_csrf():void{$t=$_POST['csrf']??'';if(!$t||!hash_equals($_SESSION['csrf']??'',$t)){http_response_code(419);exit('Sesión expirada.');}}
function logged_in():bool{return !empty($_SESSION['dg_user']['id']);}
function current_user():?array{return $_SESSION['dg_user']??null;}
function base_url(string $p=''):string{global $config;return rtrim($config['base_path']??'/doggiebot','/').'/'.ltrim($p,'/');}
function absolute_url(string $p=''):string{
  $host=preg_replace('/[^A-Za-z0-9.:-]/','',$_SERVER['HTTP_HOST']??$_SERVER['SERVER_NAME']??'');
  $https=!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off';
  $proto=$https?'https':'http';
  return $host?($proto.'://'.$host.base_url($p)):base_url($p);
}
function require_auth():void{if(!logged_in()){header('Location: '.base_url('login'));exit;}}
function redirect_to(string $u):never{header('Location: '.$u);exit;}
function flash(string $t,string $m):void{$_SESSION['flash'][]=['type'=>$t,'message'=>$m];}
function pull_flashes():array{$f=$_SESSION['flash']??[];unset($_SESSION['flash']);return $f;}
function is_admin():bool{return (current_user()['role']??'')==='admin';}
function require_admin():void{if(!is_admin())throw new RuntimeException('Acción restringida al administrador.');}
function fmt_dt($v):string{if(!$v)return '—';try{$d=new DateTime((string)$v,new DateTimeZone('UTC'));$d->setTimezone(new DateTimeZone(date_default_timezone_get()));return $d->format('d/m/Y H:i');}catch(Throwable $e){return (string)$v;}}
function fmt_date($v):string{if(!$v)return '—';try{$d=DateTime::createFromFormat('!Y-m-d',(string)$v);if(!$d)$d=DateTime::createFromFormat('!d/m/Y',(string)$v);return $d?$d->format('d/m/Y'):(string)$v;}catch(Throwable $e){return (string)$v;}}
function status_class(string $s):string{$s=strtolower($s);if(in_array($s,['confirmed','completed','sent','closed','resolved','active'],true))return 'success';if(in_array($s,['pending','new','review','in_process'],true))return 'warning';if(in_array($s,['cancelled','error','inactive'],true))return 'danger';return 'neutral';}
function setting(PDO $p,string $k,string $f=''):string{$v=db_value($p,'SELECT setting_value FROM dg_settings WHERE setting_key=?',[$k]);return $v===false||$v===null?$f:(string)$v;}
function setting_nonempty(PDO $p,string $k,string $f=''):string{$v=trim(setting($p,$k,''));return $v!==''?$v:$f;}

function permission_catalog():array{return [
  'dashboard'=>['Dashboard','Ver indicadores y clasificación de clientes.'],
  'contacts'=>['Contactos','Ver contactos y su clasificación.'],
  'products'=>['Menú / Productos','Consultar y administrar el menú.'],
  'orders'=>['Pedidos Pick&Go','Consultar pedidos y actualizar su estado.'],
  'reservations'=>['Reservaciones','Consultar reservaciones y actualizar su estado.'],
  'invoices'=>['Facturación','Consultar solicitudes de factura y actualizar su estado.'],
  'events'=>['Eventos / Catering','Consultar solicitudes de eventos y actualizar su estado.'],
  'messages'=>['Plantillas','Consultar y enviar mensajes por plantilla.'],
];}
function user_permissions():array{
  static $cache=null; global $pdo;
  if($cache!==null)return $cache;
  if(is_admin()){
    $cache=[];foreach(permission_catalog() as $k=>$v)$cache[$k]=['view'=>1,'edit'=>1];return $cache;
  }
  $uid=(int)(current_user()['id']??0);$cache=[];
  try{$rows=db_all($pdo,'SELECT module_key,can_view,can_edit FROM dg_user_permissions WHERE user_id=?',[$uid]);foreach($rows as $r)$cache[$r['module_key']]=['view'=>(int)$r['can_view'],'edit'=>(int)$r['can_edit']];}catch(Throwable $e){}
  return $cache;
}
function can_view_module(string $m):bool{if(is_admin())return true;$p=user_permissions();return !empty($p[$m]['view']);}
function can_edit_module(string $m):bool{if(is_admin())return true;$p=user_permissions();return !empty($p[$m]['view'])&&!empty($p[$m]['edit']);}
function require_module(string $m,bool $edit=false):void{if($edit?!can_edit_module($m):!can_view_module($m))throw new RuntimeException($edit?'No tienes permiso para modificar este módulo.':'No tienes permiso para ver este módulo.');}
function first_allowed_page():string{foreach(array_keys(permission_catalog()) as $m)if(can_view_module($m))return $m;return 'profile';}

function firebase_post(PDO $pdo,array $config,array $payload):array{
  $url=trim(setting_nonempty($pdo,'firebase_crm_url',$config['firebase_crm_url']??''));$key=trim($config['firebase_admin_key']??'');
  if(!$url||!$key)return ['ok'=>false,'http'=>0,'data'=>['error'=>'Configura el endpoint y admin key.']];
  $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_POST=>true,CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>120,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-Admin-Key: '.$key],CURLOPT_POSTFIELDS=>json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)]);
  $raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  if($raw===false)return ['ok'=>false,'http'=>$http,'data'=>['error'=>$err?:'Error de conexión']];
  $data=json_decode($raw,true);if(!is_array($data))$data=['error'=>trim(strip_tags((string)$raw))?:'Respuesta no válida'];
  return ['ok'=>$http>=200&&$http<300&&!empty($data['ok']),'http'=>$http,'data'=>$data];
}
