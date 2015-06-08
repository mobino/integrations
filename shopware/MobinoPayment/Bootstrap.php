<?php
class Shopware_Plugins_Frontend_MobinoPayment_Bootstrap extends Shopware_Components_Plugin_Bootstrap
{
    /**
     * initiates this class
     */
    public function init()
    {
    }

    /**
     * Returns the version
     *
     * @return string
     */
    public function getVersion()
    {
        return "1.0.0";
    }

    public function getLabel()
    {
        return 'Payments processed with Mobino';
    }


    /**
     * Returns the controller path
     *
     * @return string
     */
    public static function onGetControllerPath()
    {
        Shopware()->Template()->addTemplateDir(Shopware()->Plugins()->Frontend()->MobinoPayment()->Path() . '/Views/');
        return Shopware()->Plugins()->Frontend()->MobinoPayment()->Path() . '/Controllers/frontend/Mobino.php';
    }

    /**
     * Get Info for the Pluginmanager
     *
     * @return array
     */
    public function getInfo()
    {
        return array('version' => $this->getVersion(),
            'author' => 'Mobino SA',
            'source' => $this->getSource(),
            'supplier' => 'Mobino SA',
            'support' => 'support@mobino.com',
            'link' => 'https://www.mobino.com',
            'copyright' => 'Copyright (c) 2015, Mobino SA',
            'label' => 'Mobino',
            'description' => '<h2>Payment plugin for Shopware Community Edition Version > 4.0.0</h2>'
            . '<ul>'
            . '<li style="list-style: inherit;">Mobino Payments - the fastest and most secure e-commerce experience</li>'
            . '</ul>'
        );
    }

    /**
     * Performs the necessary installation steps
     *
     * @throws Exception
     * @return boolean
     */
    public function install()
    {
        try {
            $this->createPaymentMeans();
            $this->_createForm();
            $this->_createEvents();
            $this->Plugin()->setActive(true);
        }
        catch (Exception $exception) {
            $this->uninstall();
            throw new Exception($exception->getMessage());
        }

        $installSuccess = parent::install();
        return $installSuccess;
    }

    /**
     * Performs the necessary uninstall steps
     *
     * @return boolean
     */
    public function uninstall()
    {
        $configHelper = new Shopware_Plugins_Frontend_MobinoPayment_Components_ConfigHelper();
        $configHelper->persist();
        return parent::uninstall();
    }

    /**
     * Updates the Plugin and its components
     *
     * @param string $oldVersion
     *
     * @throws Exception
     * @return boolean
     */
    public function update($oldVersion)
    {
        try {
            return true;
        } catch (Exception $exception) {
            Shopware()->Log()->Err($exception->getMessage());
            throw new Exception($exception->getMessage());
        }
    }

    /**
     * Disables the plugin
     *
     * @throws Exception
     * @return boolean
     */
    public function disable()
    {
        try {
            $payment[0] = 'mobino';
            foreach ($payment as $key) {
                $currentPayment = $this->Payments()->findOneBy(array('name' => $key));
                if ($currentPayment) {
                    $currentPayment->setActive(false);
                }
            }
        } catch (Exception $exception) {
            Shopware()->Log()->Err("Cannot disable payment: " . $exception->getMessage());
            throw new Exception("Cannot disable payment: " . $exception->getMessage());
        }

        return parent::disable();
    }

    /**
     * Creates the payment method
     *
     * @throws Exception
     * @return void
     */
    protected function createPaymentMeans()
    {
        try {
 
            $this->createPayment(
                array(
                    'active' => 1,
                    'name' => 'mobino',
                    'action' => 'mobino',
                    'description' => 'Mobino',
                    'additionalDescription' =>
                        '<img src="http://o08.net/apk/logo-mobino-rgbs.png"/>' .
                        '<div id="payment_desc">
                            The safest and fastest way to pay. install it on your smartphone here: http://install.mobino.com
                        </div>'
                )
            );
        } catch (Exception $exception) {
            Shopware()->Log()->Err("There was an error creating the payment means. " . $exception->getMessage());
            throw new Exception("There was an error creating the payment means. " . $exception->getMessage());
        }
    }

    /**
     * Creates the configuration fields
     *
     * @throws Exception
     * @return void
     */
    private function _createForm()
    {
        try {
            $form = $this->Form();
            $configHelper = new Shopware_Plugins_Frontend_MobinoPayment_Components_ConfigHelper();
            $data = $configHelper->loadData();
            $form->setElement('text', 'apikey', array('label' => 'API Key', 'required' => true, 'value' => $data['apikey']));
            $form->setElement('text', 'apisecret', array('label' => 'API secret', 'required' => true, 'value' => $data['apisecret']));
        }
        catch (Exception $exception) {
            Shopware()->Log()->Err("There was an error creating the plugin configuration. " . $exception->getMessage());
            throw new Exception("There was an error creating the plugin configuration. " . $exception->getMessage());
        }
    }

    /**
     * Creates all Events for the plugins
     *
     * @throws Exception
     * @return void
     */
    private function _createEvents()
    {
        try {
            $this->subscribeEvent('Enlight_Controller_Dispatcher_ControllerPath_Frontend_Mobino', 'onGetControllerPath');
        }
        catch (Exception $exception) {
            Shopware()->Log()->Err("There was an error registering the plugins events. " . $exception->getMessage());
            throw new Exception("There was an error registering the plugins events. " . $exception->getMessage());
        }
    }
}
