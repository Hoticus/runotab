<?php

namespace App\Controller;

use App\Entity\Invitation;
use App\Entity\User;
use App\Form\EmailVerificationFormType;
use App\Form\RegistrationFormType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Authenticator\Token\PostAuthenticationToken;
use Symfony\Component\Security\Http\RememberMe\RememberMeHandlerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class RegistrationController extends AbstractController
{
    private $mailer;
    private $session;
    private $translator;

    public function __construct(MailerInterface $mailer, RequestStack $request_stack, TranslatorInterface $translator)
    {
        $this->mailer = $mailer;
        $this->session = $request_stack->getSession();
        $this->translator = $translator;
    }

    /**
     * @Route("/register", name="app_register")
     */
    public function register(
        Request $request,
        UserPasswordHasherInterface $password_encoder
    ): Response {
        $user = new User();
        $this->session->set('user', $user);
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);
        $first_form_error = isset($form->getErrors(true)[0]) ? $form->getErrors(true)[0]->getMessage() : null;

        if ($form->isSubmitted() && $form->isValid()) {
            if (
                !$this->getDoctrine()->getRepository(Invitation::class)
                    ->findOneBy(['invitation_code' => $invitation_code = hash("sha256", str_replace(
                        '-',
                        '',
                        $form->get('invitationCode')->getData()
                    ))])
            ) {
                $this->addFlash('registration_error', 'Please enter a valid invitation code.');

                return $this->redirectToRoute('app_register');
            }
            $this->session->set('invitation_code', $invitation_code);

            $user->setPassword(
                $password_encoder->hashPassword(
                    $user,
                    $form->get('password')->getData()
                )
            );

            $verification_code = "";
            for ($i = 1; $i <= 6; $i++) {
                $verification_code .= random_int(0, 9);
            }
            $this->session->set('verification_code', $verification_code);

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@runotab.com', 'Runotab'))
                ->to($user->getEmail())
                ->subject($this->translator->trans('Email Confirmation'))
                ->htmlTemplate('security/email_verification_mail.html.twig')
                ->context([
                    'verification_code' => $verification_code,
                    'full_name' => $user->getName() . " " . $user->getSurname()
                ]);
            $this->mailer->send($email);
            $this->session->set('email_sending_cooldown_end', time() + 60);

            return $this->redirectToRoute(
                'app_verify_email'
            );
        }

        return $this->render('security/register.html.twig', [
            'registrationForm' => $form->createView(),
            'first_form_error' => $first_form_error
        ]);
    }

    /**
     * @Route("/verify/email", name="app_verify_email")
     */
    public function verifyUserEmail(
        Request $request,
        TokenStorageInterface $token_storage,
        RememberMeHandlerInterface $remember_me
    ) {
        $user = $this->session->get('user');
        $verification_code = $this->session->get('verification_code');

        if (!$user || !$verification_code) {
            return $this->redirectToRoute('app_register');
        }

        $form = $this->createForm(EmailVerificationFormType::class);
        $form->handleRequest($request);
        $first_form_error = isset($form->getErrors(true)[0]) ? $form->getErrors(true)[0]->getMessage() : null;

        if ($form->isSubmitted() && $form->isValid()) {
            if ($form->get('verification_code')->getData() == $verification_code) {
                $em = $this->getDoctrine()->getManager();
                $invitation = $em->getRepository(Invitation::class)
                    ->findOneBy(['invitation_code' => $this->session->get('invitation_code')]);

                $this->session->remove('user');
                $this->session->remove('verification_code');
                $this->session->remove('email_sending_cooldown_end');
                $this->session->remove('invitation_code');

                if (!$invitation) {
                    $this->addFlash('registration_error', 'The invitation code is outdated.');

                    return $this->redirectToRoute('app_register');
                }

                $user->setInvitedBy($invitation->getCreatedBy());
                if ($invited_by = $em->getRepository(User::class)->find($invitation->getCreatedBy())) {
                    $invited_by_user_invitations = $invited_by->getInvitations();
                    unset($invited_by_user_invitations[1][array_search(
                        $invitation->getId(),
                        $invited_by_user_invitations[1]
                    )]);
                    $invited_by_user_invitations[1] = array_values($invited_by_user_invitations[1]);
                    $invited_by->setInvitations($invited_by_user_invitations);
                    $user->setRating(floor($invited_by->getRating() / 2));
                    $user->setInvitations([floor($invited_by->getRating() / 2), []]);
                }
                $user->setLocale($request->getLocale());
                $this->session->set('_locale', $request->getLocale());

                $em->persist($user);
                $em->remove($invitation);
                $em->flush();

                $token = new PostAuthenticationToken($user, 'main', $user->getRoles());
                $token_storage->setToken($token);
                $this->session->set('_security_main', serialize($token));
                $remember_me->createRememberMeCookie($user);

                return $this->redirectToRoute('default');
            }

            if ($this->session->get('email_sending_cooldown_end') < time()) {
                $verification_code = "";
                for ($i = 1; $i <= 6; $i++) {
                    $verification_code .= random_int(0, 9);
                }
                $this->session->set('verification_code', $verification_code);

                $email = (new TemplatedEmail())
                    ->from(new Address('no-reply@runotab.com', 'Runotab'))
                    ->to($user->getEmail())
                    ->subject($this->translator->trans('Email Confirmation'))
                    ->htmlTemplate('security/email_verification_mail.html.twig')
                    ->context([
                        'verification_code' => $verification_code,
                        'full_name' => $user->getName() . " " . $user->getSurname()
                    ]);
                $this->mailer->send($email);
                $this->session->set('email_sending_cooldown_end', time() + 60);

                $this->addFlash(
                    'email_verification_error',
                    'This code is invalid. We have sent you a new mail with a new code.'
                );
            } else {
                $this->addFlash(
                    'email_verification_error',
                    'This code is invalid.'
                );
            }

            return $this->redirectToRoute(
                'app_verify_email'
            );
        }

        return $this->render('security/email_verification.html.twig', [
            'emailVerificationForm' => $form->createView(),
            'first_form_error' => $first_form_error
        ]);
    }

    /**
     * @Route("/verify/email/resend", name="app_resend_verification_mail")
     */
    public function resendVerififcationMail()
    {
        $user = $this->session->get('user');
        $verification_code = $this->session->get('verification_code');
        $email_sending_cooldown_end = $this->session->get('email_sending_cooldown_end');

        if (!$user || !$verification_code) {
            return $this->redirectToRoute('app_register');
        }

        if ($email_sending_cooldown_end < time()) {
            $verification_code = "";
            for ($i = 1; $i <= 6; $i++) {
                $verification_code .= random_int(0, 9);
            }
            $this->session->set('verification_code', $verification_code);

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@runotab.com', 'Runotab'))
                ->to($user->getEmail())
                ->subject($this->translator->trans('Email Confirmation'))
                ->htmlTemplate('security/email_verification_mail.html.twig')
                ->context([
                    'verification_code' => $verification_code,
                    'full_name' => $user->getName() . " " . $user->getSurname()
                ]);
            $this->mailer->send($email);
            $this->session->set('email_sending_cooldown_end', time() + 60);

            $this->addFlash(
                'email_verification_notice',
                'We have sent you a new mail with a new code.'
            );
        } else {
            $this->addFlash(
                'email_verification_error',
                'Wait 60 seconds before sending a new mail.'
            );
        }

        return $this->redirectToRoute(
            'app_verify_email'
        );
    }
}
