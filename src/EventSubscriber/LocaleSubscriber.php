<?php

namespace App\EventSubscriber;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class LocaleSubscriber implements EventSubscriberInterface
{
    private $parameter_bag;

    public function __construct(ParameterBagInterface $parameter_bag)
    {
        $this->parameter_bag = $parameter_bag;
    }

    public function onKernelRequest(RequestEvent $event)
    {
        $request = $event->getRequest();
        if (!$request->hasPreviousSession()) {
            return;
        }

        $supported_locales = explode("|", $this->parameter_bag->get('app.supported_locales'));
        $user_browser_locales = $request->getLanguages();

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
