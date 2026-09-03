<?php

declare(strict_types=1);

/*
 * نفس القاعدة: نصوص السيرفر بس (الإيميلات والإشعارات المحفوظة). نصوص الشاشة في
 * frontend/messages/{en,ar}.json.
 *
 * الإشعار بيتكتب مرة واحدة، بلغة المستلم، في لحظة إرساله — وبيفضل كده، لأنه
 * سجل لحاجة اتقالت لحد.
 */

return [
    'assigned' => [
        'subject' => 'التذكرة :reference اتخصّصت ليك',
        'line' => ':actor خصّص ":subject" ليك.',
        'action' => 'افتح التذكرة',
    ],

    'customer_replied' => [
        'subject' => 'رد جديد على التذكرة :reference',
        'line' => 'العميل رد على ":subject".',
        'action' => 'اقرأ الرد',
    ],

    'sla_at_risk' => [
        'subject' => 'التذكرة :reference قربت تتأخر',
        'line' => '":subject" فاضلها :minutes دقيقة على هدف :timer.',
        'action' => 'افتح التذكرة',
    ],

    'sla_breached' => [
        'subject' => 'التذكرة :reference اتأخرت عن هدفها',
        'line' => '":subject" اتأخرت عن هدف :timer بـ :minutes دقيقة.',
        'action' => 'افتح التذكرة',
    ],

    'timer' => [
        'response' => 'أول رد',
        'resolution' => 'الحل',
    ],

    'greeting' => 'أهلاً :name،',
    'signoff' => 'Ragab CRM',
];
