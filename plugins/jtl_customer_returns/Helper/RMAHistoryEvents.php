<?php

declare(strict_types=1);

namespace Plugin\jtl_customer_returns\Helper;

/**
 * RMA History Events - Konstanten für Event-Logging
 */
class RMAHistoryEvents
{
    /** @var string RMA wurde erstellt */
    public const RMA_CREATED = 'rma_created';
    
    /** @var string Status wurde geändert */
    public const STATUS_CHANGED = 'status_changed';
    
    /** @var string Item-Status wurde aktualisiert */
    public const ITEM_STATUS_UPDATED = 'item_status_updated';
    
    /** @var string Wawi-Synchronisation durchgeführt */
    public const WAWI_SYNCED = 'wawi_synced';
    
    /** @var string Wawi-Update empfangen (von Wawi → Shop) */
    public const WAWI_UPDATE_RECEIVED = 'wawi_update_received';
    
    /** @var string Versandlabel erstellt */
    public const LABEL_CREATED = 'label_created';
    
    /** @var string Bestätigungs-E-Mail versendet */
    public const EMAIL_CONFIRMATION_SENT = 'email_confirmation_sent';
    
    /** @var string Status-Update-E-Mail versendet */
    public const EMAIL_STATUS_UPDATE_SENT = 'email_status_update_sent';
    
    /** @var string Gutschein-E-Mail versendet */
    public const EMAIL_VOUCHER_SENT = 'email_voucher_sent';
    
    /** @var string Rückzahlungs-E-Mail versendet */
    public const EMAIL_REFUND_SENT = 'email_refund_sent';
    
    /** @var string Kundendaten anonymisiert (DSGVO) */
    public const CUSTOMER_ANONYMIZED = 'customer_anonymized';
    
    /** @var string Kommentar hinzugefügt */
    public const COMMENT_ADDED = 'comment_added';
    
    /** @var string Admin-Notiz hinzugefügt */
    public const ADMIN_NOTE_ADDED = 'admin_note_added';
    
    /**
     * Event-Namen für Menschen lesbar machen
     *
     * @param string $event
     * @param string $ISO Sprachkürzel (GER, ENG)
     * @return string
     */
    public static function getEventLabel(string $event, string $ISO = 'GER'): string
    {
        $labels = [
            'GER' => [
                self::RMA_CREATED => 'Retoure erstellt',
                self::STATUS_CHANGED => 'Status geändert',
                self::ITEM_STATUS_UPDATED => 'Artikel-Status aktualisiert',
                self::WAWI_SYNCED => 'Mit Wawi synchronisiert',
                self::WAWI_UPDATE_RECEIVED => 'Wawi-Update empfangen',
                self::LABEL_CREATED => 'Versandlabel erstellt',
                self::EMAIL_CONFIRMATION_SENT => 'Bestätigungs-E-Mail versendet',
                self::EMAIL_STATUS_UPDATE_SENT => 'Status-E-Mail versendet',
                self::EMAIL_VOUCHER_SENT => 'Gutschein-E-Mail versendet',
                self::EMAIL_REFUND_SENT => 'Rückzahlungs-E-Mail versendet',
                self::CUSTOMER_ANONYMIZED => 'Kundendaten anonymisiert',
                self::COMMENT_ADDED => 'Kommentar hinzugefügt',
                self::ADMIN_NOTE_ADDED => 'Admin-Notiz hinzugefügt',
            ],
            'ENG' => [
                self::RMA_CREATED => 'Return created',
                self::STATUS_CHANGED => 'Status changed',
                self::ITEM_STATUS_UPDATED => 'Item status updated',
                self::WAWI_SYNCED => 'Synced with Wawi',
                self::WAWI_UPDATE_RECEIVED => 'Wawi update received',
                self::LABEL_CREATED => 'Shipping label created',
                self::EMAIL_CONFIRMATION_SENT => 'Confirmation email sent',
                self::EMAIL_STATUS_UPDATE_SENT => 'Status update email sent',
                self::EMAIL_VOUCHER_SENT => 'Voucher email sent',
                self::EMAIL_REFUND_SENT => 'Refund email sent',
                self::CUSTOMER_ANONYMIZED => 'Customer data anonymized',
                self::COMMENT_ADDED => 'Comment added',
                self::ADMIN_NOTE_ADDED => 'Admin note added',
            ]
        ];
        
        return $labels[$ISO][$event] ?? $event;
    }
    
    /**
     * Event-Icon (für Frontend-Darstellung)
     *
     * @param string $event
     * @return string Bootstrap Icon-Klasse
     */
    public static function getEventIcon(string $event): string
    {
        $icons = [
            self::RMA_CREATED => 'bi-plus-circle',
            self::STATUS_CHANGED => 'bi-arrow-repeat',
            self::ITEM_STATUS_UPDATED => 'bi-box',
            self::WAWI_SYNCED => 'bi-cloud-upload',
            self::WAWI_UPDATE_RECEIVED => 'bi-cloud-download',
            self::LABEL_CREATED => 'bi-printer',
            self::EMAIL_CONFIRMATION_SENT => 'bi-envelope-check',
            self::EMAIL_STATUS_UPDATE_SENT => 'bi-envelope',
            self::EMAIL_VOUCHER_SENT => 'bi-gift',
            self::EMAIL_REFUND_SENT => 'bi-currency-euro',
            self::CUSTOMER_ANONYMIZED => 'bi-person-x',
            self::COMMENT_ADDED => 'bi-chat-left-text',
            self::ADMIN_NOTE_ADDED => 'bi-pencil-square',
        ];
        
        return $icons[$event] ?? 'bi-info-circle';
    }
    
    /**
     * Event-Farbe (für Timeline-Darstellung)
     *
     * @param string $event
     * @return string Bootstrap Color-Klasse
     */
    public static function getEventColor(string $event): string
    {
        $colors = [
            self::RMA_CREATED => 'success',
            self::STATUS_CHANGED => 'primary',
            self::ITEM_STATUS_UPDATED => 'info',
            self::WAWI_SYNCED => 'success',
            self::WAWI_UPDATE_RECEIVED => 'info',
            self::LABEL_CREATED => 'warning',
            self::EMAIL_CONFIRMATION_SENT => 'success',
            self::EMAIL_STATUS_UPDATE_SENT => 'info',
            self::EMAIL_VOUCHER_SENT => 'success',
            self::EMAIL_REFUND_SENT => 'success',
            self::CUSTOMER_ANONYMIZED => 'secondary',
            self::COMMENT_ADDED => 'light',
            self::ADMIN_NOTE_ADDED => 'secondary',
        ];
        
        return $colors[$event] ?? 'secondary';
    }
}
