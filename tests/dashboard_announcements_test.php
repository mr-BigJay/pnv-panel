<?php
// Isolated rendering regression test: php tests/dashboard_announcements_test.php
$root=dirname(__DIR__);
$tmp=sys_get_temp_dir().'/pnv-notice-'.bin2hex(random_bytes(6));mkdir($tmp,0700);
copy($root.'/pnv_dashboard_announcements.php',$tmp.'/pnv_dashboard_announcements.php');
file_put_contents($tmp.'/campaign_lib.php', '<?php');
copy($root.'/announcement_display_lib.php',$tmp.'/announcement_display_lib.php');
putenv('PNV_ANNOUNCEMENT_VIEWS_PATH='.$tmp.'/views.json');
function campaignAnnouncementIsActive($row){return true;}
ini_set('session.save_path',$tmp);session_start();$_SESSION['user']='fixture';
function campaignUserAnnouncements($username){return $GLOBALS['fixtures'];}
function renderNotice($tmp){ob_start();include $tmp.'/pnv_dashboard_announcements.php';return ob_get_clean();}
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo "PASS $label\n";}
try{
 $GLOBALS['fixtures']=[['id'=>'new','title'=>'خبر','message'=>'<script>alert(1)</script>','is_read'=>false],['id'=>'read','title'=>'OLD_READ_TITLE','message'=>'old','is_read'=>true]];
 $html=renderNotice($tmp);
 check(str_contains($html,'<dialog '),'renders a dialog');
 check(str_contains($html,'>ادامه</button>'),'exact Continue label');
 check(str_contains($html,'OLD_READ_TITLE'),'read active notice remains available manually');
 check(substr_count($html,'data-auto="1"')===1,'only one automatic modal per response');
 check(str_contains($html,'&lt;script&gt;alert(1)&lt;/script&gt;'),'message escaped');
 check(str_contains($html,'data-notice-index="0"')&&str_contains($html,'openNotice(dialogs[Number(button.dataset.noticeIndex)])'),'manual reopening wired');
 check(!str_contains($html,'fetch('),'manual reopening and Continue do not increment count');
 $GLOBALS['fixtures']=[];check(renderNotice($tmp)==='','empty state');
 unset($_SESSION['user']);check(renderNotice($tmp)==='','unauthenticated state');
 $dashboard=file_get_contents($root.'/dashboard.php');
 check(substr_count($dashboard,"require __DIR__ . '/pnv_dashboard_announcements.php'")===1,'dashboard integrates component once');
}finally{
 session_write_close();foreach(glob($tmp.'/*') as $file)unlink($file);rmdir($tmp);
}
