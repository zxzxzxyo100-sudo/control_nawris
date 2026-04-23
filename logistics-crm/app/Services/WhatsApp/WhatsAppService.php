<?php

namespace App\Services\WhatsApp;

use App\Models\Order;

/**
 * قوالب رسائل واتساب بلهجة ليبية + بناء روابط https://wa.me/
 */
final class WhatsAppService
{
    private const LONG_DELAY_THRESHOLD_DAYS = 5;

    /**
     * رسالة للزبون: تأخير 3–4 أيام (أو أقل عند الحاجة التشغيلية).
     */
    public function templateCustomerShortDelay(string $customerName, string $orderReference): string
    {
        return 'السلام عليكم يا سيد/ة '.$customerName.'، معاك شركة النورس للشحن. نعتذر منك عالتأخير البسيط في طلبيتك رقم '
            .$orderReference.'، الشحنة حالياً مع المندوب وفي طريقها ليك بإذن الله. شكراً لصبرك معانا.';
    }

    /**
     * رسالة للزبون: تأخير أكثر من 5 أيام.
     */
    public function templateCustomerLongDelay(string $orderReference): string
    {
        return 'أهلاً بيك، نعتذر منك جداً بخصوص طلبيتك رقم '.$orderReference
            .'. صار معنا تأخير خارج عن إرادتنا وجاري متابعة الموضوع حالياً مع المندوب لتوصيلها لك في أقرب وقت. بارك الله فيك على سعة بالك.';
    }

    /**
     * استفسار للكابتن عن طلب متأخر.
     */
    public function templateCaptainInquiry(string $captainName, string $orderReference, string $customerName, int $daysLate): string
    {
        return 'يا كابتن '.$captainName.'، بخصوص الطلبية رقم '.$orderReference.' للزبون '.$customerName
            .'. الشحنة طولت عندك وليها '.$daysLate.' أيام. يا ريت تأكدنا على حالتها توا أو تبلغنا لو فيه أي مشكلة صايرة معاك.';
    }

    public function customerDisplayName(Order $order): string
    {
        return $order->user?->name ?? 'زبوننا المحترم';
    }

    public function customerMessageForOrder(Order $order, int $daysLate): string
    {
        $name = $this->customerDisplayName($order);
        $ref = $order->reference;

        if ($daysLate >= self::LONG_DELAY_THRESHOLD_DAYS) {
            return $this->templateCustomerLongDelay($ref);
        }

        return $this->templateCustomerShortDelay($name, $ref);
    }

    public function captainMessageForOrder(Order $order, int $daysLate): string
    {
        $cap = $order->captain?->full_name ?? 'كابتن';
        $cust = $this->customerDisplayName($order);

        return $this->templateCaptainInquiry($cap, $order->reference, $cust, max(0, $daysLate));
    }

    /**
     * رابط واتساب للزبون أو null إن لم يتوفر رقم.
     */
    public function customerWhatsAppUrl(Order $order, int $daysLate): ?string
    {
        $digits = $this->normalizePhoneDigits($this->resolveCustomerPhoneRaw($order));

        if ($digits === null) {
            return null;
        }

        return $this->buildWaMeUrl($digits, $this->customerMessageForOrder($order, $daysLate));
    }

    /**
     * رابط واتساب للكابتن أو null.
     */
    public function captainWhatsAppUrl(Order $order, int $daysLate): ?string
    {
        if (! $order->captain) {
            return null;
        }

        $digits = $this->normalizePhoneDigits($order->captain->phone);

        if ($digits === null) {
            return null;
        }

        return $this->buildWaMeUrl($digits, $this->captainMessageForOrder($order, $daysLate));
    }

    private function resolveCustomerPhoneRaw(Order $order): ?string
    {
        if ($order->customer_phone) {
            return $order->customer_phone;
        }

        return $order->user?->phone;
    }

    private function buildWaMeUrl(string $digitsOnly, string $message): string
    {
        return 'https://wa.me/'.$digitsOnly.'?text='.rawurlencode($message);
    }

    /**
     * تطبيع الرقم لصيغة wa.me (أرقام فقط، بدون +).
     */
    public function normalizePhoneDigits(?string $raw): ?string
    {
        if ($raw === null || trim($raw) === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        return $digits !== '' ? $digits : null;
    }
}
