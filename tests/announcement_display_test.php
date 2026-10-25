<?php
require dirname(__DIR__).'/campaign_lib.php';
require dirname(__DIR__).'/announcement_display_lib.php';
$tmp=sys_get_temp_dir().'/pnv-views-'.bin2hex(random_bytes(6));mkdir($tmp,0700);
putenv('PNV_ANNOUNCEMENT_VIEWS_PATH='.$tmp.'/views.json');
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
function notice($id,$limit=1){return ['id'=>$id,'status'=>'active','display_limit'=>$limit];}
try{
 $a=notice('a',3);
 for($i=0;$i<3;$i++)check(pnvAnnouncementReserveDisplay('alice',[$a])==='a','automatic view '.($i+1));
 check(pnvAnnouncementReserveDisplay('alice',[$a])===null,'quota exhausted');
 check(pnvAnnouncementReserveDisplay('bob',[$a])==='a','independent user');
 $a['display_limit']=4;check(pnvAnnouncementReserveDisplay('alice',[$a])==='a','increase limit grants one additional view');
 $a['display_limit']=1;check(pnvAnnouncementReserveDisplay('alice',[$a])===null,'decrease does not reset counts');
 check(pnvAnnouncementReserveDisplay('alice',[notice('manual',0)])===null,'zero means manual only');
 $legacy=notice('legacy');$legacy['is_read']=true;
 check(pnvAnnouncementReserveDisplay('alice',[$legacy])===null,'legacy read counts as one');
 $legacy['display_limit']=2;check(pnvAnnouncementReserveDisplay('alice',[$legacy])==='legacy','legacy reader receives additional allowance');
 $expired=notice('expired');$expired['expires_at']=time()-60;
 $future=notice('future');$future['starts_at']=time()+3600;
 $off=notice('off');$off['status']='inactive';
 check(pnvAnnouncementReserveDisplay('alice',[$expired,$future,$off])===null,'inactive/out-of-window notices excluded');
 check(pnvAnnouncementReserveDisplay('alice',[notice('first'),notice('second')])==='first','one notice per visit');
 check(pnvAnnouncementReserveDisplay('alice',[notice('first'),notice('second')])==='second','next eligible notice on next visit');
 file_put_contents($tmp.'/views.json','broken');check(pnvAnnouncementReserveDisplay('alice',[notice('safe')])===null,'corrupt counters not reset');
 check(file_get_contents($tmp.'/views.json')==='broken','corrupt file untouched');
}finally{foreach(glob($tmp.'/*') as $f)unlink($f);rmdir($tmp);}
