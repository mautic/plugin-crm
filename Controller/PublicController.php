<?php

namespace MauticPlugin\MauticCrmBundle\Controller;

use function assert;
use Mautic\CoreBundle\Controller\CommonController;
use Mautic\PluginBundle\Helper\IntegrationHelper;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class class PublicController extends CommonController.
 */
class PublicController extends CommonController
{
    public function contactDataAction(Request $request, LoggerInterface $mauticLogger, IntegrationHelper $integrationHelper)
    {
        $content = $request->getContent();
        if (!empty($content)) {
            $data = json_decode($content, true); // 2nd param to get as array
        } else {
            return new Response('ERROR');
        }

        $integration = 'Hubspot';

        $integrationObject = $integrationHelper->getIntegrationObject($integration);
        assert(false !== $integrationObject);

        foreach ($data as $info) {
            $object = explode('.', $info['subscriptionType']);
            $id     = $info['objectId'];

            try {
                switch ($object[0]) {
                    case 'contact': $integrationObject->getContacts($id);
                        break;
                    case 'company':
                        $integrationObject->getCompanies($id);
                        break;
                }
            } catch (\Exception $ex) {
                $mauticLogger->log('error', 'ERROR on Hubspot webhook: '.$ex->getMessage());
            }
        }

        return new Response('OK');
    }
}
