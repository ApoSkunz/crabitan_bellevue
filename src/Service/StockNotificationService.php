<?php

declare(strict_types=1);

namespace Service;

use Model\WineModel;

/**
 * Service de notification email lors du franchissement du seuil de stock bas.
 *
 * Envoie un email uniquement aux clients ayant le vin dans leur panier BDD
 * quand le stock passe sous STOCK_ALERTE_LIMIT.
 * Les abonnés newsletter sont exclus : leur consentement couvre la newsletter,
 * pas les alertes stock (scope RGPD distinct).
 * La colonne low_stock_notified_at évite les doublons de notification.
 */
class StockNotificationService
{
    private const DEFAULT_STOCK_LIMIT = 300;

    private WineModel $wineModel;
    private MailService $mailService;

    /**
     * Initialise le service avec les dépendances injectables (testabilité).
     *
     * @param WineModel|null   $wineModel   Modèle vin (null = instanciation automatique)
     * @param MailService|null $mailService Service mail (null = instanciation automatique)
     */
    public function __construct(?WineModel $wineModel = null, ?MailService $mailService = null)
    {
        $this->wineModel   = $wineModel   ?? new WineModel();
        $this->mailService = $mailService ?? new MailService();
    }

    /**
     * Retourne le seuil d'alerte stock bas depuis la configuration.
     *
     * @return int
     */
    public function getStockAlertLimit(): int
    {
        return (int) ($_ENV['STOCK_ALERTE_LIMIT'] ?? self::DEFAULT_STOCK_LIMIT);
    }

    /**
     * Vérifie si une notification doit être envoyée et l'envoie le cas échéant.
     *
     * Une notification est envoyée si :
     * - l'ancien stock était au-dessus du seuil
     * - le nouveau stock est en-dessous ou égal au seuil
     * - aucune notification n'a déjà été envoyée (low_stock_notified_at IS NULL)
     *
     * @param array<string, mixed> $wine     Données du vin (id, label_name, stock_quantity, low_stock_notified_at, slug, image_path, wine_color)
     * @param int                  $newStock Nouveau stock après décrémentation
     * @return bool True si une notification a été envoyée
     */
    public function notifyIfThresholdCrossed(array $wine, int $newStock): bool
    {
        $limit    = $this->getStockAlertLimit();
        $oldStock = (int) ($wine['stock_quantity'] ?? 0);
        $notified = $wine['low_stock_notified_at'] ?? null;
        $wineId   = (int) ($wine['id'] ?? 0);

        // Seuil non franchi ou déjà notifié
        if ($oldStock <= $limit || $newStock > $limit || $notified !== null) {
            return false;
        }

        $appUrl = rtrim($_ENV['APP_URL'] ?? 'https://crabitanbellevue.fr', '/');

        // Notifier uniquement les clients ayant ce vin dans leur panier BDD
        $cartUsers = $this->wineModel->getCartUsersWithWine($wineId);

        foreach ($cartUsers as $user) {
            $this->sendLowStockAlert(
                (string) ($user['email'] ?? ''),
                (string) ($user['first_name'] ?? ''),
                (string) ($user['lang'] ?? 'fr'),
                $wine,
                $appUrl,
                (string) ($user['newsletter_unsubscribe_token'] ?? '')
            );
        }

        // Marquer la notification comme envoyée
        $this->wineModel->setLowStockNotifiedAt($wineId);

        return true;
    }

    /**
     * Envoie un email d'alerte stock bas à un destinataire.
     *
     * @param string               $toEmail   Adresse email destinataire
     * @param string               $toName    Prénom ou email du destinataire
     * @param string               $lang      Langue (fr|en)
     * @param array<string, mixed> $wine      Données du vin
     * @param string               $appUrl    URL de base de l'application
     * @param string               $unsubToken Token de désabonnement newsletter
     * @return void
     */
    private function sendLowStockAlert(
        string $toEmail,
        string $toName,
        string $lang,
        array $wine,
        string $appUrl,
        string $unsubToken
    ): void {
        if ($toEmail === '') {
            return;
        }

        $limit      = $this->getStockAlertLimit();
        $labelName  = htmlspecialchars((string) ($wine['label_name'] ?? ''), ENT_QUOTES);
        $vintage    = (int) ($wine['vintage'] ?? 0);
        $slug       = rawurlencode((string) ($wine['slug'] ?? ''));
        $imagePath  = (string) ($wine['image_path'] ?? '');

        if ($lang === 'fr') {
            $subject   = "Stock limité — {$wine['label_name']} {$vintage} bientôt épuisé";
            $wineUrl   = htmlspecialchars("{$appUrl}/fr/vins/{$slug}", ENT_QUOTES);
            $title     = 'Stock limité';
            $greeting  = "Cher(e) {$toName},";
            $body      = "Le <strong>{$labelName} {$vintage}</strong> que vous avez dans votre panier"
                . " est bientôt en rupture de stock (moins de {$limit} bouteilles disponibles)."
                . " Nous vous recommandons de finaliser votre commande rapidement.";
            $btnLabel  = 'Commander maintenant';
        } else {
            $subject   = "Limited stock — {$wine['label_name']} {$vintage} almost sold out";
            $wineUrl   = htmlspecialchars("{$appUrl}/en/wines/{$slug}", ENT_QUOTES);
            $title     = 'Limited Stock';
            $greeting  = "Dear {$toName},";
            $body      = "The <strong>{$labelName} {$vintage}</strong> in your cart"
                . " is almost out of stock (fewer than {$limit} bottles remaining)."
                . " We recommend completing your order soon.";
            $btnLabel  = 'Order now';
        }

        $imageHtml = '';
        if ($imagePath !== '') {
            $safeImg   = htmlspecialchars("{$appUrl}/assets/images/wines/{$imagePath}", ENT_QUOTES);
            $imageHtml = "<img src=\"{$safeImg}\" alt=\"{$labelName} {$vintage}\""
                . " width=\"192\" style=\"display:block;width:192px;max-width:100%;"
                . "height:auto;margin:16px auto;border:0;border-radius:4px;\">";
        }

        $ctaBtn = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 auto 32px;">'
            . '<tr><td style="background:linear-gradient(135deg,#e8c86a,#c9a84c);border-radius:2px;">'
            . "<a href=\"{$wineUrl}\" style=\"display:inline-block;padding:14px 36px;"
            . 'font-family:Georgia,serif;font-size:14px;letter-spacing:2px;text-transform:uppercase;'
            . 'color:#1a1208;text-decoration:none;font-weight:bold;">'
            . $btnLabel
            . '</a></td></tr></table>';

        $htmlContent = "<p style=\"margin:0 0 24px;font-size:22px;color:#1a1208;font-family:Georgia,serif;\">"
            . htmlspecialchars($greeting, ENT_QUOTES)
            . "</p>"
            . "<p style=\"margin:0 0 16px;font-size:15px;line-height:1.7;color:#3d3425;\">"
            . $body
            . "</p>"
            . $imageHtml
            . $ctaBtn;

        $unsubParam = $unsubToken !== '' ? $unsubToken : null;
        $stockInfo  = $lang === 'en'
            ? 'You are receiving this email because this wine is in your cart.'
            : 'Vous recevez cet email car ce vin est dans votre panier.';
        $htmlBody   = $this->mailService->buildNewsletterHtml(
            $title,
            $htmlContent,
            null,
            $unsubParam,
            $lang,
            null,
            $stockInfo
        );

        try {
            $this->mailService->sendLowStockAlert($toEmail, $toName, $subject, $htmlBody);
        } catch (\Throwable) {
            // Échec silencieux — la notification n'est pas critique
        }
    }
}
