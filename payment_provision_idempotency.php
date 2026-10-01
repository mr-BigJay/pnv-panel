<?php

/**
 * Idempotent payment provisioning (renew/buy) — fail-closed on storage errors.
 * Production flows must use xuiApprovePaymentIndex; test scripts may bypass explicitly.
 */

if(!function_exists('xuiPaymentProvisionStorePath')){

    function xuiPaymentProvisionStorePath(){
        $override = getenv('PNV_PROVISION_STORE_PATH');

        if(is_string($override) && trim($override) !== ''){
            return trim($override);
        }

        return __DIR__ . '/db/payment_provision_idempotency.json';
    }

    function xuiPaymentProvisionIdKeyFromRow($row){
        if(!is_array($row)){
            return '';
        }

        $tracking = trim((string)($row[3] ?? ''));
        $user = strtolower(trim((string)($row[0] ?? '')));
        $created = intval($row[8] ?? 0);
        $amount = intval($row[12] ?? 0);
        $type = trim((string)($row[9] ?? ''));

        if($user === '' && $tracking === '' && $created <= 0){
            return '';
        }

        return 'pay:' . $user
            . ':' . $tracking
            . ':' . $created
            . ':' . $amount
            . ':' . ($type === 'تمدید' ? 'renew' : 'buy');
    }

    /** @deprecated use xuiPaymentProvisionIdKeyFromRow */
    function xuiPaymentProvisionIdKey($index, $row){
        return xuiPaymentProvisionIdKeyFromRow($row);
    }

    function xuiPaymentProvisionStorageError($message, $code = 'storage'){
        return [
            'state' => 'error',
            'ok' => false,
            'error' => $message,
            'code' => $code,
        ];
    }

    /**
     * @return array{ok:bool,error?:string,data?:array,raw?:string}
     */
    function xuiPaymentProvisionReadStore($fp){
        rewind($fp);
        $raw = stream_get_contents($fp);
        $raw = is_string($raw) ? $raw : '';

        if(trim($raw) === ''){
            return ['ok' => true, 'data' => [], 'raw' => ''];
        }

        $data = json_decode($raw, true);

        if(!is_array($data)){
            return [
                'ok' => false,
                'error' => 'فایل idempotency خراب است (JSON نامعتبر)',
                'code' => 'corrupt',
                'raw' => $raw,
            ];
        }

        return ['ok' => true, 'data' => $data, 'raw' => $raw];
    }

    function xuiPaymentProvisionWriteStore($fp, array $data){
        $encoded = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if($encoded === false){
            return ['ok' => false, 'error' => 'encode idempotency ناموفق بود', 'code' => 'encode'];
        }

        ftruncate($fp, 0);
        rewind($fp);
        $written = fwrite($fp, $encoded);

        if($written === false){
            return ['ok' => false, 'error' => 'نوشتن idempotency ناموفق بود', 'code' => 'write'];
        }

        fflush($fp);

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,error?:string,code?:string,data?:array}
     */
    function xuiPaymentProvisionWithStoreLock(callable $mutator){
        $file = xuiPaymentProvisionStorePath();
        $dir = dirname($file);

        if(!is_dir($dir)){
            if(!@mkdir($dir, 0755, true) && !is_dir($dir)){
                return xuiPaymentProvisionStorageError('پوشه idempotency ساخته نشد', 'mkdir');
            }
        }

        $fp = @fopen($file, 'c+');

        if($fp === false){
            return xuiPaymentProvisionStorageError('باز کردن فایل idempotency ناموفق بود', 'fopen');
        }

        try{
            if(!flock($fp, LOCK_EX)){
                return xuiPaymentProvisionStorageError('قفل idempotency گرفته نشد', 'flock');
            }

            $read = xuiPaymentProvisionReadStore($fp);

            if(empty($read['ok'])){
                return [
                    'ok' => false,
                    'error' => $read['error'] ?? 'read failed',
                    'code' => $read['code'] ?? 'read',
                ];
            }

            $mutatorResult = $mutator($read['data'], $fp);

            if(!is_array($mutatorResult)){
                return xuiPaymentProvisionStorageError('خطای داخلی idempotency', 'internal');
            }

            if(!empty($mutatorResult['skip_write'])){
                return array_merge(['ok' => true], $mutatorResult);
            }

            if(empty($mutatorResult['ok'])){
                return $mutatorResult;
            }

            $data = $mutatorResult['data'] ?? $read['data'];

            if(count($data) > 800){
                uasort($data, static function($a, $b){
                    return intval($b['done_at'] ?? $b['started_at'] ?? 0) <=> intval($a['done_at'] ?? $a['started_at'] ?? 0);
                });
                $data = array_slice($data, 0, 600, true);
            }

            $write = xuiPaymentProvisionWriteStore($fp, $data);

            if(empty($write['ok'])){
                return $write;
            }

            return array_merge(['ok' => true, 'data' => $data], $mutatorResult);
        }
        finally{
            flock($fp, LOCK_UN);
            fclose($fp);
        }
    }

    function xuiPaymentProvisionTestFetchClientHook($intent){
        if(!empty($GLOBALS['xuiPaymentProvisionTestFetchClient'])){
            $fn = $GLOBALS['xuiPaymentProvisionTestFetchClient'];

            return is_callable($fn) ? $fn($intent) : null;
        }

        return null;
    }

    function xuiPaymentProvisionFetchClientForIntent(array $intent){
        $mock = xuiPaymentProvisionTestFetchClientHook($intent);

        if(is_array($mock)){
            return $mock;
        }

        if(!function_exists('xuiLoadConfig')){
            return null;
        }

        $config = xuiLoadConfig();
        $serverId = trim((string)($intent['server_id'] ?? ''));
        $subId = trim((string)($intent['sub_id'] ?? ''));
        $email = trim((string)($intent['email'] ?? ''));
        $server = null;

        foreach(is_array($config['servers'] ?? null) ? $config['servers'] : [] as $srv){
            if(is_array($srv) && (string)($srv['id'] ?? '') === $serverId){
                $server = $srv;
                break;
            }
        }

        if(!$server && $subId !== '' && function_exists('xuiFindRenewServerBySubId')){
            $found = xuiFindRenewServerBySubId($subId, $config);

            if(is_array($found)){
                $server = $found['server'] ?? null;
            }
        }

        if(!$server){
            return null;
        }

        $client = null;

        if($subId !== '' && function_exists('xuiFindClientBySubId')){
            $client = xuiFindClientBySubId($server, $subId, '');
        }

        if(!$client && $email !== '' && function_exists('xuiApiRequest')){
            $full = xuiApiRequest($server, 'GET', '/panel/api/clients/get/' . rawurlencode($email));

            if(!empty($full['success']) && is_array($full['obj'] ?? null) && function_exists('xuiNormalizeClientRecord')){
                $client = xuiNormalizeClientRecord($full['obj']['client'] ?? $full['obj']);
            }
        }

        if(!is_array($client)){
            return null;
        }

        $total = function_exists('xuiClientTotalBytes') ? xuiClientTotalBytes($client) : 0;

        return [
            'server_id' => $server['id'] ?? $serverId,
            'email' => trim((string)($client['email'] ?? $email)),
            'sub_id' => trim((string)($client['subId'] ?? $subId)),
            'client_uuid' => trim((string)($client['id'] ?? '')),
            'total_bytes' => max(0, intval($total)),
        ];
    }

    /**
     * @return array{status:string,result?:array,error?:string,code?:string}
     */
    function xuiPaymentProvisionReconcileEntry(array $entry){
        if(!empty($entry['api_succeeded']) && is_array($entry['pending_result'] ?? null)){
            return [
                'status' => 'applied',
                'result' => $entry['pending_result'],
                'source' => 'api_succeeded_flag',
            ];
        }

        $intent = is_array($entry['intent'] ?? null) ? $entry['intent'] : [];

        if(($intent['type'] ?? '') === 'تمدید'){
            $baseline = intval($intent['baseline_total_bytes'] ?? -1);
            $addBytes = intval($intent['add_bytes'] ?? 0);

            if($baseline < 0 || $addBytes <= 0){
                return [
                    'status' => 'fail_closed',
                    'error' => 'intent تمدید برای reconcile ناقص است',
                    'code' => 'intent_incomplete',
                ];
            }

            $expected = $baseline + $addBytes;
            $snap = xuiPaymentProvisionFetchClientForIntent($intent);

            if(!is_array($snap)){
                return [
                    'status' => 'fail_closed',
                    'error' => 'reconcile: client از پنل خوانده نشد',
                    'code' => 'reconcile_unreachable',
                ];
            }

            $current = intval($snap['total_bytes'] ?? 0);

            if($current >= $expected){
                $result = is_array($entry['pending_result'] ?? null) ? $entry['pending_result'] : [
                    'ok' => true,
                    'email' => $snap['email'] ?? ($intent['email'] ?? ''),
                    'sub_id' => $snap['sub_id'] ?? ($intent['sub_id'] ?? ''),
                    'server_id' => $snap['server_id'] ?? ($intent['server_id'] ?? ''),
                    'link' => $intent['expected_link'] ?? '',
                    'method' => 'reconcile',
                    'reconciled' => true,
                ];

                return ['status' => 'applied', 'result' => $result, 'source' => 'baseline_delta'];
            }

            return ['status' => 'not_applied', 'source' => 'baseline_delta'];
        }

        if(($intent['type'] ?? '') === 'خرید'){
            $email = trim((string)($intent['expected_email'] ?? $intent['email'] ?? ''));
            $subId = trim((string)($intent['sub_id'] ?? ''));

            if($email === '' && $subId === ''){
                return [
                    'status' => 'fail_closed',
                    'error' => 'intent خرید برای reconcile ناقص است',
                    'code' => 'intent_incomplete',
                ];
            }

            $snap = xuiPaymentProvisionFetchClientForIntent($intent);

            if(is_array($snap) && ($snap['email'] ?? '') !== ''){
                $result = is_array($entry['pending_result'] ?? null) ? $entry['pending_result'] : [
                    'ok' => true,
                    'email' => $snap['email'],
                    'sub_id' => $snap['sub_id'] ?? $subId,
                    'server_id' => $snap['server_id'] ?? ($intent['server_id'] ?? ''),
                    'link' => $intent['expected_link'] ?? '',
                    'method' => 'reconcile',
                    'reconciled' => true,
                ];

                return ['status' => 'applied', 'result' => $result, 'source' => 'buy_client_exists'];
            }

            return ['status' => 'not_applied', 'source' => 'buy_client_exists'];
        }

        return [
            'status' => 'fail_closed',
            'error' => 'نوع intent ناشناخته',
            'code' => 'intent_type',
        ];
    }

    function xuiPaymentProvisionBuildIntent($row, $typeHint = ''){
        if(!is_array($row)){
            return ['ok' => false, 'error' => 'رد پرداخت نامعتبر است'];
        }

        if(!empty($GLOBALS['xuiPaymentProvisionTestIntentOverride']) && is_array($GLOBALS['xuiPaymentProvisionTestIntentOverride'])){
            return [
                'ok' => true,
                'intent' => $GLOBALS['xuiPaymentProvisionTestIntentOverride'],
            ];
        }

        if(!function_exists('xuiPaymentRowType')){
            require_once __DIR__ . '/xui_lib.php';
        }

        $type = xuiPaymentRowType($row, $typeHint);
        $planText = trim((string)($row[2] ?? ''));
        $gb = function_exists('xuiParsePlanGb') ? xuiParsePlanGb($planText) : 0;
        $days = function_exists('xuiParsePlanDays') ? xuiParsePlanDays($planText) : 0;
        $addBytes = function_exists('xuiGbToBytes') ? xuiGbToBytes($gb) : 0;

        $intent = [
            'type' => $type,
            'payment_key' => xuiPaymentProvisionIdKeyFromRow($row),
            'plan_text' => $planText,
            'add_gb' => $gb,
            'add_days' => max(0, intval($days)),
            'add_bytes' => max(0, intval($addBytes)),
            'created_at' => time(),
        ];

        if($type === 'تمدید'){
            if(!function_exists('xuiResolveRenewContext')){
                require_once __DIR__ . '/xui_lib.php';
            }

            $ctx = xuiResolveRenewContext($row);

            if(empty($ctx['ok'])){
                return ['ok' => false, 'error' => $ctx['error'] ?? 'resolve renew ناموفق'];
            }

            $server = $ctx['server'];
            $client = $ctx['client'];
            $parsed = $ctx['parsed'];
            $email = trim((string)($client['email'] ?? ''));
            $baseline = function_exists('xuiClientTotalBytes') ? xuiClientTotalBytes($client) : 0;
            $link = function_exists('xuiBuildSubLink')
                ? xuiBuildSubLink($parsed['host'] ?? '', $parsed['sub_id'] ?? '', xuiLoadConfig())
                : trim((string)($row[1] ?? ''));

            $intent['server_id'] = $server['id'] ?? '';
            $intent['sub_id'] = $parsed['sub_id'] ?? '';
            $intent['email'] = $email;
            $intent['client_uuid'] = trim((string)($client['id'] ?? ''));
            $intent['baseline_total_bytes'] = max(0, intval($baseline));
            $intent['expected_link'] = $link;

            return ['ok' => true, 'intent' => $intent];
        }

        $intent['subname'] = trim((string)($row[1] ?? ''));
        $intent['expected_email'] = '';

        return ['ok' => true, 'intent' => $intent];
    }

    /**
     * @return array{state:string,result?:array,error?:string,code?:string,busy?:bool}
     */
    function xuiPaymentProvisionBegin($key, array $intent){
        $key = trim((string)$key);

        if($key === ''){
            return xuiPaymentProvisionStorageError('کلید idempotency خالی است', 'key_empty');
        }

        $out = xuiPaymentProvisionWithStoreLock(static function(array $data) use ($key, $intent){
            $entry = is_array($data[$key] ?? null) ? $data[$key] : null;

            if(is_array($entry) && ($entry['state'] ?? '') === 'done' && is_array($entry['result'] ?? null)){
                return [
                    'ok' => true,
                    'skip_write' => true,
                    'state' => 'done',
                    'result' => $entry['result'],
                ];
            }

            if(is_array($entry) && ($entry['state'] ?? '') === 'running'){
                if(!empty($entry['api_succeeded']) && is_array($entry['pending_result'] ?? null)){
                    $data[$key] = [
                        'state' => 'done',
                        'done_at' => time(),
                        'intent' => $entry['intent'] ?? $intent,
                        'result' => $entry['pending_result'],
                        'recovered_from' => 'api_succeeded',
                    ];

                    return [
                        'ok' => true,
                        'data' => $data,
                        'state' => 'done',
                        'result' => $entry['pending_result'],
                    ];
                }

                $reconcile = xuiPaymentProvisionReconcileEntry($entry);

                if(($reconcile['status'] ?? '') === 'applied' && is_array($reconcile['result'] ?? null)){
                    $data[$key] = [
                        'state' => 'done',
                        'done_at' => time(),
                        'intent' => $entry['intent'] ?? $intent,
                        'result' => $reconcile['result'],
                        'recovered_from' => $reconcile['source'] ?? 'reconcile',
                    ];

                    return [
                        'ok' => true,
                        'data' => $data,
                        'state' => 'done',
                        'result' => $reconcile['result'],
                    ];
                }

                if(($reconcile['status'] ?? '') === 'fail_closed'){
                    return [
                        'ok' => false,
                        'error' => $reconcile['error'] ?? 'reconcile fail-closed',
                        'code' => $reconcile['code'] ?? 'reconcile',
                    ];
                }

                if(($reconcile['status'] ?? '') === 'not_applied'){
                    $started = intval($entry['started_at'] ?? 0);

                    if($started > 0 && (time() - $started) < 120){
                        return [
                            'ok' => true,
                            'skip_write' => true,
                            'state' => 'busy',
                            'busy' => true,
                        ];
                    }

                    $entryIntent = is_array($entry['intent'] ?? null) ? $entry['intent'] : $intent;

                    if(($entryIntent['type'] ?? '') === 'تمدید'){
                        $baseline = intval($entryIntent['baseline_total_bytes'] ?? -1);
                        $snap = xuiPaymentProvisionFetchClientForIntent($entryIntent);

                        if(!is_array($snap)){
                            return [
                                'ok' => false,
                                'error' => 'running قدیمی: reconcile ممکن نیست (پنل)',
                                'code' => 'reconcile_unreachable',
                            ];
                        }

                        $current = intval($snap['total_bytes'] ?? 0);

                        if($baseline >= 0 && $current > $baseline){
                            return [
                                'ok' => false,
                                'error' => 'running قدیمی: حجم از baseline بیشتر شده ولی به expected نرسیده — fail-closed',
                                'code' => 'ambiguous_partial',
                            ];
                        }
                    }

                    if(!empty($entry['api_succeeded'])){
                        return [
                            'ok' => false,
                            'error' => 'running با api_succeeded بدون commit — نباید دوباره provision شود',
                            'code' => 'api_succeeded_stuck',
                        ];
                    }

                    $data[$key] = [
                        'state' => 'running',
                        'started_at' => time(),
                        'intent' => $entryIntent,
                        'api_succeeded' => false,
                        'retried_after' => $started,
                    ];

                    return [
                        'ok' => true,
                        'data' => $data,
                        'state' => 'owned',
                        'recovered_from' => 'stale_retry_no_delta',
                    ];
                }
            }

            $data[$key] = [
                'state' => 'running',
                'started_at' => time(),
                'intent' => $intent,
                'api_succeeded' => false,
            ];

            return [
                'ok' => true,
                'data' => $data,
                'state' => 'owned',
            ];
        });

        if(empty($out['ok'])){
            return xuiPaymentProvisionStorageError($out['error'] ?? 'storage', $out['code'] ?? 'storage');
        }

        return $out;
    }

    /**
     * @return array{ok:bool,error?:string,code?:string}
     */
    function xuiPaymentProvisionMarkApiSucceeded($key, array $result, array $intent){
        $key = trim((string)$key);

        if($key === '' || empty($result['ok'])){
            return ['ok' => false, 'error' => 'mark api succeeded: ورودی نامعتبر', 'code' => 'invalid'];
        }

        return xuiPaymentProvisionWithStoreLock(static function(array $data) use ($key, $result, $intent){
            $entry = is_array($data[$key] ?? null) ? $data[$key] : [];

            $data[$key] = array_merge($entry, [
                'state' => 'running',
                'api_succeeded' => true,
                'pending_result' => $result,
                'intent' => $intent,
                'api_succeeded_at' => time(),
            ]);

            return ['ok' => true, 'data' => $data];
        });
    }

    /**
     * @return array{ok:bool,error?:string,code?:string}
     */
    function xuiPaymentProvisionCommit($key, array $result, array $intent){
        $key = trim((string)$key);

        if($key === '' || empty($result['ok'])){
            return ['ok' => false, 'error' => 'commit: نتیجه provisioning نامعتبر', 'code' => 'invalid'];
        }

        if(!empty($GLOBALS['xuiPaymentProvisionTestFailCommit'])){
            unset($GLOBALS['xuiPaymentProvisionTestFailCommit']);
            $mark = xuiPaymentProvisionMarkApiSucceeded($key, $result, $intent);

            if(empty($mark['ok'])){
                return [
                    'ok' => false,
                    'error' => 'commit test fail: mark api_succeeded ناموفق: ' . ($mark['error'] ?? ''),
                    'code' => 'commit_and_mark_failed',
                ];
            }

            return [
                'ok' => false,
                'error' => 'commit idempotency ناموفق؛ api_succeeded ذخیره شد — retry بدون bulkAdjust مجدد',
                'code' => 'commit_failed_marked',
                'recoverable' => true,
            ];
        }

        $out = xuiPaymentProvisionWithStoreLock(static function(array $data) use ($key, $result, $intent){
            $data[$key] = [
                'state' => 'done',
                'done_at' => time(),
                'intent' => $intent,
                'result' => $result,
            ];

            return ['ok' => true, 'data' => $data];
        });

        if(empty($out['ok'])){
            $mark = xuiPaymentProvisionMarkApiSucceeded($key, $result, $intent);

            if(empty($mark['ok'])){
                return [
                    'ok' => false,
                    'error' => 'commit ناموفق و mark api_succeeded هم ناموفق: ' . ($mark['error'] ?? ''),
                    'code' => 'commit_and_mark_failed',
                ];
            }

            return [
                'ok' => false,
                'error' => 'commit idempotency ناموفق؛ api_succeeded ذخیره شد — retry بدون bulkAdjust مجدد',
                'code' => 'commit_failed_marked',
                'recoverable' => true,
            ];
        }

        return ['ok' => true];
    }

    /**
     * @return array{ok:bool,error?:string,code?:string}
     */
    function xuiPaymentProvisionAbort($key){
        $key = trim((string)$key);

        if($key === ''){
            return ['ok' => false, 'error' => 'abort: کلید خالی', 'code' => 'key_empty'];
        }

        $out = xuiPaymentProvisionWithStoreLock(static function(array $data) use ($key){
            if(!isset($data[$key])){
                return ['ok' => true, 'skip_write' => true];
            }

            if(($data[$key]['state'] ?? '') === 'done'){
                return ['ok' => true, 'skip_write' => true];
            }

            if(!empty($data[$key]['api_succeeded'])){
                return [
                    'ok' => false,
                    'error' => 'abort ممنوع: api_succeeded ثبت شده',
                    'code' => 'abort_denied',
                ];
            }

            unset($data[$key]);

            return ['ok' => true, 'data' => $data];
        });

        if(empty($out['ok'])){
            return [
                'ok' => false,
                'error' => $out['error'] ?? 'abort storage failed',
                'code' => $out['code'] ?? 'storage',
            ];
        }

        return ['ok' => true];
    }

    /**
     * Orchestrates begin → provision callback → commit/mark.
     *
     * @param callable():array $provisionFn must return xuiProvision* result array
     * @return array provisioning result or error
     */
    function xuiPaymentProvisionExecute($row, $typeHint, callable $provisionFn){
        $built = xuiPaymentProvisionBuildIntent($row, $typeHint);

        if(empty($built['ok'])){
            return ['ok' => false, 'error' => $built['error'] ?? 'intent build failed'];
        }

        $intent = $built['intent'];
        $key = trim((string)($intent['payment_key'] ?? ''));

        if($key === ''){
            return xuiPaymentProvisionStorageError('کلید پرداخت ساخته نشد', 'key_empty');
        }

        $claim = xuiPaymentProvisionBegin($key, $intent);

        if(($claim['state'] ?? '') === 'error'){
            return [
                'ok' => false,
                'error' => $claim['error'] ?? 'idempotency error',
                'code' => $claim['code'] ?? 'storage',
                'fail_closed' => true,
            ];
        }

        if(!empty($claim['busy'])){
            return [
                'ok' => false,
                'error' => $claim['error'] ?? 'صدور اشتراک همین پرداخت در حال انجام است',
                'busy' => true,
                'code' => $claim['code'] ?? 'busy',
            ];
        }

        if(($claim['state'] ?? '') === 'done' && is_array($claim['result'] ?? null)){
            return array_merge($claim['result'], [
                'idempotent' => true,
                'idempotency' => 'done',
            ]);
        }

        if(($claim['state'] ?? '') !== 'owned'){
            return [
                'ok' => false,
                'error' => 'وضعیت idempotency نامعتبر: ' . ($claim['state'] ?? ''),
                'code' => 'state',
                'fail_closed' => true,
            ];
        }

        $result = $provisionFn();

        if(empty($result['ok'])){
            $abort = xuiPaymentProvisionAbort($key);

            if(empty($abort['ok'])){
                return [
                    'ok' => false,
                    'error' => ($result['error'] ?? 'provision failed') . ' | abort: ' . ($abort['error'] ?? ''),
                    'code' => 'provision_abort',
                ];
            }

            return $result;
        }

        $commit = xuiPaymentProvisionCommit($key, $result, $intent);

        if(empty($commit['ok'])){
            return [
                'ok' => false,
                'error' => $commit['error'] ?? 'commit failed',
                'code' => $commit['code'] ?? 'commit',
                'recoverable' => !empty($commit['recoverable']),
                'provision_result' => $result,
            ];
        }

        return $result;
    }
}
