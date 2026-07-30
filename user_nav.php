<?php

if(!function_exists('userBackBar')){

    /**
     * Unified top back control for user panel pages.
     *
     * @param string $href   Target URL (default dashboard)
     * @param string $title  Optional centered page title
     * @param array  $attrs  Extra options: class, label
     */
    function userBackBar($href = 'dashboard.php', $title = '', $attrs = []){

        $label = $attrs['label'] ?? 'بازگشت';
        $extraClass = trim((string)($attrs['class'] ?? ''));
        $barClass = 'userBackBar' . ($extraClass !== '' ? ' ' . $extraClass : '');

        $hrefEsc = htmlspecialchars((string)$href, ENT_QUOTES, 'UTF-8');
        $labelEsc = htmlspecialchars((string)$label, ENT_QUOTES, 'UTF-8');
        $titleEsc = htmlspecialchars((string)$title, ENT_QUOTES, 'UTF-8');

        echo '<div class="' . htmlspecialchars($barClass, ENT_QUOTES, 'UTF-8') . '">';
        echo '<a class="userBack" href="' . $hrefEsc . '">' . $labelEsc . '</a>';

        if($titleEsc !== ''){
            echo '<div class="userBackTitle">' . $titleEsc . '</div>';
            echo '<span class="userBackSpacer" aria-hidden="true"></span>';
        }

        echo '</div>';

    }

}
