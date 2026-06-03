<?php

declare(strict_types=1);

namespace App\Services\Metadata;

use App\Models\AppNotification;
use App\Models\AuditLog;
use App\Models\Entity;
use App\Models\Federation;
use App\Models\FederationValidator;
use DOMDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ExternalValidatorService
{
    public function validate(FederationValidator $validator, string $xml): array
    {
        try {
            $timeout    = $validator->timeout ?? 30;
            $metaParam  = [$validator->metadata_arg_name => $xml];
            $extraParams = [];

            if ($validator->optional_args) {
                parse_str($validator->optional_args, $extraParams);
            }

            $allParams = array_merge($metaParam, $extraParams);

            if ($validator->http_method === 'GET') {
                if ($validator->args_separator === '/') {
                    // Path-style: append each value as a URL segment
                    $url = rtrim($validator->url, '/');
                    foreach ($allParams as $val) {
                        $url .= '/' . rawurlencode((string) $val);
                    }
                    $response = Http::timeout($timeout)->get($url);
                } else {
                    // Standard query-string (separator is & or similar)
                    $response = Http::timeout($timeout)->get($validator->url, $allParams);
                }
            } else {
                $response = Http::timeout($timeout)->asForm()->post($validator->url, $allParams);
            }

            if (! $response->successful()) {
                return [
                    'status'  => 'http_error',
                    'code'    => null,
                    'message' => 'Validator returned HTTP ' . $response->status(),
                    'raw'     => '',
                ];
            }

            $dom = new DOMDocument();
            if (! @$dom->loadXML($response->body())) {
                return [
                    'status'  => 'error',
                    'code'    => null,
                    'message' => 'Invalid XML response from validator',
                    'raw'     => $response->body(),
                ];
            }

            $code    = $dom->getElementsByTagName($validator->response_code_element)->item(0)?->textContent;
            $message = $dom->getElementsByTagName($validator->response_message_element)->item(0)?->textContent;

            $status = match ($code) {
                $validator->success_value  => 'success',
                $validator->warning_value  => 'warning',
                $validator->error_value    => 'error',
                $validator->critical_value => 'critical',
                default                    => 'error',
            };

            return [
                'status'  => $status,
                'code'    => $code,
                'message' => $message ?? '',
                'raw'     => $response->body(),
            ];

        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            $msg = strtolower($e->getMessage());

            if (str_contains($msg, 'timed out') || str_contains($msg, 'timeout')) {
                return [
                    'status'  => 'timeout',
                    'code'    => null,
                    'message' => 'Connection timed out after ' . ($validator->timeout ?? 30) . 's',
                    'raw'     => '',
                ];
            }

            if (str_contains($msg, 'ssl') || str_contains($msg, 'certificate') || str_contains($msg, 'tls')) {
                return [
                    'status'  => 'ssl_error',
                    'code'    => null,
                    'message' => 'SSL/TLS error: ' . $e->getMessage(),
                    'raw'     => '',
                ];
            }

            return [
                'status'  => 'unreachable',
                'code'    => null,
                'message' => 'Could not connect: ' . $e->getMessage(),
                'raw'     => '',
            ];

        } catch (\Exception $e) {
            return [
                'status'  => 'error',
                'code'    => null,
                'message' => $e->getMessage(),
                'raw'     => '',
            ];
        }
    }

    public function validateEntity(Entity $entity, FederationValidator $validator): array
    {
        $xml = app(EntityMetadataService::class)->renderXml($entity);

        return $this->validate($validator, $xml);
    }

    /**
     * Run all enabled_on_registration validators for an entity/federation pair.
     * Always non-blocking — the entity proceeds regardless of outcome.
     * Mandatory validator failures trigger a bell notification to each federation manager.
     */
    public function runOnRegistration(Entity $entity, Federation $federation, string $triggeredBy = 'registration'): void
    {
        $validators = $federation->validators()
            ->where('enabled', true)
            ->where('enabled_on_registration', true)
            ->get();

        if ($validators->isEmpty()) {
            return;
        }

        foreach ($validators as $validator) {
            $result = $this->validateEntity($entity, $validator);

            Log::info('External validator run on registration', [
                'validator'    => $validator->name,
                'entity'       => $entity->entity_id,
                'federation'   => $federation->name,
                'status'       => $result['status'],
                'triggered_by' => $triggeredBy,
            ]);

            AuditLog::create([
                'user_id'    => null,
                'action'     => 'validator_run',
                'model_type' => Entity::class,
                'model_id'   => $entity->id,
                'new_values' => [
                    'validator'    => $validator->name,
                    'status'       => $result['status'],
                    'message'      => $result['message'],
                    'federation'   => $federation->name,
                    'triggered_by' => $triggeredBy,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            $failed = in_array($result['status'], ['error', 'critical', 'timeout', 'unreachable', 'ssl_error', 'http_error'], true);

            if ($validator->mandatory && $failed) {
                foreach ($federation->managers as $manager) {
                    AppNotification::create([
                        'user_id'      => $manager->id,
                        'type'         => 'validator_failed',
                        'title'        => 'Mandatory validator failed',
                        'body'         => "Validator \"{$validator->name}\" reported {$result['status']} for entity \"{$entity->entity_id}\": {$result['message']}",
                        'subject_type' => 'federation',
                        'subject_id'   => $federation->id,
                        'action_url'   => route('federations.validators.index', $federation),
                    ]);
                }
            }
        }
    }
}
