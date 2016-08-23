<?php

namespace HaaseIT\HCSFNG\Backend;

class Helper
{
    public static function getLanguage($container)
    {
        $langavailable = $container['conf']["lang_available"];
        if (
            $container['conf']["lang_detection_method"] == 'domain'
            && isset($container['conf']["lang_by_domain"])
            && is_array($container['conf']["lang_by_domain"])
        ) { // domain based language detection
            foreach ($container['conf']["lang_by_domain"] as $sKey => $sValue) {
                if ($_SERVER["SERVER_NAME"] == $sValue || $_SERVER["SERVER_NAME"] == 'www.'.$sValue) {
                    $sLang = $sKey;
                    break;
                }
            }
        } elseif ($container['conf']["lang_detection_method"] == 'legacy') { // legacy language detection
            $sLang = key($langavailable);
            if (isset($_GET["language"]) && array_key_exists($_GET["language"], $langavailable)) {
                $sLang = strtolower($_GET["language"]);
                setcookie('language', strtolower($_GET["language"]), 0, '/');
            } elseif (isset($_COOKIE["language"]) && array_key_exists($_COOKIE["language"], $langavailable)) {
                $sLang = strtolower($_COOKIE["language"]);
            } elseif (isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) && array_key_exists(substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2), $langavailable)) {
                $sLang = substr($_SERVER["HTTP_ACCEPT_LANGUAGE"], 0, 2);
            }
        }
        if (!isset($sLang)) {
            $sLang = key($langavailable);
        }

        return $sLang;
    }

    // don't remove this, this is the fallback for unavailable twig functions
    public static function reachThrough($string) {
        return $string;
    }
}
