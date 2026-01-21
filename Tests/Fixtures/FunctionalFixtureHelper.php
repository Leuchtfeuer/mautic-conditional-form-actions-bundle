<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

final class FunctionalFixtureHelper
{
    public function __construct(
        private EntityManagerInterface $em,
        private KernelBrowser $client,
    ) {
    }

    public function createAndEnablePlugin(): void
    {
        $plugin = new Plugin();
        $plugin->setName('Conditional Form Actions by Leuchtfeuer');
        $plugin->setBundle('LeuchtfeuerConditionalFormActionsBundle');
        $this->em->persist($plugin);

        $integration = new Integration();
        $integration->setPlugin($plugin);
        $integration->setIsPublished(true);
        $integration->setName('LeuchtfeuerConditionalFormActions');
        $this->em->persist($integration);
        $this->em->flush();
    }

    public function createSegment(string $name, string $alias): LeadList
    {
        $segment = new LeadList();
        $segment->setName($name);
        $segment->setAlias($alias);
        $segment->setPublicName($name);
        $this->em->persist($segment);
        $this->em->flush();

        return $segment;
    }

    public function createCompany(string $name): Company
    {
        $company = new Company();
        $company->setName($name);
        $this->em->persist($company);

        return $company;
    }

    public function addContactToCompany(Lead $lead, Company $company, \DateTime $dateAdded = null, bool $isPrimary = true): CompanyLead
    {
        $companyLead = new CompanyLead();
        $companyLead->setCompany($company);
        $companyLead->setLead($lead);
        $companyLead->setDateAdded($dateAdded ?? new \DateTime());
        $companyLead->setPrimary($isPrimary);
        $this->em->persist($companyLead);

        return $companyLead;
    }

    /**
     * @param array<int, array<string,mixed>> $actions
     */
    public function createComplexFormViaApi(string $name, array $actions = []): Form
    {
        $formPayload = [
            'name'        => $name,
            'alias'       => str_replace(' ', '', strtolower($name)),
            'description' => '',
            'formType'    => 'standalone',
            'isPublished' => true,
            'fields'      => [
                [
                    'label'        => 'Email',
                    'type'         => 'email',
                    'alias'        => 'email',
                    'leadField'    => 'email',
                    'mappedField'  => 'email',
                    'mappedObject' => 'contact',
                ],
                [
                    'label'        => 'Country',
                    'type'         => 'country',
                    'alias'        => 'country',
                    'leadField'    => 'country',
                    'mappedField'  => 'country',
                    'mappedObject' => 'contact',
                ],
                [
                    'label'        => 'Company Name',
                    'type'         => 'text',
                    'alias'        => 'companyname',
                    'leadField'    => 'company',
                    'mappedField'  => 'companyname',
                    'mappedObject' => 'company',
                ],
                [
                    'label'       => 'Lead Source', // This field is NOT mapped to a contact/company field
                    'type'        => 'text',
                    'alias'       => 'source',
                ],
                [
                    'label'      => 'Interests',
                    'alias'      => 'interests',
                    'type'       => 'checkboxgrp',
                    'properties' => [
                        'syncList'   => 0,
                        'optionlist' => [
                            'list' => [
                                ['label' => 'Technology', 'value' => 'tech'],
                                ['label' => 'Marketing', 'value' => 'marketing'],
                                ['label' => 'Sales', 'value' => 'sales'],
                            ],
                        ],
                    ],
                ],
                [
                    'label'      => 'Colors',
                    'alias'      => 'colors',
                    'type'       => 'select',
                    'properties' => [
                        'syncList'   => 0,
                        'list'       => [
                            'list' => [
                                ['label' => 'Red', 'value' => 'red'],
                                ['label' => 'Green', 'value' => 'green'],
                                ['label' => 'Blue', 'value' => 'blue'],
                            ],
                        ],
                    ],
                ],
                [
                    'label'      => 'Available days',
                    'alias'      => 'available_days',
                    'type'       => 'select',
                    'properties' => [
                        'syncList'   => 0,
                        'multiple'   => 1,
                        'list'       => [
                            'list' => [
                                ['label' => 'Monday', 'value' => 'monday'],
                                ['label' => 'Tuesday', 'value' => 'tuesday'],
                                ['label' => 'Wednesday', 'value' => 'wednesday'],
                                ['label' => 'Thursday', 'value' => 'thursday'],
                                ['label' => 'Friday', 'value' => 'friday'],
                                ['label' => 'Saturday', 'value' => 'saturday'],
                                ['label' => 'Sunday', 'value' => 'sunday'],
                            ],
                        ],
                    ],
                ],
                [
                    'label'      => 'Preferred Contact Method',
                    'alias'      => 'contact_preference',
                    'type'       => 'radiogrp',
                    'properties' => [
                        'syncList'   => 0,
                        'optionlist' => [
                            'list' => [
                                ['label' => 'Email', 'value' => 'email'],
                                ['label' => 'Phone', 'value' => 'phone'],
                                ['label' => 'Text Message', 'value' => 'sms'],
                            ],
                        ],
                        'labelAttributes' => '',
                    ],
                ],
                [
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
            'actions'    => $actions,
            'postAction' => 'return',
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $formPayload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $formId         = $response['form']['id'];
        $repository     = $this->em->getRepository(Form::class);

        return $repository->find($formId);
    }
}
