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

namespace CoquardCyrilleFreelance\AbstractPaymentPrestashop;

use CoquardCyrilleFreelance\AbstractPaymentPrestashop\Status\Contracts\OrderStatusInterface;
use Context;
use Exception;
use HelperForm;
use Language;
use Media;
use Module;
use OrderState;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use PrestaShop\PrestaShop\Adapter\Entity\Validate;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use Tools;

trait ModulePaymentTrait
{
    protected $orderStatus;
    protected $context;

    public function setUp(OrderStatusInterface $orderStatus, Context $context)
    {
        $this->context = $context;
        $this->orderStatus = $orderStatus;
        $this->tab = 'payments_gateways';
        $this->is_eu_compatible = false;
        $this->bootstrap = true;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->module_link = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $this->confirmUninstall = $this->trans('Are you sure you want to delete your details?', [], 'Modules.Scbpayment.Description');
    }

    public function install()
    {
        // Install default
        if (!parent::install() || !$this->installSQL() || !$this->registrationHook() || !$this->createConfigKey() || !$this->installStates()) {
            return false;
        }

        return true;
    }

    public function uninstall()
    {
        if (!$this->uninstallSQL() || !$this->deleteConfigKey() || !parent::uninstall()) {
            return false;
        }

        return true;
    }

    abstract protected function installSQL();

    abstract protected function uninstallSQL();

    /**
     * [registrationHook description]
     *
     * @return [type] [description]
     */
    protected function registrationHook()
    {
        if (!$this->registerHook($this->getHooknames())) {
            return false;
        }

        return true;
    }

    protected function getHooknames()
    {
        return [
            'paymentOptions',
            'paymentReturn',
            'displayBackOfficeHeader',
            'displayOrderConfirmation',
        ];
    }

    /**
     * Returns the translation domain from the module
     *
     * @return string translation domain from the module
     */
    abstract public function getModuleTranslationDomain(): string;

    protected function createConfigKey()
    {
        foreach ($this->getConfigKeys() as $key) {
            if (!Configuration::updateValue($this->getConfigPrefix() . $key, '')) {
                return false;
            }
        }

        return true;
    }

    protected function getConfigKeys()
    {
        return [
            'CHANNEL_ID',
            'CHANNEL_SECRET_KEY',
            'SANDBOX_CHANNEL_ID',
            'SANDBOX_CHANNEL_SECRET_KEY',
            'SANDBOX_MODE',
        ];
    }

    abstract public function getConfigPrefix();

    /**
     * Delete config keys of the project
     *
     * @param string $query
     *
     * @return bool
     */
    protected function deleteConfigKey()
    {
        foreach ($this->getConfigKeys() as $key) {
            if (!Configuration::deleteByName($this->getConfigPrefix() . $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Install all states
     *
     * @return bool success
     */
    protected function installStates()
    {
        try {
            foreach ($this->orderStatus->listStates() as $state => $infos) {
                $this->createState($state, $infos['description'], $infos['color']);
            }
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    protected function createState($key, $description, $color, $send_email = false, $hidden = false, $delivery = false, $loggable = false, $invoice = false)
    {
        if (!Configuration::get($key)
            || !Validate::isLoadedObject(new OrderState(Configuration::get($key)))) {
            $order_state = new OrderState();
            $order_state->name = [];
            foreach (Language::getLanguages() as $language) {
                if (in_array(Tools::strtolower($language['iso_code']), array_keys($description))) {
                    $order_state->name[$language['id_lang']] = $description[Tools::strtolower($language['iso_code'])];
                } else {
                    $order_state->name[$language['id_lang']] = $description['en'];
                }
            }
            $order_state->send_email = $send_email;
            $order_state->color = $color;
            $order_state->hidden = $hidden;
            $order_state->delivery = $delivery;
            $order_state->logable = $loggable;
            $order_state->invoice = $invoice;
            $order_state->add();
            Configuration::updateValue($key, (int) $order_state->id);
        }
    }

    public function getContent()
    {
        $this->collectSubmitedInfos();
        $helper = new HelperForm();
        $helper->show_toolbar = false;

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;

        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->submit_action = 'submit';

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = [
            'fields_value' => $this->getValues(),
            'languages' => Context::getContext()->controller->getLanguages(),
            'id_language' => Context::getContext()->language->id,
        ];

        return $helper->generateForm([
            $this->getConfigForm(),
        ]);
    }

    protected function collectSubmitedInfos()
    {
        if (!Tools::isSubmit('submit')) {
            return;
        }
        $configKeys = $this->getConfigKeys();
        array_walk($configKeys, function ($key) {
            Configuration::updateValue($this->getConfigPrefix() . $key, Tools::getValue($key));
        });
    }

    protected function getValues()
    {
        $values = [];
        $configKeys = $this->getConfigKeys();
        foreach ($configKeys as $key) {
            $values[$this->getConfigPrefix() . $key] = Configuration::get($this->getConfigPrefix() . $key);
        }

        return $values;
    }

    protected function getConfigForm()
    {
        return [
            'form' => [
                'legend' => [
                    'title' => $this->trans('Setting info.', [], 'Modules.Scbpayment.Settings'),
                    'icon' => 'icon-envelope',
                ],
                'input' => [
                    [
                        'type' => 'text',
                        'label' => $this->trans('Channel ID', [], 'Modules.Scbpayment.Settings'),
                        'name' => $this->getConfigPrefix() . 'CHANNEL_ID',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Secret Key', [], 'Modules.Scbpayment.Settings'),
                        'name' => $this->getConfigPrefix() . 'CHANNEL_SECRET_KEY',
                    ],
                    [
                        'type' => 'switch',
                        'label' => $this->trans('Sandbox Mode', [], 'Modules.Scbpayment.Settings'),
                        'name' => $this->getConfigPrefix() . 'SANDBOX_MODE',
                        'is_bool' => true,
                        'desc' => $this->trans('Activate "Sandbox Mode', [], "Modules.{$this->getModuleTranslationDomain()}.Settings"),
                        'values' => [
                            [
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->trans('Activation', [], "Modules.{$this->getModuleTranslationDomain()}.Settings"),
                            ],
                            [
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->trans('Deactivation', [], "Modules.{$this->getModuleTranslationDomain()}.Settings"),
                            ],
                        ],
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Channel ID', [], "Modules.{$this->getModuleTranslationDomain()}.Settings"),
                        'name' => $this->getConfigPrefix() . 'SANDBOX_CHANNEL_ID',
                    ],
                    [
                        'type' => 'text',
                        'label' => $this->trans('Channel Secret Key', [], "Modules.{$this->getModuleTranslationDomain()}.Settings"),
                        'name' => $this->getConfigPrefix() . 'SANDBOX_CHANNEL_SECRET_KEY',
                    ],
                ],
                'submit' => [
                    'title' => $this->trans('Save', [], "Modules.{$this->getModuleTranslationDomain()}.Settings"),
                ],
            ],
        ];
    }

    public function hookPaymentOptions($params)
    {
            if (!$this->active || !$this->isConfigured()) {
                return false;
            }
        try {

            $externalOption = new PaymentOption();


            return [$externalOption];
        } catch (Exception $lpe) {
            return false;
        }
    }

    protected function setUpPaymentOption(PaymentOption $paymentOption): PaymentOption {
        $paymentOption->setCallToActionText($this->trans('Pay with %name%', ['%name%' => $this->name], "Modules.{$this->getModuleTranslationDomain()}.Settings"))
            ->setAction($this->context->link->getModuleLink($this->name, 'request', [], true))
            ->setLogo(Media::getMediaPath($this->getLogo()));
        return $paymentOption;
    }

    protected function isConfigured()
    {
        return (Configuration::get($this->getConfigPrefix() . 'SANDBOX_MODE') && Configuration::get($this->getConfigPrefix() . 'SANDBOX_CHANNEL_ID') && Configuration::get($this->getConfigPrefix() . 'SANDBOX_CHANNEL_SECRET_KEY'))
            || (Configuration::get($this->getConfigPrefix() . 'CHANNEL_ID') && Configuration::get($this->getConfigPrefix() . 'CHANNEL_SECRET_KEY'));
    }

    abstract protected function getLogo();

    protected function isModuleActive()
    {
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == $this->name) {
                return (int) $module['id_module'] == (int) $this->id;
            }
        }

        return false;
    }

    abstract public function hookPaymentReturn($params);
}
