<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInitcef2e32088cb7aca361256449f6426bc
{
    public static $prefixLengthsPsr4 = array (
        'L' => 
        array (
            'LINE\\' => 5,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'LINE\\' => 
        array (
            0 => __DIR__ . '/..' . '/linecorp/line-bot-sdk/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInitcef2e32088cb7aca361256449f6426bc::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInitcef2e32088cb7aca361256449f6426bc::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
