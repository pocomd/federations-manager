<?php

declare(strict_types=1);

use App\Models\MailTemplate;
use App\Models\SystemPreference;
use App\Services\Mail\MailTemplateService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function makeTemplate(string $subject, string $body): MailTemplate
{
    return new MailTemplate([
        'name'    => 'Test',
        'group'   => 'general',
        'subject' => $subject,
        'body'    => $body,
        'lang'    => 'en',
    ]);
}

function dummyData(array $overrides = []): array
{
    return array_merge([
        'federation'       => null,
        'entity'           => null,
        'contact'          => null,
        'cert_expiry_date' => '',
        'cert_subject'     => '',
    ], $overrides);
}

it('render replaces [[entity_name]] placeholder', function () {
    $entity = \App\Models\Entity::factory()->sp()->create();
    $entity->uiInfo()->updateOrCreate(
        ['field' => 'display_name', 'lang' => 'en'],
        ['value' => 'My Service Provider'],
    );
    $entity->load('uiInfo');

    $service = new MailTemplateService();
    $tmpl    = makeTemplate('Hello', 'Entity: [[entity_name]]');
    $result  = $service->render($tmpl, dummyData(['entity' => $entity]));

    expect($result['body'])->toContain('My Service Provider');
});

it('render replaces [[contact_name]] placeholder', function () {
    $contact = new \App\Models\EntityContact([
        'given_name' => 'Jane',
        'sur_name'   => 'Doe',
        'email'      => 'jane@example.org',
        'type'       => 'technical',
    ]);

    $service = new MailTemplateService();
    $tmpl    = makeTemplate('Hi [[contact_name]]', 'Dear [[contact_name]]');
    $result  = $service->render($tmpl, dummyData(['contact' => $contact]));

    expect($result['subject'])->toContain('Jane Doe')
        ->and($result['body'])->toContain('Jane Doe');
});

it('render replaces [[mail_signature]] from SystemPreference', function () {
    SystemPreference::create([
        'key'      => 'mail_signature',
        'value'    => 'Kind regards, Registry Team',
        'type'     => 'textarea',
        'label'    => 'Signature',
        'category' => 'mail',
    ]);

    $service = new MailTemplateService();
    $tmpl    = makeTemplate('Sub', '[[mail_signature]]');
    $result  = $service->render($tmpl, dummyData());

    expect($result['body'])->toContain('Kind regards, Registry Team');
});

it('render leaves unknown placeholders unchanged', function () {
    $service = new MailTemplateService();
    $tmpl    = makeTemplate('Sub', 'Value: [[unknown_placeholder]]');
    $result  = $service->render($tmpl, dummyData());

    expect($result['body'])->toContain('[[unknown_placeholder]]');
});

it('render replaces placeholders in both subject and body', function () {
    $federation = \App\Models\Federation::factory()->create(['name' => 'RENAM']);

    $service = new MailTemplateService();
    $tmpl    = makeTemplate('[[federation_name]] notice', 'Welcome to [[federation_name]]');
    $result  = $service->render($tmpl, dummyData(['federation' => $federation]));

    expect($result['subject'])->toContain('RENAM')
        ->and($result['body'])->toContain('RENAM');
});
