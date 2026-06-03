<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\MailTemplate;
use Illuminate\Database\Seeder;

class MailTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name'      => 'Entity registration request',
                'group'     => 'entity_registration',
                'lang'      => 'en',
                'subject'   => '[[entity_name]] registration request — [[federation_name]]',
                'body'      => "Dear [[contact_name]],\n\nYour entity [[entity_name]] ([[entity_id]]) has been submitted for registration in [[federation_name]].\n\nOur team will review your request and contact you shortly.\n\n[[mail_signature]]",
                'is_active' => true,
            ],
            [
                'name'      => 'Entity approved',
                'group'     => 'entity_registration',
                'lang'      => 'en',
                'subject'   => '[[entity_name]] approved — [[federation_name]]',
                'body'      => "Dear [[contact_name]],\n\nWe are pleased to inform you that your entity [[entity_name]] has been approved and is now active in [[federation_name]].\n\n[[mail_signature]]",
                'is_active' => true,
            ],
            [
                'name'      => 'Certificate expiry warning',
                'group'     => 'certificate_expiry',
                'lang'      => 'en',
                'subject'   => 'Certificate expiry warning — [[entity_name]]',
                'body'      => "Dear [[contact_name]],\n\nThis is a reminder that the certificate for [[entity_name]] will expire on [[cert_expiry_date]].\n\nCertificate subject: [[cert_subject]]\n\nPlease renew your certificate before the expiry date to avoid service interruption.\n\n[[mail_signature]]",
                'is_active' => true,
            ],
            [
                'name'      => 'Compliance check failure',
                'group'     => 'compliance_failure',
                'lang'      => 'en',
                'subject'   => 'Action required: compliance issues detected for [[entity_id]]',
                'body'      => "Dear [[contact_name]],\n\nDuring a recent compliance check of entities registered in [[federation_name]], your entity [[entity_name]] ([[entity_id]]) was found to have the following issues:\n\n[[validation_errors]]\n\nPlease review and resolve these issues at your earliest convenience. Entities that do not meet compliance requirements may be suspended from the federation.\n\nIf you have any questions, please reply to this email or contact the federation operator.\n\n[[mail_signature]]",
                'is_active' => true,
            ],
            [
                'name'      => 'General announcement',
                'group'     => 'general',
                'lang'      => 'en',
                'subject'   => '[[federation_name]] — announcement',
                'body'      => "Dear [[contact_name]],\n\n[[mail_signature]]",
                'is_active' => true,
            ],
            [
                'name'      => 'Entity suspended',
                'group'     => 'entity_suspended',
                'lang'      => 'en',
                'subject'   => '[[entity_name]] suspended',
                'body'      => "Dear [[contact_name]],\n\nWe would like to inform you that your entity [[entity_name]] ([[entity_id]]) has been suspended and is no longer active in any federation.\n\nIf you believe this is an error or would like to appeal, please contact the federation operator.\n\n[[mail_signature]]",
                'is_active' => true,
            ],
            [
                'name'      => 'Federation deactivated',
                'group'     => 'federation_deactivated',
                'lang'      => 'en',
                'subject'   => 'Federation [[federation_name]] deactivated',
                'body'      => "Dear [[contact_name]],\n\nWe would like to inform you that the federation [[federation_name]] has been deactivated. Your entity [[entity_name]] ([[entity_id]]) is affected by this change.\n\nIf you have any questions, please contact the federation operator.\n\n[[mail_signature]]",
                'is_active' => true,
            ],
            [
                'name'      => 'Invitation',
                'group'     => 'invitation',
                'lang'      => 'en',
                'subject'   => 'You have been invited to join [[federation_name]]',
                'body'      => "Dear [[contact_name]],\n\nYou have been invited to join [[federation_name]].\n\nClick the link below to create your account and accept the invitation:\n[[invitation_url]]\n\nThis invitation expires in [[expiry_hours]] hours.\n\nIf you did not expect this invitation, you can safely ignore this email.\n\n[[mail_signature]]",
                'is_active' => true,
            ],
        ];

        foreach ($templates as $data) {
            MailTemplate::firstOrCreate(
                ['name' => $data['name'], 'lang' => $data['lang']],
                $data
            );
        }
    }
}
