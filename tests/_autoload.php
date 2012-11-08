<?php
/**
 * Setup autoloading
 */
if (file_exists(__DIR__ . '/../../../autoload.php')) {
    include_once __DIR__ . '/../../../autoload.php';
} else {
    // if composer autoloader is missing, explicitly load the standard
    // autoloader by relativepath
    require_once __DIR__ . '/../../../zendframework/zendframework/library/Zend/Loader/StandardAutoloader.php';
}

$loader = new Zend\Loader\StandardAutoloader(array(
    Zend\Loader\StandardAutoloader::LOAD_NS => array(
        'Zend'              => __DIR__ . '/../../../zendframework/zendframework/library/Zend',
        'Contain'           => __DIR__ . '/../../contain/src/Contain',
        'ContainTest'       => __DIR__ . '/../../contain/tests/Contain',
        'ContainMapperTest' => __DIR__ . '/ContainMapper',
        'ContainMapper'     => __DIR__ . '/../src/ContainMapper',
    ),
));
$loader->register();
