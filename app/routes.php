<?php
// Routes

//$app->get('/', 'App\Action\HomeAction:dispatch')->setName('homepage');
$app->get('/step-group', 'App\Action\Championship\StepGroupAction:dispatch')->setName('championship.step-group');
