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
    'mentioned' => [
        'subject' => ':actor طلبك على التذكرة :reference',
        'line' => 'ذكرك :actor في ملاحظة على ":subject".',
        'action' => 'اقرأ الملاحظة',
    ],

    'reminder' => [
        'subject' => 'تذكير: :about',
        'line' => 'طلبت تذكيرك بخصوص ":about".',
        'action' => 'افتحه',
    ],

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

    /*
     * السبب جوّه السطر مش ورا اللينك: "التذكرة اتصعّدت" ده إنذار، لكن "فلان
     * صعّدها لأن العميل مستني أربع أيام على قطعة محدّش طلبها" ده حاجة المشرف
     * يقدر يتصرف عليها من تليفونه — وده كل الغرض من التصعيد.
     */
    'escalated' => [
        'subject' => 'التذكرة :reference اتصعّدت',
        'line' => ':by صعّد ":subject" — :reason',
        'action' => 'افتح التذكرة',
    ],

    'timer' => [
        'response' => 'أول رد',
        'resolution' => 'الحل',
    ],

    'greeting' => 'أهلاً :name،',
    'signoff' => 'Ragab CRM',
];
