<?php

namespace App\Repository;

use App\Entity\Photo;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @method User|null find($id, $lockMode = null, $lockVersion = null)
 * @method User|null findOneBy(array $criteria, array $orderBy = null)
 * @method User[]    findAll()
 * @method User[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(UserInterface $user, string $newEncodedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', \get_class($user)));
        }

        $user->setPassword($newEncodedPassword);
        $this->_em->persist($user);
        $this->_em->flush();
    }

    /**
     * Get the avatar photo path
     *
     * @param UserInterface $user
     * @return string
     */
    public function getAvatarPhotoPath(UserInterface $user): string
    {
        $avatar_photo = $user->getAvatarPhoto();
        return $avatar_photo
            ? 'uploads/photos/' . $this->_em->find(Photo::class, $avatar_photo)->getFileName()
            : 'build/images/no-photo.png';
    }

    /**
     * Get the invited users photos
     *
     * @param UserInterface $user
     * @return array
     */
    public function getInvitedUsersPhotos(UserInterface $user): array
    {
        $invited_users_photos = [];
        foreach ($user->getInvitations()[2] as $invited_user_id) {
            $invited_user = $this->_em->find(User::class, $invited_user_id);
            $invited_users_photos[] = [
                $this->getAvatarPhotoPath($invited_user),
                $invited_user_id,
                $invited_user->getName() . ' ' . $invited_user->getSurname()
            ];
        }
        return $invited_users_photos;
    }

    // /**
    //  * @return User[] Returns an array of User objects
    //  */
    /*
    public function findByExampleField($value)
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->orderBy('u.id', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }
    */

    /*
    public function findOneBySomeField($value): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.exampleField = :val')
            ->setParameter('val', $value)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
    */
}
