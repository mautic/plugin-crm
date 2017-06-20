<?php

/*
 * @copyright   2014 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticCrmBundle\Api;

use Mautic\PluginBundle\Exception\ApiErrorException;

class ConnectwiseApi extends CrmApi
{
    /**
     * @param        $endpoint
     * @param array  $parameters
     * @param string $method
     *
     * @return mixed|string
     *
     * @throws ApiErrorException
     */
    protected function request($endpoint, $parameters = [], $method = 'GET')
    {
        if (isset($this->keys['password'])) {
            $parameters['password'] = $this->keys['password'];
            $parameters['username'] = $this->keys['username'];
        }
        $apiUrl = $this->integration->getApiUrl();

        $url = sprintf('%s/%s', $apiUrl, $endpoint);

        $response = $this->integration->makeRequest($url, $parameters, $method, ['encode_parameters' => 'json', 'cw-app-id' => $this->integration->getCompanyCookieKey()]);

        if (is_array($response) && !empty($response['status']) && $response['status'] == 'error') {
            throw new ApiErrorException($response['error']);
        } elseif (is_array($response) && !empty($response['errors'])) {
            $errors = [];
            foreach ($response['errors'] as $error) {
                $errors[] = $error['error'];
            }

            throw new ApiErrorException(implode(' ', $errors));
        } else {
            return $response;
        }
    }

    public function getCompanies()
    {
        return $this->request('company/companies');
    }

    /**
     * @return mixed|string
     *
     * @throws ApiErrorException
     */
    public function getLeads()
    {
        return $this->request('company/contacts');
    }
}
