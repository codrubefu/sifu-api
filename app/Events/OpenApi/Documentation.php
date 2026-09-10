<?php

namespace App\Events\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Tag(name: 'Events', description: 'Event management endpoints.')]
#[OA\Tag(name: 'Event Categories', description: 'Event category management endpoints.')]
#[OA\Tag(name: 'Event Occurrences', description: 'Concrete event occurrence endpoints.')]
#[OA\Tag(name: 'Event Participants', description: 'Event participant management endpoints.')]
class Documentation
{
}
