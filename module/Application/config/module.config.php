<?php

return array(
    'router' => array(
        'routes' => array(
            'home' => array(
                'type' => 'Zend\Mvc\Router\Http\Literal',
                'options' => array(
                    'route'    => '/',
                    'defaults' => array(
                        // Tells your route which dispatch config to use
                        'dispatch' => 'Dispatch\Main',
                        'controller' => 'Page\Index',
                        'action'     => 'index',
                    )
                ),
            ),
        ),
    ),
    'dispatch' => array(
        // This allows you to override the defaults on a route by route basis
        'routes' => array(
            // e.g.
            //'home' => array(
            //    'Dispatch\Main' => array(
            //        'header' => 'Layout\AlternativeHeader'
            //    )
            //),
        ),
        // This defines the default partial controllers (or recursive dispatches) to run for a given dispatch
        'defaults' => array(
            'Dispatch\Main' => array(
                'header' => 'Layout\Header',
                // You can define a sub-dispatch for more complicated views
                'sidebar' => 'Dispatch\Sidebar',
                'footer' => 'Layout\Footer'
            ),
            'Dispatch\Sidebar' => array(
                'basket' => 'Layout\Basket'
            )
        )
    ),
    'service_manager' => array(
        'invokables' => array(
            
        )
    ),
    'controllers' => array(
        'invokables' => array(
            'Dispatch\Main' => 'Application\Controller\Dispatch\MainController',
            'Dispatch\Sidebar' => 'Application\Controller\Dispatch\SidebarController',
            'Layout\Header' => 'Application\Controller\Layout\HeaderController',
            'Layout\Footer' => 'Application\Controller\Layout\FooterController',
            'Layout\Basket' => 'Application\Controller\Layout\BasketController',
            'Page\Index' => 'Application\Controller\Page\IndexController'
        ),
    ),
    'view_manager' => array(
        'display_not_found_reason' => true,
        'display_exceptions'       => true,
        'doctype'                  => 'HTML5',
        'not_found_template'       => 'error/404',
        'exception_template'       => 'error/index',
        'template_map' => array(
            'layout/layout'           => __DIR__ . '/../view/layout/layout.phtml',
            'error/404'               => __DIR__ . '/../view/error/404.phtml',
            'error/index'             => __DIR__ . '/../view/error/index.phtml',
        ),
        'template_path_stack' => array(
            __DIR__ . '/../view',
        ),
    ),
);
