<?php

declare(strict_types=1);

namespace MauticPlugin\LeuchtfeuerConditionalFormActionsBundle\Tests\Fixtures;

use Doctrine\ORM\EntityManagerInterface;
use Mautic\EmailBundle\Entity\Email;
use Mautic\EmailBundle\Entity\Stat;
use Mautic\FormBundle\Entity\Form;
use Mautic\LeadBundle\Entity\Company;
use Mautic\LeadBundle\Entity\CompanyLead;
use Mautic\LeadBundle\Entity\Lead;
use Mautic\LeadBundle\Entity\LeadDevice;
use Mautic\LeadBundle\Entity\LeadList;
use Mautic\PageBundle\Entity\Page;
use Mautic\PageBundle\Entity\Redirect;
use Mautic\PluginBundle\Entity\Integration;
use Mautic\PluginBundle\Entity\Plugin;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Component\HttpFoundation\Request;

final class FunctionalFixtureHelper
{
    public function __construct(
        private EntityManagerInterface $em,
        private KernelBrowser $client
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

    public function createEmail(string $name, string $content): Email
    {
        $email = new Email();
        $email->setName($name);
        $email->setSubject($name);
        $email->setCustomHtml($content);
        $email->setEmailType('template');
        $email->setIsPublished(true);
        $this->em->persist($email);
        $this->em->flush();

        return $email;
    }

    public function createContact(string $email): Lead
    {
        $contact = new Lead();
        $contact->setEmail($email);
        $this->em->persist($contact);
        $this->em->flush();

        return $contact;
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

    public function createForm(string $name, string $alias): Form
    {
        $form = new Form();
        $form->setName($name);
        $form->setAlias($alias);
        $form->setPostActionProperty('Success');
        $this->em->persist($form);
        $this->em->flush();

        return $form;
    }

    public function createFormWithCompanyViaApi(string $name): Form
    {
        $formPayload = [
            'name'        => $name,
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
                    'label'        => 'Company',
                    'type'         => 'text',
                    'alias'        => 'company',
                    'leadField'    => 'companyname',
                    'mappedField'  => 'companyname',
                    'mappedObject' => 'company',
                ],
                [
                    'label' => 'Submit',
                    'type'  => 'button',
                ],
            ],
            'postAction' => 'return',
        ];

        $this->client->request(Request::METHOD_POST, '/api/forms/new', $formPayload);
        $clientResponse = $this->client->getResponse();
        $response       = json_decode($clientResponse->getContent(), true);
        $formId         = $response['form']['id'];
        $repository     = $this->em->getRepository(Form::class);

        return $repository->find($formId);
    }

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

    public function emulateEmailLinkClicked(Lead $contact): void
    {
        // Create a redirect link
        $redirectUrl = 'https://mautic.org';
        $redirect    = new Redirect();
        $redirect->setRedirectId(uniqid());
        $redirect->setUrl($redirectUrl);
        $this->em->persist($redirect);

        // Create an Email and Stat for tracking context for the contact
        $email    = $this->createEmail('Action Email', 'Click Here');
        $statHash = uniqid('stat', true);
        $stat     = new Stat();
        $stat->setEmail($email);
        $stat->setEmailAddress($contact->getEmail());
        $stat->setDateSent(new \DateTime());
        $stat->setLead($contact);
        $stat->setTrackingHash($statHash);
        $this->em->persist($stat);
        $this->em->flush();
        $this->em->clear();

        $ct = [
            'source'  => ['email', $email->getId()],
            'email'   => $email->getId(),
            'stat'    => $statHash,
            'lead'    => $contact->getId(),
            'channel' => ['email' => $email->getId()],
        ];
        $encodedCt = base64_encode(serialize($ct));

        $this->client->request(Request::METHOD_GET, "/r/{$redirect->getRedirectId()}?ct={$encodedCt}");
    }

    public function emulatePageVisit(Lead $contact): void
    {
        $device = new LeadDevice();
        $device->setDateAdded(new \DateTime());
        $device->setTrackingId(uniqid());
        $device->setLead($contact);
        $this->em->persist($device);
        $this->em->flush();

        $this->client->request('POST', '/mtc/event', [
            'page_url'         => 'https://example.com',
            'mautic_device_id' => $device->getTrackingId(),
        ]);
    }

    public function createLandingPage(string $title = 'LP', string $alias = 'lp', bool $isPublished = true, string $html = '<html><body>LP</body></html>'): Page
    {
        $page = new Page();
        $page->setTitle($title);
        $page->setAlias($alias);
        $page->setIsPublished($isPublished);
        $page->setCustomHtml($html);
        $this->em->persist($page);
        $this->em->flush();

        return $page;
    }

    public function emulateFormSubmit(Lead $contact, Company $company = null): void
    {
        $formData = [
            'mauticform[email]'   => $contact->getEmail(),
        ];
        if (null !== $company) {
            $formData['mauticform[company]'] = $company->getName();
        }
        $form = $this->createFormWithCompanyViaApi('Test Form');

        $this->submitForm($form, $formData);
    }

    public function emulateFormSubmitWithTracking(Lead $contact, Company $company = null): void
    {
        $formData = [
            'mauticform[email]'   => $contact->getEmail(),
        ];
        if (null !== $company) {
            $formData['mauticform[company]'] = $company->getName();
        }
        $form  = $this->createFormWithCompanyViaApi('Test Form');
        $token = '{form='.$form->getId().'}';
        $this->createLandingPage(alias: 'test-lp', html: "<html><body>{$token}</body></html>");
        $this->client->request('GET', '/test-lp');
        $this->client->enableReboot();
        $this->submitForm($form, $formData);
    }

    /**
     * @param array<string,string> $formData
     */
    public function submitForm(Form $form, array $formData): void
    {
        $formNameForId = strtolower(str_replace(' ', '', $form->getName()));

        $crawler     = $this->client->request(Request::METHOD_GET, "/form/{$form->getId()}");
        $formCrawler = $crawler->filter("form[id=mauticform_{$formNameForId}]");
        $formElement = $formCrawler->form();
        $formElement->setValues($formData);
        $this->client->submit($formElement);
    }
}
