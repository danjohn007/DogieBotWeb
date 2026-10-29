<?php
require __DIR__.'/app/bootstrap.php';
require __DIR__.'/app/layout.php';
require_auth();
$user=current_user();
$page=preg_replace('/[^a-z_]/','',$_GET['page']??'dashboard');
$allowed=['dashboard','contacts','products','orders','reservations','invoices','events','messages','users','settings','profile'];
if(!in_array($page,$allowed,true))$page='dashboard';
if(in_array($page,['users','settings'],true)&&!is_admin())redirect_to(base_url('index.php?page='.first_allowed_page()));
if(isset(permission_catalog()[$page])&&!can_view_module($page))redirect_to(base_url('index.php?page='.first_allowed_page()));

/**
 * Guarda una imagen de menú/producto optimizada para web y WhatsApp.
 * Acepta archivos de hasta 12 MB y, cuando GD está disponible, los reduce
 * automáticamente a JPEG para evitar imágenes pesadas en el chat.
 */
function save_catalog_image(array $file,string $folder,string $prefix,int $maxDimension=1800,int $quality=86):array{
  $err=(int)($file['error']??UPLOAD_ERR_NO_FILE);
  if($err!==UPLOAD_ERR_OK){
    if(in_array($err,[UPLOAD_ERR_INI_SIZE,UPLOAD_ERR_FORM_SIZE],true))throw new RuntimeException('La imagen supera el límite de carga del servidor. El sistema acepta hasta 12 MB; espera unos minutos tras subir el parche y vuelve a intentarlo.');
    throw new RuntimeException('No fue posible recibir la imagen. Intenta nuevamente.');
  }
  $size=(int)($file['size']??0);
  if($size<1)throw new RuntimeException('La imagen está vacía o no pudo leerse.');
  if($size>12*1024*1024)throw new RuntimeException('La imagen original no debe superar 12 MB.');
  $tmp=(string)($file['tmp_name']??'');
  if($tmp===''||!is_uploaded_file($tmp))throw new RuntimeException('No se recibió un archivo de imagen válido.');

  $finfo=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($tmp);
  $allowed=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
  if(!isset($allowed[$mime]))throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WEBP.');
  $info=@getimagesize($tmp);
  if(!$info||empty($info[0])||empty($info[1]))throw new RuntimeException('No fue posible leer las dimensiones de la imagen.');
  $w=(int)$info[0];$h=(int)$info[1];
  if($w>10000||$h>10000||($w*$h)>60000000)throw new RuntimeException('La resolución de la imagen es demasiado grande. Usa una imagen de hasta 10,000 px por lado.');

  $dir=__DIR__.'/assets/uploads/'.$folder;
  if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('No se pudo crear el directorio de imágenes.');

  // Optimización del lado del servidor. Esto garantiza archivos ligeros para WhatsApp.
  if(function_exists('imagecreatefromstring')&&function_exists('imagejpeg')){
    $raw=@file_get_contents($tmp);$src=$raw!==false?@imagecreatefromstring($raw):false;
    if($src!==false){
      $scale=min(1,$maxDimension/max($w,$h));$nw=max(1,(int)round($w*$scale));$nh=max(1,(int)round($h*$scale));
      $dst=imagecreatetruecolor($nw,$nh);$white=imagecolorallocate($dst,255,255,255);imagefill($dst,0,0,$white);
      imagecopyresampled($dst,$src,0,0,0,0,$nw,$nh,$w,$h);
      $fileName=$prefix.'_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(5)).'.jpg';
      $rel='assets/uploads/'.$folder.'/'.$fileName;$dest=__DIR__.'/'.$rel;
      $qualities=array_values(array_unique([$quality,80,74,68]));$saved=false;
      foreach($qualities as $q){if(@imagejpeg($dst,$dest,$q)){clearstatcache(true,$dest);$saved=true;if((int)@filesize($dest)<=3500000)break;}}
      imagedestroy($dst);imagedestroy($src);
      if($saved&&is_file($dest))return ['path'=>$rel,'url'=>absolute_url($rel),'size'=>(int)filesize($dest),'optimized'=>true];
    }
  }

  // Respaldo para servidores sin GD. El navegador ya intenta optimizar antes de enviar.
  if($size>5*1024*1024)throw new RuntimeException('El servidor no tiene disponible el optimizador de imágenes y el archivo supera 5 MB. Usa JPG/WEBP o reduce la imagen antes de subirla.');
  $ext=$allowed[$mime];$fileName=$prefix.'_'.gmdate('Ymd_His').'_'.bin2hex(random_bytes(5)).'.'.$ext;
  $rel='assets/uploads/'.$folder.'/'.$fileName;$dest=__DIR__.'/'.$rel;
  if(!move_uploaded_file($tmp,$dest))throw new RuntimeException('No fue posible guardar la imagen.');
  return ['path'=>$rel,'url'=>absolute_url($rel),'size'=>(int)filesize($dest),'optimized'=>false];
}

if($_SERVER['REQUEST_METHOD']==='POST'){
  verify_csrf();$a=$_POST['action']??'';
  try{
    if($a==='send_template'){
      require_module('messages',true);$cid=(int)($_POST['contact_id']??0);$body=trim($_POST['body_text']??'');if(!$cid||$body==='')throw new RuntimeException('Selecciona contacto y escribe mensaje.');
      $c=db_one($pdo,'SELECT * FROM dg_contacts WHERE id=? AND active=1',[$cid]);if(!$c)throw new RuntimeException('Contacto no disponible.');
      $r=firebase_post($pdo,$config,['action'=>'template','contact_id'=>$cid,'phone'=>$c['phone'],'template_name'=>setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']),'language'=>setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']),'body_text'=>$body]);
      if(!$r['ok'])throw new RuntimeException($r['data']['error']??('HTTP '.$r['http']));flash('success','Plantilla enviada correctamente.');redirect_to(base_url('index.php?page=messages'));
    }
    if($a==='send_template_bulk'){
      require_module('messages',true);$body=trim($_POST['body_text']??'');$type=(int)($_POST['customer_type_id']??0);if($body==='')throw new RuntimeException('Escribe el mensaje.');
      if($type>0&&!db_one($pdo,'SELECT id FROM dg_customer_types WHERE id=? AND active=1',[$type]))throw new RuntimeException('La clasificación seleccionada no está disponible.');
      $payload=['action'=>'template_bulk','template_name'=>setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']),'language'=>setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']),'body_text'=>$body];
      if($type>0)$payload['customer_type_id']=$type;
      $r=firebase_post($pdo,$config,$payload);if(!$r['ok'])throw new RuntimeException($r['data']['error']??('HTTP '.$r['http']));
      flash('success','Envío terminado: '.(int)($r['data']['sent']??0).' enviados; '.(int)($r['data']['failed']??0).' con error.');redirect_to(base_url('index.php?page=messages'));
    }
    if($a==='save_product_category'){
      require_module('products',true);$id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$sort=(int)($_POST['sort_order']??100);
      if(mb_strlen($name)<2||mb_strlen($name)>100)throw new RuntimeException('Escribe un nombre de categoría válido.');
      $dup=(int)db_value($pdo,'SELECT COUNT(*) FROM dg_product_categories WHERE name=? AND id<>?',[$name,$id]);if($dup)throw new RuntimeException('Ya existe una categoría con ese nombre.');
      if($id){
        $oldCat=db_one($pdo,'SELECT name FROM dg_product_categories WHERE id=?',[$id]);if(!$oldCat)throw new RuntimeException('Categoría no encontrada.');
        $pdo->beginTransaction();
        $pdo->prepare('UPDATE dg_product_categories SET name=?,sort_order=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$name,$sort,$id]);
        $pdo->prepare('UPDATE dg_products SET category=?,updated_at=UTC_TIMESTAMP() WHERE category_id=?')->execute([$name,$id]);
        $pdo->commit();
      }else{$pdo->prepare('INSERT INTO dg_product_categories(name,active,sort_order) VALUES(?,1,?)')->execute([$name,$sort]);}
      flash('success','Categoría guardada.');redirect_to(base_url('index.php?page=products'));
    }
    if($a==='toggle_product_category'){
      require_module('products',true);$id=(int)($_POST['id']??0);$pdo->prepare('UPDATE dg_product_categories SET active=IF(active=1,0,1),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);flash('success','Estado de la categoría actualizado.');redirect_to(base_url('index.php?page=products'));
    }
    if($a==='delete_product_category'){
      require_module('products',true);$id=(int)($_POST['id']??0);if(!$id)throw new RuntimeException('Categoría no válida.');
      $cat=db_one($pdo,'SELECT id,name FROM dg_product_categories WHERE id=?',[$id]);if(!$cat)throw new RuntimeException('Categoría no encontrada.');
      $used=(int)db_value($pdo,'SELECT COUNT(*) FROM dg_products WHERE category_id=?',[$id]);
      if($used>0)throw new RuntimeException('No puedes eliminar esta categoría porque tiene '.$used.' producto(s). Reasigna esos productos a otra categoría o desactívala.');
      $pdo->prepare('DELETE FROM dg_product_categories WHERE id=?')->execute([$id]);
      flash('success','Categoría eliminada.');redirect_to(base_url('index.php?page=products'));
    }
    if($a==='save_product'){
      require_module('products',true);$id=(int)($_POST['id']??0);$categoryId=(int)($_POST['category_id']??0);$name=trim($_POST['name']??'');$price=(float)($_POST['price']??0);$sort=(int)($_POST['sort_order']??100);
      if(!$categoryId||mb_strlen($name)<2||mb_strlen($name)>160||$price<0)throw new RuntimeException('Categoría, producto y precio válidos son obligatorios.');
      $cat=db_one($pdo,'SELECT id,name,active FROM dg_product_categories WHERE id=?',[$categoryId]);if(!$cat)throw new RuntimeException('La categoría seleccionada no existe.');
      $current=$id?db_one($pdo,'SELECT * FROM dg_products WHERE id=?',[$id]):null;if($id&&!$current)throw new RuntimeException('Producto no encontrado.');
      $imagePath=$current['image_path']??null;$imageUrl=$current['image_url']??null;$newImage=false;
      if(!empty($_FILES['product_image']['name'])&&($_FILES['product_image']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_NO_FILE){
        $savedImage=save_catalog_image($_FILES['product_image'],'products','product',1600,86);
        $imagePath=$savedImage['path'];$imageUrl=$savedImage['url'];$newImage=true;
      }
      if($id){
        $pdo->prepare('UPDATE dg_products SET category_id=?,category=?,name=?,price=?,sort_order=?,image_path=?,image_url=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$categoryId,$cat['name'],$name,$price,$sort,$imagePath,$imageUrl,$id]);
        if($newImage&&!empty($current['image_path'])&&str_starts_with($current['image_path'],'assets/uploads/products/')&&$current['image_path']!==$imagePath){$old=__DIR__.'/'.$current['image_path'];if(is_file($old))@unlink($old);}
        flash('success','Producto actualizado.');
      }else{$pdo->prepare('INSERT INTO dg_products(category_id,category,name,price,image_path,image_url,active,sort_order) VALUES(?,?,?,?,?,?,1,?)')->execute([$categoryId,$cat['name'],$name,$price,$imagePath,$imageUrl,$sort]);flash('success','Producto agregado al menú.');}
      redirect_to(base_url('index.php?page=products'));
    }
    if($a==='remove_product_image'){
      require_module('products',true);$id=(int)($_POST['id']??0);$r=db_one($pdo,'SELECT image_path FROM dg_products WHERE id=?',[$id]);if(!$r)throw new RuntimeException('Producto no encontrado.');
      if(!empty($r['image_path'])&&str_starts_with($r['image_path'],'assets/uploads/products/')){$old=__DIR__.'/'.$r['image_path'];if(is_file($old))@unlink($old);}
      $pdo->prepare('UPDATE dg_products SET image_path=NULL,image_url=NULL,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);flash('success','Imagen del producto eliminada.');redirect_to(base_url('index.php?page=products&edit_product='.$id));
    }
    if($a==='toggle_product'){
      require_module('products',true);$id=(int)($_POST['id']??0);$pdo->prepare('UPDATE dg_products SET active=IF(active=1,0,1),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);flash('success','Estado del producto actualizado.');redirect_to(base_url('index.php?page=products'));
    }
    if($a==='save_menu_image'){
      require_module('products',true);
      if(empty($_FILES['menu_image']['name'])||($_FILES['menu_image']['error']??UPLOAD_ERR_NO_FILE)===UPLOAD_ERR_NO_FILE)throw new RuntimeException('Selecciona una imagen del menú.');
      $savedImage=save_catalog_image($_FILES['menu_image'],'menu','menu',2000,88);
      $old=setting($pdo,'menu_image_path','');$rel=$savedImage['path'];
      if($old&&str_starts_with($old,'assets/uploads/menu/')&&$old!==$rel){$oldPath=__DIR__.'/'.$old;if(is_file($oldPath))@unlink($oldPath);}
      $st=$pdo->prepare('INSERT INTO dg_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
      $st->execute(['menu_image_path',$rel]);$st->execute(['menu_image_url',$savedImage['url']]);
      flash('success','Imagen del menú optimizada y actualizada. DoggieBot la enviará al mostrar el menú.');redirect_to(base_url('index.php?page=products'));
    }
    if($a==='remove_menu_image'){
      require_module('products',true);$old=setting($pdo,'menu_image_path','');if($old&&str_starts_with($old,'assets/uploads/menu/')){$oldPath=__DIR__.'/'.$old;if(is_file($oldPath))@unlink($oldPath);}
      $st=$pdo->prepare('INSERT INTO dg_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');$st->execute(['menu_image_path','']);$st->execute(['menu_image_url','']);
      flash('success','Imagen del menú retirada.');redirect_to(base_url('index.php?page=products'));
    }
    if($a==='classify_contact'){
      require_module('contacts',true);$id=(int)($_POST['id']??0);$type=(int)($_POST['customer_type_id']??0);$pdo->prepare('UPDATE dg_contacts SET customer_type_id=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$type?:null,$id]);flash('success','Clasificación actualizada.');redirect_to(base_url('index.php?page=contacts'));
    }
    if($a==='save_customer_type'){
      require_admin();$id=(int)($_POST['id']??0);$name=trim($_POST['name']??'');$desc=trim($_POST['description']??'');if(mb_strlen($name)<2)throw new RuntimeException('Escribe un nombre para la clasificación.');
      if($id)$pdo->prepare('UPDATE dg_customer_types SET name=?,description=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$name,$desc?:null,$id]);
      else $pdo->prepare('INSERT INTO dg_customer_types(name,description,active,sort_order) VALUES(?,?,1,100)')->execute([$name,$desc?:null]);
      flash('success','Clasificación guardada.');redirect_to(base_url('index.php?page=contacts'));
    }
    if($a==='toggle_customer_type'){
      require_admin();$id=(int)($_POST['id']??0);$pdo->prepare('UPDATE dg_customer_types SET active=IF(active=1,0,1),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);flash('success','Estado de clasificación actualizado.');redirect_to(base_url('index.php?page=contacts'));
    }
    $moduleMap=[
      'order'=>['dg_orders','orders',['pending','in_process','completed','cancelled']],
      'reservation'=>['dg_reservations','reservations',['pending','in_process','completed','cancelled']],
      'invoice'=>['dg_invoice_requests','invoices',['pending','review','completed','cancelled']],
      'event'=>['dg_event_requests','events',['pending','review','confirmed','completed','cancelled']],
    ];
    foreach($moduleMap as $key=>$meta){if($a==='update_'.$key){require_module($meta[1],true);$id=(int)($_POST['id']??0);$status=(string)($_POST['status']??'pending');$notes=trim($_POST['notes']??'');if(!in_array($status,$meta[2],true))throw new RuntimeException('Estado no válido para este módulo.');$pdo->prepare("UPDATE {$meta[0]} SET status=?,notes=?,updated_at=UTC_TIMESTAMP() WHERE id=?")->execute([$status,$notes?:null,$id]);flash('success','Registro actualizado.');redirect_to(base_url('index.php?page='.$meta[1]));}}
    if($a==='save_user'){
      require_admin();$id=(int)($_POST['id']??0);$name=trim($_POST['full_name']??'');$email=mb_strtolower(trim($_POST['email']??''));$role=($_POST['role']??'staff')==='admin'?'admin':'staff';$active=!empty($_POST['active'])?1:0;$password=(string)($_POST['password']??'');
      if(mb_strlen($name)<2||!filter_var($email,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Nombre y correo válidos son obligatorios.');
      $dupe=(int)db_value($pdo,'SELECT COUNT(*) FROM dg_admin_users WHERE email=? AND id<>?',[$email,$id]);if($dupe)throw new RuntimeException('Ese correo ya está registrado.');
      if($id){
        if($id===(int)$user['id']&&(!$active||$role!=='admin'))throw new RuntimeException('No puedes desactivar tu propia cuenta ni quitarte el rol administrador desde este módulo.');
        if($password!==''&&strlen($password)<10)throw new RuntimeException('La nueva contraseña debe tener al menos 10 caracteres.');
        if($password!=='')$pdo->prepare('UPDATE dg_admin_users SET full_name=?,email=?,role=?,active=?,password_hash=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$name,$email,$role,$active,password_hash($password,PASSWORD_DEFAULT),$id]);
        else $pdo->prepare('UPDATE dg_admin_users SET full_name=?,email=?,role=?,active=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$name,$email,$role,$active,$id]);
      }else{
        if(strlen($password)<10)throw new RuntimeException('La contraseña inicial debe tener al menos 10 caracteres.');
        $pdo->prepare('INSERT INTO dg_admin_users(email,password_hash,full_name,role,active) VALUES(?,?,?,?,?)')->execute([$email,password_hash($password,PASSWORD_DEFAULT),$name,$role,$active]);$id=(int)$pdo->lastInsertId();
      }
      $pdo->prepare('DELETE FROM dg_user_permissions WHERE user_id=?')->execute([$id]);
      if($role==='staff'){
        $ins=$pdo->prepare('INSERT INTO dg_user_permissions(user_id,module_key,can_view,can_edit) VALUES(?,?,?,?)');
        foreach(permission_catalog() as $k=>$meta){$view=!empty($_POST['perm'][$k]['view'])?1:0;$edit=$view&&!empty($_POST['perm'][$k]['edit'])?1:0;$ins->execute([$id,$k,$view,$edit]);}
      }
      flash('success','Usuario y permisos guardados.');redirect_to(base_url('index.php?page=users'));
    }
    if($a==='toggle_user'){
      require_admin();$id=(int)($_POST['id']??0);if($id===(int)$user['id'])throw new RuntimeException('No puedes desactivar tu propia cuenta.');$pdo->prepare('UPDATE dg_admin_users SET active=IF(active=1,0,1),updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$id]);flash('success','Estado del usuario actualizado.');redirect_to(base_url('index.php?page=users'));
    }
    if($a==='save_settings'){
      require_admin();$s=$pdo->prepare('INSERT INTO dg_settings(setting_key,setting_value) VALUES(?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');foreach(['marketing_template_name','marketing_template_language','firebase_crm_url','location_map_url','location_address'] as $k)$s->execute([$k,trim($_POST[$k]??'')]);flash('success','Configuración guardada.');redirect_to(base_url('index.php?page=settings'));
    }
    if($a==='test_firebase'){
      require_admin();$r=firebase_post($pdo,$config,['action'=>'health']);flash($r['ok']?'success':'danger',$r['ok']?'Conexión Web → Firebase → MySQL correcta.':($r['data']['error']??('HTTP '.$r['http'])));redirect_to(base_url('index.php?page=settings'));
    }
    if($a==='update_profile'){
      $name=trim($_POST['full_name']??'');$email=mb_strtolower(trim($_POST['email']??''));if(mb_strlen($name)<2||mb_strlen($name)>190)throw new RuntimeException('El nombre debe tener entre 2 y 190 caracteres.');if(!filter_var($email,FILTER_VALIDATE_EMAIL)||mb_strlen($email)>190)throw new RuntimeException('Correo electrónico inválido.');$exists=db_value($pdo,'SELECT COUNT(*) FROM dg_admin_users WHERE email=? AND id<>?',[$email,$user['id']]);if((int)$exists>0)throw new RuntimeException('Ese correo ya está registrado.');$photo=$user['profile_photo']??null;
      if(!empty($_FILES['profile_photo']['name'])){if(($_FILES['profile_photo']['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('No fue posible subir la fotografía.');if((int)$_FILES['profile_photo']['size']>3*1024*1024)throw new RuntimeException('La fotografía no debe superar 3 MB.');$finfo=new finfo(FILEINFO_MIME_TYPE);$mime=$finfo->file($_FILES['profile_photo']['tmp_name']);$ext=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$mime]??null;if(!$ext)throw new RuntimeException('Solo se permiten imágenes JPG, PNG o WEBP.');$dir=__DIR__.'/assets/uploads/profile';if(!is_dir($dir)&&!mkdir($dir,0755,true))throw new RuntimeException('No se pudo crear el directorio de fotografías.');$file='user_'.$user['id'].'_'.bin2hex(random_bytes(8)).'.'.$ext;$dest=$dir.'/'.$file;if(!move_uploaded_file($_FILES['profile_photo']['tmp_name'],$dest))throw new RuntimeException('No se pudo guardar la fotografía.');$newPhoto='assets/uploads/profile/'.$file;if($photo&&str_starts_with($photo,'assets/uploads/profile/')){$old=__DIR__.'/'.$photo;if(is_file($old))@unlink($old);}$photo=$newPhoto;}
      $pdo->prepare('UPDATE dg_admin_users SET full_name=?,email=?,profile_photo=?,updated_at=UTC_TIMESTAMP() WHERE id=?')->execute([$name,$email,$photo,$user['id']]);$_SESSION['dg_user']['full_name']=$name;$_SESSION['dg_user']['email']=$email;$_SESSION['dg_user']['profile_photo']=$photo;flash('success','Perfil actualizado correctamente.');redirect_to(base_url('index.php?page=profile'));
    }
    if($a==='change_password'){
      $cur=$_POST['current_password']??'';$new=$_POST['new_password']??'';$conf=$_POST['confirm_password']??'';$r=db_one($pdo,'SELECT password_hash FROM dg_admin_users WHERE id=?',[$user['id']]);if(!$r||!password_verify($cur,$r['password_hash']))throw new RuntimeException('Contraseña actual incorrecta.');if(strlen($new)<10||$new!==$conf)throw new RuntimeException('La nueva contraseña debe tener al menos 10 caracteres y coincidir con la confirmación.');$pdo->prepare('UPDATE dg_admin_users SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$user['id']]);flash('success','Contraseña actualizada.');redirect_to(base_url('index.php?page=profile'));
    }
  }catch(Throwable $e){flash('danger',$e->getMessage());redirect_to($_SERVER['REQUEST_URI']);}
}

function page_intro(string $eyebrow,string $title,string $text):void{?><div class="page-intro"><div><span class="eyebrow"><?=h($eyebrow)?></span><h2 class="section-title"><?=h($title)?></h2><p class="muted"><?=h($text)?></p></div></div><?php }
function status_label(string $status):string{
  return ['pending'=>'Pendiente','in_process'=>'En proceso','review'=>'Revisión','confirmed'=>'Confirmado','completed'=>'Finalizado','cancelled'=>'Cancelado'][$status]??ucfirst(str_replace('_',' ',$status));
}
function status_form(string $action,int $id,string $status,?string $notes,bool $editable,array $options=[]):void{
  if(!$options)$options=['pending','review','confirmed','completed','cancelled'];
  if(!$editable){?><span class="badge <?=h(status_class($status))?>"><?=h(status_label($status))?></span><?php return;}
  ?><details class="row-actions"><summary><span class="badge <?=h(status_class($status))?>"><?=h(status_label($status))?></span><span>Editar</span></summary><form method="post" class="compact-form"><?=csrf_input()?><input type="hidden" name="action" value="<?=h($action)?>"><input type="hidden" name="id" value="<?=$id?>"><label>Estado<select name="status"><?php foreach($options as $opt):?><option value="<?=h($opt)?>" <?=$status===$opt?'selected':''?>><?=h(status_label($opt))?></option><?php endforeach;?></select></label><label>Notas<textarea name="notes" placeholder="Notas internas"><?=h($notes)?></textarea></label><button class="btn secondary">Guardar cambios</button></form></details><?php
}
function contact_actions(int $contactId):void{
  $hasContacts=can_view_module('contacts');$canMessage=can_edit_module('messages');
  if(!$hasContacts&&!$canMessage)return;
  ?><div class="record-actions"><?php if($hasContacts):?><a class="btn ghost btn-sm" href="<?=h(base_url('index.php?page=contacts&view_contact='.$contactId))?>">Ver contacto</a><?php endif;?><?php if($canMessage):?><a class="btn secondary btn-sm" href="<?=h(base_url('index.php?page=messages&contact_id='.$contactId))?>">WhatsApp</a><?php endif;?></div><?php
}

if($page==='dashboard'){
  $m=['contacts'=>(int)db_value($pdo,'SELECT COUNT(*) FROM dg_contacts WHERE active=1'),'orders'=>(int)db_value($pdo,"SELECT COUNT(*) FROM dg_orders WHERE status='pending'"),'reservations'=>(int)db_value($pdo,"SELECT COUNT(*) FROM dg_reservations WHERE status IN ('pending','in_process')"),'invoices'=>(int)db_value($pdo,"SELECT COUNT(*) FROM dg_invoice_requests WHERE status IN ('pending','review')"),'events'=>(int)db_value($pdo,"SELECT COUNT(*) FROM dg_event_requests WHERE status IN ('pending','review')"),'interactions'=>(int)db_value($pdo,'SELECT COUNT(*) FROM dg_messages WHERE DATE(created_at)=UTC_DATE()')];
  $months=db_all($pdo,"SELECT DATE_FORMAT(created_at,'%Y-%m') ym,COUNT(*) total FROM dg_contacts WHERE created_at>=DATE_SUB(UTC_DATE(),INTERVAL 5 MONTH) GROUP BY ym ORDER BY ym");
  $types=db_all($pdo,'SELECT t.id,t.name,t.description,COUNT(c.id) total FROM dg_customer_types t LEFT JOIN dg_contacts c ON c.customer_type_id=t.id AND c.active=1 WHERE t.active=1 GROUP BY t.id,t.name,t.description,t.sort_order ORDER BY t.sort_order,t.name');$unclassified=(int)db_value($pdo,'SELECT COUNT(*) FROM dg_contacts WHERE active=1 AND customer_type_id IS NULL');
  render_header('Inicio','dashboard');page_intro('Resumen general','Dashboard comercial','Información clave de DoggieBot en una vista limpia y rápida.');
  ?><div class="grid metrics"><?php foreach([['Contactos',$m['contacts'],'Registrados en CRM'],['Pedidos',$m['orders'],'Pendientes Pick&Go'],['Reservaciones',$m['reservations'],'Activas'],['Facturación',$m['invoices'],'Por revisar'],['Eventos',$m['events'],'Solicitudes abiertas'],['Interacciones',$m['interactions'],'Mensajes de hoy']] as $x):?><div class="metric"><div class="label"><?=$x[0]?></div><div class="value"><?=$x[1]?></div><div class="muted"><?=$x[2]?></div></div><?php endforeach;?></div>
  <div class="card"><div class="card-head"><div><span class="eyebrow">Segmentación</span><h3>Customer Journey</h3><p class="muted">Cada clasificación describe el tipo de relación que el contacto tiene con Los Dogos.</p></div></div><div class="grid journey"><?php foreach($types as $t):?><div class="stage"><div class="stage-count"><?=$t['total']?></div><h4><?=h($t['name'])?></h4><p><?=h($t['description']?:'Clasificación comercial de clientes y prospectos.')?></p></div><?php endforeach;?><div class="stage muted-stage"><div class="stage-count"><?=$unclassified?></div><h4>Sin clasificar</h4><p>Contactos que todavía no tienen asignado un tipo de cliente.</p></div></div></div>
  <div class="grid two"><div class="card"><div class="card-head"><div><h3>Nuevos contactos</h3><p class="muted">Últimos meses</p></div></div><?php $max=max(array_column($months,'total')?:[1]);foreach($months as $r):?><div class="progress-row"><div><span><?=h($r['ym'])?></span><b><?=$r['total']?></b></div><div class="bar"><span style="width:<?=max(6,round($r['total']/$max*100))?>%"></span></div></div><?php endforeach;?></div><div class="card"><div class="card-head"><div><h3>Actividad comercial</h3><p class="muted">Pendientes que requieren seguimiento</p></div></div><?php foreach([['Pedidos pendientes',$m['orders']],['Reservaciones activas',$m['reservations']],['Facturas por revisar',$m['invoices']],['Eventos por revisar',$m['events']]] as $r):?><div class="progress-row"><div><span><?=$r[0]?></span><b><?=$r[1]?></b></div><div class="bar"><span style="width:<?=min(100,max(5,$r[1]*12))?>%"></span></div></div><?php endforeach;?></div></div><?php render_footer();exit;
}

if($page==='contacts'){
  $viewId=(int)($_GET['view_contact']??0);$types=db_all($pdo,'SELECT * FROM dg_customer_types ORDER BY active DESC,sort_order,name');$editable=can_edit_module('contacts');
  if($viewId>0){
    $c=db_one($pdo,'SELECT c.*,t.name customer_type_name FROM dg_contacts c LEFT JOIN dg_customer_types t ON t.id=c.customer_type_id WHERE c.id=?',[$viewId]);if(!$c)throw new RuntimeException('Contacto no encontrado.');
    $orders=db_all($pdo,'SELECT * FROM dg_orders WHERE contact_id=? ORDER BY id DESC',[$viewId]);
    $reservations=db_all($pdo,'SELECT * FROM dg_reservations WHERE contact_id=? ORDER BY id DESC',[$viewId]);
    $invoices=db_all($pdo,'SELECT * FROM dg_invoice_requests WHERE contact_id=? ORDER BY id DESC',[$viewId]);
    $events=db_all($pdo,'SELECT * FROM dg_event_requests WHERE contact_id=? ORDER BY id DESC',[$viewId]);
    $messages=db_all($pdo,'SELECT * FROM dg_messages WHERE contact_id=? ORDER BY id DESC LIMIT 250',[$viewId]);$messages=array_reverse($messages);
    $promos=(int)db_value($pdo,"SELECT COUNT(*) FROM dg_messages WHERE contact_id=? AND direction='OUT' AND sender_type='CRM' AND message_type='template'",[$viewId]);
    render_header('Detalle de contacto','contacts');page_intro('CRM','Historial del contacto','Pedidos, promociones enviadas y conversación de WhatsApp en un solo lugar.');
    ?><div class="contact-detail-head card"><div><a class="back-link" href="<?=h(base_url('index.php?page=contacts'))?>">← Volver a contactos</a><h2><?=h($c['full_name']?:$c['profile_name']?:'Sin nombre')?></h2><p class="muted"><?=h($c['phone'])?> · <?=h($c['email']?:'Sin correo')?> · <?=h($c['customer_type_name']?:'Sin clasificar')?></p></div><div class="contact-summary"><div><strong><?=count($orders)?></strong><span>Pedidos</span></div><div><strong><?=$promos?></strong><span>Promociones</span></div><div><strong><?=count($messages)?></strong><span>Mensajes recientes</span></div></div><?php if(can_edit_module('messages')):?><a class="btn primary" href="<?=h(base_url('index.php?page=messages&contact_id='.$viewId))?>">Enviar WhatsApp</a><?php endif;?></div>
    <div class="grid two"><div class="card"><div class="card-head"><div><h3>Historial de pedidos</h3><p class="muted">Pedidos Pick&Go registrados.</p></div></div><?php if(!$orders):?><p class="empty-state">Este contacto todavía no tiene pedidos.</p><?php else:?><div class="stack-list"><?php foreach($orders as $o):?><div class="history-item"><div><strong><?=h($o['folio'])?></strong><small><?=h(fmt_dt($o['created_at']))?> · <?=h(status_label($o['status']))?></small></div><p><?=nl2br(h($o['items_text']))?></p></div><?php endforeach;?></div><?php endif;?></div>
    <div class="card"><div class="card-head"><div><h3>Actividad adicional</h3><p class="muted">Reservaciones, facturación y eventos.</p></div></div><div class="activity-stats"><div><strong><?=count($reservations)?></strong><span>Reservaciones</span></div><div><strong><?=count($invoices)?></strong><span>Facturas</span></div><div><strong><?=count($events)?></strong><span>Eventos</span></div><div><strong><?=$promos?></strong><span>Plantillas promocionales</span></div></div></div></div>
    <div class="card"><div class="card-head"><div><h3>Conversación</h3><p class="muted">Historial reciente de mensajes entrantes, respuestas del bot y plantillas del CRM.</p></div></div><div class="chat-history"><?php if(!$messages):?><p class="empty-state">No hay mensajes registrados.</p><?php else:foreach($messages as $m):$out=$m['direction']==='OUT';?><div class="chat-row <?=$out?'out':'in'?>"><div class="chat-bubble"><div class="chat-meta"><?=h($out?($m['sender_type']==='CRM'?'CRM':'DoggieBot'):'Cliente')?> · <?=h(fmt_dt($m['created_at']))?></div><div><?=nl2br(h($m['body']?:'['.$m['message_type'].']'))?></div></div></div><?php endforeach;endif;?></div></div><?php render_footer();exit;
  }
  $rows=db_all($pdo,'SELECT c.*,t.name customer_type_name FROM dg_contacts c LEFT JOIN dg_customer_types t ON t.id=c.customer_type_id ORDER BY c.last_inbound_at DESC,c.id DESC LIMIT 500');render_header('Contactos','contacts');page_intro('CRM','Contactos','Clasifica contactos, revisa su historial y accede a su conversación.');
  ?><div class="card"><div class="card-head"><div><h3>Base de contactos</h3><p class="muted">Hasta 500 contactos recientes.</p></div><span class="count-pill"><?=count($rows)?> registros</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Cliente</th><th>WhatsApp</th><th>Email</th><th>Clasificación</th><th>Último contacto</th><th>Acciones</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><div class="cell-title"><?=h($r['full_name']?:$r['profile_name']?:'Sin nombre')?></div><div class="cell-sub">ID #<?=$r['id']?></div></td><td><?=h($r['phone'])?></td><td><?=h($r['email']?:'—')?></td><td><?php if($editable):?><form method="post" class="inline-select-form"><?=csrf_input()?><input type="hidden" name="action" value="classify_contact"><input type="hidden" name="id" value="<?=$r['id']?>"><select name="customer_type_id" onchange="this.form.submit()"><option value="0">Sin clasificar</option><?php foreach($types as $t):if(!$t['active']&&(int)$t['id']!==(int)$r['customer_type_id'])continue;?><option value="<?=$t['id']?>" <?=((int)$r['customer_type_id']===(int)$t['id'])?'selected':''?>><?=h($t['name'])?></option><?php endforeach;?></select></form><?php else:?><span class="badge neutral"><?=h($r['customer_type_name']?:'Sin clasificar')?></span><?php endif;?></td><td><?=h(fmt_dt($r['last_inbound_at']))?></td><td><?php contact_actions((int)$r['id']);?></td></tr><?php endforeach;?></tbody></table></div></div>
  <?php if(is_admin()):?><details class="card admin-details"><summary>Administrar clasificaciones</summary><div class="grid two detail-body"><div><h3>Nueva clasificación</h3><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_customer_type"><label>Nombre<input name="name" required maxlength="100"></label><label>Descripción<textarea name="description" maxlength="500" placeholder="Qué significa este tipo de cliente"></textarea></label><button class="btn primary">Agregar clasificación</button></form></div><div><h3>Clasificaciones actuales</h3><div class="stack-list"><?php foreach($types as $t):?><div class="stack-item"><div><strong><?=h($t['name'])?></strong><small><?=h($t['description']?:'Sin descripción')?></small></div><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="toggle_customer_type"><input type="hidden" name="id" value="<?=$t['id']?>"><button class="btn ghost"><?=$t['active']?'Desactivar':'Activar'?></button></form></div><?php endforeach;?></div></div></div></details><?php endif;render_footer();exit;
}

if($page==='products'){
  $editable=can_edit_module('products');$menuImage=setting($pdo,'menu_image_path','');$menuImageUrl=setting($pdo,'menu_image_url','');
  $categories=db_all($pdo,'SELECT c.*,COUNT(p.id) product_count FROM dg_product_categories c LEFT JOIN dg_products p ON p.category_id=c.id GROUP BY c.id ORDER BY c.sort_order,c.name');
  $rows=db_all($pdo,'SELECT p.*,COALESCE(c.name,p.category) category_name,c.active category_active FROM dg_products p LEFT JOIN dg_product_categories c ON c.id=p.category_id ORDER BY COALESCE(c.sort_order,9999),COALESCE(c.name,p.category),p.sort_order,p.id');
  $editId=(int)($_GET['edit_product']??0);$edit=$editId?db_one($pdo,'SELECT * FROM dg_products WHERE id=?',[$editId]):null;
  $editCatId=(int)($_GET['edit_category']??0);$editCat=$editCatId?db_one($pdo,'SELECT * FROM dg_product_categories WHERE id=?',[$editCatId]):null;
  render_header('Menú / Productos','products');page_intro('Catálogo','Menú / Productos','Administra la imagen general del menú, categorías e imágenes individuales. DoggieBot muestra primero las categorías y después los productos de la categoría elegida.');
  if($editable):?><div class="grid two"><div class="card compact-card"><div class="card-head"><div><h3><?=$edit?'Editar producto':'Agregar producto'?></h3><p class="muted">Cada producto pertenece a una categoría y puede tener su propia fotografía.</p></div></div><form method="post" enctype="multipart/form-data" class="form-grid"><?=csrf_input()?><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?=$edit['id']??0?>"><label>Categoría<select name="category_id" required><option value="">Selecciona...</option><?php foreach($categories as $cat):if(!$cat['active']&&(!$edit||(int)$edit['category_id']!==(int)$cat['id']))continue;?><option value="<?=$cat['id']?>" <?=($edit&&(int)$edit['category_id']===(int)$cat['id'])?'selected':''?>><?=h($cat['name'])?><?=$cat['active']?'':' (inactiva)'?></option><?php endforeach;?></select></label><label>Producto<input name="name" value="<?=h($edit['name']??'')?>" required maxlength="160"></label><label>Precio<input type="number" step="0.01" min="0" name="price" value="<?=h($edit['price']??'')?>" required></label><label>Orden<input type="number" name="sort_order" value="<?=h($edit['sort_order']??100)?>"></label><label class="span-2">Imagen del producto<input type="file" name="product_image" accept="image/jpeg,image/png,image/webp" data-auto-optimize="1600"><small class="muted">JPG, PNG o WEBP. Puedes subir hasta 12 MB; se optimiza automáticamente para WhatsApp.</small><small class="image-opt-status muted" aria-live="polite"></small></label><?php if($edit&&!empty($edit['image_path'])):?><div class="span-2 product-edit-preview"><img src="<?=h(base_url($edit['image_path']))?>" alt="<?=h($edit['name'])?>"><span>Imagen actual</span></div><?php endif;?><div class="span-2 form-actions"><button class="btn primary"><?=$edit?'Guardar cambios':'Agregar producto'?></button><?php if($edit):?><a class="btn ghost" href="<?=h(base_url('index.php?page=products'))?>">Cancelar</a><?php endif;?></div></form><?php if($edit&&!empty($edit['image_path'])):?><form method="post" onsubmit="return confirm('¿Quitar la imagen de este producto?')"><?=csrf_input()?><input type="hidden" name="action" value="remove_product_image"><input type="hidden" name="id" value="<?=$edit['id']?>"><button class="btn ghost">Quitar imagen del producto</button></form><?php endif;?></div><div class="card compact-card"><div class="card-head"><div><h3>Imagen general del menú</h3><p class="muted">Es la primera imagen que recibirá el cliente antes de elegir una categoría.</p></div></div><?php if($menuImage):?><img class="menu-preview" src="<?=h(base_url($menuImage))?>" alt="Menú actual"><?php else:?><div class="menu-placeholder">Sin imagen cargada</div><?php endif;?><form method="post" enctype="multipart/form-data"><?=csrf_input()?><input type="hidden" name="action" value="save_menu_image"><label>Nueva imagen<input type="file" name="menu_image" accept="image/jpeg,image/png,image/webp" data-auto-optimize="2000" required><small class="muted">JPG, PNG o WEBP. Puedes subir hasta 12 MB; se optimiza automáticamente.</small><small class="image-opt-status muted" aria-live="polite"></small></label><div class="form-actions"><button class="btn primary">Guardar imagen</button></div></form><?php if($menuImage):?><form method="post" onsubmit="return confirm('¿Quitar la imagen actual del menú?')" class="menu-remove-form"><?=csrf_input()?><input type="hidden" name="action" value="remove_menu_image"><button class="btn ghost">Quitar imagen actual</button></form><?php endif;?><?php if($menuImageUrl):?><small class="muted">URL pública activa para DoggieBot.</small><?php endif;?></div></div>
  <details class="card admin-details" open><summary>Categorías del menú</summary><div class="grid two detail-body"><div><h3><?=$editCat?'Editar categoría':'Nueva categoría'?></h3><p class="muted">Crea, renombra y ordena las categorías. Para eliminar una categoría primero debe quedar sin productos.</p><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_product_category"><input type="hidden" name="id" value="<?=$editCat['id']??0?>"><label>Nombre<input name="name" value="<?=h($editCat['name']??'')?>" required maxlength="100"></label><label>Orden<input type="number" name="sort_order" value="<?=h($editCat['sort_order']??100)?>"></label><div class="form-actions"><button class="btn primary"><?=$editCat?'Guardar cambios':'Agregar categoría'?></button><?php if($editCat):?><a class="btn ghost" href="<?=h(base_url('index.php?page=products'))?>">Cancelar</a><?php endif;?></div></form></div><div><h3>Categorías actuales</h3><div class="stack-list"><?php foreach($categories as $cat):?><div class="stack-item"><div><strong><?=h($cat['name'])?></strong><small><?=$cat['product_count']?> productos · Orden <?=$cat['sort_order']?></small></div><span class="badge <?=$cat['active']?'success':'neutral'?>"><?=$cat['active']?'Activa':'Inactiva'?></span><div class="category-actions"><a class="btn ghost" href="<?=h(base_url('index.php?page=products&edit_category='.$cat['id']))?>">Editar</a><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="toggle_product_category"><input type="hidden" name="id" value="<?=$cat['id']?>"><button class="btn ghost"><?=$cat['active']?'Desactivar':'Activar'?></button></form><?php if((int)$cat['product_count']===0):?><form method="post" onsubmit="return confirm('¿Eliminar la categoría <?=h(addslashes($cat['name']))?>?')"><?=csrf_input()?><input type="hidden" name="action" value="delete_product_category"><input type="hidden" name="id" value="<?=$cat['id']?>"><button class="btn ghost danger-text">Eliminar</button></form><?php else:?><span class="muted category-delete-note">Reasigna productos para eliminar</span><?php endif;?></div></div><?php endforeach;?></div></div></div></details><?php endif;
  ?><div class="card"><div class="card-head"><div><h3>Catálogo</h3><p class="muted">El bot solo mostrará categorías activas y productos activos. Las fotografías se envían por bloques para no saturar el chat.</p></div><span class="count-pill"><?=count($rows)?> productos</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Imagen</th><th>Categoría</th><th>Producto</th><th>Precio</th><th>Estado</th><?php if($editable):?><th>Acciones</th><?php endif;?></tr></thead><tbody><?php foreach($rows as $r):?><tr class="<?=$r['active']?'':'row-inactive'?>"><td><?php if(!empty($r['image_path'])):?><img class="product-thumb" src="<?=h(base_url($r['image_path']))?>" alt="<?=h($r['name'])?>"><?php else:?><div class="product-thumb product-thumb-empty">Sin foto</div><?php endif;?></td><td><span class="category-tag"><?=h($r['category_name'])?></span><?php if(isset($r['category_active'])&&!$r['category_active']):?><div class="cell-sub">Categoría inactiva</div><?php endif;?></td><td><div class="cell-title"><?=h($r['name'])?></div></td><td class="money">$<?=number_format($r['price'],2)?></td><td><span class="badge <?=$r['active']?'success':'neutral'?>"><?=$r['active']?'Activo':'Inactivo'?></span></td><?php if($editable):?><td><div class="record-actions"><a class="btn ghost btn-sm" href="<?=h(base_url('index.php?page=products&edit_product='.$r['id']))?>">Editar</a><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?=$r['id']?>"><button class="btn <?=$r['active']?'ghost':'secondary'?> btn-sm"><?=$r['active']?'Desactivar':'Activar'?></button></form></div></td><?php endif;?></tr><?php endforeach;?></tbody></table></div></div>
<script>
(function(){
  const inputs=document.querySelectorAll('input[type="file"][data-auto-optimize]');
  const fmt=n=>n<1024*1024?(n/1024).toFixed(0)+' KB':(n/1024/1024).toFixed(2)+' MB';
  inputs.forEach(input=>{
    input.addEventListener('change',async()=>{
      const file=input.files&&input.files[0]; if(!file)return;
      const status=input.parentElement.querySelector('.image-opt-status');
      const form=input.closest('form'); const submit=form&&form.querySelector('button[type="submit"],button:not([type])');
      if(file.size>12*1024*1024){input.value=''; if(status)status.textContent='La imagen supera 12 MB.'; return;}
      if(status)status.textContent='Optimizando '+fmt(file.size)+'…'; if(submit)submit.disabled=true;
      try{
        const bmp=await createImageBitmap(file); const max=parseInt(input.dataset.autoOptimize||'1800',10);
        const scale=Math.min(1,max/Math.max(bmp.width,bmp.height));
        const w=Math.max(1,Math.round(bmp.width*scale)),h=Math.max(1,Math.round(bmp.height*scale));
        const canvas=document.createElement('canvas'); canvas.width=w; canvas.height=h;
        const ctx=canvas.getContext('2d'); ctx.fillStyle='#fff'; ctx.fillRect(0,0,w,h); ctx.drawImage(bmp,0,0,w,h); bmp.close&&bmp.close();
        let quality=.88; let blob=null;
        for(const q of [.88,.82,.76,.70]){blob=await new Promise(resolve=>canvas.toBlob(resolve,'image/jpeg',q)); if(blob&&blob.size<=2500000){quality=q;break;}}
        if(blob&&blob.size<file.size){
          const dt=new DataTransfer(); dt.items.add(new File([blob],file.name.replace(/\.[^.]+$/,'')+'.jpg',{type:'image/jpeg',lastModified:Date.now()})); input.files=dt.files;
          if(status)status.textContent='Lista: '+fmt(file.size)+' → '+fmt(blob.size)+' (optimizada).';
        }else if(status)status.textContent='Lista para subir: '+fmt(file.size)+'.';
      }catch(e){if(status)status.textContent='Se enviará la imagen original; el servidor intentará optimizarla.';}
      finally{if(submit)submit.disabled=false;}
    });
  });
})();
</script>
<?php render_footer();exit;
}

if($page==='orders'){
  $rows=db_all($pdo,'SELECT o.*,c.phone,c.full_name,c.profile_name FROM dg_orders o JOIN dg_contacts c ON c.id=o.contact_id ORDER BY o.id DESC');$editable=can_edit_module('orders');render_header('Pedidos Pick&Go','orders');page_intro('Operación','Pedidos Pick&Go','Estados: pendiente, en proceso, finalizado o cancelado. El flujo informa la bebida de cortesía al iniciar el pedido.');
  ?><div class="card"><div class="table-wrap"><table class="data-table"><thead><tr><th>Folio</th><th>Cliente</th><th>Pedido</th><th>Entrega</th><th>Comentarios</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><span class="folio"><?=h($r['folio'])?></span></td><td><div class="cell-title"><?=h($r['customer_name']?:$r['full_name']?:$r['profile_name']?:'Sin nombre')?></div><div class="cell-sub"><?=h($r['phone'])?></div></td><td class="text-cell"><?=nl2br(h($r['items_text']))?></td><td><div class="cell-title"><?=h(!empty($r['pickup_date'])?fmt_date($r['pickup_date']):'—')?></div><div class="cell-sub"><?=h($r['pickup_time'])?></div></td><td class="text-cell"><?=h($r['comments']?:'—')?></td><td><?php status_form('update_order',(int)$r['id'],$r['status'],$r['notes'],$editable,['pending','in_process','completed','cancelled']);?></td><td><?php contact_actions((int)$r['contact_id']);?></td></tr><?php endforeach;?></tbody></table></div></div><?php render_footer();exit;
}

if($page==='reservations'){
  $rows=db_all($pdo,'SELECT r.*,c.full_name,c.profile_name,c.phone FROM dg_reservations r JOIN dg_contacts c ON c.id=r.contact_id ORDER BY r.id DESC');$editable=can_edit_module('reservations');render_header('Reservaciones','reservations');page_intro('Visitas','Reserva y obtén una bebida de cortesía','Estados: pendiente, en proceso, finalizado o cancelado.');
  ?><div class="card"><div class="table-wrap"><table class="data-table"><thead><tr><th>Cliente</th><th>Fecha</th><th>Personas</th><th>Horario</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><div class="cell-title"><?=h($r['full_name']?:$r['profile_name']?:$r['phone'])?></div><div class="cell-sub"><?=h($r['phone'])?></div></td><td><?=h(fmt_date($r['reservation_date']))?></td><td><span class="count-pill"><?=h($r['people_count'])?></span></td><td><?=h($r['reservation_time'])?></td><td><?php status_form('update_reservation',(int)$r['id'],$r['status'],$r['notes'],$editable,['pending','in_process','completed','cancelled']);?></td><td><?php contact_actions((int)$r['contact_id']);?></td></tr><?php endforeach;?></tbody></table></div></div><?php render_footer();exit;
}

if($page==='invoices'){
  $invoiceFilesAvailable=true;$invoiceWarning='';$rows=[];
  try{db_value($pdo,'SELECT 1 FROM dg_invoice_files LIMIT 1');}catch(Throwable $e){$invoiceFilesAvailable=false;$invoiceWarning='La tabla de fotografías de ticket todavía no está disponible.';}
  try{
    if($invoiceFilesAvailable){
      $rows=db_all($pdo,'SELECT i.*,c.full_name,c.profile_name,c.phone,(SELECT COUNT(*) FROM dg_invoice_files f WHERE f.invoice_id=i.id) has_ticket FROM dg_invoice_requests i LEFT JOIN dg_contacts c ON c.id=i.contact_id ORDER BY i.id DESC');
    }else{
      $rows=db_all($pdo,'SELECT i.*,c.full_name,c.profile_name,c.phone,0 AS has_ticket FROM dg_invoice_requests i LEFT JOIN dg_contacts c ON c.id=i.contact_id ORDER BY i.id DESC');
    }
  }catch(Throwable $e){
    $rows=[];$invoiceWarning='No se pudo consultar la información de facturación. Ejecuta el SQL de reparación del módulo y vuelve a cargar la página.';
  }
  $editable=can_edit_module('invoices');render_header('Facturación','invoices');page_intro('Administración','Facturación','Consulta los datos fiscales y la fotografía del ticket enviada por cada cliente.');
  if($invoiceWarning && is_admin()):?><div class="alert warning"><strong>Facturación requiere ajuste de base de datos.</strong> <?=h($invoiceWarning)?></div><?php endif;
  ?><div class="card"><div class="card-head"><div><h3>Solicitudes de facturación</h3><p class="muted">Los tickets se abren en una pestaña protegida del CRM.</p></div><span class="count-pill"><?=count($rows)?> solicitudes</span></div><div class="table-wrap"><table class="data-table"><thead><tr><th>Cliente</th><th>Ticket</th><th>RFC</th><th>Datos fiscales</th><th>Email</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php if(!$rows):?><tr><td colspan="7"><div class="empty-state">No hay solicitudes disponibles.</div></td></tr><?php endif;foreach($rows as $r):?><tr><td><div class="cell-title"><?=h($r['full_name']?:$r['profile_name']?:$r['phone'])?></div><div class="cell-sub"><?=h($r['phone'])?></div></td><td><?php if((int)$r['has_ticket']>0):?><a class="ticket-preview-link" href="<?=h(base_url('ticket.php?id='.$r['id']))?>" target="_blank" rel="noopener"><img class="ticket-thumb" src="<?=h(base_url('ticket.php?id='.$r['id']))?>" alt="Ticket"><span>Ver ticket</span></a><?php else:?><span class="badge neutral">Sin ticket</span><?php endif;?></td><td><span class="folio"><?=h($r['rfc'])?></span></td><td class="text-cell"><strong>Pago:</strong> <?=h($r['payment_method'])?><br><strong>Régimen:</strong> <?=h($r['tax_regime'])?><br><strong>CFDI:</strong> <?=h($r['cfdi_use'])?></td><td><?=h($r['email'])?></td><td><?php status_form('update_invoice',(int)$r['id'],$r['status'],$r['notes'],$editable,['pending','review','completed','cancelled']);?></td><td><?php contact_actions((int)$r['contact_id']);?></td></tr><?php endforeach;?></tbody></table></div></div><?php render_footer();exit;
}

if($page==='events'){
  $rows=db_all($pdo,'SELECT e.*,c.full_name,c.profile_name,c.phone FROM dg_event_requests e JOIN dg_contacts c ON c.id=e.contact_id ORDER BY e.id DESC');$editable=can_edit_module('events');render_header('Eventos / Catering','events');page_intro('Catering','Eventos / Catering','El servicio para eventos puede solicitarse desde las 08:00 h, incluso antes del horario regular del restaurante.');
  ?><div class="card"><div class="table-wrap"><table class="data-table"><thead><tr><th>Cliente</th><th>Evento</th><th>Fecha / hora</th><th>Duración</th><th>Invitados</th><th>Productos</th><th>Dirección</th><th>Estado</th><th>Acciones</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><div class="cell-title"><?=h($r['full_name']?:$r['profile_name']?:$r['phone'])?></div><div class="cell-sub"><?=h($r['phone'])?></div></td><td><?=h($r['event_type'])?></td><td><div class="cell-title"><?=h(fmt_date($r['event_date']))?></div><div class="cell-sub"><?=h($r['start_time'])?></div></td><td><?=h($r['service_hours'])?> h</td><td><?=h($r['guest_range'])?></td><td class="text-cell"><?=h($r['products_text'])?></td><td class="text-cell"><?=h($r['address'])?></td><td><?php status_form('update_event',(int)$r['id'],$r['status'],$r['notes'],$editable,['pending','review','confirmed','completed','cancelled']);?></td><td><?php contact_actions((int)$r['contact_id']);?></td></tr><?php endforeach;?></tbody></table></div></div><?php render_footer();exit;
}

if($page==='messages'){
  $contacts=db_all($pdo,'SELECT id,full_name,profile_name,phone FROM dg_contacts WHERE active=1 ORDER BY COALESCE(full_name,profile_name),phone');$types=db_all($pdo,'SELECT id,name,description,(SELECT COUNT(*) FROM dg_contacts c WHERE c.customer_type_id=t.id AND c.active=1) total FROM dg_customer_types t WHERE active=1 ORDER BY sort_order,name');$editable=can_edit_module('messages');$preselect=(int)($_GET['contact_id']??0);render_header('Plantillas','messages');page_intro('WhatsApp','Mensajes por plantilla','Envía a una persona, a todos los contactos o únicamente a una clasificación.');
  if(!$editable):?><div class="card"><p>No tienes permiso para realizar envíos. Puedes consultar este módulo, pero los controles de envío están deshabilitados.</p></div><?php else:?><div class="grid two"><div class="card"><div class="card-head"><div><h3>Envío individual</h3><p class="muted">Busca por nombre o número y selecciona un contacto.</p></div></div><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="send_template"><label>Buscar contacto<input type="search" id="contactSearch" placeholder="Nombre o WhatsApp" autocomplete="off"></label><label>Contacto<select name="contact_id" id="contactSelect" required><option value="">Selecciona...</option><?php foreach($contacts as $c):$label=($c['full_name']?:$c['profile_name']?:'Sin nombre').' · '.$c['phone'];?><option value="<?=$c['id']?>" data-search="<?=h(mb_strtolower($label))?>" <?=$preselect===(int)$c['id']?'selected':''?>><?=h($label)?></option><?php endforeach;?></select></label><label>Mensaje<textarea name="body_text" required maxlength="3000" placeholder="Contenido que se enviará en {{1}}"></textarea></label><button class="btn primary">Enviar plantilla</button></form></div><div class="card"><div class="card-head"><div><h3>Envío por audiencia</h3><p class="muted">Todos los contactos o una clasificación concreta.</p></div></div><form method="post" onsubmit="return confirm('¿Confirmas el envío de esta plantilla a la audiencia seleccionada?')"><?=csrf_input()?><input type="hidden" name="action" value="send_template_bulk"><label>Audiencia<select name="customer_type_id"><option value="0">Todos los contactos activos (<?=count($contacts)?>)</option><?php foreach($types as $t):?><option value="<?=$t['id']?>"><?=h($t['name'])?> (<?=$t['total']?>)</option><?php endforeach;?></select></label><label>Mensaje<textarea name="body_text" required maxlength="3000" placeholder="Contenido que se enviará en {{1}}"></textarea></label><button class="btn primary">Enviar a audiencia</button></form><div class="audience-notes"><?php foreach($types as $t):?><div><strong><?=h($t['name'])?></strong><span><?=h($t['description']?:'Sin descripción')?> · <?=$t['total']?> contactos</span></div><?php endforeach;?></div></div></div><script>(function(){const q=document.getElementById('contactSearch'),s=document.getElementById('contactSelect');if(!q||!s)return;const all=[...s.options].slice(1).map(o=>({value:o.value,text:o.textContent,search:(o.dataset.search||o.textContent).toLowerCase(),selected:o.selected}));q.addEventListener('input',()=>{const term=q.value.trim().toLowerCase();const current=s.value;s.innerHTML='<option value="">Selecciona...</option>';all.filter(x=>!term||x.search.includes(term)).forEach(x=>{const o=new Option(x.text,x.value,false,x.value===current||(!current&&x.selected));s.add(o);});});})();</script><?php endif;render_footer();exit;
}

if($page==='users'){
  require_admin();$editId=(int)($_GET['edit_user']??0);$edit=$editId?db_one($pdo,'SELECT * FROM dg_admin_users WHERE id=?',[$editId]):null;$editPerm=[];if($edit){foreach(db_all($pdo,'SELECT * FROM dg_user_permissions WHERE user_id=?',[$editId]) as $p)$editPerm[$p['module_key']]=$p;}$users=db_all($pdo,'SELECT * FROM dg_admin_users ORDER BY active DESC,role,email');render_header('Usuarios','users');page_intro('Accesos','Usuarios','Crea cuentas y define exactamente qué módulos pueden ver o editar.');
  ?><div class="grid users-layout"><div class="card"><div class="card-head"><div><h3><?=$edit?'Editar usuario':'Nuevo usuario'?></h3><p class="muted">Los administradores tienen acceso total. Los usuarios estándar usan permisos por módulo.</p></div></div><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?=$edit['id']??0?>"><div class="form-grid"><label>Nombre completo<input name="full_name" value="<?=h($edit['full_name']??'')?>" required maxlength="190"></label><label>Correo electrónico<input type="email" name="email" value="<?=h($edit['email']??'')?>" required maxlength="190"></label><label>Rol<select name="role" id="roleSelect" onchange="document.getElementById('permBox').classList.toggle('is-hidden',this.value==='admin')"><option value="staff" <?=($edit&&$edit['role']==='staff')?'selected':''?>>Usuario</option><option value="admin" <?=($edit&&$edit['role']==='admin')?'selected':''?>>Administrador</option></select></label><label><?=$edit?'Nueva contraseña (opcional)':'Contraseña inicial'?><input type="password" name="password" minlength="10" <?=$edit?'':'required'?> autocomplete="new-password"></label></div><label class="switch-line"><input type="checkbox" name="active" value="1" <?=!$edit||$edit['active']?'checked':''?>> <span>Usuario activo</span></label><div id="permBox" class="permission-box <?=($edit&&$edit['role']==='admin')?'is-hidden':''?>"><div class="permission-head"><strong>Permisos por módulo</strong><span>Ver permite consultar. Editar permite cambiar estados, datos o realizar envíos.</span></div><?php foreach(permission_catalog() as $k=>$meta):$p=$editPerm[$k]??null;?><div class="permission-row"><div><strong><?=h($meta[0])?></strong><small><?=h($meta[1])?></small></div><label><input type="checkbox" name="perm[<?=$k?>][view]" value="1" <?=$p&&$p['can_view']?'checked':''?>> Ver</label><label><input type="checkbox" name="perm[<?=$k?>][edit]" value="1" <?=$p&&$p['can_edit']?'checked':''?>> Editar</label></div><?php endforeach;?></div><div class="form-actions"><button class="btn primary">Guardar usuario</button><?php if($edit):?><a class="btn ghost" href="<?=h(base_url('index.php?page=users'))?>">Cancelar</a><?php endif;?></div></form></div><div class="card"><div class="card-head"><div><h3>Usuarios registrados</h3><p class="muted">Administra accesos sin compartir la cuenta principal.</p></div></div><div class="stack-list"><?php foreach($users as $u):?><div class="user-item"><div class="user-avatar"><?=h(mb_strtoupper(mb_substr($u['full_name'],0,1)))?></div><div class="grow"><strong><?=h($u['full_name'])?></strong><small><?=h($u['email'])?></small><div class="user-meta"><span class="badge <?=$u['role']==='admin'?'warning':'neutral'?>"><?=h($u['role']==='admin'?'Administrador':'Usuario')?></span><span class="badge <?=$u['active']?'success':'danger'?>"><?=$u['active']?'Activo':'Inactivo'?></span></div></div><div class="user-actions"><a class="btn ghost" href="<?=h(base_url('index.php?page=users&edit_user='.$u['id']))?>">Editar</a><?php if((int)$u['id']!==(int)$user['id']):?><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="toggle_user"><input type="hidden" name="id" value="<?=$u['id']?>"><button class="btn ghost"><?=$u['active']?'Desactivar':'Activar'?></button></form><?php endif;?></div></div><?php endforeach;?></div></div></div><?php render_footer();exit;
}

if($page==='settings'){
  require_admin();render_header('Configuración','settings');page_intro('Sistema','Configuración','Parámetros generales para plantillas, Firebase y ubicación.');?><div class="card settings-card"><form method="post" class="form-grid"><?=csrf_input()?><input type="hidden" name="action" value="save_settings"><label>Plantilla<input name="marketing_template_name" value="<?=h(setting_nonempty($pdo,'marketing_template_name',$config['marketing_template_name']))?>"></label><label>Idioma<input name="marketing_template_language" value="<?=h(setting_nonempty($pdo,'marketing_template_language',$config['marketing_template_language']))?>"></label><label>Endpoint Firebase<input name="firebase_crm_url" value="<?=h(setting_nonempty($pdo,'firebase_crm_url',$config['firebase_crm_url']))?>"></label><label>URL Maps<input name="location_map_url" value="<?=h(setting($pdo,'location_map_url'))?>"></label><label class="span-2">Dirección<input name="location_address" value="<?=h(setting($pdo,'location_address'))?>"></label><div><button class="btn primary">Guardar configuración</button></div></form><div class="divider"></div><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="test_firebase"><button class="btn ghost">Probar conexión Web → Firebase → MySQL</button></form></div><?php render_footer();exit;
}

if($page==='profile'){
  $user=db_one($pdo,'SELECT id,email,full_name,role,profile_photo FROM dg_admin_users WHERE id=?',[$user['id']])?:$user;render_header('Mi Perfil','profile');page_intro('Cuenta','Mi Perfil','Administra tu información personal y credenciales de acceso.');?><div class="profile-grid"><div class="card profile-card"><div class="avatar-xl"><?php if(!empty($user['profile_photo'])):?><img src="<?=h(base_url($user['profile_photo']))?>" alt="Foto de perfil"><?php else:?><span><?=h(mb_strtoupper(mb_substr($user['full_name']?:'U',0,1)))?></span><?php endif;?></div><h2><?=h($user['full_name'])?></h2><p class="muted"><?=h($user['email'])?></p><span class="badge success"><?=h($user['role']==='admin'?'Administrador':'Usuario')?></span></div><div><div class="card"><h3>Información personal</h3><form method="post" enctype="multipart/form-data"><?=csrf_input()?><input type="hidden" name="action" value="update_profile"><label>Nombre completo<input name="full_name" value="<?=h($user['full_name'])?>" maxlength="190" required></label><label>Correo electrónico<input type="email" name="email" value="<?=h($user['email'])?>" maxlength="190" required></label><label>Foto de perfil<input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp"><small class="muted">JPG, PNG o WEBP. Máximo 3 MB.</small></label><button class="btn primary">Guardar perfil</button></form></div><div class="card"><h3>Cambiar contraseña</h3><form method="post"><?=csrf_input()?><input type="hidden" name="action" value="change_password"><label>Contraseña actual<input type="password" name="current_password" required autocomplete="current-password"></label><label>Nueva contraseña<input type="password" name="new_password" minlength="10" required autocomplete="new-password"></label><label>Confirmar nueva contraseña<input type="password" name="confirm_password" minlength="10" required autocomplete="new-password"></label><button class="btn primary">Actualizar contraseña</button></form></div></div></div><?php render_footer();exit;
}
