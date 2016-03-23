<?php

return array(
    'service_manager' => array(
        'factories' => array(
            'DispatchListener' => function ($sm) {
                return new \Dispatcher\DispatchListener($sm->get('ControllerManager'));
            },
        )
    )
);
