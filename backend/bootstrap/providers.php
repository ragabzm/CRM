<?php

use App\Modules\Customers\CustomersServiceProvider;
use App\Modules\Email\EmailServiceProvider;
use App\Modules\Platform\PlatformServiceProvider;
use App\Modules\Portal\PortalServiceProvider;
use App\Modules\Security\SecurityServiceProvider;
use App\Modules\Sla\SlaServiceProvider;
use App\Modules\Tickets\TicketsServiceProvider;
use App\Providers\AppServiceProvider;

// Listed in tier order (T0 -> T4) so the boot sequence mirrors module-tiers.php.
return [
    AppServiceProvider::class,
    PlatformServiceProvider::class,
    SecurityServiceProvider::class,
    CustomersServiceProvider::class,
    TicketsServiceProvider::class,
    SlaServiceProvider::class,
    EmailServiceProvider::class,
    PortalServiceProvider::class,
];
