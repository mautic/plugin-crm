<?php
namespace MauticPlugin\MauticCrmBundle\Api;

use Mautic\LeadBundle\MauticLeadBundle;
use Mautic\PluginBundle\Exception\ApiErrorException;
use MauticPlugin\MauticCrmBundle\Integration\CrmAbstractIntegration;

class SalesforceApi extends CrmApi
{
    protected $object  = 'Lead';
    protected $requestSettings = array(
        'encode_parameters' => 'json'
    );

    public function __construct(CrmAbstractIntegration $integration)
    {
        parent::__construct($integration);

        $this->requestSettings['curl_options'] = array(
            CURLOPT_SSLVERSION => defined('CURL_SSLVERSION_TLSv1_1') ? CURL_SSLVERSION_TLSv1_1 : CURL_SSLVERSION_TLSv1_1
        );
    }

    public function request($operation, $elementData = array(), $method = 'GET', $retry = false, $object = null, $queryUrl = null)
    {
        if(!$object){
            $object = $this->object;
        }

        if(!$queryUrl){
            $queryUrl = $this->integration->getApiUrl();
            $request_url = sprintf($queryUrl . '/%s/%s', $object, $operation);
        }
        else{
            $request_url = sprintf($queryUrl . '/%s', $operation);
        }


        $response = $this->integration->makeRequest($request_url, $elementData, $method, $this->requestSettings);

        if (!empty($response['errors'])) {
            throw new ApiErrorException(implode(', ', $response['errors']));
        } elseif (is_array($response)) {
            $errors = array();
            foreach ($response as $r) {
                if (is_array($r) && !empty($r['errorCode']) && !empty($r['message'])) {
                    // Check for expired session then retry if we can refresh
                    if ($r['errorCode'] == 'INVALID_SESSION_ID' && !$retry) {
                        $refreshError = $this->integration->authCallback(array('use_refresh_token' => true));

                        if (empty($refreshError)) {
                            return $this->request($operation, $elementData, $method, true);
                        }
                    }

                    $errors[] = $r['message'];
                }
            }

            if (!empty($errors)) {
                throw new ApiErrorException(implode(', ', $errors));
            }
        }

        return $response;
    }

    /**
     * @return mixed
     */
    public function getLeadFields()
    {
        return $this->request('describe');
    }

    /**
     * Creates Salesforce lead
     *
     * @param array $data
     *
     * @return mixed
     */
    public function createLead(array $data, $lead)
    {
        $createdLeadData =  $this->request('', $data, 'POST');
        //todo: check if push activities is selected in config

        return $createdLeadData;
    }

    public function createLeadActivity(array $salesForceLeadData, $lead)
    {
        $mActivityObjectName = 'Mautic_timeline__c';

        // get lead's point activity
        $pointsRepo = $this->integration->getLeadData('points', $lead);
        $leadUrl = $pointsRepo['leadUrl'];
        if(!empty($pointsRepo))
        {
            $deltaType = '';
            foreach ($pointsRepo as $pointActivity)
            {
                if($pointActivity['delta']>0)
                {
                    $deltaType = 'added';
                }
                else
                {
                    $deltaType = 'subtracted';
                }

                $activityData = array(
                    'ActivityDate__c'   => $pointActivity['dateAdded']->format('c'),
                    'Description__c'    => $pointActivity['type'].":".$pointActivity['eventName']." ".$deltaType." ".$pointActivity['actionName'],
                    'WhoId__c'          => $salesForceLeadData['id'],
                    'Name'           => 'Mautic TimeLine Activity',
                    'MauticLead__c'     => $lead->getId(),
                    'Mautic_url__c'     => $leadUrl
                );
                //todo: log posted activities so that they don't get sent over again
                $this->request('', $activityData, 'POST', false, $mActivityObjectName);
            }
        }
    }

    /**
     * Get Salesforce leads
     *
     * @param string $query
     *
     * @return mixed
     */
    public function getLeads($query, $object)
    {
        //find out if start date is not our of range for org
        if ($query['start'])
        {
            $queryUrl = $this->integration->getQueryUrl();
            $organization = $this->request('query', array("q"=>"SELECT CreatedDate from Organization"),'GET',false,null,$queryUrl);

            if(strtotime($query['start']) < strtotime($organization['records'][0]['CreatedDate']))
            {
                $query['start'] = date('c',strtotime($organization['records'][0]['CreatedDate']." +1 hour"));
            }
        }
        $fields = $this->integration->getAvailableLeadFields();
        $fields['id']=array('id' => array());

        if($fields)
        {
            $fields = implode(", ",array_keys($fields));
        }

        $getLeadsQuery = "SELECT ".$fields." from Lead where LastModifiedDate>=".$query['start']." and LastModifiedDate<=".$query['end'];
        $result = $this->request('query', array("q"=>$getLeadsQuery),'GET',false,null,$queryUrl);

        return $result;
    }

    /**
     * Get Salesforce leads
     *
     * @param string $query
     *
     * @return mixed
     */
    public function getSalesForceLeadById($id, $params, $object)
    {
        return $this->request($id.'/',$params, 'GET', false,$object);
    }
}