<?php
/**
 * @package     Mautic
 * @copyright   2014 Mautic Contributors. All rights reserved.
 * @author      Mautic
 * @link        http://mautic.org
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticCrmBundle\Integration;


use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Integration\AbstractIntegration;

/**
 * Class CrmAbstractIntegration
 *
 * @package MauticPlugin\MauticCrmBundle\Integration
 */
abstract class CrmAbstractIntegration extends AbstractIntegration
{

    protected $auth;

    /**
     * @param Integration $settings
     */
    public function setIntegrationSettings(Integration $settings)
    {
        //make sure URL does not have ending /
        $keys = $this->getDecryptedApiKeys($settings);
        if (isset($keys['url']) && substr($keys['url'], -1) == '/') {
            $keys['url'] = substr($keys['url'], 0, -1);
            $this->encryptAndSetApiKeys($keys, $settings);
        }

        parent::setIntegrationSettings($settings);
    }

    /**
     * {@inheritdoc}
     *
     * @return string
     */
    public function getAuthenticationType()
    {
        return 'rest';
    }

    /**
     * @return array
     */
    public function getSupportedFeatures()
    {
        return array('push_lead');
    }

    /**
     * @param $lead
     */
    public function pushLead($lead, $config = array())
    {
        $config = $this->mergeConfigToFeatureSettings($config);

        if (empty($config['leadFields'])) {
            return array();
        }

        $mappedData = $this->populateLeadData($lead, $config);

        $this->amendLeadDataBeforePush($mappedData);

        if (empty($mappedData)) {
            return false;
        }

        try {
            if ($this->isAuthorized()) {
                $this->getApiHelper()->createLead($mappedData);
                return true;
            }
        } catch (\Exception $e) {
            $this->logIntegrationError($e);
        }
        return false;
    }

    /**
     * Amend mapped lead data before pushing to CRM
     *
     * @param $mappedData
     */
    public function amendLeadDataBeforePush(&$mappedData)
    {

    }

    /**
     * @return string
     */
    public function getClientIdKey()
    {
        return 'client_id';
    }

    /**
     * @return string
     */
    public function getClientSecretKey()
    {
        return 'client_secret';
    }


    /**
     * {@inheritdoc}
     */
    public function sortFieldsAlphabetically()
    {
        return false;
    }

    /**
     * Get the API helper
     *
     * @return Object
     */
    public function getApiHelper()
    {
        static $helper;
        if (empty($helper)) {
            $class = '\\MauticPlugin\\MauticCrmBundle\\Api\\'.$this->getName().'Api';
            $helper = new $class($this);
        }

        return $helper;
    }
}