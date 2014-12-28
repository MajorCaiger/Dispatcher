<?php

namespace Application\Controller\Layout;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class HeaderController extends AbstractActionController
{
    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/header');

        return $view;
    }
}
