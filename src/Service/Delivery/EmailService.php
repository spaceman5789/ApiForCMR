<?php

namespace App\Service\Delivery;

use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

class EmailService
{

    /**
     *
     * @var MailerInterface
     */
    private $mailer;

    /**
     * Create a new instance.
     *
     * @param MailerInterface $params
     * @return void
     */
    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Prepare data to create colis.
     *
     * @param string $subject
     * @param string $view
     * @param array $data
     * 
     * @return void
     */
    public function send($subject, $view, array $data = []): void
    {
        try {
            $email = (new TemplatedEmail())
                ->subject($subject)
                ->htmlTemplate("emails/$view.html.twig")
                ->context($data);

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            throw $e;
        }
    }
}
