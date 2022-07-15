<?php

namespace App\Utils;

use App\Entity\Image;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Security\Core\Security;

class ImageHandler
{
    private $allowedExtension = ['jpg', 'jpeg'];
    private $customFunctions;
    private $appKernel;
    private $em;
    private $maxFileSize = 5000000; // 5MB in bytes
    private $user;
    private $hasError = false;
    private $errorMessage;

    public function __construct(CustomFunctions $customFunctions, KernelInterface $appKernel)
    {
        $this->customFunctions = $customFunctions;
        $this->appKernel = $appKernel;
    }

    public function checkImage(UploadedFile $file): void
    {
        $extension = $file->guessExtension();
        if (!in_array($extension, $this->allowedExtension)) {
            $this->errorMessage = 'فرمت عکس وارد شده معتبر نمی باشد';
            $this->hasError = true;
            return;
        }
        $size = $file->getSize();
        if ($size > $this->maxFileSize) {
            $this->errorMessage = 'حداکثر اندازه فایل می تواند ۵ مگابایت باشد';
            $this->hasError = true;
        }
    }

    public function addImage(UploadedFile $file, string $directory, EntityManagerInterface $em): Image
    {
        $extension = $file->guessExtension();
        $name = $this->customFunctions::generateString(5) . '.' . uniqid('', true) . '.' . $extension;
        $path = $this->appKernel->getProjectDir().$directory;
        $full_path = $path . '/' . $name;
        $file->move($path, $name);

        $photo = new Image();
        $photo->setName($name);
        $photo->setPath($full_path);
        $em->persist($photo);

        return $photo;
    }

    public function deleteImage(string $name)
    {
        $photo = $this->em->getRepository(PrivatePhoto::class)->findOneBy(['name' => $name]);
        $full_path = $photo->getPath();
        $this->em->remove($photo);
        $this->em->flush();
        unlink($full_path);
    }

    public function getErrorMessage()
    {
        return $this->errorMessage;
    }

    public function getHasError(): bool
    {
        return $this->hasError;
    }
}