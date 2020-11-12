<?php


namespace AbstractPaymentPrestashop\Currencies;


use AbstractPaymentPrestashop\Exceptions\AbstractPaymentException;
use PrestaShop\PrestaShop\Adapter\Entity\Currency;

abstract class AbstractCurrency
{
    protected $currencyScales = [];

    /**
     * Standardize the amount for the currency given in parameter for the currency
     * @param float $amount amount to convert
     * @param string $currency ISO from the currency of the transaction
     * @return float formatted price of the money
     * @throws AbstractPaymentException
     */
    public function standardizeAmount($amount, $currency) {
        $upperCurrencyCode = strtoupper($currency);
        if(!in_array($upperCurrencyCode, array_keys($this->currencyScales)))
            throw new AbstractPaymentException();

        $scale = $this->currencyScales[$upperCurrencyCode];
        if (is_string($amount))
            $amount = floatval($amount);
        return (float) number_format($amount, $scale, '.', '');
    }

    /**
     * Returns the enabled currencies that the module supports
     * @param int $moduleId id of the module
     * @return array list of currrencies' ISO
     */
    public function getEnabledCurrencies($moduleId) {
        $enabledCurrencies = array();
        $currencies = Currency::getPaymentCurrencies($moduleId);
        foreach ($currencies as $idx => $currency)
            array_push($enabledCurrencies, strtoupper($currency['iso_code']));

        return array_intersect($enabledCurrencies, array_keys($this->currencyScales));
    }

    /**
     * Returns the symbol from the currency code
     * @param $currencyCode string currency code
     * @return string currency code
     */
    public function getSymbol(string $currencyCode): string {
        $currencyId = Currency::getIdByIsoCode($currencyCode);
        $currency = new Currency($currencyId);
        return $currency->symbol;
    }
}