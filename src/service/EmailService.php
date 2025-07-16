<?php

namespace App\Service;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private PHPMailer $mailer;

    public function __construct()
    {
        $this->mailer = new PHPMailer(true);

        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host       = SMTP_HOST;
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = SMTP_USERNAME;
        $this->mailer->Password   = SMTP_PASSWORD;
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port       = SMTP_PORT;

        // Sender
        $this->mailer->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
    }

    /**
     * Sends the order confirmation email.
     */
    public function sendOrderConfirmation(string $toEmail, string $customerName, array $orderData): bool
    {
        try {
            // Recipient
            $this->mailer->addAddress($toEmail, $customerName);

            // Content
            $this->mailer->isHTML(true);
            $this->mailer->Subject = 'Your Aura Perfumes Order Confirmation #' . $orderData['orderNumber'];
            $this->mailer->Body    = $this->createOrderEmailHtml($customerName, $orderData);
            $this->mailer->AltBody = 'Your order has been placed successfully. Thank you for shopping with us!';

            $this->mailer->send();
            return true;
        } catch (Exception $e) {
            error_log("Message could not be sent. Mailer Error: {$this->mailer->ErrorInfo}");
            return false;
        }
    }

    /**
     * Creates the HTML content for the order email.
     * In a real app, you might use a templating engine for this.
     */
    private function createOrderEmailHtml(string $customerName, array $orderData): string
    {
        $html = "<h1>Thank you for your order, {$customerName}!</h1>";
        $html .= "<p>Your order #{$orderData['orderNumber']} has been placed successfully.</p>";
        $html .= "<h2>Order Summary</h2>";
        $html .= "<ul>";
        foreach ($orderData['items'] as $item) {
            $html .= "<li>{$item['quantity']}x {$item['id']} - \${$item['price']}</li>"; // Note: Use a lookup for product name here
        }
        $html .= "</ul>";
        $html .= "<h3>Total: \${$orderData['totalAmount']}</h3>";

        return $html;
    }
}
