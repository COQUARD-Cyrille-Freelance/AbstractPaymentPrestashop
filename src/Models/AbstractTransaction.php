<?php
/**
* Copyright 2020 COQUARD Cyrille
*
* Redistribution and use in source and binary forms, with or without modification, are permitted provided that the following conditions are met:
*
* 1. Redistributions of source code must retain the above copyright notice, this list of conditions and the following disclaimer.
*
* 2. Redistributions in binary form must reproduce the above copyright notice, this list of conditions and the following disclaimer in the documentation and/or other materials provided with the distribution.
*
* 3. Neither the name of the copyright holder nor the names of its contributors may be used to endorse or promote products derived from this software without specific prior written permission.
*
* THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
*/

namespace CoquardCyrilleFreelance\AbstractPaymentPrestashop\Models;

use PrestaShop\PrestaShop\Adapter\Entity\Db;
use PrestaShop\PrestaShop\Adapter\Entity\DbQuery;
use PrestaShop\PrestaShop\Adapter\Entity\ObjectModel;

abstract class AbstractTransaction extends ObjectModel
{
    protected static $tableName;
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
        $query->from(static::$tableName);
        $query->where('order_id = ' . ((int) $orderId));

        if (!empty($orderStateName)) {
            $query->where('trx_state = \'' . pSQL($orderStateName) . '\'');
        }

        return self::createInstance(Db::getInstance()->executeS($query));
    }

    protected static function createInstance($dataArr)
    {
        $instances = [];
        foreach ($dataArr as $instance) {
            array_push($instances, new static($instance[static::$primaryKey]));
        }

        return array_pop($instances);
    }
}
