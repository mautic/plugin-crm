<?php

declare(strict_types=1);

namespace MauticPlugin\MauticCrmBundle\Tests\Pipedrive\Import;

use GuzzleHttp\Psr7\Response as GuzzleResponse;
use Mautic\LeadBundle\Entity\Lead;
use MauticPlugin\MauticCrmBundle\Tests\Pipedrive\PipedriveTest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LeadTest extends PipedriveTest
{
    private $features = [
        'objects' => [
            'company',
        ],
        'leadFields' => [
            'first_name' => 'firstname',
            'last_name'  => 'lastname',
            'email'      => 'email',
            'phone'      => 'phone',
        ],
    ];

    private $updateData = ['email' => 'test@test.pl', 'firstname'=> 'Test', 'lastname'=>'Person', 'phone'=>'678465345'];

    protected function beforeBeginTransaction(): void
    {
        $this->resetAutoincrement([
            'leads',
        ]);
    }

    public function testCreateLeadViaUpdate(): void
    {
        $this->installPipedriveIntegration(
            true,
            $this->features,
            [
                'url'   => '',
                'token' => 'token',
            ]
        );
        $data = $this->getData('person.updated');

        $this->makeRequest(Request::METHOD_POST, $data);

        $response     = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $lead         = $this->em->getRepository(Lead::class)->find(1);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode(), $response->getContent());
        $this->assertEquals('ok', $responseData['status']);
        $this->assertEquals('Test Person', $lead->getName());
        $this->assertEquals('test@test.pl', $lead->getEmail());
        $this->assertEquals('678465345', $lead->getPhone());
        $this->assertNotNull($lead->getDateAdded());
        $this->assertEquals('gravatar', $lead->getPreferredProfileImage());
    }

    public function testUpdateLead(): void
    {
        $this->installPipedriveIntegration(
            true,
            $this->features,
            [
                'url'   => '',
                'token' => 'token',
            ]
        );
        $data = json_decode($this->getData('person.updated'), true);

        $lead = $this->createLead([], null, $this->updateData);
        $this->createLeadIntegrationEntity($data['current']['id'], $lead->getId());

        $this->makeRequest('POST', json_encode($data));

        $response     = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $lead         = $this->em->getRepository(Lead::class)->find(1);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals('ok', $responseData['status']);
        $this->assertEquals('Test Person', $lead->getName());
        $this->assertEquals('test@test.pl', $lead->getEmail());
        $this->assertEquals('678465345', $lead->getPhone());
        $this->assertNull($lead->getOwner());
        $this->assertNull($lead->getCompany());
        $this->assertNotNull($lead->getDateModified());
    }

    public function testUpdateLeadOwner(): void
    {
        $newOwnerId    = 88;
        $newOwnerEmail = 'new@admin.com';

        $this->installPipedriveIntegration(
            true,
            $this->features,
            [
                'url'   => '',
                'token' => 'token',
            ]
        );

        $data = json_decode($this->getData('person.updated'), true);

        $oldUser = $this->createUser(true);
        $this->createLead([], $oldUser);
        $newUser = $this->createUser(true, $newOwnerEmail, 'admin2');

        $this->addPipedriveOwner($newOwnerId, $newUser->getEmail());

        $data['current']['owner_id'] = $newOwnerId;

        $this->makeRequest('POST', json_encode($data));

        $response     = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $lead         = $this->em->getRepository(Lead::class)->find(2);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals('ok', $responseData['status']);
        $this->assertNotNull($lead->getOwner());
        $this->assertEquals($newOwnerEmail, $lead->getOwner()->getEmail());
    }

    public function testUpdateLeadCompany(): void
    {
        $newCompanyId      = 88;
        $newCompanyName    = 'New Company Name';
        $newCompanyAddress = 'Madrid, Spain';

        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/Post/persons/find'))); // find company by name
        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/Post/organizations'))); // create company
        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/Post/persons/find'))); // find person by email
        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/Post/persons'))); // create person
        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/personFields'))); // get fields

        $this->installPipedriveIntegration(
            true,
            $this->features,
            [
                'url'   => '',
                'token' => 'token',
            ]
        );

        $data = json_decode($this->getData('person.updated'), true);

        $oldCompany = $this->createCompany();
        $newCompany = $this->createCompany($newCompanyName, $newCompanyAddress);

        $lead = $this->createLead($oldCompany, null);

        $this->createLeadIntegrationEntity($data['current']['id'], $lead->getId());
        $this->createCompanyIntegrationEntity($newCompanyId, $newCompany->getId());

        $data['current']['org_id'] = $newCompanyId;
        $this->makeRequest('POST', json_encode($data));

        $response     = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $lead         = $this->em->getRepository(Lead::class)->find(1);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals('ok', $responseData['status']);
        $this->assertNotNull($lead->getCompany());
        $this->assertEquals($newCompanyName, $lead->getCompany());
        $this->assertNotNull($lead->getDateModified());
    }

    public function testRemoveLeadCompany(): void
    {
        $companyModel = self::$container->get('mautic.lead.model.company');

        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/Post/persons/find'))); // find company by name
        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/Post/organizations'))); // create company
        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/Post/persons/find'))); // find person by email
        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/Post/persons'))); // create person
        $this->mockHandler->append(new GuzzleResponse(Response::HTTP_OK, [], self::getData('Api/personFields'))); // get fields

        $this->installPipedriveIntegration(
            true,
            $this->features,
            [
                'url'   => '',
                'token' => 'token',
            ]
        );
        $data = json_decode($this->getData('person.updated'), true);

        $oldCompany    = $this->createCompany();
        $lead          = $this->createLead($oldCompany, null, $this->updateData);
        $leadCompanies = $companyModel->getCompanyLeadRepository()->getCompaniesByLeadId($lead->getId());
        $this->assertEquals(count($leadCompanies), 1);

        $this->createLeadIntegrationEntity($data['current']['id'], $lead->getId());

        $this->makeRequest('POST', json_encode($data));

        $response     = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $lead         = $this->em->getRepository(Lead::class)->find(1);

        $leadCompanies = $companyModel->getCompanyLeadRepository()->getCompaniesByLeadId($lead->getId());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals($responseData['status'], 'ok');
        $this->assertEquals(count($leadCompanies), 0);
        $this->assertNotNull($lead->getDateModified());
    }

    public function testRemoveLeadOwner(): void
    {
        $this->installPipedriveIntegration(
            true,
            $this->features,
            [
                'url'   => '',
                'token' => 'token',
            ]
        );
        $data = json_decode($this->getData('person.updated'), true);

        $owner = $this->createUser(true);
        $lead  = $this->createLead([], $owner);

        $this->assertNotNull($lead->getOwner());
        $this->assertEquals($lead->getOwner()->getEmail(), $owner->getEmail());

        $this->createLeadIntegrationEntity($data['current']['id'], $lead->getId());

        $data['current']['owner_id'] = null;

        $this->makeRequest('POST', json_encode($data));

        $response     = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);
        $lead         = $this->em->getRepository(Lead::class)->find(1);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals($responseData['status'], 'ok');
        $this->assertNull($lead->getOwner());
        $this->assertNotNull($lead->getDateModified());
    }

    public function testDeleteLead(): void
    {
        $this->installPipedriveIntegration(
            true,
            $this->features,
            [
                'url'   => '',
                'token' => 'token',
            ]
        );
        $lead = $this->createLead();
        $json = $this->getData('person.deleted');
        $data = json_decode($json, true);
        $this->createLeadIntegrationEntity($data['previous']['id'], $lead->getId());

        $this->assertEquals(count($this->em->getRepository(Lead::class)->findAll()), 1);

        $this->makeRequest('POST', json_encode($data));

        $response     = $this->client->getResponse();
        $responseData = json_decode($response->getContent(), true);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertEquals($responseData['status'], 'ok');
        $this->assertEquals(count($this->em->getRepository(Lead::class)->findAll()), 0);
    }
}
