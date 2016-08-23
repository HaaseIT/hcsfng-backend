<?php

require __DIR__.'/../../app/init.php';

$response = new \Zend\Diactoros\Response();
$response = $response->withStatus($P->status);
$response->getBody()->write($container['twig']->render($container['conf']["template_base"], $P->payload));

$emitter = new \Zend\Diactoros\Response\SapiEmitter();
$emitter->emit($response);
