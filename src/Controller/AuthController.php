<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\PasswordRecoveryFirstFormType;
use App\Form\PasswordRecoverySecondFormType;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoderInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class AuthController extends AbstractController
{
    /**
     * @Route("/login", name="app_login")
     */
    public function login(
        AuthenticationUtils $authenticationUtils
    ): Response {
        // get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();
        // last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', ['last_username' => $lastUsername, 'error' => $error]);
    }

    /**
     * @Route("/logout", name="app_logout")
     */
    public function logout()
    {
        throw new \Exception('Please try again later');
    }

    /**
     * @Route("/restore/password", name="app_restore_password_first")
     */
    public function restorePasswordFirst(Request $request, SessionInterface $session, MailerInterface $mailer)
    {
        $form = $this->createForm(PasswordRecoveryFirstFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository(User::class)->findOneBy(['email' => $form->get('email')->getData()]);

            if (!$user) {
                $this->addFlash('password_recovery_first_error', 'There is no account with this email.');

                return $this->redirectToRoute('app_restore_password_first');
            }

            $recovery_code = "";
            for ($i = 1; $i <= 6; $i++) {
                $recovery_code .= random_int(0, 9);
            }
            $session->set('recovery_code', $recovery_code);

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@runotab.com', 'Runotab'))
                ->to($user->getEmail())
                ->subject('Password Recovery')
                ->htmlTemplate('security/password_recovery_mail.html.twig')
                ->context([
                    'recovery_code' => $recovery_code,
                    'full_name' => $user->getName() . " " . $user->getSurname()
                ]);
            $mailer->send($email);

            $session->set('email_sending_cooldown_end', time() + 60);
            $session->set('password_recovery_email', $user->getEmail());

            return $this->redirectToRoute('app_restore_password_second');
        }

        return $this->render('security/password_recovery_first.html.twig', [
            'form' => $form->createView()
        ]);
    }

    /**
     * @Route("/restore/password/confirm", name="app_restore_password_second")
     */
    public function restorePasswordSecond(
        Request $request,
        SessionInterface $session,
        UserPasswordEncoderInterface $password_encoder,
        MailerInterface $mailer
    ) {
        if (!$email = $session->get('password_recovery_email')) {
            return $this->redirectToRoute('app_restore_password_first');
        }

        $form = $this->createForm(PasswordRecoverySecondFormType::class);
        $form->handleRequest($request);
        $first_form_error = isset($form->getErrors(true)[0]) ? $form->getErrors(true)[0]->getMessage() : null;

        if ($form->isSubmitted() && $form->isValid()) {
            $em = $this->getDoctrine()->getManager();
            $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($form->get('recovery_code')->getData() == $session->get('recovery_code')) {
                // encode the plain password
                $user->setPassword(
                    $password_encoder->encodePassword(
                        $user,
                        $form->get('password')->getData()
                    )
                );
                $em->flush();

                $session->remove('recovery_code');
                $session->remove('password_recovery_email');
                $session->remove('email_sending_cooldown_end');

                $this->addFlash('login_notice', 'Your password has been successfully changed.');

                return $this->redirectToRoute('app_login');
            }

            if ($session->get('email_sending_cooldown_end') < time()) {
                $recovery_code = "";
                for ($i = 1; $i <= 6; $i++) {
                    $recovery_code .= random_int(0, 9);
                }
                $session->set('recovery_code', $recovery_code);

                $email = (new TemplatedEmail())
                    ->from(new Address('no-reply@runotab.com', 'Runotab'))
                    ->to($user->getEmail())
                    ->subject('Password Recovery')
                    ->htmlTemplate('security/password_recovery_mail.html.twig')
                    ->context([
                        'recovery_code' => $recovery_code,
                        'full_name' => $user->getName() . " " . $user->getSurname()
                    ]);
                $mailer->send($email);
                $session->set('email_sending_cooldown_end', time() + 60);

                $this->addFlash(
                    'password_recovery_second_error',
                    'Verification code is invalid. We have sent you a new mail with a new code.'
                );
            } else {
                $this->addFlash(
                    'password_recovery_second_error',
                    'Verification code is invalid.'
                );
            }

            return $this->redirectToRoute(
                'app_restore_password_second'
            );
        }

        return $this->render('security/password_recovery_second.html.twig', [
            'form' => $form->createView(),
            'first_form_error' => $first_form_error
        ]);
    }

    /**
     * @Route("/restore/password/resend", name="app_resend_password_recovery_mail")
     */
    public function resendPasswordRecoveryMail(SessionInterface $session, MailerInterface $mailer)
    {
        $password_recovery_email = $session->get('password_recovery_email');

        $em = $this->getDoctrine()->getManager();
        $user = $em->getRepository(User::class)->findOneBy(['email' => $password_recovery_email]);

        $recovery_code = $session->get('recovery_code');
        $email_sending_cooldown_end = $session->get('email_sending_cooldown_end');

        if (!$user || !$recovery_code) {
            return $this->redirectToRoute('app_restore_password_first');
        }

        if ($email_sending_cooldown_end < time()) {
            $recovery_code = "";
            for ($i = 1; $i <= 6; $i++) {
                $recovery_code .= random_int(0, 9);
            }
            $session->set('recovery_code', $recovery_code);

            $email = (new TemplatedEmail())
                ->from(new Address('no-reply@runotab.com', 'Runotab'))
                ->to($user->getEmail())
                ->subject('Password Recovery')
                ->htmlTemplate('security/password_recovery_mail.html.twig')
                ->context([
                    'recovery_code' => $recovery_code,
                    'full_name' => $user->getName() . " " . $user->getSurname()
                ]);
            $mailer->send($email);
            $session->set('email_sending_cooldown_end', time() + 60);

            $this->addFlash(
                'password_recovery_second_notice',
                'We have sent you a new mail with a new code.'
            );
        } else {
            $this->addFlash(
                'password_recovery_second_error',
                'Wait 60 seconds before sending a new mail.'
            );
        }

        return $this->redirectToRoute(
            'app_restore_password_second'
        );
    }
}
