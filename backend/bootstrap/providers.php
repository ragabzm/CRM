<?php

use App\Modules\Ai\AiServiceProvider;
use App\Modules\Channels\ChannelsServiceProvider;
use App\Modules\Customers\CustomersServiceProvider;
use App\Modules\Knowledge\KnowledgeServiceProvider;
use App\Modules\Email\EmailServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Modules\Portal\PortalServiceProvider;
use App\Modules\Security\SecurityServiceProvider;
use App\Modules\Sla\SlaServiceProvider;
use App\Modules\Tickets\TicketsServiceProvider;
use App\Providers\AppServiceProvider;

// Listed in tier order (T0 -> T5) so the boot sequence mirrors module-tiers.php.
return [
    AppServiceProvider::class,
    PlatformServiceProvider::class,
    SecurityServiceProvider::class,
    // T1 beside Security: every future consumer sits above it.
    AiServiceProvider::class,
    CustomersServiceProvider::class,
    KnowledgeServiceProvider::class,
    TicketsServiceProvider::class,
    SlaServiceProvider::class,
    PortalServiceProvider::class,
    ChannelsServiceProvider::class,
    // Last: Email consumes the channel spine.
    EmailServiceProvider::class,
];
