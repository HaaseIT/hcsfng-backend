<?php

error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
require_once __DIR__.'/config/constants.php';
ini_set('error_log', PATH_LOGS.'php_error.log');
ini_set('xdebug.overload_var_dump', 0);
ini_set('xdebug.var_display_max_depth', 10);
ini_set('html_errors', 0);

mb_internal_encoding('UTF-8');
header("Content-Type: text/html; charset=UTF-8");

if (ini_get('session.auto_start') == 1) {
    die('Please disable session.autostart for this to work.');
}

require_once __DIR__.'/../vendor/autoload.php';

$container = new Pimple\Container();

$AuraLoader = new Aura\Autoload\Loader;
$AuraLoader->register();
$AuraLoader->addPrefix('\HaaseIT\HCSFNG\Backend', __DIR__.'/../src');

// PSR-7 Stuff
// Init request object
$container['request'] = Zend\Diactoros\ServerRequestFactory::fromGlobals();

// cleanup request
$requesturi = urldecode($container['request']->getRequestTarget());
$container['requesturi'] = \substr($requesturi, \strlen(\dirname($_SERVER['PHP_SELF'])));
if (substr($container['requesturi'], 1, 1) != '/') {
    $container['requesturi'] = '/'.$container['requesturi'];
}
$container['request'] = $container['request']->withRequestTarget($container['requesturi']);

use Symfony\Component\Yaml\Yaml;
$container['conf'] = function ($c) {
    $conf = Yaml::parse(file_get_contents(__DIR__.'/config/core.dist.yml'));
    if (is_file(__DIR__.'/config/core.yml')) $conf = array_merge($conf, Yaml::parse(file_get_contents(__DIR__.'/config/core.yml')));
    //$conf = array_merge($conf, Yaml::parse(file_get_contents(__DIR__.'/config/config.countries.yml')));
    $conf = array_merge($conf, Yaml::parse(file_get_contents(__DIR__.'/config/secrets.yml')));

    if (!empty($conf['maintenancemode']) && $conf['maintenancemode']) {
        $conf["templatecache_enable"] = false;
        $conf["debug"] = false;
        $conf['textcatsverbose'] = false;
    } else {
        $conf['maintenancemode'] = false;
    }

    return $conf;
};

date_default_timezone_set($container['conf']["defaulttimezone"]);
$container['lang'] = HaaseIT\HCSFNG\Backend\Helper::getLanguage($container);

if (file_exists(PATH_BASEDIR.'src/hardcodedtextcats/'.$container['lang'].'.php')) {
    $HT = require PATH_BASEDIR.'src/hardcodedtextcats/'.$container['lang'].'.php';
} else {
    if (file_exists(PATH_BASEDIR.'src/hardcodedtextcats/'.key($container['conf']["lang_available"]).'.php')) {
        $HT = require PATH_BASEDIR.'src/hardcodedtextcats/'.key($container['conf']["lang_available"]).'.php';
    } else {
        $HT = require PATH_BASEDIR.'src/hardcodedtextcats/de.php';
    }
}
use \HaaseIT\HCSF\HardcodedText;
HardcodedText::init($HT);

$container['navstruct'] = [];
$container['db'] = null;
$container['entitymanager'] = null;
if (!$container['conf']['maintenancemode']) {
// ----------------------------------------------------------------------------
// Begin database init
// ----------------------------------------------------------------------------

    $container['entitymanager'] = function ($c)
    {
        $doctrineconfig = Doctrine\ORM\Tools\Setup::createAnnotationMetadataConfiguration([PATH_BASEDIR."/src"], $c['conf']['debug']);

        $connectionParams = array(
            'url' => $c['conf']["db_type"].'://'.$c['conf']["db_user"].':'.$c['conf']["db_password"].'@'.$c['conf']["db_server"].'/'.$c['conf']["db_name"],
            'charset' => 'UTF8',
            'driverOptions' => [
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            ],
        );

        return Doctrine\ORM\EntityManager::create($connectionParams, $doctrineconfig);
    };

    $container['db'] = function ($c)
    {
        return $c['entitymanager']->getConnection()->getWrappedConnection();
    };

    // ----------------------------------------------------------------------------
    // more init stuff
    // ----------------------------------------------------------------------------
    $container['textcats'] = function ($c)
    {
        $langavailable = $c['conf']["lang_available"];
        $textcats = new \HaaseIT\Textcat($c, key($langavailable), $c['conf']['textcatsverbose'], PATH_LOGS);
        $textcats->loadTextcats();

        return $textcats;
    };

    $container['navstruct'] = function ($c)
    {
        $navstruct = include __DIR__.'/config/config.navi.php';

        if (isset($navstruct["admin"])) {
            unset($navstruct["admin"]);
        }

        $navstruct["admin"][HardcodedText::get('admin_nav_home')] = '/_admin/index.html';

        if ($c['conf']["enable_module_shop"]) {
            $navstruct["admin"][HardcodedText::get('admin_nav_orders')] = '/_admin/shopadmin.html';
            $navstruct["admin"][HardcodedText::get('admin_nav_items')] = '/_admin/itemadmin.html';
            $navstruct["admin"][HardcodedText::get('admin_nav_itemgroups')] = '/_admin/itemgroupadmin.html';
        }

        if ($c['conf']["enable_module_customer"]) {
            $navstruct["admin"][HardcodedText::get('admin_nav_customers')] = '/_admin/customeradmin.html';
        }

        $navstruct["admin"][HardcodedText::get('admin_nav_pages')] = '/_admin/pageadmin.html';
        $navstruct["admin"][HardcodedText::get('admin_nav_textcats')] = '/_admin/textcatadmin.html';
        $navstruct["admin"][HardcodedText::get('admin_nav_cleartemplatecache')] = '/_admin/cleartemplatecache.html';
        $navstruct["admin"][HardcodedText::get('admin_nav_clearimagecache')] = '/_admin/clearimagecache.html';
        $navstruct["admin"][HardcodedText::get('admin_nav_phpinfo')] = '/_admin/phpinfo.html';
        $navstruct["admin"][HardcodedText::get('admin_nav_dbstatus')] = '/_admin/dbstatus.html';

        return $navstruct;
    };
}

// ----------------------------------------------------------------------------
// Begin Twig loading and init
// ----------------------------------------------------------------------------

$container['twig'] = function ($c) {
    $loader = new Twig_Loader_Filesystem([__DIR__ . '/../src/views/']);
    $twig_options = [
        'autoescape' => false,
        'debug' => (isset($c['conf']["debug"]) && $c['conf']["debug"] ? true : false)
    ];
    if (isset($c['conf']["templatecache_enable"]) && $c['conf']["templatecache_enable"] &&
        is_dir(PATH_TEMPLATECACHE) && is_writable(PATH_TEMPLATECACHE)) {
        $twig_options["cache"] = PATH_TEMPLATECACHE;
    }
    $twig = new Twig_Environment($loader, $twig_options);

    if ($c['conf']['allow_parsing_of_page_content']) {
        $twig->addExtension(new Twig_Extension_StringLoader());
    } else { // make sure, template_from_string is callable
        $twig->addFunction('template_from_string', new Twig_Function_Function('\HaaseIT\HCSF\Backend\Helper::reachThrough'));
    }

    if (isset($c['conf']["debug"]) && $c['conf']["debug"]) {
        //$twig->addExtension(new Twig_Extension_Debug());
    }
    $twig->addFunction('HT', new Twig_Function_Function('\HaaseIT\HCSF\HardcodedText::get'));
    //$twig->addFunction('gFF', new Twig_Function_Function('\HaaseIT\Tools::getFormField'));

    return $twig;
};
