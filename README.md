Dispatcher
==========
Dispatcher is a Zend Framework 2 module that allows you to configure each route to dispatch to multiple controllers per request, and then stitches together the views. This allows you to widgetize your application, and allows you to further seperate your code which makes it more re-useable and testable.

### Installation

###### Via Composer
<pre>
...
"require": {
    "major-caiger/dispatcher": "~0.1"
}
...
</pre>

#### ZF2 Application
Add "Dispatcher" to the modules list in you're application.config.php

### Example
###### module.config.php
<pre>
...
'router' => array(
    'routes' => array(
        'home' => array(
            'type' => 'segment',
                'options' => array(
                    'route' => '[/:action][/]',
                    'defaults' => array(
                        'dispatch' => 'Dispatch\Main', // Defines the dispatch config
                        'controller' => 'Application\Controller\Index',
                        'action' => 'index',
                    ),
                ),
            ),
        ),
    ),
),
'dispatch' => array(
    'defaults' => array(
        // Define which partial/controllers are dispatched to
        'Dispatch\Main' => array(
            // i.e. Child view name => Controller alias/Dispatch config
            'header' => 'Layout\Header',
            'sidebar' => 'Dispatch\Sidebar', // Allows you to nest further dispatch config
            'footer' => 'Layout\Footer'
        ),
        'Dispatch\Sidebar' => array(
            'basket' => 'Widget\Basket'
        ),
    ),
    // This allows you to override the defaults on a route by route basis
    'routes' => array(
        'home' => array(
            'Dispatch\Main' => array(
                'sidebar' => 'Application\AlternativeSidebar'
            ),
        ),
    ),
),
'controllers' => array(
    'invokables' => array(
        // Dispatch controllers
        'Dispatch\Main' => 'Application\Controller\Dispatch\MainController',
        'Dispatch\Sidebar' => 'Application\Controller\Dispatch\SidebarController',
        // Partial controllers
        'Widget\Basket' => 'Application\Controller\Widget\BasketController',
        'Layout\Header' => 'Application\Controller\Layout\HeaderController',
        'Layout\Footer' => 'Application\Controller\Layout\FooterController',
        // Application controllers
        'Application\Controller\Index' => 'Application\Controller\IndexController',
    ),
),
...
</pre>

You're dispatch config "Dispatch\Main" is also a controller alias, which points to a controller, that will construct a view that stitches together the partials such as the following.

###### view/dispatch/main.phtml
<pre>
&lt;div class="header"&gt;
    &lt;?php echo $this->header; ?&gt;
&lt;/div&gt;
&lt;?php echo $this->content; /* This is the view generated by the routes controller definition */ ?&gt;
&lt;div class="sidebar"&gt;
    &lt;?php echo $this->sidebar; ?&gt;
&lt;/div&gt;
&lt;div class="footer"&gt;
    &lt;?php echo $this->footer; ?&gt;
&lt;/div&gt;
</pre>

### Note
All dispatch and partial controllers must extend ZF2s <code>AbstractActionController</code> and have a <code>dispatchAction</code> method.

### Other features
By sending either a POST or GET parameter to a route with a dispatch config such as <code>?dispatch=basket</code> you will just be returned the content for that partial view. This means that you can interact with individual "widgets" or partials on the page and just reload the parts you are interested in without having to process the logic for the other partials.
###### Note: ?dispatch=content will return you the content from the routes controller and action definition