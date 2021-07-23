<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Twig\Environment;
use Twig\Extension\CoreExtension;

class LocaleSubscriber implements EventSubscriberInterface
{
    public function __construct(private ParameterBagInterface $parameter_bag, private Environment $twig)
    {
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();

        // set timezone to twig from cookies
        if ($user_timezone = $request->cookies->get('timezone')) {
            $this->twig->getExtension(CoreExtension::class)->setTimezone($user_timezone);
        }

        if (!$request->hasPreviousSession()) {
            return;
        }

        $supported_locales = explode("|", $this->parameter_bag->get('app.supported_locales'));
        $user_browser_locales = $request->getLanguages();
        $user_browser_locale = null;

        foreach ($user_browser_locales as $user_browser_locale) {
            if (in_array($user_browser_locale, $supported_locales)) {
                break;
            }
        }

        if ($locale = $request->attributes->get('_locale')) {
            $request->getSession()->set('_locale', $locale);
        } else {
            $request->setLocale($request->getSession()->get(
                '_locale',
                in_array($user_browser_locale, $supported_locales) ? $user_browser_locale : 'en'
            ));
        }
    }

    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event)
    {
        $request = $event->getRequest();
        // set locale from DB
        $request->getSession()->set('_locale', $event->getAuthenticationToken()->getUser()->getLocale());
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
            'security.interactive_login' => 'onSecurityInteractiveLogin'
        ];
    }
}
