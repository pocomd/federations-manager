<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Entity;
use App\Models\EntityContact;
use App\Models\EntityEndpoint;
use App\Models\EntityUiInfo;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Entity>
 */
class EntityFactory extends Factory
{
    protected $model = Entity::class;

    public function definition(): array
    {
        $domain = fake()->unique()->domainName();

        return [
            'entity_id'                     => "https://{$domain}/saml2/metadata",
            'type'                          => 'idp',
            'status'                        => 'active',
            'edugain'                       => false,
            'registration_authority'        => config('federation.registration_authority') ?? '',

            // IdP specific
            'scope'                         => $domain,
            'nameid_formats'                => ['urn:oasis:names:tc:SAML:2.0:nameid-format:persistent'],

            // SP specific (defaults; overridden by sp() state)
            'sp_want_authn_requests_signed' => true,
            'sp_want_assertions_signed'     => true,
            'requested_attributes'          => [],
        ];
    }

    /**
     * Create child records (uiInfo, contacts, endpoints) after entity is persisted.
     * Called for both idp() and sp() states — afterCreating detects type from the saved model.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (Entity $entity): void {
            $domain  = parse_url($entity->entity_id, PHP_URL_HOST) ?? 'example.com';
            $orgName = ucwords(str_replace(['.', '-'], ' ', explode('.', $domain)[0])) . ' Organisation';
            $base    = "https://{$domain}";

            // ── mdui:UIInfo + md:Organization ─────────────────────────────
            $uiInfoRows = [
                ['field' => 'display_name',   'lang' => 'en', 'value' => $orgName],
                ['field' => 'description',    'lang' => 'en', 'value' => fake()->sentence()],
                ['field' => 'information_url','lang' => 'en', 'value' => "{$base}/"],
                ['field' => 'privacy_url',    'lang' => 'en', 'value' => "{$base}/privacy"],
                ['field' => 'org_name',       'lang' => 'en', 'value' => $orgName],
                ['field' => 'org_display_name','lang' => 'en','value' => $orgName],
                ['field' => 'org_url',        'lang' => 'en', 'value' => "{$base}/"],
            ];

            foreach ($uiInfoRows as $row) {
                EntityUiInfo::create([
                    'entity_id' => $entity->id,
                    'field'     => $row['field'],
                    'lang'      => $row['lang'],
                    'value'     => $row['value'],
                ]);
            }

            // ── md:ContactPerson ──────────────────────────────────────────
            EntityContact::create([
                'entity_id'  => $entity->id,
                'type'       => 'technical',
                'given_name' => 'Technical',
                'sur_name'   => 'Contact',
                'email'      => "technical@{$domain}",
            ]);

            EntityContact::create([
                'entity_id'  => $entity->id,
                'type'       => 'support',
                'given_name' => 'Support',
                'sur_name'   => 'Contact',
                'email'      => "support@{$domain}",
            ]);

            // ── Endpoints ─────────────────────────────────────────────────
            if ($entity->type === 'idp') {
                EntityEndpoint::create([
                    'entity_id' => $entity->id,
                    'type'      => 'sso',
                    'binding'   => EntityEndpoint::BINDING_HTTP_REDIRECT,
                    'location'  => "{$base}/saml2/idp/SSO/Redirect",
                ]);
                EntityEndpoint::create([
                    'entity_id' => $entity->id,
                    'type'      => 'sso',
                    'binding'   => EntityEndpoint::BINDING_HTTP_POST,
                    'location'  => "{$base}/saml2/idp/SSO/POST",
                ]);
                EntityEndpoint::create([
                    'entity_id' => $entity->id,
                    'type'      => 'slo',
                    'binding'   => EntityEndpoint::BINDING_HTTP_REDIRECT,
                    'location'  => "{$base}/saml2/idp/SLO/Redirect",
                ]);
            }

            if ($entity->type === 'sp') {
                EntityEndpoint::create([
                    'entity_id'  => $entity->id,
                    'type'       => 'acs',
                    'binding'    => EntityEndpoint::BINDING_HTTP_POST,
                    'location'   => "{$base}/saml2/sp/ACS/POST",
                    'index'      => 1,
                    'is_default' => true,
                ]);
                EntityEndpoint::create([
                    'entity_id' => $entity->id,
                    'type'      => 'acs',
                    'binding'   => EntityEndpoint::BINDING_HTTP_REDIRECT,
                    'location'  => "{$base}/saml2/sp/ACS/Redirect",
                    'index'     => 2,
                ]);
                EntityEndpoint::create([
                    'entity_id' => $entity->id,
                    'type'      => 'slo',
                    'binding'   => EntityEndpoint::BINDING_HTTP_REDIRECT,
                    'location'  => "{$base}/saml2/sp/SLO/Redirect",
                ]);
            }
        });
    }

    /** Identity Provider state. */
    public function idp(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'idp',
        ]);
    }

    /** Service Provider state. */
    public function sp(): static
    {
        // Use a regular closure (not arrow fn) so fake()->unique() is called
        // fresh per entity, not once for the whole batch.
        return $this->state(function (array $attributes): array {
            $domain = fake()->unique()->domainName();

            return [
                'type'           => 'sp',
                'entity_id'      => "https://{$domain}/saml2/metadata",
                'scope'          => null,
                'nameid_formats' => [],
            ];
        });
    }

    /** Draft status state. */
    public function draft(): static
    {
        return $this->state(fn () => ['status' => 'draft']);
    }
}
