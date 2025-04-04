<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class MailHelper {
    private $mailer;

    public function __construct() {
        $this->mailer = new PHPMailer(true);
        
        // Server settings
        $this->mailer->isSMTP();
        $this->mailer->Host = 'smtp.gmail.com';  // Use your SMTP host
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = 'stockportwarehouse@gmail.com'; // Your email
        $this->mailer->Password = 'evlhoqavnzvrvcma';    // Your app password
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mailer->Port = 587;
    }

    public function sendDeliveryConfirmation($customerEmail, $orderDetails) {
        try {
            $this->mailer->setFrom('stockportwarehouse@gmail.com', 'Stockport Warehouse');
            $this->mailer->addAddress($customerEmail);
            
            $this->mailer->isHTML(true);
            $this->mailer->Subject = "Order #{$orderDetails['CustomerOrderID']} Delivery Confirmation";
            
            $this->mailer->Body = $this->getEmailTemplate($orderDetails);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            $errorLog = __DIR__ . '/../logs/mail_errors.log';
            $errorMessage = date('[Y-m-d H:i:s]') . " Mail Error:\n";
            $errorMessage .= "Message: " . $e->getMessage() . "\n";
            $errorMessage .= "Customer Email: " . $customerEmail . "\n";
            $errorMessage .= "Order ID: " . $orderDetails['CustomerOrderID'] . "\n";
            $errorMessage .= "PHPMailer Error: " . $this->mailer->ErrorInfo . "\n";
            $errorMessage .= "----------------------------------------\n";
            
            error_log($errorMessage, 3, $errorLog);
            return false;
        }
    }

    private function getEmailTemplate($details) {
        $totalAmount = $details['Price'] * $details['Quantity'];
        $formattedDate = date('F d, Y', strtotime($details['OrderDate']));
        
        return "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2>Order Delivery Confirmation</h2>
            <p>Dear {$details['CustomerName']},</p>
            <p>Your order #{$details['CustomerOrderID']} has been successfully delivered.</p>
            
            <div style='background: #f5f5f5; padding: 15px; margin: 20px 0;'>
                <h3>Order Details:</h3>
                <p>Order Date: {$formattedDate}</p>
                <p>Product: {$details['ProductName']}</p>
                <p>Quantity: {$details['Quantity']} units</p>
                <p>Price per Unit: ₱{$details['Price']}</p>
                <p>Total Weight: " . ($details['Weight'] * $details['Quantity']) . " {$details['weight_unit']}</p>
                <p>Total Amount: ₱{$totalAmount}</p>
            </div>
            
            <p>Thank you for your business!</p>
            <p>Best regards,<br>Stockport Warehouse</p>
        </div>";
    }
}