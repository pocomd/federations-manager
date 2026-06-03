<?php

declare(strict_types=1);

use App\Models\FederationValidator;
use App\Services\Metadata\ExternalValidatorService;
use Illuminate\Support\Facades\Http;

function makeValidator(array $overrides = []): FederationValidator
{
    return new FederationValidator(array_merge([
        'name'                     => 'Test Validator',
        'url'                      => 'https://validator.example.org/validate',
        'http_method'              => 'GET',
        'metadata_arg_name'        => 'metadata',
        'optional_args'            => null,
        'args_separator'           => '&',
        'response_code_element'    => 'returncode',
        'response_message_element' => 'message',
        'success_value'            => '0',
        'warning_value'            => '1',
        'error_value'              => '2',
        'critical_value'           => '3',
    ], $overrides));
}

function xmlResponse(string $code, string $message): string
{
    return "<?xml version=\"1.0\"?><validation><returncode>{$code}</returncode><message>{$message}</message></validation>";
}

it('returns success when validator responds with code 0', function () {
    Http::fake(['*' => Http::response(xmlResponse('0', 'Validation passed'), 200)]);

    $result = (new ExternalValidatorService())->validate(makeValidator(), '<xml/>');

    expect($result['status'])->toBe('success')
        ->and($result['message'])->toBe('Validation passed');
});

it('returns warning when validator responds with code 1', function () {
    Http::fake(['*' => Http::response(xmlResponse('1', 'Minor issue'), 200)]);

    $result = (new ExternalValidatorService())->validate(makeValidator(), '<xml/>');

    expect($result['status'])->toBe('warning');
});

it('returns error when validator responds with code 2', function () {
    Http::fake(['*' => Http::response(xmlResponse('2', 'Validation error'), 200)]);

    $result = (new ExternalValidatorService())->validate(makeValidator(), '<xml/>');

    expect($result['status'])->toBe('error');
});

it('returns critical when validator responds with code 3', function () {
    Http::fake(['*' => Http::response(xmlResponse('3', 'Critical failure'), 200)]);

    $result = (new ExternalValidatorService())->validate(makeValidator(), '<xml/>');

    expect($result['status'])->toBe('critical');
});

it('returns unreachable when HTTP request fails', function () {
    Http::fake(['*' => Http::response('', 503)]);

    $result = (new ExternalValidatorService())->validate(makeValidator(), '<xml/>');

    expect($result['status'])->toBe('unreachable');
});

it('returns error when response is not valid XML', function () {
    Http::fake(['*' => Http::response('NOT XML', 200)]);

    $result = (new ExternalValidatorService())->validate(makeValidator(), '<xml/>');

    expect($result['status'])->toBe('error')
        ->and($result['message'])->toBe('Invalid XML response from validator');
});

it('handles custom response element names correctly', function () {
    $xml = "<?xml version=\"1.0\"?><result><code>0</code><text>OK</text></result>";
    Http::fake(['*' => Http::response($xml, 200)]);

    $v      = makeValidator([
        'response_code_element'    => 'code',
        'response_message_element' => 'text',
    ]);
    $result = (new ExternalValidatorService())->validate($v, '<xml/>');

    expect($result['status'])->toBe('success')
        ->and($result['message'])->toBe('OK');
});

it('sends metadata via GET with correct param name', function () {
    Http::fake(['*' => Http::response(xmlResponse('0', 'OK'), 200)]);

    (new ExternalValidatorService())->validate(
        makeValidator(['http_method' => 'GET', 'metadata_arg_name' => 'saml_meta']),
        '<EntityDescriptor/>'
    );

    Http::assertSent(fn ($req) => $req->method() === 'GET'
        && str_contains($req->url(), 'saml_meta='));
});

it('sends metadata via POST with correct param name', function () {
    Http::fake(['*' => Http::response(xmlResponse('0', 'OK'), 200)]);

    (new ExternalValidatorService())->validate(
        makeValidator(['http_method' => 'POST', 'metadata_arg_name' => 'xml_data']),
        '<EntityDescriptor/>'
    );

    Http::assertSent(fn ($req) => $req->method() === 'POST'
        && isset($req->data()['xml_data']));
});
