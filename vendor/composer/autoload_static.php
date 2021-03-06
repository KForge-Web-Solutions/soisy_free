<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit2df7cecc726b2488c1787ff038fe10d9
{
    public static $prefixLengthsPsr4 = array (
        'K' => 
        array (
            'KForge\\Soisy\\' => 13,
            'KForge\\Lib\\' => 11,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'KForge\\Soisy\\' => 
        array (
            0 => __DIR__ . '/..' . '/kforge/soisy-lib-php/src',
        ),
        'KForge\\Lib\\' => 
        array (
            0 => __DIR__ . '/../..' . '/classes',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit2df7cecc726b2488c1787ff038fe10d9::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit2df7cecc726b2488c1787ff038fe10d9::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit2df7cecc726b2488c1787ff038fe10d9::$classMap;

        }, null, ClassLoader::class);
    }
}
