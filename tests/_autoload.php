<?php
/**
 * Setup autoloading
 */
$loader = array(
    'Contain'           => __DIR__ . '/../../contain/src/Contain',
    'ContainTest'       => __DIR__ . '/../../contain/tests/Contain',
    'ContainMapperTest' => __DIR__ . '/ContainMapper',
    'ContainMapper'     => __DIR__ . '/../src/ContainMapper',
);

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
} else {
    // if composer autoloader is missing, explicitly load the standard
    // autoloader by relativepath
    require_once __DIR__ . '/../../../zendframework/zendframework/library/Zend/Loader/StandardAutoloader.php';
    $loader['Zend'] = __DIR__ . '/../../../zendframework/zendframework/library/Zend';
}

$loader = new Zend\Loader\StandardAutoloader(array(
    Zend\Loader\StandardAutoloader::LOAD_NS => $loader,
));
$loader->register();
