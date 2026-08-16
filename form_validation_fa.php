<?php

if(!function_exists('pnvFormValidationFaScript')){

    function pnvFormValidationFaScript(){
        static $printed = false;

        if($printed){
            return;
        }

        $printed = true;
        echo '<script src="/form_validation_fa.js?v=1"></script>' . "\n";
    }

}
