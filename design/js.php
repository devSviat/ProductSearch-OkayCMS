<?php

use Okay\Core\TemplateConfig\Js;

return [
    (new Js('live-search.js'))
        ->setPosition('footer')
        ->setIndividual(true)
        ->setDefer(true),
];
