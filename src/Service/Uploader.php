<?php
namespace App\Service;


use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\DependencyInjection\ContainerInterface;

class Uploader
{
   public function __construct(private Filesystem $fs, private  $profileFolder, private $profileFolderPublic)
      {
         
      }

   public function uploadProfileImage(UploadedFile $picture, string $oldPicturePath = null): string
   {
      $folder = $this->profileFolder;    // Je recupere le dosiier ou je vais stocker l'image
      $ext = $picture->guessExtension() ?? 'bin'; // je defini l'extension
      $filename = bin2hex(random_bytes(10)) . '.' .$ext;  // je genere un nom aléatoire
      $picture->move($folder, $filename); // Je déplace l'image $filename dans le dossier $folder
      if($oldPicturePath)
      {
         $this->fs->remove($folder . '/' .pathinfo($oldPicturePath, PATHINFO_BASENAME));
      }
      return $this->profileFolderPublic .'/' .$filename;
   }
}