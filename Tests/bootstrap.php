<?php

declare(strict_types=1);

$mauticRoot   = dirname(__DIR__, 3);
$pluginVendor = dirname(__DIR__).'/vendor/autoload.php';
$mauticVendor = $mauticRoot.'/vendor/autoload.php';

if (file_exists($pluginVendor)) {
    include_once $pluginVendor;
} elseif (file_exists($mauticVendor)) {
    include_once $mauticVendor;
} else {
    throw new RuntimeException('Composer autoload not found.');
}

$kernelDir = $mauticRoot.'/app';
if (is_dir($kernelDir)) {
    $_SERVER['KERNEL_DIR'] = $kernelDir;
    $_ENV['KERNEL_DIR']    = $kernelDir;
}
