<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public function __construct(private UserPasswordHasherInterface $password_hasher)
    {
    }

    public function load(ObjectManager $manager)
    {
        $users = [
            [
                'invited_by' => 0,
                'locale' => 'ru'
            ],
            [
                'invited_by' => 1,
                'locale' => 'en',
                'invitations' => [1, [3], []],
                'rating' => 5
            ],
            [
                'invited_by' => 2,
                'locale' => 'cs'
            ]
        ];
        foreach ($users as $user_data_key => $user_data) {
            $user = new User();

            $user->setEmail("test" . $user_data_key + 1 . "@test.com");
            $user->setPassword($this->password_hasher->hashPassword($user, "test" . $user_data_key + 1 . "_123456"));
            $user->setName("Test" . $user_data_key + 1 . "Name");
            $user->setSurname("Test" . $user_data_key + 1 . "Surame");
            $user->setInvitedBy($user_data['invited_by']);
            $user->setLocale($user_data['locale']);
            if (isset($user_data['invitations'])) {
                $user->setInvitations($user_data['invitations']);
            }
            if (isset($user_data['rating'])) {
                $user->setRating($user_data['rating']);
            }

            $manager->persist($user);
        }

        $manager->flush();
    }
}
