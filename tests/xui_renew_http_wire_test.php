<?php
/**
 * Local HTTP wire E2E for 3x-ui renew. Starts a fake API on 127.0.0.1 only.
 * No real XUI, users, credentials, payments, or external network.
 */
if(PHP_SAPI !== 'cli'){
    exit(1);
}
if(!function_exists('curl_init') || !function_exists('proc_open')){
    echo "SKIP local HTTP wire test (requires curl and proc_open)\n";
    exit(0);
}

require_once __DIR__ . '/../xui_lib.php';

$dir=sys_get_temp_dir().'/pnv_xui_wire_'.getmypid().'_'.bin2hex(random_bytes(4));
mkdir($dir,0700,true);
$fixture=<<<'PHP'
<?php
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
$method=$_SERVER['REQUEST_METHOD'];
$body=file_get_contents('php://input');
$ctype=$_SERVER['CONTENT_TYPE']??'';
$record=[
    'method'=>$method,
    'path'=>$path,
    'content_type'=>$ctype,
    'body'=>$body,
];
file_put_contents(__DIR__.'/wire.jsonl',json_encode($record)."\n",FILE_APPEND|LOCK_EX);
header('Content-Type: application/json');
if($method==='POST' && strpos($path,'/panel/api/clients/update/')===0){
    http_response_code(404);
    echo json_encode(['success'=>false,'msg'=>'not found']);
    return;
}
if($method==='POST' && strpos($path,'/panel/api/inbounds/updateClient/')===0){
    if(stripos($ctype,'application/x-www-form-urlencoded')!==false){
        parse_str($body,$fields);
        $clients=json_decode($fields['settings']??'',true);
        if(($fields['id']??'')==='2' && is_array($clients) && isset($clients['clients'][0]['totalGB'])){
            echo json_encode(['success'=>true,'msg'=>'updated']);
            return;
        }
        http_response_code(400);
        echo json_encode(['success'=>false,'msg'=>'invalid form']);
        return;
    }
    http_response_code(415);
    echo json_encode(['success'=>false,'msg'=>'need form']);
    return;
}
http_response_code(404);
echo json_encode(['success'=>false,'msg'=>'not found']);
PHP;
file_put_contents($dir.'/router.php',$fixture);

$listener=stream_socket_server('tcp://127.0.0.1:0',$errno,$errstr);
if(!$listener){
    fwrite(STDERR,"FAIL bind localhost: $errstr\n");
    exit(1);
}
$socketName=stream_socket_get_name($listener,false);
$port=intval(substr($socketName,strrpos($socketName,':')+1));
fclose($listener);
$cmd=escapeshellarg(PHP_BINARY).' -S 127.0.0.1:'.$port.' '.escapeshellarg($dir.'/router.php');
$log=fopen($dir.'/server.log','w+');
$proc=proc_open($cmd,[0=>['pipe','r'],1=>$log,2=>$log],$pipes,$dir);
if(!is_resource($proc)){
    fwrite(STDERR,"FAIL local HTTP fixture start\n");
    exit(1);
}
fclose($pipes[0]);

try{
    $ready=false;
    for($i=0;$i<40;$i++){
        $sock=@fsockopen('127.0.0.1',$port,$e,$msg,0.2);
        if(is_resource($sock)){
            fclose($sock);
            $ready=true;
            break;
        }
        usleep(50000);
    }
    if(!$ready) throw new RuntimeException('localhost fake 3x-ui did not start');

    $server=['base_url'=>'http://127.0.0.1:'.$port,'api_token'=>'dummy-test-token','inbound_id'=>2];
    $client=[
        'id'=>'ed5226d6-5a06-4db3-a010-80dbdd90dbef',
        'email'=>'fake@example.test',
        'subId'=>'fakeSub123456',
        '_inbound_id'=>2,
        'totalGB'=>1073741824,
    ];
    $r=xuiApplyRenewPlanAbsolute($server,$client,8,30,1700000000000);
    if(empty($r['ok']) || ($r['method']??'')!=='updateClient_absolute_form'){
        throw new RuntimeException('expected modern 404 then form success; got '.json_encode(['ok'=>$r['ok']??false,'method'=>$r['method']??null]));
    }
    $rows=[];
    foreach(file($dir.'/wire.jsonl',FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[] as $line){
        $row=json_decode($line,true);
        if(is_array($row)) $rows[]=$row;
    }
    if(count($rows)!==2) throw new RuntimeException('expected exactly two HTTP requests, received '.count($rows));
    if($rows[0]['path']!=='/panel/api/clients/update/fake%40example.test') throw new RuntimeException('modern route mismatch');
    if(stripos($rows[0]['content_type'],'application/json')===false) throw new RuntimeException('modern content type mismatch');
    if($rows[1]['path']!=='/panel/api/inbounds/updateClient/ed5226d6-5a06-4db3-a010-80dbdd90dbef'){
        throw new RuntimeException('legacy route mismatch');
    }
    if(stripos($rows[1]['content_type'],'application/x-www-form-urlencoded')===false){
        throw new RuntimeException('legacy content type mismatch');
    }
    parse_str($rows[1]['body'],$fields);
    $payload=json_decode($fields['settings']??'',true);
    if(($fields['id']??'')!=='2'||($payload['clients'][0]['totalGB']??0)!==xuiGbToBytes(8)){
        throw new RuntimeException('legacy form fields mismatch');
    }
    echo "PASS local HTTP modern 404 -> legacy form success\n";
    echo "PASS actual cURL content-types, routes, and volume\n";
    echo "PASS two POSTs only, no extra JSON replay\n";
}
catch(Throwable $e){
    fwrite(STDERR,"FAIL local HTTP wire test: ".$e->getMessage()."\n");
    exit(1);
}
finally{
    proc_terminate($proc);
    proc_close($proc);
    fclose($log);
    foreach(['router.php','server.log','wire.jsonl'] as $item){
        @unlink($dir.'/'.$item);
    }
    @rmdir($dir);
}
