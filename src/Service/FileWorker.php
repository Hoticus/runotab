<?php

namespace App\Service;

use Exception;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileWorker
{
    /**
     * Upload a file
     *
     * @param UploadedFile $file
     * @param string $target_directory
     * @return string File name
     */
    public function upload(UploadedFile $file, string $target_directory): string
    {
        $filename = uniqid() . '.' . $file->guessExtension();

        while (file_exists($target_directory . '/' . $filename)) {
            $filename = uniqid() . '.' . $file->guessExtension();
        }

        try {
            $file_mime = $file->getMimeType();
            $file->move($target_directory, $filename);

            // compress if the file is a photo
            if (in_array($file_mime, ['image/jpeg', 'image/png'])) {
                switch ($file_mime) {
                    case 'image/jpeg':
                        $image = imagecreatefromjpeg($target_directory . '/' . $filename);
                        // fixing orientation
                        $exif = exif_read_data($target_directory . '/' . $filename);
                        if ($image && $exif && isset($exif['Orientation'])) {
                            $ort = $exif['Orientation'];

                            if ($ort == 6 || $ort == 5) {
                                $image = imagerotate($image, 270, 0);
                            }
                            if ($ort == 3 || $ort == 4) {
                                $image = imagerotate($image, 180, 0);
                            }
                            if ($ort == 8 || $ort == 7) {
                                $image = imagerotate($image, 90, 0);
                            }

                            if ($ort == 5 || $ort == 4 || $ort == 7) {
                                imageflip($image, IMG_FLIP_HORIZONTAL);
                            }
                        }
                        break;
                    case 'image/png':
                        $image = imagecreatefrompng($target_directory . '/' . $filename);
                        break;
                }
                imagejpeg($image, $target_directory . '/' . $filename, 60);
            }
        } catch (FileException $e) {
            throw new Exception($e);
        }

        return $filename;
    }

    /**
     * Delete a file
     *
     * @param string $file_path
     * @return void
     */
    public function delete(string $file_path): void
    {
        $filesystem = new Filesystem();
        $filesystem->remove($file_path);
    }
}
