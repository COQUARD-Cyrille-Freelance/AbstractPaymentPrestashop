<?php


namespace AbstractPaymentPrestashop\Models;

use PrestaShop\PrestaShop\Adapter\Entity\Db;
use PrestaShop\PrestaShop\Adapter\Entity\DbQuery;
use PrestaShop\PrestaShop\Adapter\Entity\ObjectModel;

abstract class AbstractTransaction extends ObjectModel
{
    protected static $table;
    protected static $primaryKey;
    public $id;
    public $order_id;
    public $order_ref;
    public $customer_id;
    public $trx_id;
    public $trx_state;
    public $trx_currency_code;
    public $total_amount;
    public $paid_amount;
    public $shipping_amount;
    public $date_add;
    public $date_upd;

    /**
     * @see ObjectModel::$definition
     */
    public static $definition = [];

    public static function getLatestByOrderId($orderId, $orderStateName = null)
    {
        $query = new DbQuery();
        $query->select('*');
        $query->from(self::$table);
        $query->where('order_id = ' . ((int) $orderId));

        if (!empty ($orderStateName))
        {
            $query->where('trx_state = \''.pSQL($orderStateName).'\'');
        }

        return self::createInstance(Db::getInstance()->executeS($query));
    }

    private static function createInstance($dataArr)
    {
        $instances = array();
        foreach($dataArr as $instance)
            array_push($instances, new self($instance[self::$primaryKey));
        return array_pop($instances);
    }
}