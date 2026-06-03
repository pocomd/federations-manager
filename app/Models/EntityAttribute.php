<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EntityAttribute extends Model
{
    use HasUuids;

    public const ATTR_ENTITY_CATEGORY         = 'entity_category';
    public const ATTR_ENTITY_CATEGORY_SUPPORT = 'entity_category_support'; // IdPs: RFC 8409 §4
    public const ATTR_ASSURANCE_PROFILE       = 'assurance_profile';

    // REFEDS entity category attribute values
    public const URI_RS           = 'http://refeds.org/category/research-and-scholarship';
    public const URI_COCO_V2      = 'https://refeds.org/category/code-of-conduct/v2';
    public const URI_HFD          = 'http://refeds.org/category/hide-from-discovery';
    public const URI_ANONYMOUS    = 'https://refeds.org/category/anonymous';
    public const URI_PSEUDONYMOUS = 'https://refeds.org/category/pseudonymous';
    public const URI_PERSONALIZED = 'https://refeds.org/category/personalized';

    // REFEDS assurance profile attribute values
    public const URI_SIRTFI  = 'https://refeds.org/sirtfi';
    public const URI_SIRTFI2 = 'https://refeds.org/sirtfi2';
    public const URI_MFA     = 'https://refeds.org/profile/mfa';
    public const URI_SFA     = 'https://refeds.org/profile/sfa';

    protected $fillable = [
        'entity_id',
        'attribute_name',
        'attribute_value',
    ];

    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }
}
