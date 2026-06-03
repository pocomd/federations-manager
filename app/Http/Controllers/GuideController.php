<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\View\View;

class GuideController extends Controller
{
    private const PAGES = [
        'dashboard', 'entities', 'federations', 'certificates', 'metadata',
        'invitations', 'statistics', 'mail-templates', 'attributes', 'rules',
        'webhooks', 'import', 'scheduler', 'preferences', 'users', 'audit',
        'notifications', 'signing', 'simplesamlphp',
    ];

    private const TITLES = [
        'dashboard'      => 'Dashboard',
        'entities'       => 'Entities',
        'federations'    => 'Federations',
        'certificates'   => 'Certificates',
        'metadata'       => 'Metadata',
        'invitations'    => 'Invitations',
        'notifications'  => 'Notifications',
        'statistics'     => 'Statistics',
        'mail-templates' => 'Mail Templates',
        'attributes'     => 'Attribute Definitions',
        'rules'          => 'Compliance Rules',
        'webhooks'       => 'Webhooks',
        'import'         => 'Import from Jagger',
        'scheduler'      => 'Scheduler',
        'preferences'    => 'System Preferences',
        'users'          => 'Users',
        'audit'          => 'Audit Log',
        'signing'        => 'Metadata Signing',
        'simplesamlphp'  => 'SimpleSAMLphp Authentication',
    ];

    public const OP_GROUPS = [
        'entities'    => ['title' => 'Entity Operations',        'icon' => 'bi-diagram-3',     'desc' => 'Register, import, edit, suspend, validate, delete and restore entities. Manage metadata XML, requested attributes, ARP, and co-manager invitations.'],
        'federations' => ['title' => 'Federation Operations',    'icon' => 'bi-share',         'desc' => 'Create federations, manage membership, approve or reject entities, assign managers, generate metadata, upload signing keys, send member emails.'],
        'users'       => ['title' => 'User & Access Operations', 'icon' => 'bi-people',        'desc' => 'Invite new users, approve access requests, change roles, and suspend or reactivate accounts.'],
        'monitoring'  => ['title' => 'Monitoring & Reporting',   'icon' => 'bi-bar-chart-line', 'desc' => 'Monitor certificate expiry, review the audit log, and export statistics as CSV.'],
        'system'      => ['title' => 'System Administration',    'icon' => 'bi-gear',          'desc' => 'Import from Jagger, run scheduled jobs, configure scheduler timings, set system preferences, manage compliance rules and webhooks.'],
    ];

    public function index(): View
    {
        return view('guide.index', [
            'searchIndex' => $this->buildSearchIndex(),
            'opsIndex'    => $this->buildOpsIndex(),
        ]);
    }

    public function show(string $page): View
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        return view('guide.show', [
            'page'        => $page,
            'searchIndex' => $this->buildSearchIndex(),
            'opsIndex'    => $this->buildOpsIndex(),
        ]);
    }

    public function showOperations(string $group): View
    {
        abort_unless(array_key_exists($group, self::OP_GROUPS), 404);

        return view('guide.operations.' . $group, [
            'group'       => $group,
            'searchIndex' => $this->buildSearchIndex(),
            'opsIndex'    => $this->buildOpsIndex(),
        ]);
    }

    private function buildSearchIndex(): array
    {
        $index = [];
        foreach (self::PAGES as $key) {
            $html = view("guide.sections.{$key}")->render();
            $text = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $index[] = [
                'key'   => $key,
                'title' => self::TITLES[$key] ?? $key,
                'text'  => trim($text),
            ];
        }
        return $index;
    }

    private function buildOpsIndex(): array
    {
        $index = [];
        foreach (array_keys(self::OP_GROUPS) as $group) {
            if (!view()->exists("guide.operations.{$group}")) {
                continue;
            }
            $html  = view("guide.operations.partials.{$group}")->render();
            $parts = preg_split('/<div\s+x-show="op\s*===\s*(\d+)"\s*x-cloak>/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

            for ($i = 1; $i + 1 < count($parts); $i += 2) {
                $opNum   = (int) $parts[$i];
                $content = $parts[$i + 1];
                preg_match('/<h5[^>]*>(.*?)<\/h5>/s', $content, $m);
                $title   = trim(strip_tags($m[1] ?? ''));
                $text    = preg_replace('/\s+/', ' ', html_entity_decode(strip_tags($content), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                $index[] = [
                    'group'      => $group,
                    'groupTitle' => self::OP_GROUPS[$group]['title'],
                    'op'         => $opNum,
                    'title'      => $title ?: self::OP_GROUPS[$group]['title'],
                    'text'       => trim($text),
                ];
            }
        }
        return $index;
    }
}
