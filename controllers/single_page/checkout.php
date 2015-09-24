<?php
namespace Concrete\Package\VividStore\Controller\SinglePage;

use PageController;
use Core;
use View;
use Package;
use Session;
use Config;
use Loader;
use UserAttributeKey;

use \Concrete\Package\VividStore\Src\VividStore\Order\Order as StoreOrder;
use \Concrete\Package\VividStore\Src\VividStore\Cart\Cart as StoreCart;
use \Concrete\Package\VividStore\Src\VividStore\Payment\Method as StorePaymentMethod;
use \Concrete\Package\VividStore\Src\VividStore\Customer\Customer as StoreCustomer;
use \Concrete\Package\VividStore\Src\VividStore\Discount\DiscountRule as StoreDiscountRule;

class Checkout extends PageController
{
    public function view()
    {
        $pkg = Package::getByHandle('vivid_store');

        $customer = new StoreCustomer();
        $this->set('customer', $customer);
        $guestCheckout = Config::get('vividstore.guestCheckout');
        $this->set('guestCheckout', ($guestCheckout ? $guestCheckout : 'off'));
        $this->set('requiresLogin', StoreCart::requiresLogin());

        if(StoreCart::getTotalItemsInCart() == 0){
            $this->redirect("/cart/");
        }
        $this->set('form',Core::make("helper/form"));

        $allcountries = Core::make('helper/lists/countries')->getCountries();

        $db = Loader::db();

        $ak = UserAttributeKey::getByHandle('billing_address');
        $row = $db->GetRow(
            'select akHasCustomCountries, akDefaultCountry from atAddressSettings where akID = ?',
            array($ak->getAttributeKeyID())
        );

        $defaultBillingCountry = $row['akDefaultCountry'];

        if ($row['akHasCustomCountries'] == 1) {
            $availableBillingCountries = $db->GetCol(
                'select country from atAddressCustomCountries where akID = ?',
                array($ak->getAttributeKeyID())
            );

            $billingCountries = array();
            foreach($availableBillingCountries as $countrycode) {
                $billingCountries[$countrycode] = $allcountries[$countrycode];
            }
        } else {
            $billingCountries =  $allcountries;
        }

        $ak = UserAttributeKey::getByHandle('shipping_address');
        $row = $db->GetRow(
            'select akHasCustomCountries, akDefaultCountry from atAddressSettings where akID = ?',
            array($ak->getAttributeKeyID())
        );

        $defaultShippingCountry = $row['akDefaultCountry'];

        if ($row['akHasCustomCountries'] == 1) {
            $availableShippingCountries = $db->GetCol(
                'select country from atAddressCustomCountries where akID = ?',
                array($ak->getAttributeKeyID())
            );

            $shippingCountries = array();
            foreach($availableShippingCountries as $countrycode) {
                $shippingCountries[$countrycode] = $allcountries[$countrycode];
            }
        } else {
            $shippingCountries = $allcountries;
        }

        $discountsWithCodesExist = StoreDiscountRule::discountsWithCodesExist();

        $this->set("discountsWithCodesExist",$discountsWithCodesExist);

        $this->set('cart', StoreCart::getCart());
        $this->set('discounts', StoreCart::getDiscounts());
        $this->set('hasCode', StoreCart::hasCode());

        $this->set("billingCountries",$billingCountries);
        $this->set("shippingCountries",$shippingCountries);

        $this->set("defaultBillingCountry",$defaultBillingCountry);
        $this->set("defaultShippingCountry",$defaultShippingCountry);

        $this->set("states",Core::make('helper/lists/states_provinces')->getStates());

        $totals = StoreCart::getTotals();

        $this->set('subtotal',$totals['subTotal']);
        $this->set('taxes',$totals['taxes']);

        $taxBased = Config::get('vividstore.taxBased');
        $taxlabel = Config::get('vividstore.taxName');
        
        $this->set('taxtotal',$totals['taxTotal']);

        $this->set('shippingtotal',$totals['shippingTotal']);
        $this->set('total',$totals['total']);
        $this->set('shippingEnabled', StoreCart::isShippable());

        $this->addHeaderItem("
            <script type=\"text/javascript\">
                var PRODUCTMODAL = '".View::url('/productmodal')."';
                var CARTURL = '".View::url('/cart')."';
                var CHECKOUTURL = '".View::url('/checkout')."';
            </script>
        ");

        $packagePath = $pkg->getRelativePath();
        $this->requireAsset('javascript', 'vivid-store');
        $this->requireAsset('css', 'vivid-store');
        $this->addFooterItem("
            <script type=\"text/javascript\">
                vividStore.loadViaHash();
            </script>
        ");

        $enabledMethods = StorePaymentMethod::getEnabledMethods();

        $availableMethods = array();

        foreach($enabledMethods as $em) {
            $emmc = $em->getMethodController();

            if ($totals['total'] >= $emmc->getPaymentMinimum() && $totals['total'] <=  $emmc->getPaymentMaximum()) {
                $availableMethods[] = $em;
            }
        }

        $this->set("enabledPaymentMethods",$availableMethods);
    }
    
    public function failed()
    {
        $this->set('paymentErrors',Session::get('paymentErrors'));
        $this->view();
    }
    public function submit()
    {
        $data = $this->post();
        
        //process payment
        $pmHandle = $data['payment-method'];
        $pm = StorePaymentMethod::getByHandle($pmHandle);
        if(!($pm !== false)){
            //There was no payment method enabled somehow.
            //so we'll force invoice.
            $pm = StorePaymentMethod::getByHandle('invoice');
        }

        if($pm->getMethodController()->external == true){
            $pmsess = Session::get('paymentMethod');
            $pmsess[$pm->getPaymentMethodID()] = $data['payment-method'];
            Session::set('paymentMethod',$pmsess);
            $order = StoreOrder::add($data,$pm,'incomplete');
            Session::set('orderID',$order->getOrderID());
            $this->redirect('/checkout/external');
        } else {
            $payment = $pm->submitPayment();
            if($payment['error']==1){
                $pmsess = Session::get('paymentMethod');
                $pmsess[$pm->getPaymentMethodID()] = $data['payment-method'];
                Session::set('paymentMethod',$pmsess);
                $pesess = Session::get('paymentErrors');
                $pesess = $payment['errorMessage'];
                Session::set('paymentErrors',$pesess);
                $this->redirect("/checkout/failed#payment");
            } else {
                StoreOrder::add($data,$pm);
                $this->redirect('/checkout/complete');
            }
        }

    }
    public function external()
    {
        $pm = Session::get('paymentMethod');
        /*print_r($pm);
        exit();die();
        */
        foreach($pm as $pmID=>$handle){
            $pm = StorePaymentMethod::getByID($pmID);
        }
        //$pm = PaymentMethod::getByHandle($pm[3]);
        $this->set('pm',$pm);
        $this->set('action',$pm->getMethodController()->getAction());

    }
    public function validate()
    {
        
    }

}
