<?php

namespace Application\Controller\Layout;

use Zend\Mvc\Controller\AbstractActionController;
use Zend\View\Model\ViewModel;

class BasketController extends AbstractActionController
{
    public function indexAction()
    {
        $view = new ViewModel(
            array(
                'item' => $this->params()->fromPost('item')
            )
        );
        $view->setTemplate('layout/basket');
        $view->setTerminal($this->params('isPartial'));

        return $view;
    }
}
