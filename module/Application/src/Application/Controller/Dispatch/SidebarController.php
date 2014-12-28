<?php

namespace Application\Controller\Dispatch;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class SidebarController extends AbstractActionController
{
    public function indexAction()
    {
        $view = new ViewModel();
        $view->setTemplate('layout/sidebar');

        return $view;
    }
}
