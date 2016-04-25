<?php
// Routes

//$app->get('/', 'App\Action\HomeAction:dispatch')->setName('homepage');
$app->get('/step-group', 'App\Action\Championship\StepGroupAction:dispatch')->setName('championship.step-group');
$app->get('/last-16', 'App\Action\Championship\Last16Action:dispatch')->setName('championship.last-16');
