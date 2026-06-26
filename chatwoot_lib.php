<?php

if(!function_exists('chatwootConfig')){

    function chatwootConfigPath(){

        return __DIR__ . '/db/chatwoot.json';

    }

    function chatwootConfig(){

        static $config = null;

        if($config !== null){
            return $config;
        }

        $defaults = [
            'enabled' => false,
            'base_url' => '',
            'website_token' => '',
            'identity_validation_key' => '',
            'admin_url' => ''
        ];

        $path = chatwootConfigPath();

        if(!file_exists($path)){
            $config = $defaults;
            return $config;
        }

        $loaded = json_decode(file_get_contents($path), true);

        if(!is_array($loaded)){
            $config = $defaults;
            return $config;
        }

        $config = array_merge($defaults, $loaded);

        return $config;

    }

    function chatwootEnabled(){

        $config = chatwootConfig();

        return !empty($config['enabled'])
            && trim($config['base_url'] ?? '') !== ''
            && trim($config['website_token'] ?? '') !== '';

    }

    function chatwootAdminUrl(){

        $config = chatwootConfig();
        $adminUrl = trim($config['admin_url'] ?? '');

        if($adminUrl !== ''){
            return $adminUrl;
        }

        return rtrim(trim($config['base_url'] ?? ''), '/') . '/app';

    }

    function chatwootGetUserMobile($username, $users = null){

        if($users === null){

            $usersFile = __DIR__ . '/db/users.json';

            if(file_exists($usersFile)){
                $users = json_decode(file_get_contents($usersFile), true);
            }

        }

        if(!is_array($users)){
            return '';
        }

        foreach($users as $u){

            if(
                strtolower(trim($u['username'] ?? ''))
                ===
                strtolower(trim($username))
            ){
                return trim($u['mobile'] ?? '');
            }

        }

        return '';

    }

    function chatwootIdentifierHash($identifier){

        $config = chatwootConfig();
        $key = trim($config['identity_validation_key'] ?? '');

        if($key === ''){
            return '';
        }

        return hash_hmac('sha256', $identifier, $key);

    }

    function chatwootRenderWidget($username, $options = []){

        if(!chatwootEnabled()){
            return;
        }

        $config = chatwootConfig();
        $baseUrl = rtrim(trim($config['base_url']), '/');
        $token = trim($config['website_token']);
        $mobile = chatwootGetUserMobile($username);
        $hash = chatwootIdentifierHash($username);
        $autoOpen = !empty($options['auto_open']);
        $position = $options['position'] ?? 'right';

        $userPayload = [
            'name' => $username
        ];

        if($hash !== ''){
            $userPayload['identifier_hash'] = $hash;
        }

        if($mobile !== ''){
            $userPayload['phone_number'] = $mobile;
        }

        $userJson = json_encode($userPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $attrsJson = json_encode([
            'username' => $username,
            'panel' => 'ticketin'
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        ?>

<script>
(function(d,t){
    var BASE_URL = <?php echo json_encode($baseUrl, JSON_UNESCAPED_SLASHES); ?>;
    var g = d.createElement(t);
    var s = d.getElementsByTagName(t)[0];
    g.src = BASE_URL + "/packs/js/sdk.js";
    g.defer = true;
    g.async = true;
    s.parentNode.insertBefore(g,s);
    g.onload = function(){
        window.chatwootSDK.run({
            websiteToken: <?php echo json_encode($token, JSON_UNESCAPED_SLASHES); ?>,
            baseUrl: BASE_URL,
            position: <?php echo json_encode($position, JSON_UNESCAPED_SLASHES); ?>
        });
        window.addEventListener("chatwoot:ready", function(){
            if(window.$chatwoot){
                window.$chatwoot.setUser(
                    <?php echo json_encode($username, JSON_UNESCAPED_UNICODE); ?>,
                    <?php echo $userJson; ?>
                );
                window.$chatwoot.setCustomAttributes(<?php echo $attrsJson; ?>);
                <?php if($autoOpen){ ?>
                window.$chatwoot.toggle("open");
                <?php } ?>
            }
        });
    };
})(document,"script");
</script>

        <?php

    }

}
