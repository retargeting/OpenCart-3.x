<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit4db17df9584139d53ccd934cad06e743
{
    public static $prefixLengthsPsr4 = array (
        'R' => 
        array (
            'RetargetingSDK\\' => 15,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'RetargetingSDK\\' => 
        array (
            0 => __DIR__ . '/..' . '/retargeting/retargeting-sdk/lib',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit4db17df9584139d53ccd934cad06e743::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit4db17df9584139d53ccd934cad06e743::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}