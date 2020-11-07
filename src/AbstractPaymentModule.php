<?php


namespace AbstractPaymentPrestashop;

use AbstractPaymentPrestashop\Status\Contracts\OrderStatusInterface;
use Context;
use Exception;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShop\PrestaShop\Adapter\Entity\Validate;
use PrestaShop\PrestaShop\Adapter\Entity\Configuration;
use OrderState;
use Language;
use Media;
use Module;
use Tools;
use PrestaShop\PrestaShop\Adapter\Entity\PaymentModule;

abstract class AbstractPaymentModule extends PaymentModule
{
    protected $orderStatus;
    protected $context;

    public function __construct(OrderStatusInterface $orderStatus, Context $context) {
        $this->context = $context;
        $this->orderStatus = $orderStatus;
        $this->tab = 'payments_gateways';
        $this->is_eu_compatible = false;
        $this->bootstrap = true;
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->module_link = $this->context->link->getAdminLink('AdminModules', true) . '&configure=' . $this->name . '&tab_module=' . $this->tab . '&module_name=' . $this->name;
        $this->confirmUninstall = $this->l('Are you sure you want to delete your details?');
        parent::__construct();
    }

    public function install(){
        // Install default
        if (!parent::install() || ! $this->installSQL() || ! $this->registrationHook() || ! $this->createConfigKey() || !$this->installStates()) {
            return false;
        }
        return true;
    }

    public function uninstall(){
        if(! $this->uninstallSQL() || ! $this->deleteConfigKey() || !parent::uninstall())
            return false;
        return true;
    }

    protected abstract function installSQL();

    protected abstract function uninstallSQL();

    /**
     * [registrationHook description]
     * @return [type] [description]
     */
    protected function registrationHook() {
        if (! $this->registerHook($this->getHooknames()))
            return false;
        return true;
    }

    protected function getHooknames() {
        return [
            'paymentOptions',
            'paymentReturn',
            'displayBackOfficeHeader',
            'displayOrderConfirmation',
        ];
    }

    protected function createConfigKey()
    {
        foreach ($this->getConfigKeys() as $key) {
            if (!Configuration::updateValue($this->getConfigPrefix() . $key, '')) {
                return false;
            }
        }
        return true;
    }

    protected function getConfigKeys() {
        return [
            'CHANNEL_ID',
            'CHANNEL_SECRET_KEY',
            'SANDBOX_CHANNEL_ID',
            'SANDBOX_CHANNEL_SECRET_KEY',
            'SANDBOX_MODE',
        ];
    }

    public abstract function getConfigPrefix();

    /**
     * Delete config keys of the project
     * @param	string $query
     * @return	boolean
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

    protected function installStates(){
        try{
            foreach ($this->orderStatus->listStates() as $state => $infos)
                $this->createState($state, $infos['description'], $infos['color']);
        }catch (Exception $e) {
            return false;
        }
        return true;
    }

    protected function createState($key, $description, $color, $send_email = false, $hidden = false, $delivery = false, $loggable = false, $invoice = false) {
        if (!Configuration::get($key)
            || !Validate::isLoadedObject(new OrderState(Configuration::get($key)))) {
            $order_state = new OrderState();
            $order_state->name = array();
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

    public function getContent() {
        $this->collectSubmitedInfos();
        $helper = new HelperForm();
        $helper->show_toolbar = false;

        $lang = new Language((int) Configuration::get('PS_LANG_DEFAULT'));
        $helper->default_form_language = $lang->id;

        $helper->id = (int) Tools::getValue('id_carrier');
        $helper->submit_action = 'submit';

        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getValues(),
            'languages' => Context::getContext()->controller->getLanguages(),
            'id_language' => Context::getContext()->language->id
        );

        return $helper->generateForm(array(
            $this->getConfigForm()
        ));
    }

    protected function collectSubmitedInfos() {
        if (! Tools::isSubmit('submit'))
            return;
        $configKeys = $this->getConfigKeys();
        array_walk($configKeys, function ($key) {
            Configuration::updateValue($this->getConfigPrefix() . $key, Tools::getValue($key));
        });
    }

    protected function getValues() {
        $values = array();
        $configKeys = $this->getConfigKeys();
        foreach($configKeys as $key) {
            $values[$key] = Configuration::get($this->getConfigPrefix() . $key);
        }
        return $values;
    }

    protected function getConfigForm()
    {
        return array(
            'form' => array(
                'legend' => array(
                    'title' => $this->l('Setting info.'),
                    'icon' => 'icon-envelope'
                ),
                'input' => array(
                    array(
                        'type' => 'text',
                        'label' => 'Channel ID',
                        'name' => $this->getConfigPrefix() . 'CHANNEL_ID',
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Secret Key',
                        'name' => $this->getConfigPrefix() . 'CHANNEL_SECRET_KEY',
                    ),
                    array(
                        'type' => 'switch',
                        'label' => 'Sandbox Mode',
                        'name' => $this->getConfigPrefix() . 'SANDBOX_MODE',
                        'is_bool' => true,
                        'desc' => $this->l('Activate "Sandbox Mode"'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Activation')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('Deactivation')
                            )
                        )
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Channel ID',
                        'name' => $this->getConfigPrefix() . 'SANDBOX_CHANNEL_ID',
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Channel Secret Key',
                        'name' => $this->getConfigPrefix() . 'SANDBOX_CHANNEL_SECRET_KEY',
                    ),
                ),
                'submit' => array(
                    'title' => $this->l('save'),
                )
            )
        );
    }

    public function hookPaymentOptions($params)
    {
        try
        {
            if (! $this->active || ! $this->isConfigured())
            {
                return false;
            }

            $externalOption = new PaymentOption();
            $externalOption->setCallToActionText($this->l("Pay with {$this->name}"))
                ->setAction($this->context->link->getModuleLink($this->name, 'request', array(), true))
                ->setLogo(Media::getMediaPath($this->getLogo()));

            return [$externalOption];
        }catch (Exception $lpe)
        {
            return false;
        }
    }

    protected function isConfigured(){
        return (Configuration::get($this->getConfigPrefix() . 'SANDBOX_MODE') && Configuration::get($this->getConfigPrefix() . 'SANDBOX_CHANNEL_ID') && Configuration::get($this->getConfigPrefix() . 'SANDBOX_CHANNEL_SECRET_KEY'))
            || (Configuration::get($this->getConfigPrefix() . 'CHANNEL_ID') && Configuration::get($this->getConfigPrefix() . 'CHANNEL_SECRET_KEY'));
    }

    protected abstract function getLogo();

    protected function isModuleActive(){
        foreach (Module::getPaymentModules() as $module)
        {
            if ($module['name'] == $this->name)
            {
                return ((int) $module['id_module'] == (int) $this->id);
            }
        }
        return false;
    }

    public abstract function hookPaymentReturn($params);
}