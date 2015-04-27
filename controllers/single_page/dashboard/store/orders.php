<?php

namespace Concrete\Package\VividStore\Controller\SinglePage\Dashboard\Store;
use \Concrete\Core\Page\Controller\DashboardPageController;
use Core;
use Package;
use \Concrete\Package\VividStore\Src\VividStore\Orders\OrderStatus\OrderStatus;

use \Concrete\Package\VividStore\Src\VividStore\Orders\OrderList;
use \Concrete\Package\VividStore\Src\VividStore\Orders\Order as VividOrder;

defined('C5_EXECUTE') or die("Access Denied.");
class Orders extends DashboardPageController
{

    public function view($status = '')
    {
        $orderList = new OrderList();

        if ($this->get('keywords')) {
            $orderList->setSearch($this->get('keywords'));
        }

        if ($status) {
            $orderList->setStatus($status);
        }

        $orderList->setItemsPerPage(20);

        $paginator = $orderList->getPagination();
        $pagination = $paginator->renderDefaultView();
        $this->set('orderList',$paginator->getCurrentPageResults());  
        $this->set('pagination',$pagination);
        $this->set('paginator', $paginator);     
        $this->set('orderStatuses', OrderStatus::getList());
        $pkg = Package::getByHandle('vivid_store');
        $packagePath = $pkg->getRelativePath();
        $this->addHeaderItem(Core::make('helper/html')->css($packagePath.'/css/vividStoreDashboard.css'));
        $this->addFooterItem(Core::make('helper/html')->javascript($packagePath.'/js/vividStoreFunctions.js'));

        $this->set('statuses', OrderStatus::getAll());

    }
    public function order($oID)
    {
        $order = VividOrder::getByID($oID);
        $this->set("order",$order);
        $this->set('orderStatuses', OrderStatus::getList());
        $pkg = Package::getByHandle('vivid_store');
        $packagePath = $pkg->getRelativePath();
        $this->addFooterItem(Core::make('helper/html')->javascript($packagePath.'/js/vividStoreFunctions.js'));
    }
    public function removed()
    {
        $this->set("success",t("Order Removed"));
        $this->view();
    }
    public function updatestatus($oID)
    {
        $data = $this->post();
        VividOrder::getByID($oID)->updateStatus($data['orderStatus']);
        $this->redirect('/dashboard/store/orders/order',$oID);
    }
    public function remove($oID)
    {
        VividOrder::getByID($oID)->remove();
        $this->redirect('/dashboard/store/orders/removed');
    }

}
