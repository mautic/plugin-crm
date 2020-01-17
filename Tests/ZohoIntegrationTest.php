<?php
/*
 * @copyright   2017 Mautic Contributors. All rights reserved
 * @author      Mautic
 *
 * @link        http://mautic.org
 *
 * @license     GNU/GPLv3 http://www.gnu.org/licenses/gpl-3.0.html
 */

namespace MauticPlugin\MauticCrmBundle\Tests;

use Mautic\CoreBundle\Helper\EncryptionHelper;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Tests\Integration\AbstractIntegrationTestCase;
use MauticPlugin\MauticCrmBundle\Api\CrmApi;
use MauticPlugin\MauticCrmBundle\Integration\ZohoIntegration;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Translation\Translator;

class ZohoIntegrationTest extends AbstractIntegrationTestCase
{
    /** @var ZohoIntegration */
    private $integration;

    protected function setUp()
    {
        parent::setUp();

        $encryptionHelper = $this->getMockBuilder(EncryptionHelper::class)
            ->disableOriginalConstructor()
            ->setMethods(['decrypt'])
            ->getMock();
        $encryptionHelper->expects($this->any())
            ->method('decrypt')
            ->willReturnArgument(0);
        $translator = $this->getMockBuilder(Translator::class)
            ->disableOriginalConstructor()
            ->setMethods(['trans'])
            ->getMock();
        $translator->expects($this->any())
            ->method('trans')
            ->willReturnArgument(0);

        $eventMock = $this->getMockBuilder(Event::class)
            ->disableOriginalConstructor()
            ->setMethods(['getKeys'])
            ->getMock();
        $apiKeys = [
            'EMAIL_ID'     => 'test',
            'PASSWORD'     => 'test',
            'updateBlanks' => '',
            'datacenter'   => 'zoho.com',
            'AUTHTOKEN'    => 'test',
            'RESULT'       => 'test',
        ];
        $eventMock->expects($this->any())
            ->method('getKeys')
            ->willReturn($apiKeys);
        $dispatcherMock = $this->getMockBuilder(EventDispatcher::class)
            ->disableOriginalConstructor()
            ->setMethods(['dispatch'])
            ->getMock();
        $dispatcherMock
            ->method('dispatch')
            ->willReturn($eventMock);

        $this->integration = $this->getMockBuilder(ZohoIntegration::class)
            ->setMethods(['getApiHelper'])
            ->setConstructorArgs([
                $dispatcherMock,
                $this->cache,
                $this->em,
                $this->session,
                $this->request,
                $this->router,
                $translator,
                $this->logger,
                $encryptionHelper,
                $this->leadModel,
                $this->companyModel,
                $this->pathsHelper,
                $this->notificationModel,
                $this->fieldModel,
                $this->integrationEntityModel,
            ])
            ->getMock();

        $settings        = new Integration();
        $featureSettings = [
            'update_mautic' => [
                'Company'  => '1',
                'LastName' => '1',
            ],
            'leadFields' => [
                'Company'  => 'company',
                'LastName' => 'lastname',
            ],
            'updateBlanks'          => [],
            'objects'               => ['Leads'],
            'companyFields'         => [],
            'update_mautic_company' => [],
            'ignore_field_cache'    => true,
        ];
        $settings->setFeatureSettings($featureSettings);
        $settings->setSupportedFeatures(['push_lead', 'get_leads', 'push_leads']);
        $settings->setApiKeys($apiKeys);
        $leadFields = [
            'Leads' => [
                'section' => [
                    [
                        'FL' => [
                            [
                                'dv'         => 'Company',
                                'label'      => 'Company',
                                'type'       => 'Text',
                                'req'        => 'true',
                                'isreadonly' => 'false',
                            ],
                            [
                                'dv'         => 'Last Name',
                                'label'      => 'Last Name',
                                'type'       => 'Text',
                                'req'        => 'true',
                                'isreadonly' => 'false',
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $apiHelper = $this->getMockBuilder(CrmApi::class)
                          ->disableOriginalConstructor()
                          ->setMethods(['getLeadFields'])
                          ->getMock();
        $apiHelper
            ->method('getLeadFields')
            ->willReturn($leadFields);
        $this->integration->method('getApiHelper')
            ->willReturn($apiHelper);
        $this->integration->setIntegrationSettings($settings);
    }

    /**
     * Test integration.
     */
    public function testIntegration()
    {
        $this->assertSame('Zoho', $this->integration->getName());
    }

    /**
     * Test method.
     */
    public function testPopulateLeadData()
    {
        $fields = [
            'core' => [
                'lastname' => [
                    'value' => 'user',
                ],
                'company' => [
                    'value' => 'company',
                ],
            ],
        ];
        $lead = new Lead();
        $lead->setFields($fields);
        $data         = $this->integration->populateLeadData($lead, $this->integration->getIntegrationSettings()->getFeatureSettings());
        $expectedData = <<<'EOF'
<Leads>
<row no="">
<FL val="Company"><![CDATA[company]]></FL>
<FL val="Last Name"><![CDATA[user]]></FL>
</row>
</Leads>
EOF;
        $this->assertEquals($data, $expectedData);
    }
}
