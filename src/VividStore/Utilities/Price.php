<?php 
namespace Concrete\Package\VividStore\Src\VividStore\Utilities;
use Package;
defined('C5_EXECUTE') or die(_("Access Denied."));

class Price
{
    public static function format($price)
    {
        $price = floatval($price);    
        $pkg = Package::getByHandle('vivid_store');
        $symbol = $pkg->getConfig()->get('vividstore.symbol');
        $wholeSep = $pkg->getConfig()->get('vividstore.whole');
        $thousandSep = $pkg->getConfig()->get('vividstore.thousand');
        $price = $symbol . number_format(floatval($price), 2, $wholeSep, $thousandSep);
        return $price;
    }   
    public function getFloat($price)
    {
        $pkg = Package::getByHandle('vivid_store');
        $pkgconfig = $pkg->getConfig();
        $symbol = $pkgconfig->get('vividstore.symbol');
        $wholeSep = $pkgconfig->get('vividstore.whole');
        $thousandSep = $pkgconfig->get('vividstore.thousand');
        
        $price = str_replace($wholeSep, ".", $price); // replace whole separator with '.' 
        $price = str_replace($thousandSep, "", $price); //no commas, or spaces or whatevz
        $price = str_replace($symbol, "", $price);
        
        return $price;
    }
    
}
