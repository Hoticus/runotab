<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class EmailSender
{
    private $session;

    public function __construct(private MailerInterface $mailer, RequestStack $request_stack)
    {
        $this->session = $request_stack->getSession();
    }

    /**
     * Send a email and set email_sending_cooldown_end in session
     *
     * @param Email $email
     * @return void
     */
    public function send(Email $email): void
    {
        $this->mailer->send($email);
        $this->session->set('email_sending_cooldown_end', time() + 60);
    }

    /**
     * Remove email_sending_cooldown_end in session
     *
     * @return void
     */
    public function removeEmailSendingCooldownEnd(): void
    {
        $this->session->remove('email_sending_cooldown_end');
    }

    /**
     * Get email_sending_cooldown_end in session
     *
     * @return int
     */
    public function getEmailSendingCooldownEnd(): int
    {
        return $this->session->get('email_sending_cooldown_end');
    }
}
