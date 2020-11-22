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

namespace CoquardCyrilleFreelance\AbstractPaymentPrestashop\Currencies;

use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Exceptions\AbstractPaymentException;
use PrestaShop\PrestaShop\Adapter\Entity\Currency;

abstract class AbstractCurrency
{
    protected $currencyScales = [];

    /**
     * Standardize the amount for the currency given in parameter for the currency
     *
     * @param float $amount amount to convert
     * @param string $currency ISO from the currency of the transaction
     *
     * @return float formatted price of the money
     *
     * @throws AbstractPaymentException
     */
    public function standardizeAmount($amount, $currency)
    {
        $upperCurrencyCode = strtoupper($currency);
        if (!in_array($upperCurrencyCode, array_keys($this->currencyScales))) {
            throw new AbstractPaymentException();
        }
        $scale = $this->currencyScales[$upperCurrencyCode];
        if (is_string($amount)) {
            $amount = floatval($amount);
        }

        return (float) number_format($amount, $scale, '.', '');
    }

    /**
     * Returns the enabled currencies that the module supports
     *
     * @param int $moduleId id of the module
     *
     * @return array list of currrencies' ISO
     */
    public function getEnabledCurrencies($moduleId)
    {
        $enabledCurrencies = [];
        $currencies = Currency::getPaymentCurrencies($moduleId);
        foreach ($currencies as $idx => $currency) {
            array_push($enabledCurrencies, strtoupper($currency['iso_code']));
        }

        return array_intersect($enabledCurrencies, array_keys($this->currencyScales));
    }

    /**
     * Returns the symbol from the currency code
     *
     * @param $currencyCode string currency code
     *
     * @return string currency code
     */
    public function getSymbol(string $currencyCode): string
    {
        $currencyId = Currency::getIdByIsoCode($currencyCode);
        $currency = new Currency($currencyId);

        return $currency->symbol;
    }
}
