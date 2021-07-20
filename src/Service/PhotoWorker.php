<?php

namespace App\Service;

use App\Entity\Photo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class PhotoWorker
{
    public function __construct(
        private FileWorker $file_worker,
        private ContainerBagInterface $params,
        private EntityManagerInterface $em
    ) {
    }

    public function upload(Photo $photo, UploadedFile $photo_file): Photo
    {
        $photo_filename = $this->file_worker->upload($photo_file, $this->params->get('photos_directory'));
        $photo->setFileName($photo_filename);
        $this->em->persist($photo);
        $this->em->flush();

        return $photo;
    }
}
