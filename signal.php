<?php
header('Content-Type: application/json');header('Cache-Control: no-store');
function clean($v,$n=40){return substr(preg_replace('/[^a-zA-Z0-9_-]/','',$v??''),0,$n);}
$room=clean($_GET['room']??'');$id=clean($_GET['id']??'');
$name=trim(substr(strip_tags($_GET['name']??''),0,20));
$act=$_GET['action']??'';$inc=(int)($_GET['c']??0);
if(!$room||!$id){echo '{"error":1}';exit;}
$dir=__DIR__.'/data';
if(!is_dir($dir)){mkdir($dir,0755,true);file_put_contents("$dir/.htaccess","Require all denied\nDeny from all\n");}
$fp=fopen("$dir/r_$room.json",'c+');flock($fp,LOCK_EX);
$s=json_decode(stream_get_contents($fp),true)?:['seq'=>0,'peers'=>[],'msgs'=>[]];
$now=time();
foreach($s['peers'] as $k=>$p) if($now-$p['seen']>15) unset($s['peers'][$k]);
$s['msgs']=array_slice(array_values(array_filter($s['msgs'],fn($m)=>$now-$m['t']<120)),-400);
$out=['ok'=>1];
if($act==='leave'){unset($s['peers'][$id]);if(!$s['peers'])$s['msgs']=[];}
elseif(!isset($s['peers'][$id])&&count($s['peers'])>=4){$out=['full'=>1];}
else{
  if(!isset($s['peers'][$id]))$s['peers'][$id]=['joined'=>microtime(true)];
  $s['peers'][$id]['seen']=$now;$s['peers'][$id]['incall']=$inc;
  if($name!=='')$s['peers'][$id]['name']=$name;
  if($act==='send'){
    $raw=file_get_contents('php://input');
    if(strlen($raw)<20000&&($m=json_decode($raw,true))){
      $s['seq']++;$s['msgs'][]=['seq'=>$s['seq'],'from'=>$id,'to'=>clean($m['to']??''),'t'=>$now,'msg'=>$m];
    }
  }elseif($act==='join'){$out=['since'=>$s['seq']];}
  elseif($act==='poll'){
    $since=(int)($_GET['since']??0);$list=[];$last=$since;
    foreach($s['msgs'] as $m){
      if($m['seq']>$since){
        $last=max($last,$m['seq']);
        if($m['from']!==$id&&($m['to']===''||$m['to']===$id))$list[]=array_merge($m['msg'],['from'=>$m['from']]);
      }
    }
    $pl=[];
    foreach($s['peers'] as $k=>$p)if($k!==$id)$pl[]=['id'=>$k,'name'=>$p['name']??'','incall'=>(int)($p['incall']??0)];
    $out=['msgs'=>$list,'last'=>$last,'peers'=>$pl];
  }
}
ftruncate($fp,0);rewind($fp);fwrite($fp,json_encode($s));flock($fp,LOCK_UN);fclose($fp);
echo json_encode($out);
