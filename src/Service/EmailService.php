<?php
namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mime\Email;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\ApiUsage;

class EmailService
{
    private $mailer;
    private $entityManager;

    public function __construct(MailerInterface $mailer, EntityManagerInterface $entityManager)
    {
        $this->mailer = $mailer;
        $this->entityManager = $entityManager;
    }

    // public function sendEmailSendDealsToFD($responseMessage, $deals, $isError = false)
    // {
    //     $body ='';
    //     if ($isError) {
    //         $subject = "Error while adding deals to First Delivery 😱";
    //         $body = "Hello, <br> An error occurred while attempting to send the deals via the First Delivery API. Please find the error details below:<br>";
    //         $body .= "- Date and Time: " . (new \DateTime())->format('Y-m-d H:i:s') . "<br>";
    //         $body .= "- Error Message: " . $responseMessage . "<br> <br>";
    //         $body .= "Please check the issue as soon as possible. If you need assistance, do not hesitate to contact the technical team <br><br>";
    //         $body .= "Regards,<br>";
    //     } else {
           
    //         $subject = "Success in adding deals to First Delivery 🎉";
    //         $body = "Hello,<br>";
    //         $body .= "The following deals have been successfully added via the First Delivery API:<br>";
    //         $successCount = 0;

    //         if (isset($deals['successes']) && is_array($deals['successes'])) {
    //             foreach ($deals['successes'] as $deal) {
    //                 if (isset($deal['order_ID']) && isset($deal['message'])) {
    //                     $body .= "- Order ID: " . $deal['order_ID'] . ", Message: " . $deal['message'] . "<br>";
    //                     $successCount++;
    //                 }
    //             }
    //         }
    //         $halfSuccessCount = $successCount / 3;

    //         $body .= "<br><strong>Total successful deals: " . $halfSuccessCount . "</strong><br>";
    //         $errorCount = 0;
    //         if (isset($deals['errors']) && is_array($deals['errors'])) {
    //             $body .= "<br>The following errors were encountered:<br>";
    //             foreach ($deals['errors'] as $error) {
    //                 if (is_array($error) && isset($error['order_ID']) && isset($error['error'])) {
    //                     $body .= "- Order ID: " . $error['order_ID'] . ", Erreur: " . $error['error'] . "<br>";
    //                     $errorCount++;
    //                 }
    //             }
    //         }
            
    //         $body .= "<br><strong>Total errors: " . $errorCount . "</strong><br>";
    //         $body .= "- Date and Time: " . (new \DateTime())->format('Y-m-d H:i:s') . "<br>";
    //         $body .= "Regards,<br>";
    //     }
    //     dd($body);
    //     // $email = (new Email())
    //     //     ->from('support@plumbill.io')
    //     //     ->to('mr.elidrissiazeddine@gmail.com')
    //     //     ->addTo('alla.bennasr@gmail.com')
    //     //     ->subject($subject)
    //     //     ->text(strip_tags($body))
    //     //     ->html($body);
    //     $email = (new Email())
    //         ->from('support@plumbill.io')
    //         ->to('belhouari.imane@gmail.com')
    //         ->subject($subject)
    //         ->text(strip_tags($body))
    //         ->html($body);

    //     try {
    //         $this->mailer->send($email);
    //     } catch (TransportExceptionInterface $e) {
    //         // Gérer l'exception
    //         throw $e;
    //     }
    // }
    // public function sendEmailSendDealsToFD($responseMessage, $deals, $isError = false)
    // {
    //     $body = '';
    //     if ($isError) {
    //         $subject = "Error while adding deals to ARAMEX 😱";
    //         $body = "Hello,<br>An error occurred while attempting to send the deals via the ARAMEX API. Please find the error details below:<br>";
    //         $body .= "- Date and Time: " . (new \DateTime())->format('Y-m-d H:i:s') . "<br>";
    //         $body .= "- Error Message: " . $responseMessage . "<br><br>";
    //         $body .= "Please check the issue as soon as possible. If you need assistance, do not hesitate to contact the technical team.<br><br>";
    //         $body .= "Regards,<br>";
    //     } else {
    //         $subject = "Success in adding deals to ARAMEX 🎉";
    //         $body = "Hello,<br>";
    //         $body .= "The following deals have been successfully processed via the  ARAMEX API:<br><br>";

    //         // Group successes by order_ID
    //         $successesByOrder = [];
    //         if (isset($deals['successes']) && is_array($deals['successes'])) {
    //             foreach ($deals['successes'] as $success) {
    //                 if (isset($success['order_ID'])) {
    //                     $orderId = $success['order_ID'];
    //                     $successesByOrder[$orderId][] = $success;
    //                 }
    //             }
    //         }

    //         $successCount = count($successesByOrder);
    //         foreach ($successesByOrder as $orderId => $messages) {
    //             $body .= "<strong>Order ID: $orderId</strong><br>";
    //             foreach ($messages as $message) {
    //                 $body .= "- Message: " . $message['message'] . "<br>";
    //                 if (isset($message['shipment_ID'])) {
    //                     $body .= "&nbsp;&nbsp;Shipment ID: " . $message['shipment_ID'] . "<br>";
    //                 }
    //                 if (isset($message['LabelURL'])) {
    //                     $body .= "&nbsp;&nbsp;Label URL: <a href='" . $message['LabelURL'] . "'>Download Label</a><br>";
    //                 }
    //                 if (isset($message['sms_message_id'])) {
    //                     $body .= "&nbsp;&nbsp;SMS Message ID: " . $message['sms_message_id'] . "<br>";
    //                 }
    //             }
    //             $body .= "<br>";
    //         }

    //         $body .= "<strong>Total successful deals: " . $successCount . "</strong><br>";

    //         // Process errors if any
    //         $errorCount = 0;
    //         if (isset($deals['errors']) && is_array($deals['errors']) && !empty($deals['errors'])) {
    //             $body .= "<br>The following errors were encountered:<br><br>";
    //             $errorsByOrder = [];
    //             foreach ($deals['errors'] as $error) {
    //                 if (isset($error['order_ID'])) {
    //                     $orderId = $error['order_ID'];
    //                     $errorsByOrder[$orderId][] = $error['error'];
    //                 } else {
    //                     // General error not associated with an order_ID
    //                     $errorsByOrder['General'][] = $error['error'];
    //                 }
    //             }

    //             foreach ($errorsByOrder as $orderId => $errorMessages) {
    //                 $body .= "<strong>Order ID: $orderId</strong><br>";
    //                 foreach ($errorMessages as $errorMessage) {
    //                     $body .= "- Error: " . $errorMessage . "<br>";
    //                     $errorCount++;
    //                 }
    //                 $body .= "<br>";
    //             }

    //             $body .= "<strong>Total errors: " . $errorCount . "</strong><br>";
    //         }

    //         $body .= "<br>- Date and Time: " . (new \DateTime())->format('Y-m-d H:i:s') . "<br>";
    //         $body .= "Regards,<br>";
    //     }
    //     dd($body);
    //     // Send the email
    //     $email = (new Email())
    //         ->from('support@plumbill.io')
    //         ->to('belhouari.imane@gmail.com')
    //         ->subject($subject)
    //         ->text(strip_tags($body))
    //         ->html($body);

    //     try {
    //         $this->mailer->send($email);
    //     } catch (TransportExceptionInterface $e) {
    //         // Handle the exception
    //         throw $e;
    //     }
    // }

    public function sendEmailSendDealsToAramex($contentMail)
    {
        $body = '';
       
        $subject = "Success in adding deals to ARAMEX 🎉";
        $body = "Hello,<br>";
        $body .= $contentMail;
        $body .= "<br>- Date and Time: " . (new \DateTime())->format('Y-m-d H:i:s') . "<br>";
        $body .= "Regards,<br>";
        
        // Envoyer l'email
        $email = (new Email())
            ->from('support@plumbill.io')
            ->to('belhouari.imane@gmail.com')
            ->addTo('mr.elidrissiazeddine@gmail.com')
            ->subject($subject)
            ->text(strip_tags($body))
            ->html($body);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw $e;
        }
    }
   
    public function sendEmailUpdateDeals($responseMessage, $items, $statusBeforeBT ,$isError = false)
    {
        // Check if items is an array and contains 'successCount'
        if (is_array($items) && array_key_exists('successCount', $items)) {
            $successCount = $items['successCount'];
            unset($items['successCount']);
        } else {
            // Handle the case where successCount is not set or items is not an array
            $successCount = 0;
        }
    
        // Ensure the items are an array and remove any non-array elements
        $items = array_filter($items, 'is_array');
      
        // Prepare email body and subject based on results
        $subject = " [ " .$statusBeforeBT . " ] " ."Recap of Deal Updates from First Delivery 🚚";
        $body = "Hello,<br>";
        $body .= "Here is a summary of the recent deal updates performed via the First Delivery API:<br><br>";
        $body .= "<strong>📊 Summary:</strong><br>";
        $body .= "📦 <strong>Total Deals Processed:</strong> " . count($items) . "<br>";
        $body .= "✅ <strong>Successful Updates:</strong> " . $successCount . "<br>";
        $body .= "❌ <strong>Failed Updates:</strong> " . (count($items) - $successCount) . "<br><br>";
        
        if (empty($items)) {
            $body .= "<strong>No deals were processed.</strong><br>";
        } else {
            $body .= "<table border='1' cellspacing='0' cellpadding='5'>";
            $body .= "<tr>
                <th>🔖 BarCode</th>
                <th>🆔 Order ID</th>
                <th>🗓️ Created At first delivery </th>
                <th>📦 State First delivery</th>
                <th>🔢 State code First delivery</th>
                <th>⏪ Bitrix state before update</th>
                <th>⏩ Bitrix state after update</th>
                <th>📊 Bitrix code state after update</th>
                <th>⚠️ Error (if any)</th>
            </tr>";

            foreach ($items as $res) {
                if (is_array($res)) {
                    $body .= "<tr>";
                    $body .= "<td>" . $res['barCode'] . "</td>";
                    $body .= "<td>" . $res['dealID'] . "</td>";
                    $body .= "<td>" . $res['createdAt'] . "</td>";
                    if ($res['status'] === 'Updated' || $res['status'] === 'Not updated (no valid transition)' || $res['status'] === 'Not updated (no state found)') {
                
                        $body .= "<td>" . $res['stateFirstDelivery'] . "</td>";
                        $body .= "<td>" . $res['stateCodeFirstDelivery'] . "</td>";
                        $body .= "<td>" . $res['stateBitrixBeforeUpdate'] . "</td>";
                        $body .= "<td>" . $res['stateBitrixAfterUpdate'] . "</td>";
                        $body .= "<td>" . $res['stateBitrixCodeAfterUpdate'] . "</td>";
                        $body .= "<td>-</td>";  // Pas d'erreur si status = success
                    } else {
                        $body .= "<td>" . $res['stateFirstDelivery'] . "</td>";
                        $body .= "<td>-</td>";
                        $body .= "<td>-</td>";
                        $body .= "<td>-</td>";
                        $body .= "<td>-</td>";
                        $body .= "<td>" . $res['message'] . "</td>";  // Afficher le message d'erreur
                    }

                    $body .= "</tr>";
                }
            }

            $body .= "</table><br>";
        }
        $body .= "<br>- Date and Time: " . (new \DateTime())->format('Y-m-d H:i:s') . "<br>";
        $body .= "Regards,<br>";
    
        // Send the email
        $email = (new Email())
            ->from('support@plumbill.io')
            ->to('tech.plumbill@gmail.com')
            ->subject($subject)
            ->text(strip_tags($body))
            ->html($body);
    
        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Handle the exception
            throw $e;
        }
    }
    
    public function sendEmailSendDealsToDKY($responseMessage, $deals, $isError = false)
    {
        $body ='';
        if ($isError) {
            $subject = "[KENYA] --Error while adding deals to Deliveroo 😱";
            $body = "Hello, <br> An error occurred while attempting to send the deals via the Deliveroo API. Please find the error details below:<br>";
            $body .= "- Date and Time: " . (new \DateTime())->format('Y-m-d H:i:s') . "<br>";
            $body .= "- Error Message: " . $responseMessage . "<br> <br>";
            $body .= "Please check the issue as soon as possible. If you need assistance, do not hesitate to contact the technical team <br><br>";
            $body .= "Regards,<br>";
        } else {
           
            $subject = "[KENYA] -- Success in adding deals to Deliveroo 🎉";
            $body = "Hello,<br>";
            $body .= "The following deals have been successfully added via the Deliveroo API:<br>";
            
            if (isset($deals['successes']) && is_array($deals['successes'])) {
                foreach ($deals['successes'] as $deal) {
                    if (isset($deal['order_ID']) && isset($deal['message'])) {
                        $body .= "- Order ID: " . $deal['order_ID'] . ", Message: " . $deal['message'] . "<br>";
                    }
                }
            }

            if (isset($deals['errors']) && is_array($deals['errors'])) {
                $body .= "<br>The following errors were encountered:<br>";
                foreach ($deals['errors'] as $error) {
                    if (is_array($error) && isset($error['order_ID']) && isset($error['error'])) {
                        $body .= "- Order ID: " . $error['order_ID'] . ", Erreur: " . $error['error'] . "<br>";
                    }
                }
            }

            $body .= "- Date and Time: " . (new \DateTime())->format('Y-m-d H:i:s') . "<br>";
            $body .= "Regards,<br>";
        }

        $email = (new Email())
            ->from('support@plumbill.io')
            ->to('mr.elidrissiazeddine@gmail.com')
            ->addTo('sillacharity@gmail.com')
            ->subject($subject)
            ->text(strip_tags($body))
            ->html($body);

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Gérer l'exception
            throw $e;
        }
    }
}
