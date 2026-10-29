<?php
function render_header(string $title,string $active=''):void{
  $u=current_user();
  $items=[
    'dashboard'=>['Inicio','⌂'],'contacts'=>['Contactos','◉'],'products'=>['Menú / Productos','⌘'],'orders'=>['Pedidos Pick&Go','▣'],
    'reservations'=>['Reservaciones','□'],'invoices'=>['Facturación','▤'],'events'=>['Eventos / Catering','◇'],'messages'=>['Plantillas','✉'],
    'users'=>['Usuarios','♙'],'profile'=>['Mi Perfil','○'],'settings'=>['Configuración','⚙']
  ];
  $css=dirname(__DIR__).'/assets/css/app.css';$v=is_file($css)?filemtime($css):'1';
  ?><!doctype html><html lang="es"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title><?=h($title)?> | DoggieBot</title><link rel="stylesheet" href="<?=h(base_url('assets/css/app.css?v='.$v))?>"></head><body><div class="app-shell"><aside class="sidebar"><div class="brand"><img src="<?=h(base_url('assets/img/logo.png'))?>" alt="Los Dogos"><span>CRM</span></div><nav><?php foreach($items as $k=>$it):
    if(in_array($k,['users','settings'],true)&&!is_admin())continue;
    if(isset(permission_catalog()[$k])&&!can_view_module($k))continue;
    ?><a class="<?=$active===$k?'active':''?>" href="<?=h(base_url('index.php?page='.$k))?>"><span class="nav-icon"><?=$it[1]?></span><?=$it[0]?></a><?php endforeach;?></nav><div class="side-user"><?php if(!empty($u['profile_photo'])):?><img class="avatar-side" src="<?=h(base_url($u['profile_photo']))?>" alt="Perfil"><?php else:?><div class="avatar-side avatar-fallback"><?=h(mb_strtoupper(mb_substr($u['full_name']??'U',0,1)))?></div><?php endif;?><div><strong><?=h($u['full_name']??'Usuario')?></strong><small><?=h($u['email']??'')?></small></div><a href="<?=h(base_url('logout'))?>">Salir</a></div></aside><main class="main"><header class="topbar"><div><h1><?=h($title)?></h1><p>DoggieBot · CRM</p></div><a class="profile" href="<?=h(base_url('index.php?page=profile'))?>">Mi Perfil</a></header><section class="content"><?php foreach(pull_flashes() as $f):?><div class="alert <?=h($f['type'])?>"><?=h($f['message'])?></div><?php endforeach;?><?php
}
function render_footer():void{?></section></main></div></body></html><?php }
